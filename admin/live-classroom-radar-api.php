<?php
declare(strict_types=1);

/**
 * admin/live-classroom-radar-api.php  — P2.1: AI Classroom Radar
 *
 * Aggregates wrong answers + Understanding Check signals from the live session,
 * sends them to the Gemini API for gap analysis, and returns a "classroom radar"
 * showing: top confusion clusters, weak topics, and teacher intervention suggestions.
 *
 * Endpoints:
 *   GET  ?action=radar&sessionId=X   → Run AI analysis (cached per session snapshot)
 *   GET  ?action=raw&sessionId=X     → Return raw aggregated data (no AI, for debug)
 *   POST ?action=dismiss&sessionId=X → Teacher marks radar as acknowledged
 *
 * The analysis is ASSISTANT-mode: suggestions only. Teacher decides all actions.
 * Results are cached in session_radar_cache for 3 minutes to avoid API hammering.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/live-sessions-helper.php';
require_once __DIR__ . '/../includes/ai-settings.php';

// ── Schema ────────────────────────────────────────────────────────────────────
function ensureRadarSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `session_radar_cache` (
            `session_id`   VARCHAR(64)  NOT NULL,
            `snapshot_hash` VARCHAR(64)  NOT NULL COMMENT 'Hash of input data to detect changes',
            `radar_json`   MEDIUMTEXT   NOT NULL,
            `generated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `dismissed_at` DATETIME     NULL,
            PRIMARY KEY (`session_id`),
            INDEX `idx_src_session` (`session_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function radarRespond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function radarBody(): array
{
    $raw = file_get_contents('php://input');
    $d   = json_decode($raw ?: '{}', true);
    return is_array($d) ? $d : [];
}

/**
 * Aggregate classroom confusion signals from the database.
 * Returns a structured payload ready for AI analysis.
 */
