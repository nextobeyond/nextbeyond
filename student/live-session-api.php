<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/../includes/live-sessions-helper.php';

ensureLiveSessionSchema($pdo);

function studentLiveRespond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function studentLiveBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = trim((string) ($_GET['action'] ?? ''));
$currentStudentId = (int) ($currentUser['id'] ?? 0);

try {
    // ----------------------------------------------------
    // GET: status (Polling for Eyes On Me, Announcement, Boss)
    // ----------------------------------------------------
    if ($method === 'GET' && ($action === 'status' || isset($_GET['sessionId']))) {
        $sessionId = trim((string) ($_GET['sessionId'] ?? ''));
        if ($sessionId === '') {
            studentLiveRespond(['error' => 'จำเป็นต้องระบุ sessionId'], 422);
        }

        $stmt = $pdo->prepare("SELECT id, status, eyes_on_me_enabled, locked_student_ids, announcement_message, boss_fight_active, boss_name, boss_theme, boss_current_hp, boss_max_hp, boss_defeated FROM classroom_sessions WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $sessionId]);
        $session = $stmt->fetch();

        if (!$session) {
            studentLiveRespond(['error' => 'ไม่พบห้องเรียนสดนี้'], 404);
        }

        $lockedIds = json_decode((string) ($session['locked_student_ids'] ?? '[]'), true) ?: [];
        $isLocked = !empty($session['eyes_on_me_enabled']) || in_array((string) $currentStudentId, array_map('strval', $lockedIds), true);

        // Update participant's heartbeat/status
        $pdo->prepare("UPDATE session_participants SET updated_at = NOW() WHERE session_id = :sid AND student_id = :uid")->execute([':sid' => $sessionId, ':uid' => $currentStudentId]);

        studentLiveRespond([
            'success' => true,
            'sessionId' => (string) $session['id'],
            'sessionStatus' => (string) $session['status'],
            'isEyesOnMeLocked' => $isLocked,
            'announcementMessage' => $session['announcement_message'] ? (string) $session['announcement_message'] : null,
            'bossFightActive' => (bool) $session['boss_fight_active'],
            'bossName' => (string) $session['boss_name'],
            'bossTheme' => (string) $session['boss_theme'],
            'bossCurrentHp' => (int) $session['boss_current_hp'],
            'bossMaxHp' => (int) $session['boss_max_hp'],
            'bossDefeated' => (bool) $session['boss_defeated'],
        ]);
    }

    // ----------------------------------------------------
    // POST: Join via PIN
    // ----------------------------------------------------
    if ($method === 'POST' && ($action === 'join' || $action === '')) {
        $body = studentLiveBody();
        $pin = preg_replace('/[^\d]/', '', (string) ($body['sessionPin'] ?? ''));

        if (strlen($pin) !== 6) {
            studentLiveRespond(['error' => 'กรุณากรอกรหัส PIN ให้ครบ 6 หลัก'], 422);
        }

        $stmt = $pdo->prepare("SELECT s.*, CONCAT_WS(' ', u.first_name, u.last_name) AS teacher_name, e.title AS exam_title FROM classroom_sessions s JOIN users u ON u.id = s.teacher_id LEFT JOIN exams e ON e.id = s.exam_id WHERE s.session_pin = :pin AND s.status = 'active' LIMIT 1");
        $stmt->execute([':pin' => $pin]);
        $session = $stmt->fetch();

        if (!$session) {
            studentLiveRespond(['error' => 'ไม่พบห้องเรียนสดที่เปิดอยู่ด้วยรหัส PIN นี้ (กรุณาตรวจสอบว่าคุณครูเปิดห้องเรียนแล้ว)'], 404);
        }

        $sessionId = (string) $session['id'];
        $examId = (int) ($session['exam_id'] ?? 0);

        if ($examId < 1) {
            studentLiveRespond(['error' => 'ห้องเรียนนี้ยังไม่ได้กำหนดแบบทดสอบ'], 422);
        }

        // Check or insert session_participants
        $stmtP = $pdo->prepare("SELECT id, attempt_id, status FROM session_participants WHERE session_id = :sid AND student_id = :uid LIMIT 1");
        $stmtP->execute([':sid' => $sessionId, ':uid' => $currentStudentId]);
        $participant = $stmtP->fetch();

        $attemptId = null;
        if ($participant) {
            $attemptId = $participant['attempt_id'] ? (int) $participant['attempt_id'] : null;
        } else {
            // Find existing unfinished attempt for this exam
            $stmtAtt = $pdo->prepare("SELECT id FROM test_attempts WHERE user_id = :uid AND exam_id = :eid AND completed_at IS NULL ORDER BY started_at DESC LIMIT 1");
            $stmtAtt->execute([':uid' => $currentStudentId, ':eid' => $examId]);
            $existingAtt = $stmtAtt->fetchColumn();

            if ($existingAtt) {
                $attemptId = (int) $existingAtt;
            }

            $partId = 'sp-' . time() . '-' . random_int(1000, 9999);
            $stmtIns = $pdo->prepare("INSERT INTO session_participants (id, session_id, student_id, attempt_id, status, joined_at) VALUES (:pid, :sid, :uid, :aid, 'joined', NOW())");
            $stmtIns->execute([
                ':pid' => $partId,
                ':sid' => $sessionId,
                ':uid' => $currentStudentId,
                ':aid' => $attemptId,
            ]);
        }

        studentLiveRespond([
            'success' => true,
            'session' => [
                'id' => $sessionId,
                'title' => (string) $session['title'],
                'sessionPin' => (string) $session['session_pin'],
                'teacherName' => (string) $session['teacher_name'],
                'examId' => $examId,
                'examTitle' => (string) $session['exam_title'],
                'hasTimeLimit' => (bool) $session['has_time_limit'],
                'timeLimitMinutes' => $session['time_limit_minutes'] !== null ? (int) $session['time_limit_minutes'] : null,
            ],
            'redirectUrl' => 'take-test.php?id=' . $examId . '&sessionId=' . urlencode($sessionId),
        ]);
    }

    // ----------------------------------------------------
    // POST: deal_damage (Boss Fight Hit)
    // ----------------------------------------------------
    if ($method === 'POST' && $action === 'deal_damage') {
        $body = studentLiveBody();
        $sessionId = trim((string) ($body['sessionId'] ?? ''));
        $damage = max(1, min(50, (int) ($body['damage'] ?? 10)));

        if ($sessionId === '') {
            studentLiveRespond(['error' => 'จำเป็นต้องระบุ sessionId'], 422);
        }

        $stmt = $pdo->prepare("SELECT boss_fight_active, boss_current_hp, boss_max_hp, boss_combat_log, boss_defeated FROM classroom_sessions WHERE id = :id");
        $stmt->execute([':id' => $sessionId]);
        $ses = $stmt->fetch();

        if (!$ses || empty($ses['boss_fight_active'])) {
            studentLiveRespond(['success' => false, 'message' => 'บอสไฟท์ยังไม่เปิดใช้งาน']);
        }

        $curHp = (int) $ses['boss_current_hp'];
        $newHp = max(0, $curHp - $damage);
        $isDefeated = $newHp === 0;

        $studentName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?: 'นักเรียน';

        $log = json_decode((string) ($ses['boss_combat_log'] ?? '[]'), true) ?: [];
        array_unshift($log, [
            'id' => 'hit-' . microtime(true),
            'studentId' => (string) $currentStudentId,
            'studentName' => $studentName,
            'damage' => $damage,
            'timestamp' => date('c'),
        ]);
        $log = array_slice($log, 0, 50);

        $pdo->prepare("UPDATE classroom_sessions SET boss_current_hp = :nhp, boss_defeated = :def, boss_combat_log = :log WHERE id = :id")->execute([
            ':nhp' => $newHp,
            ':def' => $isDefeated ? 1 : ($ses['boss_defeated'] ? 1 : 0),
            ':log' => json_encode($log, JSON_UNESCAPED_UNICODE),
            ':id' => $sessionId,
        ]);

        studentLiveRespond([
            'success' => true,
            'damageDealt' => $damage,
            'bossCurrentHp' => $newHp,
            'bossDefeated' => $isDefeated,
        ]);
    }

    studentLiveRespond(['error' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    error_log('Student Live Session API Error: ' . $e->getMessage());
    studentLiveRespond(['error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
}
