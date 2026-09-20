<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/live-sessions-helper.php';
require_once __DIR__ . '/../includes/phase2-session-service.php';
require_once __DIR__ . '/../includes/phase5-mastery-service.php';

ensureLiveSessionSchema($pdo);
$_p2 = new Phase2SessionService($pdo);

function liveSessionRespond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function liveSessionBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$currentUserId = (int) ($consoleUser['id'] ?? 0);

try {
    // ----------------------------------------------------
    // GET: Details of specific session or list
    // ----------------------------------------------------
    if ($method === 'GET') {
        $sessionId = trim((string) ($_GET['sessionId'] ?? ''));

        if ($sessionId !== '') {
            $stmt = $pdo->prepare("SELECT s.*, COALESCE(CONCAT_WS(' ', u.first_name, u.last_name), 'คุณครู') AS teacher_name, e.title AS exam_title, e.subject AS exam_subject FROM classroom_sessions s LEFT JOIN users u ON u.id = s.teacher_id LEFT JOIN exams e ON e.id = s.exam_id WHERE s.id = :id LIMIT 1");
            $stmt->execute([':id' => $sessionId]);
            $session = $stmt->fetch();

            if (!$session) {
                liveSessionRespond(['error' => 'ไม่พบข้อมูล Live Session'], 404);
            }

            $examId = (int) ($session['exam_id'] ?? 0);
            $questions = [];
            if ($examId > 0) {
                $stmtQ = $pdo->prepare("SELECT id, sort_order, question_text, passage, options, correct_answer, explanation, skill FROM exam_questions WHERE exam_id = :eid ORDER BY sort_order, id");
                $stmtQ->execute([':eid' => $examId]);
                $rawQ = $stmtQ->fetchAll();
                $idx = 1;
                foreach ($rawQ as $q) {
                    $opts = json_decode((string) $q['options'], true) ?: [];
                    $formattedOpts = [];
                    foreach ($opts as $optKey => $optVal) {
                        $isCorrect = is_numeric($q['correct_answer']) && (int) $q['correct_answer'] === (int) $optKey;
                        $formattedOpts[] = [
                            'id' => (string) $optKey,
                            'optionKey' => (string) chr(65 + (int) $optKey),
                            'optionText' => (string) $optVal,
                            'isCorrect' => $isCorrect,
                        ];
                    }
                    $questions[] = [
                        'id' => (string) $q['id'],
                        'questionNumber' => $idx++,
                        'subject' => (string) ($session['exam_subject'] ?: 'ทั่วไป'),
                        'topic' => (string) ($q['skill'] ?: 'ข้อสอบมาตรฐาน'),
                        'difficulty' => 'ปานกลาง',
                        'questionText' => (string) $q['question_text'],
                        'explanation' => (string) ($q['explanation'] ?? ''),
                        'options' => $formattedOpts,
                    ];
                }
            }
            $totalQuestions = count($questions);

            // Fetch participants
            $stmtP = $pdo->prepare("
                SELECT sp.*, u.first_name, u.last_name, u.email, u.avatar_url,
                       ta.score, ta.correct_count, ta.total_questions, ta.completed_at, ta.started_at,
                       (SELECT COUNT(*) FROM test_answers WHERE attempt_id = ta.id) AS db_answers_count
                FROM session_participants sp
                JOIN users u ON u.id = sp.student_id
                LEFT JOIN test_attempts ta ON ta.id = sp.attempt_id
                WHERE sp.session_id = :sid
                ORDER BY sp.joined_at ASC
            ");
            $stmtP->execute([':sid' => $sessionId]);
            $participants = $stmtP->fetchAll();

            $lockedStudentIds = json_decode((string) ($session['locked_student_ids'] ?? '[]'), true) ?: [];
            $eyesOnMeEnabled = (bool) $session['eyes_on_me_enabled'];

            $students = [];
            foreach ($participants as $p) {
                $stId = (string) $p['student_id'];
                $isLocked = $eyesOnMeEnabled || in_array($stId, $lockedStudentIds, true);
                $isSubmitted = !empty($p['completed_at']) || $p['status'] === 'submitted';
                $dbAnswers = (int) ($p['db_answers_count'] ?? 0);
                $partAnswers = (int) ($p['answered_count'] ?? 0);
                $answered = max($dbAnswers, $partAnswers);
                $tot = $totalQuestions > 0 ? $totalQuestions : max(1, (int) ($p['total_questions'] ?? 0));
                $progressPct = $tot > 0 ? min(100, round(($answered / $tot) * 100)) : 0;
                $curQ = max(1, min($tot, (int) ($p['current_question'] ?? ($answered + 1))));

                $sessionStatus = $isSubmitted ? 'submitted' : (($answered > 0 || $curQ > 1 || !empty($p['attempt_id'])) ? 'in_progress' : 'joined');
                $scorePct = $p['score'] !== null ? (float) $p['score'] : null;

                $students[] = [
                    'participantId' => (string) $p['id'],
                    'studentId' => $stId,
                    'name' => trim($p['first_name'] . ' ' . $p['last_name']),
                    'email' => (string) $p['email'],
                    'avatarUrl' => $p['avatar_url'] ? (string) $p['avatar_url'] : null,
                    'joinedAt' => (string) $p['joined_at'],
                    'sessionStatus' => $sessionStatus,
                    'attemptId' => $p['attempt_id'] ? (string) $p['attempt_id'] : null,
                    'attemptStatus' => $isSubmitted ? 'submitted' : ($p['attempt_id'] ? 'in_progress' : 'not_started'),
                    'scorePercentage' => $scorePct,
                    'rawScore' => $p['correct_count'] !== null ? (int) $p['correct_count'] : null,
                    'answeredCount' => $answered,
                    'totalQuestions' => $tot,
                    'progressPct' => $progressPct,
                    'currentQuestionNumber' => $curQ,
                    'isEyesOnMeLocked' => $isLocked,
                    'lastActiveAt' => (string) ($p['updated_at'] ?? $p['joined_at']),
                ];
            }

            // Calculate Question Stats
            $questionStats = [];
            if ($examId > 0 && $questions) {
                $qIds = array_map(fn($q) => (int) $q['id'], $questions);
                $placeholders = implode(',', array_fill(0, count($qIds), '?'));
                $stmtAnswers = $pdo->prepare("
                    SELECT question_id, COUNT(*) AS total_answers, SUM(is_correct = 1) AS correct_answers
                    FROM test_answers ta
                    JOIN session_participants sp ON sp.attempt_id = ta.attempt_id
                    WHERE sp.session_id = ? AND question_id IN ($placeholders)
                    GROUP BY question_id
                ");
                $stmtAnswers->execute(array_merge([$sessionId], $qIds));
                $ansStats = [];
                foreach ($stmtAnswers->fetchAll() as $row) {
                    $ansStats[(int) $row['question_id']] = [
                        'total' => (int) $row['total_answers'],
                        'correct' => (int) $row['correct_answers'],
                    ];
                }

                foreach ($questions as $q) {
                    $stat = $ansStats[(int) $q['id']] ?? ['total' => 0, 'correct' => 0];
                    $totA = $stat['total'];
                    $corA = $stat['correct'];
                    $incA = max(0, $totA - $corA);
                    $corPct = $totA > 0 ? round(($corA / $totA) * 100) : 100;
                    $questionStats[] = [
                        'questionId' => (string) $q['id'],
                        'questionNumber' => $q['questionNumber'],
                        'topic' => $q['topic'],
                        'subject' => $q['subject'],
                        'difficulty' => $q['difficulty'],
                        'totalAttempts' => $totA,
                        'incorrectCount' => $incA,
                        'correctPct' => $corPct,
                    ];
                }
            }

            // Sort question stats by incorrectCount DESC
            usort($questionStats, fn($a, $b) => $b['incorrectCount'] <=> $a['incorrectCount']);

            // Parse Combat Log & Top Damage Dealers
            $combatLog = json_decode((string) ($session['boss_combat_log'] ?? '[]'), true) ?: [];
            $dealerMap = [];
            foreach ($combatLog as $hit) {
                $sid = (string) ($hit['studentId'] ?? '');
                $dmg = (int) ($hit['damage'] ?? 0);
                $sname = (string) ($hit['studentName'] ?? 'นักเรียน');
                if ($sid !== '') {
                    if (!isset($dealerMap[$sid])) {
                        $dealerMap[$sid] = ['studentId' => $sid, 'name' => $sname, 'totalDamage' => 0, 'hitCount' => 0];
                    }
                    $dealerMap[$sid]['totalDamage'] += $dmg;
                    $dealerMap[$sid]['hitCount']++;
                }
            }
            $topDamageDealers = array_values($dealerMap);
            usort($topDamageDealers, fn($a, $b) => $b['totalDamage'] <=> $a['totalDamage']);

            liveSessionRespond([
                'session' => [
                    'id' => (string) $session['id'],
                    'teacherId' => (string) $session['teacher_id'],
                    'teacherName' => (string) $session['teacher_name'],
                    'classroomId' => $session['classroom_id'] ? (string) $session['classroom_id'] : null,
                    'classroomName' => 'ห้องเรียนมาตรฐาน',
                    'classroomSection' => null,
                    'title' => (string) $session['title'],
                    'sessionPin' => (string) $session['session_pin'],
                    'assignmentId' => $session['assignment_id'] ? (string) $session['assignment_id'] : null,
                    'examId' => $examId > 0 ? (string) $examId : null,
                    'assignmentTitle' => (string) ($session['exam_title'] ?: 'แบบทดสอบประจำคาบ'),
                    'assignmentMode' => 'live_quiz',
                    'status' => (string) $session['status'],
                    'allowLateJoin' => (bool) $session['allow_late_join'],
                    'hasTimeLimit' => (bool) $session['has_time_limit'],
                    'timeLimitMinutes' => $session['time_limit_minutes'] !== null ? (int) $session['time_limit_minutes'] : null,
                    'educationStage' => (string) $session['education_stage'],
                    'startedAt' => (string) $session['started_at'],
                    'endedAt' => $session['ended_at'] ? (string) $session['ended_at'] : null,
                    'updatedAt' => (string) $session['updated_at'],
                    'joinedStudentsCount' => count($students),
                    'totalQuestions' => $totalQuestions,
                    'eyesOnMeEnabled' => $eyesOnMeEnabled,
                    'lockedStudentIds' => $lockedStudentIds,
                    'announcementMessage' => $session['announcement_message'] ? (string) $session['announcement_message'] : null,
                    'bossFightActive' => (bool) $session['boss_fight_active'],
                    'bossName' => (string) ($session['boss_name'] ?: 'มังกรเพลิงแห่งความรู้ ไครอส'),
                    'bossTheme' => (string) ($session['boss_theme'] ?: 'dragon'),
                    'bossCurrentHp' => (int) $session['boss_current_hp'],
                    'bossMaxHp' => (int) $session['boss_max_hp'],
                    'bossRewardPoints' => (int) $session['boss_reward_points'],
                    'bossDefeated' => (bool) $session['boss_defeated'],
                    'bossCombatLog' => $combatLog,
                    'topDamageDealers' => array_slice($topDamageDealers, 0, 5),
                ],
                'questions' => $questions,
                'students' => $students,
                'questionStats' => $questionStats,
                'bossArchetypes' => getBossArchetypes(),
                // Phase 2: understanding check stats + session topics
                'understandingStats' => $_p2->getUnderstandingStats($sessionId),
                'sessionTopics'      => $_p2->getSessionTopics($sessionId),
            ]);
        }

        // List all sessions for teacher
        $stmt = $pdo->prepare("
            SELECT s.*, e.title AS exam_title,
                   (SELECT COUNT(*) FROM session_participants WHERE session_id = s.id) AS participant_count
            FROM classroom_sessions s
            LEFT JOIN exams e ON e.id = s.exam_id
            WHERE s.teacher_id = :tid OR :isAdmin = 1
            ORDER BY s.status = 'active' DESC, s.created_at DESC
        ");
        $stmt->execute([':tid' => $currentUserId, ':isAdmin' => ($consoleUser['role'] === 'admin' ? 1 : 0)]);
        $sessions = $stmt->fetchAll();

        $activeCount = 0;
        foreach ($sessions as $s) {
            if ($s['status'] === 'active') $activeCount++;
        }

        // Available published exams to pick
        $exams = $pdo->query("SELECT id, title, subject, grade, time_limit_minutes FROM exams WHERE is_published = 1 AND status = 'active' ORDER BY title ASC")->fetchAll();

        liveSessionRespond([
            'sessions' => $sessions,
            'activeCount' => $activeCount,
            'exams' => $exams,
            'bossArchetypes' => getBossArchetypes(),
        ]);
    }

    // ----------------------------------------------------
    // POST: Create new session OR execute action
    // ----------------------------------------------------
    if ($method === 'POST') {
        $action = trim((string) ($_GET['action'] ?? ''));
        $body = liveSessionBody();

        // Specific actions on a session
        if ($action !== '') {
            $sessionId = trim((string) ($body['sessionId'] ?? ($_GET['sessionId'] ?? '')));
            if ($sessionId === '') {
                liveSessionRespond(['error' => 'จำเป็นต้องระบุ sessionId'], 422);
            }

            if ($action === 'reset_student_attempt') {
                $studentId = (int) ($body['studentId'] ?? 0);
                if ($studentId < 1) {
                    liveSessionRespond(['error' => 'ไม่พบ studentId ที่ต้องการรีเซ็ต'], 422);
                }

                $stmtP = $pdo->prepare("SELECT attempt_id FROM session_participants WHERE session_id = :sid AND student_id = :uid");
                $stmtP->execute([':sid' => $sessionId, ':uid' => $studentId]);
                $attId = $stmtP->fetchColumn();

                $pdo->beginTransaction();
                if ($attId) {
                    $pdo->prepare("DELETE FROM test_answers WHERE attempt_id = :aid")->execute([':aid' => $attId]);
                    $pdo->prepare("DELETE FROM test_attempts WHERE id = :aid")->execute([':aid' => $attId]);
                }
                $pdo->prepare("UPDATE session_participants SET attempt_id = NULL, status = 'joined', updated_at = NOW() WHERE session_id = :sid AND student_id = :uid")->execute([':sid' => $sessionId, ':uid' => $studentId]);
                $pdo->commit();

                liveSessionRespond(['success' => true, 'message' => 'รีเซ็ตการทำข้อสอบเรียบร้อยแล้ว']);
            }

            if ($action === 'follow_up_intervention') {
                $studentId = (int)($body['studentId'] ?? 0);
                if ($studentId < 1) liveSessionRespond(['error' => 'กรุณาเลือกนักเรียน'], 422);
                $context = $pdo->prepare("SELECT cs.calendar_event_id,ce.course_id,
                    COALESCE(NULLIF(?,''),(SELECT topic_name FROM session_topics WHERE session_id=cs.id ORDER BY sort_order,id LIMIT 1),cs.title) topic_name
                  FROM classroom_sessions cs LEFT JOIN calendar_events ce ON ce.id=cs.calendar_event_id WHERE cs.id=?");
                $context->execute([trim((string)($body['topicName'] ?? '')),$sessionId]);
                $ctx = $context->fetch(PDO::FETCH_ASSOC);
                $courseId = (int)($ctx['course_id'] ?? 0);
                if (!$courseId) {
                    $course = $pdo->prepare("SELECT course_id FROM enrollments WHERE user_id=? AND status IN ('active','trial') ORDER BY enrolled_at DESC LIMIT 1");
                    $course->execute([$studentId]);$courseId=(int)$course->fetchColumn();
                }
                if (!$courseId) liveSessionRespond(['error' => 'ไม่พบคอร์สสำหรับสร้างรายการติดตาม'], 422);
                $topic = trim((string)($ctx['topic_name'] ?? 'ติดตามหลังเรียน')) ?: 'ติดตามหลังเรียน';
                $gapStmt=$pdo->prepare("SELECT id FROM student_learning_gaps WHERE student_id=? AND course_id=? AND topic_name=? AND status<>'resolved' ORDER BY id DESC LIMIT 1");
                $gapStmt->execute([$studentId,$courseId,$topic]);$gapId=(int)$gapStmt->fetchColumn();
                $result=(new \NextBeyond\Mastery\InterventionService($pdo))->createManual([
                    'student_id'=>$studentId,'course_id'=>$courseId,'gap_id'=>$gapId?:null,'topic_name'=>$topic,
                    'trigger_type'=>'live_class_followup','recommended_action'=>'teacher_feedback','priority_score'=>70,
                    'teacher_notes'=>'ติดตามจาก Live Session '.$sessionId.(!empty($body['notes'])?' — '.trim((string)$body['notes']):''),
                ],$currentUserId);
                liveSessionRespond(['success'=>true]+$result,201);
            }

            if ($action === 'teacher_strike') {
                $damage = max(1, (int) ($body['damage'] ?? 25));
                $stmt = $pdo->prepare("SELECT boss_current_hp, boss_max_hp, boss_combat_log, boss_defeated FROM classroom_sessions WHERE id = :id");
                $stmt->execute([':id' => $sessionId]);
                $ses = $stmt->fetch();
                if (!$ses) liveSessionRespond(['error' => 'ไม่พบเซสชัน'], 404);

                $curHp = (int) $ses['boss_current_hp'];
                $newHp = max(0, $curHp - $damage);
                $isDefeated = $newHp === 0;

                $log = json_decode((string) ($ses['boss_combat_log'] ?? '[]'), true) ?: [];
                array_unshift($log, [
                    'id' => 'hit-' . microtime(true),
                    'studentId' => 'teacher',
                    'studentName' => 'คุณครู (Teacher Strike)',
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

                liveSessionRespond([
                    'success' => true,
                    'damageDealt' => $damage,
                    'currentHp' => $newHp,
                    'isDefeated' => $isDefeated,
                ]);
            }

            if ($action === 'reset_boss') {
                $stmt = $pdo->prepare("SELECT boss_max_hp FROM classroom_sessions WHERE id = :id");
                $stmt->execute([':id' => $sessionId]);
                $maxHp = (int) ($stmt->fetchColumn() ?: 100);

                $pdo->prepare("UPDATE classroom_sessions SET boss_current_hp = :mhp, boss_defeated = 0, boss_combat_log = '[]' WHERE id = :id")->execute([
                    ':mhp' => $maxHp,
                    ':id' => $sessionId,
                ]);
                liveSessionRespond(['success' => true, 'currentHp' => $maxHp]);
            }

            if ($action === 'setup_boss') {
                $bossName = trim((string) ($body['bossName'] ?? ''));
                $bossTheme = trim((string) ($body['bossTheme'] ?? 'dragon'));
                $archetype = getBossArchetype($bossTheme);
                if ($bossName === '') $bossName = $archetype['name'];

                $bossMaxHp = max(10, (int) ($body['bossMaxHp'] ?? 100));
                $bossRewardPoints = max(0, (int) ($body['bossRewardPoints'] ?? 50));

                $pdo->prepare("UPDATE classroom_sessions SET boss_fight_active = 1, boss_name = :bname, boss_theme = :theme, boss_max_hp = :mhp, boss_current_hp = :chp, boss_reward_points = :pts, boss_defeated = 0, boss_combat_log = '[]' WHERE id = :id")->execute([
                    ':bname' => $bossName,
                    ':theme' => $bossTheme,
                    ':mhp' => $bossMaxHp,
                    ':chp' => $bossMaxHp,
                    ':pts' => $bossRewardPoints,
                    ':id' => $sessionId,
                ]);

                liveSessionRespond(['success' => true, 'bossName' => $bossName, 'bossTheme' => $bossTheme, 'bossMaxHp' => $bossMaxHp]);
            }

            liveSessionRespond(['error' => 'ไม่พบคำสั่ง action ที่ระบุ'], 400);
        }

        // CREATE NEW SESSION
        $title = trim((string) ($body['title'] ?? ''));
        if ($title === '') {
            liveSessionRespond(['error' => 'กรุณาระบุชื่อห้องเรียนสด'], 422);
        }

        $examId = (int) ($body['examId'] ?? 0);
        $stage = trim((string) ($body['educationStage'] ?? 'university'));
        $allowLateJoin = !empty($body['allowLateJoin']) ? 1 : 0;
        $hasTimeLimit = !empty($body['hasTimeLimit']) ? 1 : 0;
        $timeLimit = $hasTimeLimit ? max(1, (int) ($body['timeLimitMinutes'] ?? 30)) : null;

        $customPin = trim((string) ($body['customPin'] ?? ''));
        $pin = $customPin !== '' && preg_match('/^\d{6}$/', $customPin) ? $customPin : generateSessionPin($pdo);

        // Enforce Single Active Session
        closeOtherActiveSessions($pdo, $currentUserId);

        $sessionId = 'ses-' . time() . '-' . random_int(1000, 9999);
        $archetype = getBossArchetype('dragon');

        $stmtIns = $pdo->prepare("
            INSERT INTO classroom_sessions (
                id, teacher_id, title, session_pin, exam_id, status,
                allow_late_join, has_time_limit, time_limit_minutes, education_stage,
                boss_name, boss_theme, boss_current_hp, boss_max_hp, boss_reward_points
            ) VALUES (
                :id, :tid, :title, :pin, :eid, 'active',
                :alj, :htl, :tlm, :stage,
                :bname, :btheme, 100, 100, 50
            )
        ");
        $stmtIns->execute([
            ':id' => $sessionId,
            ':tid' => $currentUserId,
            ':title' => $title,
            ':pin' => $pin,
            ':eid' => $examId > 0 ? $examId : null,
            ':alj' => $allowLateJoin,
            ':htl' => $hasTimeLimit,
            ':tlm' => $timeLimit,
            ':stage' => $stage,
            ':bname' => $archetype['name'],
            ':btheme' => $archetype['id'],
        ]);

        // Phase 2: link to calendar event if provided
        $calEventId = (int)($body['calendarEventId'] ?? 0);
        $readinessSummary = null;
        if ($calEventId > 0) {
            $_p2->linkSessionToEvent($sessionId, $calEventId);
            $readinessSummary = $_p2->getEventReadinessSummaryForTeacher($calEventId);
        }

        liveSessionRespond([
            'success' => true,
            'sessionId' => $sessionId,
            'sessionPin' => $pin,
            'hasTimeLimit' => (bool) $hasTimeLimit,
            'timeLimitMinutes' => $timeLimit,
            'readinessSummary' => $readinessSummary,
        ], 201);
    }

    // ----------------------------------------------------
    // PATCH: Update session fields
    // ----------------------------------------------------
    if ($method === 'PATCH') {
        $body = liveSessionBody();
        $sessionId = trim((string) ($body['sessionId'] ?? ''));
        if ($sessionId === '') {
            liveSessionRespond(['error' => 'จำเป็นต้องระบุ sessionId'], 422);
        }

        $fields = [];
        $params = [':id' => $sessionId];

        if (array_key_exists('status', $body)) {
            $status = in_array($body['status'], ['active', 'closed'], true) ? $body['status'] : 'active';
            $fields[] = 'status = :status';
            $params[':status'] = $status;
            if ($status === 'closed') {
                $fields[] = 'ended_at = NOW()';
            } elseif ($status === 'active') {
                closeOtherActiveSessions($pdo, $currentUserId, $sessionId);
            }
        }

        if (array_key_exists('title', $body)) {
            $fields[] = 'title = :title';
            $params[':title'] = trim((string) $body['title']);
        }

        if (array_key_exists('eyesOnMeEnabled', $body)) {
            $fields[] = 'eyes_on_me_enabled = :eyes';
            $params[':eyes'] = !empty($body['eyesOnMeEnabled']) ? 1 : 0;
        }

        if (array_key_exists('lockedStudentIds', $body)) {
            $fields[] = 'locked_student_ids = :locked';
            $params[':locked'] = json_encode(array_values((array) $body['lockedStudentIds']), JSON_UNESCAPED_UNICODE);
        }

        if (array_key_exists('announcementMessage', $body)) {
            $fields[] = 'announcement_message = :ann';
            $params[':ann'] = $body['announcementMessage'] !== null && trim((string) $body['announcementMessage']) !== '' ? trim((string) $body['announcementMessage']) : null;
        }

        if (array_key_exists('bossFightActive', $body)) {
            $fields[] = 'boss_fight_active = :bfa';
            $params[':bfa'] = !empty($body['bossFightActive']) ? 1 : 0;
        }

        if (array_key_exists('bossCurrentHp', $body)) {
            $newHp = max(0, (int) $body['bossCurrentHp']);
            $fields[] = 'boss_current_hp = :bchp';
            $params[':bchp'] = $newHp;
            if ($newHp === 0) {
                $fields[] = 'boss_defeated = 1';
            }
        }

        if (array_key_exists('bossDefeated', $body)) {
            $fields[] = 'boss_defeated = :bdef';
            $params[':bdef'] = !empty($body['bossDefeated']) ? 1 : 0;
        }

        if (array_key_exists('bossMaxHp', $body)) {
            $fields[] = 'boss_max_hp = :bmhp';
            $params[':bmhp'] = max(1, (int) $body['bossMaxHp']);
        }

        if (array_key_exists('bossName', $body)) {
            $fields[] = 'boss_name = :bname';
            $params[':bname'] = trim((string) $body['bossName']);
        }

        if (array_key_exists('bossTheme', $body)) {
            $fields[] = 'boss_theme = :btheme';
            $params[':btheme'] = trim((string) $body['bossTheme']);
        }

        if (!$fields) {
            liveSessionRespond(['error' => 'ไม่มีฟิลด์ที่ต้องการอัปเดต'], 422);
        }

        $sql = 'UPDATE classroom_sessions SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ((isset($newHp) && $newHp === 0) || !empty($body['bossDefeated'])) {
            $stmtDef = $pdo->prepare("SELECT boss_reward_points FROM classroom_sessions WHERE id = :id");
            $stmtDef->execute([':id' => $sessionId]);
            $rPoints = (int) ($stmtDef->fetchColumn() ?: 50);
            awardBossDefeatPoints($pdo, $sessionId, $rPoints);
        }

        // Phase 2: When session closes, sync understanding checks → topic_mastery
        if (isset($status) && $status === 'closed') {
            try { $_p2->syncUnderstandingToMastery($sessionId); } catch (Throwable $e) { /* non-fatal */ }
        }

        liveSessionRespond(['success' => true]);
    }


    // ----------------------------------------------------
    // DELETE: Delete session
    // ----------------------------------------------------
    if ($method === 'DELETE') {
        $action = trim((string) ($_GET['action'] ?? ''));
        if ($action === 'delete_all_closed') {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE sp FROM session_participants sp JOIN classroom_sessions cs ON cs.id = sp.session_id WHERE cs.status = 'closed' AND cs.teacher_id = :tid")->execute([':tid' => $currentUserId]);
            $pdo->prepare("DELETE FROM classroom_sessions WHERE status = 'closed' AND teacher_id = :tid")->execute([':tid' => $currentUserId]);
            $pdo->commit();
            liveSessionRespond(['success' => true]);
        }

        $sessionId = trim((string) ($_GET['sessionId'] ?? ''));
        if ($sessionId === '') {
            liveSessionRespond(['error' => 'จำเป็นต้องระบุ sessionId'], 422);
        }

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM session_participants WHERE session_id = :sid")->execute([':sid' => $sessionId]);
        $pdo->prepare("DELETE FROM classroom_sessions WHERE id = :sid AND (teacher_id = :tid OR :isAdmin = 1)")->execute([
            ':sid' => $sessionId,
            ':tid' => $currentUserId,
            ':isAdmin' => ($consoleUser['role'] === 'admin' ? 1 : 0),
        ]);
        $pdo->commit();

        liveSessionRespond(['success' => true]);
    }

    liveSessionRespond(['error' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Live Sessions API Error: ' . $e->getMessage());
    liveSessionRespond(['error' => 'ระบบเกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
}
