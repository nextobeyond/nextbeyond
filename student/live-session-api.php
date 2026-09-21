<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/../includes/live-sessions-helper.php';
require_once __DIR__ . '/../includes/phase2-session-service.php';

ensureLiveSessionSchema($pdo);
$_p2 = new Phase2SessionService($pdo);

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
    // GET: status (Polling for Eyes On Me, Announcement, Boss, Progress, Lobby)
    // ----------------------------------------------------
    if ($method === 'GET' && ($action === 'status' || isset($_GET['sessionId']))) {
        $sessionId = trim((string) ($_GET['sessionId'] ?? ''));
        if ($sessionId === '') {
            studentLiveRespond(['ok' => false, 'error' => 'จำเป็นต้องระบุ sessionId'], 422);
        }

        $stmt = $pdo->prepare("
            SELECT s.*, 
                   COALESCE(CONCAT_WS(' ', u.first_name, u.last_name), 'คุณครู') AS teacher_name,
                   u.avatar_url AS teacher_avatar,
                   e.title AS exam_title, e.subject AS exam_subject,
                   ce.location, ce.notes AS calendar_notes
            FROM classroom_sessions s 
            LEFT JOIN users u ON u.id = s.teacher_id
            LEFT JOIN exams e ON e.id = s.exam_id
            LEFT JOIN calendar_events ce ON ce.id = s.calendar_event_id
            WHERE s.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $sessionId]);
        $session = $stmt->fetch();

        if (!$session) {
            studentLiveRespond(['ok' => false, 'error' => 'ไม่พบห้องเรียนสดนี้'], 404);
        }

        $lockedIds = json_decode((string) ($session['locked_student_ids'] ?? '[]'), true) ?: [];
        $isLocked = !empty($session['eyes_on_me_enabled']) || in_array((string) $currentStudentId, array_map('strval', $lockedIds), true);

        // Check participant status and whether attempt was reset by teacher
        $stmtPart = $pdo->prepare("
            SELECT sp.*, ta.score, ta.completed_at
            FROM session_participants sp 
            LEFT JOIN test_attempts ta ON ta.id = sp.attempt_id
            WHERE sp.session_id = :sid AND sp.student_id = :uid LIMIT 1
        ");
        $stmtPart->execute([':sid' => $sessionId, ':uid' => $currentStudentId]);
        $part = $stmtPart->fetch();

        $clientAttemptId = (int) ($_GET['attemptId'] ?? 0);
        $resetAttempt = false;
        if ($part && $clientAttemptId > 0) {
            // If client has an attemptId, but participant attempt_id was wiped or changed
            if ($part['attempt_id'] === null || (int) $part['attempt_id'] !== $clientAttemptId) {
                $resetAttempt = true;
            }
        }

        // Real-time progress update from query params if provided
        $currentQ = isset($_GET['currentQ']) ? max(1, (int) $_GET['currentQ']) : null;
        $answered = isset($_GET['answered']) ? max(0, (int) $_GET['answered']) : null;

        if ($part) {
            if ($currentQ !== null && $answered !== null) {
                $pdo->prepare("UPDATE session_participants SET current_question = :cq, answered_count = :ac, updated_at = NOW() WHERE session_id = :sid AND student_id = :uid")
                    ->execute([':cq' => $currentQ, ':ac' => $answered, ':sid' => $sessionId, ':uid' => $currentStudentId]);
            } else {
                $pdo->prepare("UPDATE session_participants SET updated_at = NOW() WHERE session_id = :sid AND student_id = :uid")
                    ->execute([':sid' => $sessionId, ':uid' => $currentStudentId]);
            }
        }

        // Fetch participants for lobby display
        $stmtParticipants = $pdo->prepare("
            SELECT sp.student_id, sp.status, sp.joined_at, 
                   u.first_name, u.last_name, u.avatar_url
            FROM session_participants sp
            JOIN users u ON u.id = sp.student_id
            WHERE sp.session_id = :sid
            ORDER BY sp.joined_at ASC
            LIMIT 30
        ");
        $stmtParticipants->execute([':sid' => $sessionId]);
        $allParts = $stmtParticipants->fetchAll();

        // Fetch topics
        $topics = [];
        try {
            $stmtTop = $pdo->prepare("SELECT topic_name FROM session_topics WHERE session_id = :sid ORDER BY sort_order, id");
            $stmtTop->execute([':sid' => $sessionId]);
            $topics = $stmtTop->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable $e) {}

        $examId = (int) ($session['exam_id'] ?? 0);
        $scorePct = ($part && $part['score'] !== null) ? (float) $part['score'] : null;

        studentLiveRespond([
            'ok' => true,
            'success' => true,
            'sessionId' => (string) $session['id'],
            'sessionTitle' => (string) $session['title'],
            'sessionPin' => (string) $session['session_pin'],
            'sessionStatus' => (string) $session['status'],
            'teacherName' => (string) $session['teacher_name'],
            'teacherAvatar' => $session['teacher_avatar'] ? (string) $session['teacher_avatar'] : null,
            'location' => $session['location'] ? (string) $session['location'] : null,
            'topics' => $topics,
            'examId' => $examId > 0 ? $examId : null,
            'examTitle' => $examId > 0 ? (string) ($session['exam_title'] ?: 'แบบทดสอบประจำคาบ') : null,
            'examSubject' => $examId > 0 ? (string) ($session['exam_subject'] ?: 'ทั่วไป') : null,
            'hasTimeLimit' => (bool) $session['has_time_limit'],
            'timeLimitMinutes' => $session['time_limit_minutes'] !== null ? (int) $session['time_limit_minutes'] : null,
            'participantStatus' => $part ? (string) $part['status'] : null,
            'attemptId' => ($part && $part['attempt_id']) ? (int) $part['attempt_id'] : null,
            'scorePercentage' => $scorePct,
            'isEyesOnMeLocked' => $isLocked,
            'announcementMessage' => $session['announcement_message'] ? (string) $session['announcement_message'] : null,
            'bossFightActive' => false,
            'bossName' => (string) ($session['boss_name'] ?: 'มังกรเพลิงแห่งความรู้ ไครอส'),
            'bossTheme' => (string) ($session['boss_theme'] ?: 'dragon'),
            'bossCurrentHp' => (int) $session['boss_current_hp'],
            'bossMaxHp' => (int) $session['boss_max_hp'],
            'bossDefeated' => (bool) $session['boss_defeated'],
            'bossRewardPoints' => (int) $session['boss_reward_points'],
            'resetAttempt' => $resetAttempt,
            'sessionClosed' => ($session['status'] === 'closed'),
            'participantsCount' => count($allParts),
            'participants' => array_map(fn($p) => [
                'id' => (int)$p['student_id'],
                'name' => trim($p['first_name'] . ' ' . $p['last_name']),
                'avatarUrl' => $p['avatar_url'] ? (string)$p['avatar_url'] : null,
                'status' => (string)$p['status'],
            ], $allParts),
        ]);
    }

    // ----------------------------------------------------
    // POST: Join via PIN
    // ----------------------------------------------------
    if ($method === 'POST' && ($action === 'join' || $action === '')) {
        $body = studentLiveBody();
        $pin = preg_replace('/[^\d]/', '', (string) ($body['sessionPin'] ?? ''));

        if (strlen($pin) !== 6) {
            studentLiveRespond(['ok' => false, 'error' => 'กรุณากรอกรหัส PIN ให้ครบ 6 หลัก'], 422);
        }

        $stmt = $pdo->prepare("
            SELECT s.*, 
                   COALESCE(CONCAT_WS(' ', u.first_name, u.last_name), 'คุณครู') AS teacher_name, 
                   e.title AS exam_title,
                   ce.location
            FROM classroom_sessions s 
            LEFT JOIN users u ON u.id = s.teacher_id 
            LEFT JOIN exams e ON e.id = s.exam_id 
            LEFT JOIN calendar_events ce ON ce.id = s.calendar_event_id
            WHERE s.session_pin = :pin AND s.status = 'active' 
            LIMIT 1
        ");
        $stmt->execute([':pin' => $pin]);
        $session = $stmt->fetch();

        if (!$session) {
            studentLiveRespond(['ok' => false, 'error' => 'ไม่พบห้องเรียนสดที่เปิดอยู่ด้วยรหัส PIN นี้ (กรุณาตรวจสอบว่าคุณครูเปิดห้องเรียนแล้ว)'], 404);
        }

        $sessionId = (string) $session['id'];
        $examId = (int) ($session['exam_id'] ?? 0);

        // Check or insert session_participants
        $stmtP = $pdo->prepare("SELECT id, attempt_id, status FROM session_participants WHERE session_id = :sid AND student_id = :uid LIMIT 1");
        $stmtP->execute([':sid' => $sessionId, ':uid' => $currentStudentId]);
        $participant = $stmtP->fetch();

        // Check late join permission if participant has not joined yet
        if (!$participant && empty($session['allow_late_join'])) {
            $startedTimestamp = strtotime($session['started_at']);
            if (time() - $startedTimestamp > 300) {
                studentLiveRespond(['ok' => false, 'error' => 'ห้องเรียนนี้ไม่อนุญาตให้นักเรียนเข้าร่วมหลังจากเริ่มเซสชันไปแล้ว'], 403);
            }
        }

        $attemptId = null;
        if ($participant) {
            $attemptId = $participant['attempt_id'] ? (int) $participant['attempt_id'] : null;
        } else {
            if ($examId > 0) {
                // Find existing unfinished attempt for this exam
                $stmtAtt = $pdo->prepare("SELECT id FROM test_attempts WHERE user_id = :uid AND exam_id = :eid AND completed_at IS NULL ORDER BY started_at DESC LIMIT 1");
                $stmtAtt->execute([':uid' => $currentStudentId, ':eid' => $examId]);
                $existingAtt = $stmtAtt->fetchColumn();

                if ($existingAtt) {
                    $attemptId = (int) $existingAtt;
                }
            }

            $partId = 'sp-' . time() . '-' . random_int(1000, 9999);
            $stmtIns = $pdo->prepare("INSERT INTO session_participants (id, session_id, student_id, attempt_id, status, current_question, answered_count, joined_at) VALUES (:pid, :sid, :uid, :aid, 'joined', 1, 0, NOW())");
            $stmtIns->execute([
                ':pid' => $partId,
                ':sid' => $sessionId,
                ':uid' => $currentStudentId,
                ':aid' => $attemptId,
            ]);
        }

        studentLiveRespond([
            'ok' => true,
            'success' => true,
            'session' => [
                'id' => $sessionId,
                'title' => (string) $session['title'],
                'sessionPin' => (string) $session['session_pin'],
                'teacherName' => (string) $session['teacher_name'],
                'examId' => $examId > 0 ? $examId : null,
                'examTitle' => $examId > 0 ? (string) $session['exam_title'] : null,
                'hasTimeLimit' => (bool) $session['has_time_limit'],
                'timeLimitMinutes' => $session['time_limit_minutes'] !== null ? (int) $session['time_limit_minutes'] : null,
            ],
            'lobbyUrl' => 'live-session.php?sessionId=' . urlencode($sessionId),
            'examUrl' => $examId > 0 ? ('take-test.php?id=' . $examId . '&sessionId=' . urlencode($sessionId)) : null,
        ]);
    }

    // ----------------------------------------------------
    // POST: Leave session
    // ----------------------------------------------------
    if ($method === 'POST' && $action === 'leave') {
        $body = studentLiveBody();
        $sessionId = trim((string) ($body['sessionId'] ?? ''));
        if ($sessionId !== '') {
            $pdo->prepare("DELETE FROM session_participants WHERE session_id = :sid AND student_id = :uid AND status = 'joined'")
                ->execute([':sid' => $sessionId, ':uid' => $currentStudentId]);
        }
        studentLiveRespond(['ok' => true, 'success' => true]);
    }

    // ----------------------------------------------------
    // POST: deal_damage (Boss Fight Hit)
    // ----------------------------------------------------
    if ($method === 'POST' && $action === 'deal_damage') {
        $body = studentLiveBody();
        $sessionId = trim((string) ($body['sessionId'] ?? ''));
        $damage = max(1, min(50, (int) ($body['damage'] ?? 10)));

        if ($sessionId === '') {
            studentLiveRespond(['ok' => false, 'error' => 'จำเป็นต้องระบุ sessionId'], 422);
        }

        $stmt = $pdo->prepare("SELECT boss_fight_active, boss_current_hp, boss_max_hp, boss_combat_log, boss_defeated, boss_reward_points FROM classroom_sessions WHERE id = :id");
        $stmt->execute([':id' => $sessionId]);
        $ses = $stmt->fetch();

        if (!$ses || empty($ses['boss_fight_active'])) {
            studentLiveRespond(['ok' => true, 'success' => false, 'message' => 'บอสไฟท์ยังไม่เปิดใช้งาน']);
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

        if ($isDefeated && empty($ses['boss_defeated'])) {
            awardBossDefeatPoints($pdo, $sessionId, (int) ($ses['boss_reward_points'] ?? 50));
        }

        studentLiveRespond([
            'ok' => true,
            'success' => true,
            'damageDealt' => $damage,
            'bossCurrentHp' => $newHp,
            'bossDefeated' => $isDefeated,
        ]);
    }

    // ----------------------------------------------------
    // POST: understanding_check — student signals got_it/confused/somewhat
    // ----------------------------------------------------
    if ($method === 'POST' && $action === 'understanding_check') {
        $body          = studentLiveBody();
        $sessionId     = trim((string)($body['sessionId'] ?? ''));
        $understanding = trim((string)($body['understanding'] ?? ''));
        $topicName     = trim((string)($body['topicName'] ?? ''));
        $qIdx          = isset($body['questionIndex']) ? (int)$body['questionIndex'] : null;

        if ($sessionId === '') studentLiveRespond(['ok' => false, 'error' => 'sessionId required'], 422);
        if (!in_array($understanding, ['got_it','confused','somewhat'], true)) {
            studentLiveRespond(['ok' => false, 'error' => 'understanding must be got_it, confused, or somewhat'], 422);
        }

        // Verify student is a participant
        $chk = $pdo->prepare("SELECT 1 FROM session_participants WHERE session_id = :sid AND student_id = :uid LIMIT 1");
        $chk->execute([':sid' => $sessionId, ':uid' => $currentStudentId]);
        if (!$chk->fetchColumn()) studentLiveRespond(['ok' => false, 'error' => 'ไม่พบข้อมูลการเข้าร่วม'], 403);

        $_p2->saveUnderstandingCheck($sessionId, $currentStudentId, $qIdx, $topicName, $understanding);
        studentLiveRespond(['ok' => true, 'success' => true, 'understanding' => $understanding]);
    }

    studentLiveRespond(['error' => 'Method not allowed'], 405);

} catch (Throwable $e) {
    error_log('Student Live Session API Error: ' . $e->getMessage());
    studentLiveRespond(['error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
}
