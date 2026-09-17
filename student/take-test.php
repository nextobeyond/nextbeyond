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

$sessionId = trim((string)($_GET['sessionId'] ?? ''));
$liveSession = null;
if ($sessionId !== '') {
    try {
        $stmtSes = $pdo->prepare("SELECT * FROM classroom_sessions WHERE id = :id LIMIT 1");
        $stmtSes->execute([':id' => $sessionId]);
        $liveSession = $stmtSes->fetch();
        if ($liveSession) {
            $partId = 'sp-' . time() . '-' . random_int(1000, 9999);
            $stmtSP = $pdo->prepare("
                INSERT INTO session_participants (id, session_id, student_id, attempt_id, status, current_question, answered_count, joined_at)
                VALUES (:pid, :sid, :uid, :attemptId, 'in_progress', 1, 0, NOW())
                ON DUPLICATE KEY UPDATE attempt_id = :attemptId, status = 'in_progress'
            ");
            $stmtSP->execute([':pid' => $partId, ':sid' => $sessionId, ':uid' => $currentUser['id'], ':attemptId' => $attemptId]);
        }
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
    $limitSeconds = max(0, (int)$exam['time_limit_minutes'] * 60);
}
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

<!-- Live Boss Fight Floating Widget -->
<div id="student-boss-hud" class="hidden sticky top-0 z-30 bg-gradient-to-r from-slate-950 via-purple-950 to-slate-900 text-white px-4 py-2.5 shadow-xl border-b border-purple-500/30">
  <div class="max-w-4xl mx-auto flex items-center justify-between gap-3 text-xs">
    <div class="flex items-center gap-2.5 truncate">
      <span id="boss-hud-emoji" class="text-2xl animate-bounce shrink-0">🐲</span>
      <div class="truncate">
        <div class="flex items-center gap-2">
          <strong id="boss-hud-name" class="text-purple-200 text-xs sm:text-sm font-black truncate">มังกรเพลิงแห่งความรู้ ไครอส</strong>
          <span id="boss-hud-badge" class="px-2 py-0.5 rounded-full bg-pink-500/20 text-pink-300 border border-pink-500/30 text-[9px] font-extrabold uppercase">BOSS FIGHT</span>
        </div>
        <div class="text-[10px] text-slate-300">ตอบถูกเพื่อร่วมโจมตีบอส (-10 HP ต่อข้อ)</div>
      </div>
    </div>

    <!-- Mini HP Bar -->
    <div class="w-36 sm:w-56 shrink-0 space-y-1">
      <div class="flex justify-between text-[10px] font-bold font-mono">
        <span class="text-purple-300">BOSS HP</span>
        <span id="boss-hud-hp-text">100 / 100 HP</span>
      </div>
      <div class="w-full h-2.5 rounded-full bg-slate-950 p-0.5 border border-white/20 overflow-hidden">
        <div id="boss-hud-hp-bar" class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-teal-400 transition-all duration-500" style="width: 100%"></div>
      </div>
    </div>
  </div>
</div>

<!-- Floating Damage Indicator Animation -->
<div id="floating-damage" class="pointer-events-none fixed z-50 text-2xl font-black text-pink-400 drop-shadow-[0_4px_12px_rgba(231,45,130,0.8)] opacity-0 transition-all duration-700 transform scale-75">
  💥 -10 DMG!
</div>

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
<form id="submit-form" method="POST" action="submit-test.php">
  <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">
  <input type="hidden" name="exam_id" value="<?= $examId ?>">
  <input type="hidden" name="session_id" value="<?= htmlspecialchars($sessionId) ?>">
  <input type="hidden" id="answers-json" name="answers">
</form>
<script>
const LIVE_SESSION_ID = <?= json_encode($sessionId) ?>;
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

    if (data.isCorrect && LIVE_SESSION_ID) {
      showFloatingDamage(10);
      fetch('live-session-api.php?action=deal_damage', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sessionId: LIVE_SESSION_ID, damage: 10 })
      }).catch(() => {});
    }
  } catch (error) { alert(error.message); button.disabled = false; }
});

function showFloatingDamage(dmg = 10) {
  const el = document.getElementById('floating-damage');
  if (!el) return;
  el.textContent = `💥 -${dmg} DMG!`;
  el.style.left = '50%';
  el.style.top = '22%';
  el.style.transform = 'translate(-50%, -50%) scale(1.2)';
  el.style.opacity = '1';
  setTimeout(() => {
    el.style.transform = 'translate(-50%, -90px) scale(0.8)';
    el.style.opacity = '0';
  }, 900);
}

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
        if (msg !== lastAnnouncement) {
          lastAnnouncement = msg;
          banner.dataset.dismissed = '0';
        }
        if (banner.dataset.dismissed !== '1') {
          content.textContent = msg;
          banner.classList.remove('hidden');
        }
      } else {
        banner.classList.add('hidden');
      }
    }

    // 3. Boss Fight HUD
    const bossHud = document.getElementById('student-boss-hud');
    if (bossHud) {
      if (data.bossFightActive) {
        bossHud.classList.remove('hidden');
        const nameEl = document.getElementById('boss-hud-name');
        if (nameEl) nameEl.textContent = data.bossName || 'มังกรเพลิงแห่งความรู้ ไครอส';
        const curHp = data.bossCurrentHp ?? 100;
        const maxHp = Math.max(1, data.bossMaxHp ?? 100);
        const hpPct = Math.max(0, Math.min(100, Math.round((curHp / maxHp) * 100)));
        const hpText = document.getElementById('boss-hud-hp-text');
        if (hpText) hpText.textContent = `${curHp} / ${maxHp} HP (${hpPct}%)`;
        const hpBar = document.getElementById('boss-hud-hp-bar');
        if (hpBar) {
          hpBar.style.width = `${hpPct}%`;
          hpBar.className = `h-full rounded-full transition-all duration-500 ${
            hpPct > 50 ? 'bg-gradient-to-r from-emerald-400 to-teal-400'
            : hpPct > 20 ? 'bg-gradient-to-r from-amber-400 to-rose-500'
            : 'bg-gradient-to-r from-rose-600 to-pink-500 animate-pulse'
          }`;
        }
        const badge = document.getElementById('boss-hud-badge');
        if (badge) {
          if (data.bossDefeated) {
            badge.textContent = '🎉 DEFEATED!';
            badge.className = 'px-2 py-0.5 rounded-full bg-emerald-500 text-white font-black text-[9px] animate-pulse';
          } else {
            badge.textContent = 'BOSS FIGHT';
            badge.className = 'px-2 py-0.5 rounded-full bg-pink-500/20 text-pink-300 border border-pink-500/30 text-[9px] font-extrabold uppercase';
          }
        }
      } else {
        bossHud.classList.add('hidden');
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

if (LIVE_SESSION_ID) {
  setInterval(pollLiveSession, 3000);
  pollLiveSession();
}
</script>
</body>
</html>
