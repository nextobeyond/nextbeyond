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

$sessionId = trim((string)($_GET['sessionId'] ?? ''));
$liveSession = null;
if ($sessionId !== '') {
    try {
        $stmtSes = $pdo->prepare("SELECT * FROM classroom_sessions WHERE id = :id LIMIT 1");
        $stmtSes->execute([':id' => $sessionId]);
        $liveSession = $stmtSes->fetch();
    } catch (\Throwable $e) {
        // continue if table not ready
    }
}

if ($liveSession) {
    if (!empty($liveSession['has_time_limit'])) {
        $limitMinutes = max(1, (int)($liveSession['time_limit_minutes'] ?? 30));
        $limitSeconds = $limitMinutes * 60;
    } else {
        $limitSeconds = 0; // No time limit in this session
    }
} else {
    $limitSeconds = max(0, (int)($exam['time_limit_minutes'] ?? 0) * 60);
}

// ใช้ attempt ที่ยังทำไม่เสร็จต่อ เพื่อไม่ให้เวลาเริ่มใหม่เมื่อ refresh
$stmtAttempt = $pdo->prepare(
    'SELECT id, started_at, GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, NOW())) AS elapsed_seconds
     FROM test_attempts
     WHERE user_id = :uid AND exam_id = :eid AND completed_at IS NULL
     ORDER BY started_at DESC LIMIT 1'
);
$stmtAttempt->execute([':uid' => $currentUser['id'], ':eid' => $examId]);
$attempt = $stmtAttempt->fetch();

// หาก attempt เดิมหมดเวลาไปแล้ว ให้ปิด attempt นั้นแล้วเริ่มใหม่
if ($attempt && $limitSeconds > 0 && (int)$attempt['elapsed_seconds'] >= $limitSeconds) {
    $pdo->prepare('UPDATE test_attempts SET completed_at = NOW(), score = 0, correct_count = 0 WHERE id = :aid')
        ->execute([':aid' => $attempt['id']]);
    $attempt = null;
}

if (!$attempt) {
    $stmtIns = $pdo->prepare('INSERT INTO test_attempts (user_id, exam_id, total_questions, started_at) VALUES (:uid, :eid, :total, NOW())');
    $stmtIns->execute([':uid' => $currentUser['id'], ':eid' => $examId, ':total' => count($questions)]);
    $attemptId = (int)$pdo->lastInsertId();
    $elapsedSeconds = 0;
} else {
    $attemptId = (int)$attempt['id'];
    $elapsedSeconds = (int)$attempt['elapsed_seconds'];
}

