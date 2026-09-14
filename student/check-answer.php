<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/guard.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
$attemptId = (int)($payload['attemptId'] ?? 0);
$questionId = (int)($payload['questionId'] ?? 0);
$selectedAnswer = filter_var($payload['selectedAnswer'] ?? null, FILTER_VALIDATE_INT);
if ($attemptId < 1 || $questionId < 1 || $selectedAnswer === false) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'ข้อมูลคำตอบไม่ครบ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT q.correct_answer, q.explanation, q.options,
            e.time_limit_minutes,
            TIMESTAMPDIFF(SECOND, a.started_at, NOW()) AS elapsed_seconds
     FROM test_attempts a
     INNER JOIN exams e ON e.id = a.exam_id
     INNER JOIN exam_questions q ON q.exam_id = a.exam_id AND q.id = :question_id
     WHERE a.id = :attempt_id AND a.user_id = :user_id AND a.completed_at IS NULL
     LIMIT 1'
);
$stmt->execute([':question_id' => $questionId, ':attempt_id' => $attemptId, ':user_id' => $currentUser['id']]);
$question = $stmt->fetch();
if (!$question) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'ไม่พบคำถามในข้อสอบนี้'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ((int)($question['time_limit_minutes'] ?? 0) > 0
    && (int)$question['elapsed_seconds'] >= ((int)$question['time_limit_minutes'] * 60)) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'หมดเวลาทำข้อสอบแล้ว ระบบกำลังส่งคำตอบ'], JSON_UNESCAPED_UNICODE);
    exit;
}
$options = json_decode((string)$question['options'], true) ?: [];
if (!array_key_exists((int)$selectedAnswer, $options)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'ตัวเลือกไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$correctAnswer = (int)$question['correct_answer'];
echo json_encode([
    'ok' => true,
    'isCorrect' => (int)$selectedAnswer === $correctAnswer,
    'correctAnswer' => $correctAnswer,
    'explanation' => (string)($question['explanation'] ?? ''),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
