<?php
declare(strict_types=1);

/**
 * Nextbeyond Compass - Learning Journey Admin API
 * Manages Learning Goals, Learning Profiles, and Topic Mastery for Admin & Teachers
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/learning-journey-service.php';

function journeyRespond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $service = new LearningJourneyService($pdo);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $action = trim((string)($_GET['action'] ?? 'get_goals'));
        $studentId = (int)($_GET['student_id'] ?? 0);
        $courseId = isset($_GET['course_id']) && $_GET['course_id'] !== '' ? (int)$_GET['course_id'] : null;

        if ($studentId <= 0) {
            journeyRespond(['error' => 'รหัสนักเรียนไม่ถูกต้อง'], 422);
        }

        if ($action === 'get_goals') {
            $goals = $service->getStudentLearningGoals($studentId, $courseId, false);
            journeyRespond(['success' => true, 'goals' => $goals]);
        }

        if ($action === 'get_profile') {
            if (!$courseId) {
                journeyRespond(['error' => 'กรุณาระบุ course_id สำหรับ Learning Profile'], 422);
            }
            $profile = $service->getStudentLearningProfile($studentId, $courseId);
            $mastery = $service->getStudentTopicMastery($studentId, $courseId);
            journeyRespond(['success' => true, 'profile' => $profile, 'mastery' => $mastery]);
        }

        if ($action === 'get_next_action') {
            $nextAction = $service->getStudentNextAction($studentId, $courseId);
            $upcoming = $service->getStudentUpcomingList($studentId);
            journeyRespond(['success' => true, 'next_action' => $nextAction, 'upcoming' => $upcoming]);
        }

        journeyRespond(['error' => 'Action ไม่ถูกต้อง'], 400);
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) {
            $body = $_POST;
        }

        $action = trim((string)($body['action'] ?? ''));

        if ($action === 'seed_data') {
            $res = $service->seedPhase1AcceptanceData();
            journeyRespond(['success' => true, 'data' => $res, 'message' => 'เตรียมข้อมูลทดสอบ Phase 1 เรียบร้อยแล้ว']);
        }

        $studentId = (int)($body['student_id'] ?? 0);
        if ($studentId <= 0) {
            journeyRespond(['error' => 'รหัสนักเรียนไม่ถูกต้อง'], 422);
        }

        if ($action === 'save_goal') {
            $courseId = (int)($body['course_id'] ?? 0);
            if ($courseId <= 0) {
                journeyRespond(['error' => 'กรุณาเลือกคอร์สเรียน'], 422);
            }
            $goalName = trim((string)($body['goal_name'] ?? ''));
            if ($goalName === '') {
                journeyRespond(['error' => 'กรุณากรอกชื่อเป้าหมาย'], 422);
            }

            $savedId = $service->saveLearningGoal($studentId, $courseId, [
                'id' => $body['id'] ?? 0,
                'goal_type' => $body['goal_type'] ?? 'a_level',
                'goal_name' => $goalName,
                'target_score' => $body['target_score'] ?? null,
                'target_date' => $body['target_date'] ?? null,
                'priority' => $body['priority'] ?? 'high',
                'status' => $body['status'] ?? 'active',
            ]);

            journeyRespond(['success' => true, 'goal_id' => $savedId, 'message' => 'บันทึกเป้าหมายการเรียนสำเร็จ']);
        }

        if ($action === 'archive_goal') {
            $goalId = (int)($body['goal_id'] ?? 0);
            if ($goalId <= 0) {
                journeyRespond(['error' => 'รหัสเป้าหมายไม่ถูกต้อง'], 422);
            }
            $ok = $service->archiveLearningGoal($goalId, $studentId);
            journeyRespond(['success' => $ok, 'message' => 'จัดเก็บเป้าหมายเรียบร้อย']);
        }

        journeyRespond(['error' => 'Action ไม่ถูกต้อง'], 400);
    }

    journeyRespond(['error' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    journeyRespond(['error' => $e->getMessage()], 500);
}
