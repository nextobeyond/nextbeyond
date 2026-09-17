<?php
$pageTitle = 'ผลข้อสอบ';
$currentPage = 'my-tests.php';
require_once __DIR__ . '/includes/guard.php';

$attemptId = (int)($_GET['id'] ?? 0);
if ($attemptId < 1) { header('Location: my-tests.php'); exit; }
$stmtAtt = $pdo->prepare(
    'SELECT a.*, e.title, e.subject, e.grade, e.type, e.time_limit_minutes
     FROM test_attempts a INNER JOIN exams e ON e.id = a.exam_id
     WHERE a.id = :id AND a.user_id = :uid LIMIT 1'
);
$stmtAtt->execute([':id' => $attemptId, ':uid' => $currentUser['id']]);
$attempt = $stmtAtt->fetch();
if (!$attempt || !$attempt['completed_at']) { header('Location: my-tests.php'); exit; }

$stmtQ = $pdo->prepare(
    'SELECT q.id, q.sort_order, q.question_text, q.options, q.correct_answer, q.explanation, q.skill,
            ta.selected_answer, ta.is_correct
     FROM exam_questions q LEFT JOIN test_answers ta ON ta.question_id = q.id AND ta.attempt_id = :att_id
     WHERE q.exam_id = :eid ORDER BY q.sort_order, q.id'
);
$stmtQ->execute([':att_id' => $attemptId, ':eid' => $attempt['exam_id']]);
$questions = $stmtQ->fetchAll();
$total = (int)$attempt['total_questions'];
$correctCount = (int)$attempt['correct_count'];
$score = round((float)$attempt['score'], 1);
$grade = $score >= 80 ? 'ยอดเยี่ยม' : ($score >= 60 ? 'ผ่านเกณฑ์' : 'ต้องฝึกฝนเพิ่มเติม');
$skillStats = [];
foreach ($questions as $question) {
    $skill = $question['skill'] ?: 'ทั่วไป';
    $skillStats[$skill] ??= ['total' => 0, 'correct' => 0];
    $skillStats[$skill]['total']++;
    if ($question['is_correct']) $skillStats[$skill]['correct']++;
}
$cssVersion = (string)filemtime(__DIR__ . '/../assets/css/student-exam.css');
$sessionId = trim((string)($_GET['sessionId'] ?? ''));
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ผลข้อสอบ - Next Beyond</title>
  <link rel="stylesheet" href="../assets/css/output.css?v=<?= filemtime(__DIR__ . '/../assets/css/output.css') ?>"><link rel="stylesheet" href="../assets/css/student-portal.css?v=<?= filemtime(__DIR__ . '/../assets/css/student-portal.css') ?>"><link rel="stylesheet" href="../assets/css/student-exam.css?v=<?= $cssVersion ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="student-portal bg-[#f4f7fb] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col ml-[240px] max-[1024px]:ml-0 min-w-0">
    <?php include 'includes/topbar.php'; ?>
<main class="result-wrap">
  <section class="result-hero">
    <?php if ($sessionId !== ''): ?>
      <div style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;background:rgba(231,45,130,0.15);color:#ff8dc7;font-size:11px;font-weight:800;margin-bottom:12px">
        🔴 แบบทดสอบห้องเรียนสด (Live Session)
      </div>
    <?php endif; ?>
    <span class="exam-kicker"><?= htmlspecialchars($grade) ?></span>
    <h1><?= htmlspecialchars($attempt['title']) ?></h1>
    <p class="result-meta"><?= htmlspecialchars($attempt['subject'] ?: 'ทั่วไป') ?> · ส่งเมื่อ <?= date('d/m/Y H:i', strtotime((string)$attempt['completed_at'])) ?></p>
    <div class="result-score"><?= $correctCount ?> <small style="font-size:34px;color:#8794aa">/ <?= $total ?></small></div>
    <div style="font-weight:800;color:#dfe5f0">คิดเป็น <span style="color:#8b85ff"><?= $score ?>%</span></div>
    <?php if ($attempt['time_spent_seconds']): ?><p class="result-meta" style="margin-top:8px">ใช้เวลา <?= gmdate('H:i:s', (int)$attempt['time_spent_seconds']) ?></p><?php endif; ?>
    <div class="result-actions">
      <?php if ($sessionId !== ''): ?>
        <a class="secondary-btn" href="live-session.php" style="display:inline-flex;align-items:center;text-decoration:none">← กลับหน้าห้องเรียนสด</a>
      <?php else: ?>
        <a class="secondary-btn" href="tests.php" style="display:inline-flex;align-items:center;text-decoration:none">← กลับหน้ารายการข้อสอบ</a>
      <?php endif; ?>
      <a class="exam-action" href="take-test.php?id=<?= (int)$attempt['exam_id'] ?><?= $sessionId !== '' ? '&sessionId=' . urlencode($sessionId) : '' ?>">↻ สอบใหม่อีกครั้ง</a>
    </div>
  </section>

  <?php if ($skillStats): ?>
    <section class="skill-card">
      <h2 style="font-size:17px;margin-bottom:16px">คะแนนแยกตามทักษะ</h2>
      <?php foreach ($skillStats as $skill => $stat): $percent = $stat['total'] ? round($stat['correct'] / $stat['total'] * 100) : 0; ?>
        <div style="margin-top:12px"><div style="display:flex;justify-content:space-between;color:#65738a;font-size:13px"><span><?= htmlspecialchars($skill) ?></span><strong><?= $stat['correct'] ?>/<?= $stat['total'] ?> (<?= $percent ?>%)</strong></div><div style="height:8px;background:#edf1f6;border-radius:8px;margin-top:7px;overflow:hidden"><div style="height:100%;width:<?= $percent ?>%;background:#f54696;border-radius:8px"></div></div></div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <h2 class="review-title">✓ เฉลยคำตอบและคำอธิบายอย่างละเอียด</h2>
  <?php foreach ($questions as $index => $question):
    $options = json_decode((string)$question['options'], true) ?: [];
    $selected = $question['selected_answer'] !== null ? (int)$question['selected_answer'] : -1;
    $answer = (int)$question['correct_answer'];
    $isCorrect = (bool)$question['is_correct'];
  ?>
    <article class="review-card <?= $isCorrect ? 'ok' : 'bad' ?>">
      <div class="review-head"><span>ข้อที่ <?= $index + 1 ?> · <?= $isCorrect ? '✓ ถูกต้อง' : '✕ ไม่ถูกต้อง' ?></span><span>1 คะแนน</span></div>
      <div class="review-question"><?= nl2br(htmlspecialchars($question['question_text'])) ?></div>
      <?php foreach ($options as $optionIndex => $option):
        $class = $optionIndex === $answer ? 'answer' : (($optionIndex === $selected && !$isCorrect) ? 'chosen-wrong' : '');
      ?>
        <div class="review-option <?= $class ?>"><strong class="option-label"><?= chr(65 + $optionIndex) ?></strong><span><?= htmlspecialchars((string)$option) ?></span><?php if ($optionIndex === $answer): ?><strong style="margin-left:auto">คำตอบที่ถูกต้อง</strong><?php elseif ($optionIndex === $selected): ?><strong style="margin-left:auto">คำตอบของคุณ</strong><?php endif; ?></div>
      <?php endforeach; ?>
      <?php if ($selected < 0): ?><div class="review-option chosen-wrong">ไม่ได้ตอบข้อนี้</div><?php endif; ?>
      <?php if ($question['explanation']): ?><div class="explanation"><strong style="color:#8b85ff">คำอธิบาย</strong><br><?= nl2br(htmlspecialchars($question['explanation'])) ?></div><?php endif; ?>
    </article>
  <?php endforeach; ?>
</main>
</div>
</div>
</body>
</html>
