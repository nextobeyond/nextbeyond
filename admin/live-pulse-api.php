<?php
declare(strict_types=1);

/**
 * admin/live-pulse-api.php  — P1.1: Live Pulse Question (Kahoot Mode)
 *
 * Allows the teacher to push a single question to all students in real-time,
 * collect their answers, and see a live vote breakdown.
 *
 * Endpoints (all require teacher auth via admin/includes/access.php):
 *
 *   POST  ?action=push          Push a new pulse question to students
 *   POST  ?action=close         Close the active pulse question (stop accepting answers)
 *   GET   ?action=results&sessionId=X   Live vote counts for the active question
 *   DELETE ?action=clear&sessionId=X   Clear pulse question (reset)
 *
 * DB table: session_pulse_questions  (auto-created on first call)
 * DB table: session_pulse_answers
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/live-sessions-helper.php';

// ── Schema bootstrap ──────────────────────────────────────────────────────────
function ensurePulseSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `session_pulse_questions` (
            `id`           VARCHAR(64)  NOT NULL PRIMARY KEY,
            `session_id`   VARCHAR(64)  NOT NULL,
            `question_text` TEXT        NOT NULL,
            `options`      JSON         NOT NULL COMMENT 'Array of {key, text} objects',
            `correct_key`  VARCHAR(10)  NULL     COMMENT 'Correct option key, null = opinion poll',
            `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
            `pushed_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `expires_at`   DATETIME     NULL,
            `closed_at`    DATETIME     NULL,
            INDEX `idx_pq_session` (`session_id`),
            INDEX `idx_pq_active`  (`session_id`, `is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `session_pulse_answers` (
            `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `question_id` VARCHAR(64)   NOT NULL,
            `session_id`  VARCHAR(64)   NOT NULL,
            `student_id`  INT           NOT NULL,
            `chosen_key`  VARCHAR(10)   NOT NULL,
            `is_correct`  TINYINT(1)    NOT NULL DEFAULT 0,
            `answered_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_pulse_student` (`question_id`, `student_id`),
            INDEX `idx_pa_question` (`question_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

ensurePulseSchema($pdo);

// ── Helpers ───────────────────────────────────────────────────────────────────
function pulseRespond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function pulseBody(): array
{
    $raw = file_get_contents('php://input');
    $d = json_decode($raw ?: '{}', true);
    return is_array($d) ? $d : [];
}

function getPulseResults(PDO $pdo, string $questionId): array
{
    $stmt = $pdo->prepare("
        SELECT chosen_key, COUNT(*) AS votes, SUM(is_correct) AS correct_votes
        FROM session_pulse_answers
        WHERE question_id = :qid
        GROUP BY chosen_key
        ORDER BY chosen_key
    ");
    $stmt->execute([':qid' => $questionId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalVotes = array_sum(array_column($rows, 'votes'));
    $results    = [];
    foreach ($rows as $row) {
        $results[(string)$row['chosen_key']] = [
            'votes'   => (int)$row['votes'],
            'pct'     => $totalVotes > 0 ? round((int)$row['votes'] / $totalVotes * 100) : 0,
            'correct' => (int)$row['correct_votes'] > 0,
        ];
    }
    return ['totalVotes' => (int)$totalVotes, 'breakdown' => $results];
}

// ── Routing ───────────────────────────────────────────────────────────────────
$method         = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action         = trim((string) ($_GET['action'] ?? ''));
$currentUserId  = (int) ($consoleUser['id'] ?? 0);
$currentRole    = (string) ($consoleUser['role'] ?? 'teacher');

try {

    // ── GET: Live results for active pulse question ───────────────────────────
    if ($method === 'GET' && $action === 'results') {
        $sessionId = trim((string) ($_GET['sessionId'] ?? ''));
        if ($sessionId === '') pulseRespond(['error' => 'sessionId required'], 422);

        authorizeSessionControl($pdo, $sessionId, $currentUserId, $currentRole);

        $stmt = $pdo->prepare("
            SELECT pq.*, COUNT(pa.id) AS answer_count
            FROM session_pulse_questions pq
            LEFT JOIN session_pulse_answers pa ON pa.question_id = pq.id
            WHERE pq.session_id = :sid AND pq.is_active = 1
            GROUP BY pq.id
            LIMIT 1
        ");
        $stmt->execute([':sid' => $sessionId]);
        $pq = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pq) {
            pulseRespond(['active' => false, 'question' => null]);
        }

        $results = getPulseResults($pdo, (string)$pq['id']);

        pulseRespond([
            'active'   => true,
            'question' => [
                'id'          => $pq['id'],
                'text'        => $pq['question_text'],
                'options'     => json_decode((string)$pq['options'], true) ?: [],
                'correctKey'  => $pq['correct_key'],
                'isActive'    => (bool)$pq['is_active'],
                'pushedAt'    => $pq['pushed_at'],
                'expiresAt'   => $pq['expires_at'],
                'closedAt'    => $pq['closed_at'],
                'answerCount' => (int)$pq['answer_count'],
            ],
            'results' => $results,
        ]);
    }

    // ── POST: Push new pulse question ─────────────────────────────────────────
    if ($method === 'POST' && $action === 'push') {
        $body      = pulseBody();
        $sessionId = trim((string) ($body['sessionId'] ?? ''));
        if ($sessionId === '') pulseRespond(['error' => 'sessionId required'], 422);

        authorizeSessionControl($pdo, $sessionId, $currentUserId, $currentRole);

        $questionText = trim((string) ($body['questionText'] ?? ''));
        if ($questionText === '') pulseRespond(['error' => 'questionText required'], 422);

        $rawOptions = $body['options'] ?? [];
        if (!is_array($rawOptions) || count($rawOptions) < 2) {
            pulseRespond(['error' => 'At least 2 options required'], 422);
        }

        // Sanitize options: [{key, text}]
        $options = [];
        foreach ($rawOptions as $opt) {
            $key  = trim((string)($opt['key']  ?? ''));
            $text = trim((string)($opt['text'] ?? ''));
            if ($key !== '' && $text !== '') {
                $options[] = ['key' => $key, 'text' => $text];
            }
        }
        if (count($options) < 2) pulseRespond(['error' => 'At least 2 valid options required'], 422);

        $correctKey  = trim((string)($body['correctKey'] ?? '')) ?: null;
        $durationSec = isset($body['durationSeconds']) ? max(10, (int)$body['durationSeconds']) : null;
        $expiresAt   = $durationSec ? date('Y-m-d H:i:s', time() + $durationSec) : null;

        // Close any currently active pulse question first
        $pdo->prepare("
            UPDATE session_pulse_questions
            SET is_active = 0, closed_at = NOW()
            WHERE session_id = :sid AND is_active = 1
        ")->execute([':sid' => $sessionId]);

        $pqId = 'pq-' . time() . '-' . random_int(1000, 9999);

        $pdo->prepare("
            INSERT INTO session_pulse_questions
                (id, session_id, question_text, options, correct_key, is_active, expires_at)
            VALUES
                (:id, :sid, :text, :opts, :ckey, 1, :exp)
        ")->execute([
            ':id'   => $pqId,
            ':sid'  => $sessionId,
            ':text' => $questionText,
            ':opts' => json_encode($options, JSON_UNESCAPED_UNICODE),
            ':ckey' => $correctKey,
            ':exp'  => $expiresAt,
        ]);

        // Touch classroom_sessions.updated_at so SSE subscribers detect new question
        $pdo->prepare("UPDATE classroom_sessions SET updated_at = NOW() WHERE id = :sid")
            ->execute([':sid' => $sessionId]);

        pulseRespond([
            'success'    => true,
            'questionId' => $pqId,
            'expiresAt'  => $expiresAt,
        ], 201);
    }

    // ── POST: Close active pulse question ─────────────────────────────────────
    if ($method === 'POST' && $action === 'close') {
        $body      = pulseBody();
        $sessionId = trim((string) ($body['sessionId'] ?? ''));
        if ($sessionId === '') pulseRespond(['error' => 'sessionId required'], 422);

        authorizeSessionControl($pdo, $sessionId, $currentUserId, $currentRole);

        // Fetch before closing so we can return results
        $stmt = $pdo->prepare("SELECT id FROM session_pulse_questions WHERE session_id = :sid AND is_active = 1 LIMIT 1");
        $stmt->execute([':sid' => $sessionId]);
        $pqId = $stmt->fetchColumn();

        if (!$pqId) pulseRespond(['success' => true, 'message' => 'ไม่มีคำถามที่เปิดอยู่']);

        $pdo->prepare("UPDATE session_pulse_questions SET is_active = 0, closed_at = NOW() WHERE id = :id")
            ->execute([':id' => $pqId]);

        // Touch session updated_at so SSE subscribers know the question closed
        $pdo->prepare("UPDATE classroom_sessions SET updated_at = NOW() WHERE id = :sid")
            ->execute([':sid' => $sessionId]);

        $results = getPulseResults($pdo, (string)$pqId);

        pulseRespond(['success' => true, 'questionId' => $pqId, 'results' => $results]);
    }

    // ── DELETE: Clear all pulse questions for session ─────────────────────────
    if ($method === 'DELETE' && $action === 'clear') {
        $sessionId = trim((string) ($_GET['sessionId'] ?? ''));
        if ($sessionId === '') pulseRespond(['error' => 'sessionId required'], 422);

        authorizeSessionControl($pdo, $sessionId, $currentUserId, $currentRole);

        $pdo->beginTransaction();
        $pdo->prepare("
            DELETE pa FROM session_pulse_answers pa
            JOIN session_pulse_questions pq ON pq.id = pa.question_id
            WHERE pq.session_id = :sid
        ")->execute([':sid' => $sessionId]);
        $pdo->prepare("DELETE FROM session_pulse_questions WHERE session_id = :sid")
            ->execute([':sid' => $sessionId]);
        $pdo->prepare("UPDATE classroom_sessions SET updated_at = NOW() WHERE id = :sid")
            ->execute([':sid' => $sessionId]);
        $pdo->commit();

        pulseRespond(['success' => true]);
    }

    pulseRespond(['error' => 'Invalid action or method'], 400);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Live Pulse API Error: ' . $e->getMessage());
    pulseRespond(['error' => 'ระบบเกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
}
