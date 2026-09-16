<?php
/**
 * student/submit-test.php — รับคำตอบจาก take-test.php แล้วบันทึกลง DB
 * POST: attempt_id, exam_id, answers (JSON)
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tests.php');
    exit;
}

$attemptId = (int)($_POST['attempt_id'] ?? 0);
$examId    = (int)($_POST['exam_id'] ?? 0);
$answersRaw = $_POST['answers'] ?? '{}';
$answers   = json_decode($answersRaw, true) ?? [];

if ($attemptId < 1 || $examId < 1) {
    header('Location: tests.php?error=invalid');
    exit;
}

// ตรวจว่า attempt นี้เป็นของ user นี้จริง
$stmtCheck = $pdo->prepare(
    'SELECT id, started_at, GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, NOW())) AS elapsed_seconds
     FROM test_attempts
     WHERE id = :id AND exam_id = :exam_id AND user_id = :uid AND completed_at IS NULL'
);
$stmtCheck->execute([':id' => $attemptId, ':exam_id' => $examId, ':uid' => $currentUser['id']]);
$attempt = $stmtCheck->fetch();
if (!$attempt) {
    header('Location: tests.php?error=invalid_attempt');
    exit;
}

// ดึงคำถามทั้งหมดของข้อสอบ (พร้อม correct_answer)
$stmtQ = $pdo->prepare(
    'SELECT id, sort_order, correct_answer, skill FROM exam_questions WHERE exam_id = :eid ORDER BY sort_order, id'
);
$stmtQ->execute([':eid' => $examId]);
$questions = $stmtQ->fetchAll();

// คำนวณคะแนน + บันทึก answers
$pdo->beginTransaction();

$stmtAns = $pdo->prepare(
    'INSERT INTO test_answers (attempt_id, question_id, selected_answer, is_correct)
     VALUES (:attempt_id, :question_id, :selected, :is_correct)'
);

$correctCount = 0;
foreach ($questions as $i => $q) {
    $selected  = array_key_exists((string)$i, $answers) ? (int)$answers[(string)$i] : null;
    $isCorrect = $selected !== null ? ($selected === (int)$q['correct_answer'] ? 1 : 0) : 0;
    if ($isCorrect) $correctCount++;

    $stmtAns->execute([
        ':attempt_id' => $attemptId,
        ':question_id'=> $q['id'],
        ':selected'   => $selected,
        ':is_correct' => $isCorrect,
    ]);
}

$totalQ  = count($questions);
$score   = $totalQ > 0 ? round(($correctCount / $totalQ) * 100, 2) : 0;
$seconds = (int)$attempt['elapsed_seconds'];

// อัปเดต attempt
$stmtUpdate = $pdo->prepare(
    'UPDATE test_attempts SET score = :score, correct_count = :correct, total_questions = :total,
     time_spent_seconds = :seconds, completed_at = NOW() WHERE id = :id'
);
$stmtUpdate->execute([
    ':score'   => $score,
    ':correct' => $correctCount,
    ':total'   => $totalQ,
    ':seconds' => $seconds,
    ':id'      => $attemptId,
]);

$pdo->commit();

$sessionId = trim((string)($_POST['session_id'] ?? ''));
if ($sessionId !== '') {
    try {
        $stmtSP = $pdo->prepare("UPDATE session_participants SET status = 'submitted', updated_at = NOW() WHERE session_id = :sessionId AND student_id = :uid");
        $stmtSP->execute([':sessionId' => $sessionId, ':uid' => $currentUser['id']]);
    } catch (\Throwable $e) {}
}

// Trigger roadmap evaluation
require_once __DIR__ . '/../includes/roadmap-evaluator.php';
evaluateTestTask($pdo, (int)$currentUser['id'], $examId, $attemptId);

// redirect ไปดูผล
header('Location: test-result.php?id=' . $attemptId . ($sessionId !== '' ? '&sessionId=' . urlencode($sessionId) : ''));
exit;