function aggregateClassroomSignals(PDO $pdo, string $sessionId): array
{
    // 1. Wrong answer clusters per question
    $stmtWrong = $pdo->prepare("
        SELECT q.id AS question_id, q.question_text, q.skill AS topic,
               COUNT(ta.id) AS total_attempts,
               SUM(ta.is_correct = 0) AS wrong_count,
               SUM(ta.is_correct = 1) AS correct_count,
               GROUP_CONCAT(DISTINCT ta.chosen_answer ORDER BY ta.chosen_answer SEPARATOR ', ') AS wrong_choices
        FROM exam_questions q
        JOIN test_answers ta ON ta.question_id = q.id
        JOIN test_attempts att ON att.id = ta.attempt_id
        JOIN session_participants sp ON sp.attempt_id = att.id
        WHERE sp.session_id = :sid AND ta.is_correct = 0
        GROUP BY q.id
        HAVING wrong_count >= 2
        ORDER BY wrong_count DESC
        LIMIT 10
    ");
    $stmtWrong->execute([':sid' => $sessionId]);
    $wrongClusters = $stmtWrong->fetchAll(PDO::FETCH_ASSOC);

    // 2. Understanding check signals
    $stmtUC = $pdo->prepare("
        SELECT topic_name, understanding, COUNT(*) AS count
        FROM session_understanding_checks
        WHERE session_id = :sid
        GROUP BY topic_name, understanding
        ORDER BY topic_name, understanding
    ");
    $stmtUC->execute([':sid' => $sessionId]);
    $ucRows = $stmtUC->fetchAll(PDO::FETCH_ASSOC);

    // Restructure UC data by topic
    $ucByTopic = [];
    foreach ($ucRows as $row) {
        $topic = (string)($row['topic_name'] ?: 'ทั่วไป');
        $ucByTopic[$topic][$row['understanding']] = (int)$row['count'];
    }

    // 3. Session topics
    $stmtTopics = $pdo->prepare("SELECT topic_name FROM session_topics WHERE session_id = :sid ORDER BY sort_order, id");
    $stmtTopics->execute([':sid' => $sessionId]);
    $topics = $stmtTopics->fetchAll(PDO::FETCH_COLUMN) ?: [];

    // 4. Pulse question results if any
    $stmtPulse = $pdo->prepare("
        SELECT pq.question_text, pq.correct_key,
               pa.chosen_key, COUNT(*) AS votes,
               SUM(pa.is_correct = 1) AS correct_votes
        FROM session_pulse_questions pq
        JOIN session_pulse_answers pa ON pa.question_id = pq.id
        WHERE pq.session_id = :sid
        GROUP BY pq.id, pa.chosen_key
        ORDER BY pq.pushed_at DESC, votes DESC
        LIMIT 20
    ");
    try {
        $stmtPulse->execute([':sid' => $sessionId]);
        $pulseData = $stmtPulse->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        $pulseData = [];
    }

    // 5. Overall stats
    $stmtStats = $pdo->prepare("
        SELECT COUNT(DISTINCT sp.student_id) AS students_count,
               SUM(ta.completed_at IS NOT NULL) AS submitted_count,
               ROUND(AVG(ta.score), 1) AS avg_score
        FROM session_participants sp
        LEFT JOIN test_attempts ta ON ta.id = sp.attempt_id
        WHERE sp.session_id = :sid
    ");
    $stmtStats->execute([':sid' => $sessionId]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'sessionId'      => $sessionId,
        'topics'         => $topics,
        'stats'          => $stats,
        'wrongClusters'  => $wrongClusters,
        'ucByTopic'      => $ucByTopic,
        'pulseData'      => $pulseData,
    ];
}

/**
 * Build the Gemini prompt from the aggregated signals.
 */
function buildRadarPrompt(array $signals): string
{
    $topicsStr   = implode(', ', $signals['topics']) ?: 'ไม่ระบุหัวข้อ';
    $studentsN   = $signals['stats']['students_count'] ?? 0;
    $avgScore    = $signals['stats']['avg_score'] ?? 'N/A';

    $wrongText = '';
    foreach ($signals['wrongClusters'] as $wc) {
        $pct = $wc['total_attempts'] > 0
            ? round($wc['wrong_count'] / $wc['total_attempts'] * 100)
            : 0;
        $wrongText .= "- หัวข้อ: {$wc['topic']} | ตอบผิด {$wc['wrong_count']}/{$wc['total_attempts']} ({$pct}%) | คำถาม: \"{$wc['question_text']}\"\n";
    }

    $ucText = '';
    foreach ($signals['ucByTopic'] as $topic => $counts) {
        $confused = $counts['confused'] ?? 0;
        $somewhat = $counts['somewhat'] ?? 0;
        $gotIt    = $counts['got_it']   ?? 0;
        $ucText  .= "- หัวข้อ: {$topic} | ✅ {$gotIt} / 🤔 {$somewhat} / ❓ {$confused}\n";
    }

    $prompt = <<<PROMPT
คุณคือระบบวิเคราะห์ห้องเรียนสดสำหรับครู คุณมีข้อมูลจากห้องเรียนสดดังนี้:

**ข้อมูลห้องเรียน:**
- หัวข้อที่สอน: {$topicsStr}
- จำนวนนักเรียน: {$studentsN} คน
- คะแนนเฉลี่ย: {$avgScore}%

**คำถามที่ตอบผิดมากที่สุด (จาก Test):**
{$wrongText}

**สัญญาณความเข้าใจ (Understanding Check):**
{$ucText}

---

วิเคราะห์ข้อมูลนี้และตอบเป็น JSON ที่มีโครงสร้างดังนี้ (ตอบ JSON เท่านั้น ไม่มีข้อความอื่น):

{
  "overallRisk": "low|medium|high",
  "riskReason": "อธิบายสั้น ๆ ว่าทำไม (1 ประโยค)",
  "confusionClusters": [
    {
      "topic": "ชื่อหัวข้อ",
      "severity": "low|medium|high",
      "studentCount": <number>,
      "signal": "อธิบายว่าเด็กสับสนเรื่องอะไร (1-2 ประโยค)",
      "quickFix": "แนวทางที่ครูทำได้ทันทีในห้องเรียน (1 ประโยค)"
    }
  ],
  "teacherActions": [
    {
      "priority": "immediate|soon|optional",
      "action": "คำแนะนำการกระทำสำหรับครู",
      "reason": "เหตุผลสั้น ๆ"
    }
  ],
  "classroomMood": "engaged|struggling|mixed|unclear",
  "summary": "สรุปภาพรวมห้องเรียนใน 2-3 ประโยค"
}
PROMPT;

    return $prompt;
}

/**
 * Call Gemini API and parse JSON from response.
 */
function callGeminiForRadar(string $apiKey, string $prompt): array
{
    $model   = 'gemini-1.5-flash-latest';
    $url     = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);
    $payload = json_encode([
        'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature'     => 0.3,
            'maxOutputTokens' => 1500,
            'responseMimeType' => 'application/json',
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        throw new RuntimeException("Gemini API error HTTP {$httpCode}");
    }

    $resp = json_decode($response, true);
    $text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? '';

    // Strip markdown fences if present
    $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
    $text = preg_replace('/\s*```\s*$/m', '', $text);
    $text = trim($text);

    $parsed = json_decode($text, true);
    if (!is_array($parsed)) {
        throw new RuntimeException('Gemini returned non-JSON response');
    }

    return $parsed;
}

// ── Main ──────────────────────────────────────────────────────────────────────
ensureRadarSchema($pdo);

$method         = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action         = trim((string) ($_GET['action'] ?? 'radar'));
$sessionId      = trim((string) ($_GET['sessionId'] ?? (radarBody()['sessionId'] ?? '')));
$currentUserId  = (int) ($consoleUser['id'] ?? 0);
$currentRole    = (string) ($consoleUser['role'] ?? 'teacher');

if ($sessionId === '') radarRespond(['error' => 'sessionId required'], 422);

try {
    authorizeSessionControl($pdo, $sessionId, $currentUserId, $currentRole);

    // ── GET: Raw data (debug / frontend pre-check) ────────────────────────────
    if ($method === 'GET' && $action === 'raw') {
        $signals = aggregateClassroomSignals($pdo, $sessionId);
        radarRespond(['ok' => true, 'signals' => $signals]);
    }

    // ── POST: Teacher dismisses radar alert ───────────────────────────────────
    if ($method === 'POST' && $action === 'dismiss') {
        $pdo->prepare("UPDATE session_radar_cache SET dismissed_at = NOW() WHERE session_id = :sid")
            ->execute([':sid' => $sessionId]);
        radarRespond(['ok' => true, 'dismissed' => true]);
    }

    // ── GET: Main AI Radar analysis ───────────────────────────────────────────
    if ($method === 'GET' && $action === 'radar') {

        $signals     = aggregateClassroomSignals($pdo, $sessionId);
        $snapshotHash = md5(json_encode($signals));

        // Check cache — only use if data hasn't changed (same hash) and < 3 min old
        $stmtCache = $pdo->prepare("
            SELECT radar_json, snapshot_hash, generated_at, dismissed_at
            FROM session_radar_cache
            WHERE session_id = :sid LIMIT 1
        ");
        $stmtCache->execute([':sid' => $sessionId]);
        $cached = $stmtCache->fetch(PDO::FETCH_ASSOC);

        $cacheValid = $cached
            && $cached['snapshot_hash'] === $snapshotHash
            && (time() - strtotime($cached['generated_at'])) < 180; // 3 min TTL

        if ($cacheValid) {
            $radar = json_decode($cached['radar_json'], true);
            radarRespond([
                'ok'          => true,
                'radar'       => $radar,
                'fromCache'   => true,
                'generatedAt' => $cached['generated_at'],
                'dismissed'   => !empty($cached['dismissed_at']),
                'signals'     => $signals,
            ]);
        }

        // No usable cache — check if there's enough data to analyze
        $hasData = !empty($signals['wrongClusters']) || !empty($signals['ucByTopic']);
        if (!$hasData) {
            radarRespond([
                'ok'      => true,
                'radar'   => null,
                'message' => 'ยังไม่มีข้อมูลเพียงพอสำหรับการวิเคราะห์ (รอนักเรียนทำข้อสอบและกด Understanding Check)',
                'signals' => $signals,
            ]);
        }

        // Call Gemini
        $apiKey = aiSettingsGetKey($pdo);
        if ($apiKey === '') {
            radarRespond(['error' => 'ยังไม่ได้ตั้งค่า Gemini API Key (ไปที่ Settings → AI)'], 503);
        }

        $prompt = buildRadarPrompt($signals);
        $radar  = callGeminiForRadar($apiKey, $prompt);

        // Cache result
        $radarJson = json_encode($radar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pdo->prepare("
            INSERT INTO session_radar_cache (session_id, snapshot_hash, radar_json, generated_at)
            VALUES (:sid, :hash, :json, NOW())
            ON DUPLICATE KEY UPDATE snapshot_hash = :hash, radar_json = :json, generated_at = NOW(), dismissed_at = NULL
        ")->execute([':sid' => $sessionId, ':hash' => $snapshotHash, ':json' => $radarJson]);

        radarRespond([
            'ok'          => true,
            'radar'       => $radar,
            'fromCache'   => false,
            'generatedAt' => date('Y-m-d H:i:s'),
            'dismissed'   => false,
            'signals'     => $signals,
        ]);
    }

    radarRespond(['error' => 'Invalid action'], 400);

} catch (Throwable $e) {
    error_log('Classroom Radar API Error: ' . $e->getMessage());
    radarRespond(['error' => 'ระบบเกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
}
