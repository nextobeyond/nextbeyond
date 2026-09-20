<?php
/**
 * student/activity-api.php
 * NEXTBEYOND V2 — Phase 3: Learning Activity API (Autosave, Sync, Submit, State)
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/../includes/phase3-mastery-service.php';

function actRespond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$p3 = new Phase3MasteryService($pdo);
$currentStudentId = (int)($currentUser['id'] ?? 0);

if ($currentStudentId < 1) {
    actRespond(['error' => 'กรุณาเข้าสู่ระบบก่อนดำเนินการ'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawInput = file_get_contents('php://input');
$postData = [];
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $postData = $decoded;
    }
}
$input = array_merge($_POST, $postData);
$action = (string)($input['action'] ?? ($_GET['action'] ?? ''));

try {
    // 1. GET STATE / QUESTIONS
    if ($method === 'GET' || $action === 'get_state') {
        $assignmentId = (int)($_GET['assignment_id'] ?? ($input['assignment_id'] ?? 0));
        $submissionId = (int)($_GET['submission_id'] ?? ($input['submission_id'] ?? 0));

        if ($assignmentId < 1 && $submissionId < 1) {
            actRespond(['error' => 'assignment_id or submission_id required'], 422);
        }

        if ($assignmentId > 0) {
            // Verify access control
            if (!$p3->canStudentAccessAssignment($currentStudentId, $assignmentId)) {
                actRespond(['error' => 'ไม่มีสิทธิ์เข้าถึงกิจกรรมการเรียนรู้นี้'], 403);
            }
            $submission = $p3->getOrCreateSubmission($assignmentId, $currentStudentId);
            $submissionId = (int)$submission['id'];
        } else {
            // Load submission and verify ownership
            $stmtSub = $pdo->prepare("SELECT * FROM activity_submissions WHERE id = ?");
            $stmtSub->execute([$submissionId]);
            $submission = $stmtSub->fetch(PDO::FETCH_ASSOC);
            if (!$submission || (int)$submission['student_id'] !== $currentStudentId) {
                actRespond(['error' => 'ไม่พบข้อมูลการทำกิจกรรมนี้'], 404);
            }
            $assignmentId = (int)$submission['assignment_id'];
            if (!$p3->canStudentAccessAssignment($currentStudentId, $assignmentId)) {
                actRespond(['error' => 'ไม่มีสิทธิ์เข้าถึงกิจกรรมการเรียนรู้นี้'], 403);
            }
        }

        $assignment = $p3->getAssignmentDetails($assignmentId);
        if (!$assignment) {
            actRespond(['error' => 'ไม่พบข้อมูลการมอบหมายกิจกรรม'], 404);
        }

        // Fetch saved answers for this submission
        $stmtAns = $pdo->prepare("
            SELECT question_id, student_answer, is_correct, score, time_spent_seconds, hint_used, saved_at
            FROM activity_answers
            WHERE submission_id = ?
        ");
        $stmtAns->execute([$submissionId]);
        $savedAnswers = [];
        foreach ($stmtAns->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $savedAnswers[(int)$row['question_id']] = [
                'answer' => (string)$row['student_answer'],
                'time_spent' => (int)$row['time_spent_seconds'],
                'hint_used' => (bool)$row['hint_used'],
                'updated_at' => $row['saved_at'],
            ];
        }

        actRespond([
            'success' => true,
            'assignment' => [
                'id' => (int)$assignment['id'],
                'title' => $assignment['title'],
                'activity_type' => $assignment['activity_type'] ?? 'worksheet',
                'topic_name' => $assignment['topic_name'] ?? $assignment['topic'] ?? '',
                'course_title' => $assignment['course_title'] ?? '',
                'subject' => $assignment['subject'] ?? '',
                'due_date' => $assignment['due_date'],
                'max_attempts' => (int)($assignment['max_attempts'] ?? 1),
                'pass_score' => (float)($assignment['pass_score'] ?? 60),
                'question_count' => count($assignment['questions'] ?? []),
            ],
            'submission' => [
                'id' => (int)$submission['id'],
                'status' => $submission['status'],
                'attempt_number' => (int)$submission['attempt_number'],
                'started_at' => $submission['started_at'],
                'submitted_at' => $submission['submitted_at'],
                'score' => $submission['score'],
                'max_score' => $submission['max_score'],
                'percentage' => $submission['score_percent'],
            ],
            'questions' => $assignment['questions'] ?? [],
            'saved_answers' => $savedAnswers,
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    // 2. AUTOSAVE SINGLE ANSWER
    if ($action === 'autosave') {
        $submissionId = (int)($input['submission_id'] ?? 0);
        $questionId = (int)($input['question_id'] ?? 0);
        $studentAnswer = (string)($input['student_answer'] ?? '');
        $timeSpent = (int)($input['time_spent'] ?? 0);
        $hintUsed = !empty($input['hint_used']);
        $clientTimestamp = !empty($input['client_timestamp']) ? (string)$input['client_timestamp'] : null;

        if ($submissionId < 1 || $questionId < 1) {
            actRespond(['error' => 'submission_id and question_id required'], 422);
        }

        $result = $p3->autosaveAnswer(
            $submissionId,
            $currentStudentId,
            $questionId,
            $studentAnswer,
            $hintUsed,
            $timeSpent,
            $clientTimestamp
        );

        if (!$result['success']) {
            actRespond(['error' => $result['message'] ?? 'บันทึกคำตอบไม่สำเร็จ'], 400);
        }

        actRespond($result);
    }

    // 3. SYNC BATCH ANSWERS (Offline Recovery)
    if ($action === 'sync') {
        $submissionId = (int)($input['submission_id'] ?? 0);
        $answers = $input['answers'] ?? [];

        if ($submissionId < 1 || !is_array($answers)) {
            actRespond(['error' => 'submission_id and answers array required'], 422);
        }

        $result = $p3->syncPendingAnswers($submissionId, $currentStudentId, $answers);
        actRespond($result);
    }

    // 4. SUBMIT ACTIVITY (Final Grading & Evidence Recording)
    if ($action === 'submit') {
        $submissionId = (int)($input['submission_id'] ?? 0);
        $finalAnswers = isset($input['answers']) && is_array($input['answers']) ? $input['answers'] : null;

        if ($submissionId < 1) {
            actRespond(['error' => 'submission_id required'], 422);
        }

        $result = $p3->submitActivity($submissionId, $currentStudentId, $finalAnswers);

        if (!$result['success']) {
            actRespond(['error' => $result['message'] ?? 'ส่งคำตอบไม่สำเร็จ'], 400);
        }

        actRespond($result);
    }

    actRespond(['error' => 'Invalid action: ' . htmlspecialchars($action)], 400);

} catch (Throwable $e) {
    actRespond([
        'error' => 'เกิดข้อผิดพลาดของระบบ: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
    ], 500);
}