if ($sessionId !== '' && $liveSession) {
    try {
        $partId = 'sp-' . time() . '-' . random_int(1000, 9999);
        $stmtSP = $pdo->prepare("
            INSERT INTO session_participants (id, session_id, student_id, attempt_id, status, current_question, answered_count, joined_at)
            VALUES (:pid, :sid, :uid, :attemptId, 'in_progress', 1, 0, NOW())
            ON DUPLICATE KEY UPDATE attempt_id = :attemptId, status = 'in_progress'
        ");
        $stmtSP->execute([':pid' => $partId, ':sid' => $sessionId, ':uid' => $currentUser['id'], ':attemptId' => $attemptId]);
    } catch (\Throwable $e) {
        // continue if table not ready
    }
}

$remainingSeconds = $limitSeconds > 0 ? max(0, $limitSeconds - $elapsedSeconds) : 0;
$questionsForJS = array_map(static fn(array $q): array => [
    'id' => (int)$q['id'], 'questionText' => (string)$q['question_text'], 'passage' => $q['passage'],
    'options' => array_values(is_array($opts = json_decode((string)$q['options'], true)) ? $opts : []),
    'skill' => $q['skill'] ?: 'ทั่วไป',
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
<!-- Live Announcement Banner -->
<div id="live-announcement-banner" class="hidden sticky top-0 z-40 bg-gradient-to-r from-purple-600 via-indigo-600 to-pink-600 text-white px-4 py-2.5 shadow-md flex items-center justify-between text-xs">
  <div class="flex items-center gap-2">
    <span class="text-sm">📢</span>
    <span class="font-bold">ประกาศสดจากครู:</span>
    <span id="live-announcement-content" class="font-medium"></span>
  </div>
  <button type="button" onclick="const b=document.getElementById('live-announcement-banner'); b.classList.add('hidden'); b.dataset.dismissed='1';" class="text-purple-200 hover:text-white font-bold p-1 cursor-pointer">✕</button>
</div>

<!-- Eyes On Me Fullscreen Lock Overlay -->
<div id="eyes-on-me-overlay" class="hidden fixed inset-0 z-50 bg-slate-950/90 backdrop-blur-md flex flex-col items-center justify-center p-6 text-center text-white select-none">
  <div class="text-7xl mb-4 animate-bounce">👀</div>
  <h2 class="text-2xl sm:text-3xl font-black text-pink-400 mb-2">ครูกำลังอธิบาย โปรดดูกระดาน</h2>
  <p class="text-slate-300 text-xs sm:text-sm max-w-md">หน้าจอของคุณถูกหยุดไว้ชั่วคราว เพื่อร่วมรับฟังคำอธิบายจากคุณครู ข้อสอบจะเปิดให้ทำต่อทันทีที่ครูปลดล็อก</p>
  <div class="mt-6 px-4 py-2 rounded-full bg-white/10 text-xs font-bold border border-white/20">
    🔒 ระบบ Eyes On Me กำลังล็อกหน้าจอ
  </div>
</div>

<!-- Phase 2: Understanding Check Floating Panel -->
<div id="understanding-panel" style="display:none; position:fixed; bottom:24px; left:50%; transform:translateX(-50%); z-index:200; width:min(480px, calc(100vw - 32px));">
  <div style="background:linear-gradient(135deg,#1e1b4b,#312e81); border:1px solid rgba(255,255,255,.2); border-radius:20px; padding:20px 22px; box-shadow:0 16px 48px rgba(0,0,0,.45); color:#fff; backdrop-filter:blur(12px); position:relative;">
    <p style="font-size:11px; font-weight:700; opacity:.7; letter-spacing:.06em; margin-bottom:6px;" id="uc-topic-label">เช็กความเข้าใจจากครูผู้สอน</p>
    <p style="font-size:16px; font-weight:800; margin-bottom:14px;">เข้าใจบทเรียนนี้ดีแค่ไหน? 🤔</p>
    <div style="display:flex; gap:10px;">
      <button type="button" onclick="submitUnderstanding('got_it')" id="uc-got-it"
        style="flex:1; padding:11px 0; border-radius:12px; border:none; background:#22c55e; color:#fff; font-size:13px; font-weight:800; cursor:pointer; transition:.15s;">
        👍 เข้าใจแล้ว
      </button>
      <button type="button" onclick="submitUnderstanding('somewhat')" id="uc-somewhat"
        style="flex:1; padding:11px 0; border-radius:12px; border:none; background:#f59e0b; color:#fff; font-size:13px; font-weight:800; cursor:pointer; transition:.15s;">
        😐 บางส่วน
      </button>
      <button type="button" onclick="submitUnderstanding('confused')" id="uc-confused"
        style="flex:1; padding:11px 0; border-radius:12px; border:none; background:#ef4444; color:#fff; font-size:13px; font-weight:800; cursor:pointer; transition:.15s;">
        🤷 ยังไม่เข้าใจ
      </button>
    </div>
    <button type="button" onclick="dismissUnderstanding()" style="position:absolute; top:12px; right:14px; background:none; border:none; color:rgba(255,255,255,.5); font-size:20px; cursor:pointer; line-height:1;">✕</button>
  </div>
</div>

<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col ml-[240px] max-[1024px]:ml-0 min-w-0">
    <?php include 'includes/topbar.php'; ?>
<main class="test-shell">
  <header class="test-head">
    <div>
      <?php if ($sessionId !== ''): ?>
        <div style="margin-bottom:6px">
          <a href="live-session.php?sessionId=<?= urlencode($sessionId) ?>" style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:800;color:#e72d82;text-decoration:none;background:rgba(231,45,130,0.08);padding:4px 10px;border-radius:8px">
            <span>←</span> กลับหน้าห้องเรียนสด (Live Classroom)
          </a>
        </div>
      <?php endif; ?>
      <div class="test-tag"><?= htmlspecialchars($exam['subject'] ?: 'ทั่วไป') ?> · <?= htmlspecialchars($exam['grade'] ?: 'ทุกระดับ') ?></div>
      <h1><?= htmlspecialchars($exam['title']) ?></h1>
    </div>
    <?php if ($limitSeconds > 0): ?><div id="timer" class="test-clock" data-seconds="<?= $remainingSeconds ?>">00:00</div><?php endif; ?>
    <button type="button" class="exam-action submit-test" onclick="submitExam(false)">✓ ส่งข้อสอบ</button>
  </header>
  <div class="test-layout">
    <aside class="test-palette">
      <div class="palette-head"><span>รายการข้อสอบ</span><span><?= count($questions) ?> ข้อ</span></div><div id="palette" class="palette-grid"></div>
      <div class="palette-legend">■ ตอบแล้ว<br>□ ยังไม่ได้ตอบ</div>
      <?php if ($sessionId !== ''): ?>
        <a href="live-session.php?sessionId=<?= urlencode($sessionId) ?>" style="display:inline-block;margin-top:18px;color:#e72d82;font-weight:700;font-size:12px;text-decoration:none">← กลับหน้าห้องเรียนสด</a>
      <?php else: ?>
        <a href="tests.php" style="display:inline-block;margin-top:18px;color:#9aa8bd;font-size:12px">← กลับหน้ารายการ</a>
      <?php endif; ?>
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
<form id="submit-form" method="POST" action="submit-test.php">
  <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">
  <input type="hidden" name="exam_id" value="<?= $examId ?>">
  <input type="hidden" name="session_id" value="<?= htmlspecialchars($sessionId) ?>">
  <input type="hidden" id="answers-json" name="answers">
</form>
<script>
const LIVE_SESSION_ID = <?= json_encode($sessionId) ?>;
const QUESTIONS = <?= json_encode($questionsForJS, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
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
  const opts = Array.isArray(q.options) ? q.options : Object.values(q.options || {});
  document.getElementById('options').innerHTML = opts.map((option, index) => {
    let state = answers[current] === index ? ' selected' : '';
    if (checked[current]) { if (index === checked[current].correctAnswer) state = ' correct'; else if (answers[current] === index) state = ' wrong'; }
    const label = String.fromCharCode(65 + index);
    return `<button type="button" class="option${state}" onclick="selectAnswer(${index})" ${checked[current] ? 'disabled' : ''}><span class="option-label">${label}</span><span>${escapeHTML(option)}</span></button>`;
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
    const response = await fetch('check-answer.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ attemptId: ATTEMPT_ID, questionId: QUESTIONS[current].id, selectedAnswer: answers[current] })
    });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.error || 'ตรวจคำตอบไม่ได้');
    checked[current] = data;
    renderQuestion();
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

// Live Session Polling (Every 3 seconds)
let lastAnnouncement = '';
let isSessionClosedHandled = false;
async function pollLiveSession() {
  if (!LIVE_SESSION_ID) return;
  try {
    const curQ = current + 1;
    const ansCount = Object.keys(answers).length;
    const res = await fetch(`live-session-api.php?action=status&sessionId=${encodeURIComponent(LIVE_SESSION_ID)}&currentQ=${curQ}&answered=${ansCount}&attemptId=${ATTEMPT_ID}`);
    if (!res.ok) return;
    const data = await res.json();
    if (!data.ok && !data.success) return;

    // 1. Eyes On Me lock
    const overlay = document.getElementById('eyes-on-me-overlay');
    if (overlay) {
      if (data.isEyesOnMeLocked) {
        overlay.classList.remove('hidden');
      } else {
        overlay.classList.add('hidden');
      }
    }

    // 2. Announcement message
    const banner = document.getElementById('live-announcement-banner');
    const content = document.getElementById('live-announcement-content');
    if (banner && content) {
      const msg = (data.announcementMessage || '').trim();
      if (msg) {
        if (msg.startsWith('_check_')) {
          const topic = msg.replace('_check_', '').trim();
          showUnderstandingCheck(topic);
        } else {
          if (msg !== lastAnnouncement) {
            lastAnnouncement = msg;
            banner.dataset.dismissed = '0';
          }
          if (banner.dataset.dismissed !== '1') {
            content.textContent = msg;
            banner.classList.remove('hidden');
          }
        }
      } else {
        banner.classList.add('hidden');
      }
    }


    // 4. Session Closed by Teacher
    if (data.sessionClosed && !isSessionClosedHandled) {
      isSessionClosedHandled = true;
      alert('คุณครูได้สิ้นสุดห้องเรียนสดนี้แล้ว ระบบจะทำการส่งคำตอบของคุณโดยอัตโนมัติ');
      submitExam(true);
      return;
    }

    // 5. Teacher reset student attempt
    if (data.resetAttempt) {
      alert('ครูผู้สอนได้รีเซ็ตสิทธิ์การทำข้อสอบของคุณ ระบบจะเริ่มทำการรีเฟรชหน้าจอเพื่อเริ่มทำใหม่');
      localStorage.removeItem(STORAGE_KEY);
      location.reload();
      return;
    }
  } catch (_) {}
}

// Phase 2: Understanding Check Functions
let _ucTopic = '';
let _ucSubmitted = false;
function showUnderstandingCheck(topic) {
  _ucTopic = topic || '';
  _ucSubmitted = false;
  const p = document.getElementById('understanding-panel');
  const l = document.getElementById('uc-topic-label');
  if (p) {
    if (l) l.textContent = topic ? '📌 หัวข้อ: ' + topic : 'เช็กความเข้าใจจากครูผู้สอน';
    p.style.display = 'block';
    ['uc-got-it','uc-somewhat','uc-confused'].forEach(id => {
      const b = document.getElementById(id);
      if (b) { b.disabled = false; b.style.opacity = '1'; }
    });
  }
}
async function submitUnderstanding(val) {
  if (!LIVE_SESSION_ID || _ucSubmitted) return;
  _ucSubmitted = true;
  ['uc-got-it','uc-somewhat','uc-confused'].forEach(id => {
    const b = document.getElementById(id);
    if (b) { b.disabled = true; b.style.opacity = '0.5'; }
  });
  try {
    await fetch('live-session-api.php?action=understanding_check', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sessionId: LIVE_SESSION_ID, understanding: val, topicName: _ucTopic }),
    });
  } catch (_) {}
  setTimeout(dismissUnderstanding, 1200);
}
function dismissUnderstanding() {
  const p = document.getElementById('understanding-panel');
  if (p) p.style.display = 'none';
}

if (LIVE_SESSION_ID) {
  setInterval(pollLiveSession, 3000);
  pollLiveSession();
}
</script>
</body>
</html>
