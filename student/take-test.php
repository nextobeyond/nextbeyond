<?php
$pageTitle = 'กำลังทำข้อสอบ';
$currentPage = 'tests.php';
require_once __DIR__ . '/includes/guard.php';

$examId = (int)($_GET['id'] ?? 0);
if ($examId < 1) { header('Location: tests.php'); exit; }

$stmtExam = $pdo->prepare("SELECT id, title, subject, grade, type, time_limit_minutes FROM exams WHERE id = :id AND status = 'active' AND is_published = 1 LIMIT 1");
$stmtExam->execute([':id' => $examId]);
$exam = $stmtExam->fetch();
if (!$exam) { header('Location: tests.php?error=not_found'); exit; }

$stmtQ = $pdo->prepare('SELECT id, sort_order, question_text, passage, options, skill FROM exam_questions WHERE exam_id = :eid ORDER BY sort_order, id');
$stmtQ->execute([':eid' => $examId]);
$questions = $stmtQ->fetchAll();
if (!$questions) { header('Location: tests.php?error=no_questions'); exit; }

// ใช้ attempt ที่ยังทำไม่เสร็จต่อ เพื่อไม่ให้เวลาเริ่มใหม่เมื่อ refresh
$stmtAttempt = $pdo->prepare(
    'SELECT id, started_at, GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, NOW())) AS elapsed_seconds
     FROM test_attempts
     WHERE user_id = :uid AND exam_id = :eid AND completed_at IS NULL
     ORDER BY started_at DESC LIMIT 1'
);
$stmtAttempt->execute([':uid' => $currentUser['id'], ':eid' => $examId]);
$attempt = $stmtAttempt->fetch();
if (!$attempt) {
    $stmtIns = $pdo->prepare('INSERT INTO test_attempts (user_id, exam_id, total_questions, started_at) VALUES (:uid, :eid, :total, NOW())');
    $stmtIns->execute([':uid' => $currentUser['id'], ':eid' => $examId, ':total' => count($questions)]);
    $attemptId = (int)$pdo->lastInsertId();
    $elapsedSeconds = 0;
} else {
    $attemptId = (int)$attempt['id'];
    $elapsedSeconds = (int)$attempt['elapsed_seconds'];
}

