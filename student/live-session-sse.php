<?php
declare(strict_types=1);

/**
 * student/live-session-sse.php  — P1.3: Server-Sent Events Stream
 *
 * Streams real-time classroom signals to the student browser:
 *   - eyes_on_me    : lock/unlock signal
 *   - announcement  : teacher broadcast message
 *   - pulse_question: Kahoot-style push question
 *   - boss_update   : current HP sync
 *   - session_closed: session ended signal
 *
 * The client falls back to 3s polling if SSE is unsupported or disconnects.
 * Max lifetime: 55 seconds (before proxy/load-balancer timeout).
 * Client reconnects automatically via EventSource retry logic.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/guard.php';

// ── SSE headers ──────────────────────────────────────────────────────────────
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-store');
header('X-Accel-Buffering: no');   // Disable nginx output buffering
header('Connection: keep-alive');

// ── Parameters ───────────────────────────────────────────────────────────────
$sessionId = trim((string) ($_GET['sessionId'] ?? ''));
$studentId = (int) ($currentUser['id'] ?? 0);

if ($sessionId === '' || $studentId < 1) {
    echo "event: error\ndata: " . json_encode(['message' => 'sessionId required'], JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
    exit;
}

// ── Helper: send SSE event ────────────────────────────────────────────────────
function sseEmit(string $event, array $data): void
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}

// ── Initial state snapshot (sent immediately on connect) ─────────────────────
$stmt = $pdo->prepare("
    SELECT s.status, s.eyes_on_me_enabled, s.locked_student_ids,
           s.announcement_message, s.boss_fight_active, s.boss_name,
           s.boss_theme, s.boss_current_hp, s.boss_max_hp, s.boss_defeated,
           s.boss_reward_points, s.updated_at,
           pq.id        AS pq_id,
           pq.question_text AS pq_text,
           pq.options   AS pq_options,
           pq.expires_at AS pq_expires_at,
           pq.is_active AS pq_active
    FROM classroom_sessions s
    LEFT JOIN session_pulse_questions pq
           ON pq.session_id = s.id AND pq.is_active = 1
    WHERE s.id = :sid
    LIMIT 1
");
$stmt->execute([':sid' => $sessionId]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    echo "event: error\ndata: " . json_encode(['message' => 'session not found'], JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
    exit;
}

// Check student is a participant
$chkPart = $pdo->prepare("SELECT 1 FROM session_participants WHERE session_id = :sid AND student_id = :uid LIMIT 1");
$chkPart->execute([':sid' => $sessionId, ':uid' => $studentId]);
if (!$chkPart->fetchColumn()) {
    echo "event: error\ndata: " . json_encode(['message' => 'not a participant'], JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
    exit;
}

// ── Compute locked state for this student ────────────────────────────────────
function isStudentLocked(array $session, int $studentId): bool
{
    if (!empty($session['eyes_on_me_enabled'])) return true;
    $locked = json_decode((string)($session['locked_student_ids'] ?? '[]'), true) ?: [];
    return in_array((string)$studentId, array_map('strval', $locked), true);
}

// ── Send initial snapshot ────────────────────────────────────────────────────
sseEmit('init', [
    'sessionStatus'      => (string) $session['status'],
    'isEyesOnMeLocked'   => isStudentLocked($session, $studentId),
    'announcementMessage'=> $session['announcement_message'],
    'bossFightActive'    => (bool) $session['boss_fight_active'],
    'bossName'           => (string) ($session['boss_name'] ?: 'มังกรเพลิงแห่งความรู้ ไครอส'),
    'bossTheme'          => (string) ($session['boss_theme'] ?: 'dragon'),
    'bossCurrentHp'      => (int) $session['boss_current_hp'],
    'bossMaxHp'          => (int) $session['boss_max_hp'],
    'bossDefeated'       => (bool) $session['boss_defeated'],
    'pulseQuestion'      => $session['pq_id'] ? [
        'id'        => $session['pq_id'],
        'text'      => $session['pq_text'],
        'options'   => json_decode((string)$session['pq_options'], true) ?: [],
        'expiresAt' => $session['pq_expires_at'],
    ] : null,
]);

// ── Polling loop ─────────────────────────────────────────────────────────────
$lastUpdatedAt   = (string) $session['updated_at'];
$lastPqId        = $session['pq_id'];
$maxLifetime     = 55;   // seconds — reconnect before proxy kills connection
$pollInterval    = 2;    // seconds between DB checks
$startTime       = time();

set_time_limit($maxLifetime + 5);

while (true) {
    // Stop if client disconnected or lifetime exceeded
    if (connection_aborted() || (time() - $startTime) >= $maxLifetime) {
        break;
    }

    sleep($pollInterval);

    if (connection_aborted()) break;

    // Fetch current session state (lightweight query)
    $stmt = $pdo->prepare("
        SELECT s.status, s.eyes_on_me_enabled, s.locked_student_ids,
               s.announcement_message, s.boss_fight_active, s.boss_name,
               s.boss_theme, s.boss_current_hp, s.boss_max_hp, s.boss_defeated,
               s.boss_reward_points, s.updated_at,
               pq.id        AS pq_id,
               pq.question_text AS pq_text,
               pq.options   AS pq_options,
               pq.expires_at AS pq_expires_at,
               pq.is_active AS pq_active
        FROM classroom_sessions s
        LEFT JOIN session_pulse_questions pq
               ON pq.session_id = s.id AND pq.is_active = 1
        WHERE s.id = :sid
        LIMIT 1
    ");
    $stmt->execute([':sid' => $sessionId]);
    $cur = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cur) break; // Session deleted

    // ── Session closed ────────────────────────────────────────────────────────
    if ($cur['status'] === 'closed') {
        sseEmit('session_closed', ['sessionId' => $sessionId]);
        break;
    }

    // ── Only emit events when something changed ───────────────────────────────
    if ($cur['updated_at'] !== $lastUpdatedAt || $cur['pq_id'] !== $lastPqId) {
        $lastUpdatedAt = (string) $cur['updated_at'];
        $lastPqId      = $cur['pq_id'];

        // Eyes On Me / Lock state
        sseEmit('status_update', [
            'isEyesOnMeLocked'    => isStudentLocked($cur, $studentId),
            'announcementMessage' => $cur['announcement_message'],
            'bossFightActive'     => (bool) $cur['boss_fight_active'],
            'bossCurrentHp'       => (int) $cur['boss_current_hp'],
            'bossMaxHp'           => (int) $cur['boss_max_hp'],
            'bossDefeated'        => (bool) $cur['boss_defeated'],
        ]);

        // New Pulse Question pushed by teacher
        if ($cur['pq_id'] && $cur['pq_id'] !== $session['pq_id']) {
            sseEmit('pulse_question', [
                'id'        => $cur['pq_id'],
                'text'      => $cur['pq_text'],
                'options'   => json_decode((string)$cur['pq_options'], true) ?: [],
                'expiresAt' => $cur['pq_expires_at'],
            ]);
        }
    }

    // ── Heartbeat every ~10s to keep connection alive ─────────────────────────
    if ((time() - $startTime) % 10 < $pollInterval) {
        echo ": heartbeat\n\n";
        if (ob_get_level() > 0) ob_flush();
        flush();
    }
}

// ── Reconnect hint: client EventSource will auto-reconnect after retry ms ────
echo "retry: 3000\n\n";
if (ob_get_level() > 0) ob_flush();
flush();