$limitSeconds = max(0, (int)$exam['time_limit_minutes'] * 60);
$remainingSeconds = $limitSeconds > 0 ? max(0, $limitSeconds - $elapsedSeconds) : 0;
$questionsForJS = array_map(static fn(array $q): array => [
    'id' => (int)$q['id'], 'questionText' => (string)$q['question_text'], 'passage' => $q['passage'],
    'options' => json_decode((string)$q['options'], true) ?: [], 'skill' => $q['skill'] ?: 'ทั่วไป',
], $questions);
$cssVersion = (string)filemtime(__DIR__ . '/../assets/css/student-exam.css');
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($exam['title']) ?> - Next Beyond</title>
  <link rel="stylesheet" href="../assets/css/output.css?v=<?= filemtime(__DIR__ . '/../assets/css/output.css') ?>"><link rel="stylesheet" href="../assets/css/student-portal.css?v=<?= filemtime(__DIR__ . '/../assets/css/student-portal.css') ?>"><link rel="stylesheet" href="../assets/css/student-exam.css?v=<?= $cssVersion ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="student-portal bg-[#f4f7fb] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col ml-[240px] max-[1024px]:ml-0 min-w-0">
    <?php include 'includes/topbar.php'; ?>
<main class="test-shell">
  <header class="test-head">
    <div><div class="test-tag"><?= htmlspecialchars($exam['subject'] ?: 'ทั่วไป') ?> · <?= htmlspecialchars($exam['grade'] ?: 'ทุกระดับ') ?></div><h1><?= htmlspecialchars($exam['title']) ?></h1></div>
    <?php if ($limitSeconds > 0): ?><div id="timer" class="test-clock" data-seconds="<?= $remainingSeconds ?>">00:00</div><?php endif; ?>
    <button type="button" class="exam-action submit-test" onclick="submitExam(false)">✓ ส่งข้อสอบ</button>
  </header>
  <div class="test-layout">
    <aside class="test-palette">
      <div class="palette-head"><span>รายการข้อสอบ</span><span><?= count($questions) ?> ข้อ</span></div><div id="palette" class="palette-grid"></div>
      <div class="palette-legend">■ ตอบแล้ว<br>□ ยังไม่ได้ตอบ</div><a href="tests.php" style="display:inline-block;margin-top:18px;color:#9aa8bd;font-size:12px">← กลับหน้ารายการ</a>
    </aside>
    <section class="question-panel">
      <div><span id="question-label" class="question-label"></span><span class="question-score">1 คะแนน</span></div>
      <div id="passage" class="passage" hidden></div><div id="question-text" class="question-text"></div><div id="options"></div>
      <div id="check-result" class="check-result" role="status"></div>
      <div class="question-actions"><button id="prev-btn" class="secondary-btn" type="button">‹ ข้อก่อนหน้า</button><button id="check-btn" class="secondary-btn" type="button">? ตรวจคำตอบทันที</button><button id="next-btn" class="exam-action" type="button">ข้อถัดไป ›</button></div>
    </section>
  </div>
</main>
</div>
</div>
<form id="submit-form" method="POST" action="submit-test.php"><input type="hidden" name="attempt_id" value="<?= $attemptId ?>"><input type="hidden" name="exam_id" value="<?= $examId ?>"><input type="hidden" id="answers-json" name="answers"></form>
<script>
const QUESTIONS = <?= json_encode($questionsForJS, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const ATTEMPT_ID = <?= $attemptId ?>, STORAGE_KEY = 'nextbeyond-attempt-' + ATTEMPT_ID;
let answers = {};
try { answers = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') || {}; } catch (_) { answers = {}; }
const checked = {};
let current = 0;
function escapeHTML(value) { const node = document.createElement('div'); node.textContent = String(value ?? ''); return node.innerHTML; }
function renderPalette() { document.getElementById('palette').innerHTML = QUESTIONS.map((_, i) => `<button type="button" class="palette-btn ${i === current ? 'active' : ''} ${answers[i] !== undefined ? 'done' : ''}" onclick="goTo(${i})">${i + 1}</button>`).join(''); }
function renderQuestion() {
  const q = QUESTIONS[current];
  document.getElementById('question-label').textContent = `คำถามข้อที่ ${current + 1} จาก ${QUESTIONS.length}`;
  document.getElementById('question-text').textContent = q.questionText;
  const passage = document.getElementById('passage'); passage.hidden = !q.passage; passage.textContent = q.passage || '';
  document.getElementById('options').innerHTML = q.options.map((option, index) => {
    let state = answers[current] === index ? ' selected' : '';
    if (checked[current]) { if (index === checked[current].correctAnswer) state = ' correct'; else if (answers[current] === index) state = ' wrong'; }
    return `<button type="button" class="option${state}" onclick="selectAnswer(${index})" ${checked[current] ? 'disabled' : ''}><span style="margin-right:12px">○</span>${escapeHTML(option)}</button>`;
  }).join('');
  const result = document.getElementById('check-result');
  if (checked[current]) { result.classList.add('show'); result.textContent = (checked[current].isCorrect ? '✓ ถูกต้อง' : '✕ ยังไม่ถูกต้อง') + (checked[current].explanation ? ' — ' + checked[current].explanation : ''); }
  else { result.classList.remove('show'); result.textContent = ''; }
  document.getElementById('prev-btn').disabled = current === 0;
  document.getElementById('next-btn').textContent = current === QUESTIONS.length - 1 ? 'ส่งข้อสอบ ✓' : 'ข้อถัดไป ›';
  document.getElementById('check-btn').disabled = answers[current] === undefined || !!checked[current]; renderPalette();
}
function selectAnswer(index) { answers[current] = index; localStorage.setItem(STORAGE_KEY, JSON.stringify(answers)); renderQuestion(); }
function goTo(index) { current = index; renderQuestion(); window.scrollTo({top:0, behavior:'smooth'}); }
document.getElementById('prev-btn').addEventListener('click', () => goTo(Math.max(0, current - 1)));
document.getElementById('next-btn').addEventListener('click', () => current === QUESTIONS.length - 1 ? submitExam(false) : goTo(current + 1));
document.getElementById('check-btn').addEventListener('click', async () => {
  if (answers[current] === undefined || checked[current]) return;
  const button = document.getElementById('check-btn'); button.disabled = true;
  try {
    const response = await fetch('check-answer.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({attemptId:ATTEMPT_ID, questionId:QUESTIONS[current].id, selectedAnswer:answers[current]})});
    const data = await response.json(); if (!response.ok || !data.ok) throw new Error(data.error || 'ตรวจคำตอบไม่ได้'); checked[current] = data; renderQuestion();
  } catch (error) { alert(error.message); button.disabled = false; }
});
function submitExam(force) {
  if (submitExam.submitting) return;
  const missing = QUESTIONS.length - Object.keys(answers).length;
  if (!force && missing > 0 && !confirm(`ยังไม่ได้ตอบ ${missing} ข้อ ต้องการส่งข้อสอบตอนนี้หรือไม่?`)) return;
  submitExam.submitting = true;
  document.getElementById('answers-json').value = JSON.stringify(answers); localStorage.removeItem(STORAGE_KEY); document.getElementById('submit-form').submit();
}
const timer = document.getElementById('timer');
if (timer) {
  const deadline = Date.now() + (Number(timer.dataset.seconds) * 1000);
  const draw = () => {
    const seconds = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
    timer.textContent = `${String(Math.floor(seconds / 60)).padStart(2,'0')}:${String(seconds % 60).padStart(2,'0')}`;
    timer.classList.toggle('urgent', seconds <= 60);
    if (seconds <= 0) { clearInterval(timerInterval); submitExam(true); }
  };
  const timerInterval = setInterval(draw, 250); draw();
}
renderQuestion();
</script>
</body>
</html>
