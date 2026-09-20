<?php
/**
 * student/get-ready.php
 * NEXTBEYOND V2 — Phase 2: "เตรียมตัวก่อนเรียน" Pre-Class Get Ready Page
 */
declare(strict_types=1);

$pageTitle   = 'เตรียมตัวก่อนเรียน';
$currentPage = 'live-session.php'; // highlight Live Session in sidebar
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/phase2-session-service.php';

$p2 = new Phase2SessionService($pdo);

$eventId        = (int)($_GET['event_id'] ?? 0);
$currentStudentId = (int)($currentUser['id'] ?? 0);
$firstName      = trim((string)($currentUser['first_name'] ?? '')) ?: 'นักเรียน';

if ($eventId < 1) {
    header('Location: index.php');
    exit;
}

$event = $p2->getEventForStudent($eventId, $currentStudentId);
if (!$event) {
    header('Location: index.php');
    exit;
}

$topics    = $p2->getStudentReadiness($currentStudentId, $eventId);
$allTopics = $p2->getEventTopics($eventId);
$hasTopics = !empty($allTopics);

// Compute countdown string
$eventDt = new DateTimeImmutable($event['event_date'] . ' ' . $event['start_time']);
$now      = new DateTimeImmutable();
$diff     = $now->diff($eventDt);
$isToday  = $event['event_date'] === $now->format('Y-m-d');
$isSoon   = $diff->days === 0 && (int)$diff->h <= 2;

// Days of week Thai
$thDays = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัส','ศุกร์','เสาร์'];
$dayName = $thDays[(int)(new DateTimeImmutable($event['event_date']))->format('w')];
$thMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$dateObj  = new DateTimeImmutable($event['event_date']);
$dateStr  = $dayName . 'ที่ ' . $dateObj->format('j') . ' ' . $thMonths[(int)$dateObj->format('n')] . ' ' . ((int)$dateObj->format('Y') + 543);
$timeStr  = substr((string)$event['start_time'], 0, 5) . ' – ' . substr((string)$event['end_time'], 0, 5) . ' น.';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> - Nextbeyond Compass</title>
  <link rel="stylesheet" href="../assets/css/output.css?v=<?= filemtime(__DIR__ . '/../assets/css/output.css') ?>">
  <link rel="stylesheet" href="../assets/css/student-portal.css?v=<?= filemtime(__DIR__ . '/../assets/css/student-portal.css') ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="../assets/js/student-guard.js"></script>
  <style>
    .gr-bg { background: linear-gradient(135deg,#f0f4ff 0%,#fdf4ff 50%,#fff8f0 100%); min-height: calc(100vh - 64px); }
    .gr-hero { background: linear-gradient(135deg,#6366f1 0%,#8b5cf6 50%,#a855f7 100%); border-radius: 20px; padding: 28px 32px; color: #fff; box-shadow: 0 12px 40px rgba(99,102,241,.35); position: relative; overflow: hidden; }
    .gr-hero::after { content:''; position:absolute; top:-30px; right:-30px; width:160px; height:160px; border-radius:50%; background:rgba(255,255,255,.08); pointer-events:none; }
    .gr-badge { display:inline-flex; align-items:center; gap:7px; background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.3); border-radius:99px; padding:5px 14px; font-size:12px; font-weight:700; margin-bottom:12px; backdrop-filter:blur(4px); }
    .gr-title { font-size:24px; font-weight:900; line-height:1.2; margin-bottom:6px; }
    .gr-meta { font-size:13px; opacity:.88; display:flex; flex-wrap:wrap; gap:14px; margin-top:10px; }
    .gr-meta span { display:flex; align-items:center; gap:5px; }
    .gr-card { background:#fff; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); padding:24px; margin-bottom:18px; border:1px solid #e8eaf0; }
    .gr-topic-row { display:flex; align-items:center; gap:14px; padding:14px 0; border-bottom:1px solid #f1f3f8; }
    .gr-topic-row:last-child { border-bottom:none; }
    .gr-check { width:28px; height:28px; border-radius:8px; border:2px solid #d1d5db; background:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; transition:.18s; }
    .gr-check.checked { background:#22c55e; border-color:#22c55e; }
    .gr-check.checked svg { opacity:1; }
    .gr-check svg { width:15px; height:15px; color:#fff; opacity:0; transition:.18s; }
    .gr-topic-name { flex:1; font-size:15px; font-weight:700; color:#1e293b; }
    .gr-topic-lesson { font-size:12px; color:#6366f1; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:4px; }
    .gr-topic-lesson:hover { text-decoration:underline; }
    .progress-bar-wrap { height:8px; background:#e8eaf0; border-radius:99px; overflow:hidden; margin-top:10px; }
    .progress-bar-fill { height:100%; background:linear-gradient(90deg,#22c55e,#16a34a); border-radius:99px; transition:.4s ease; }
    .btn-enter { display:inline-flex; align-items:center; gap:8px; padding:12px 24px; border-radius:12px; font-weight:800; font-size:14px; cursor:pointer; text-decoration:none; transition:.2s; }
    .btn-enter-class { background:linear-gradient(135deg,#e72d82,#f43f5e); color:#fff; box-shadow:0 8px 22px rgba(231,45,130,.35); }
    .btn-enter-class:hover { transform:translateY(-2px); box-shadow:0 12px 28px rgba(231,45,130,.5); }
    .btn-secondary { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; }
    .btn-secondary:hover { background:#e8edf5; }
    .pin-badge { display:inline-flex; align-items:center; gap:8px; background:linear-gradient(135deg,#0f172a,#1e293b); color:#fff; border-radius:12px; padding:10px 18px; font-size:18px; font-weight:900; letter-spacing:.15em; font-variant-numeric:tabular-nums; }
    .pin-label { font-size:11px; font-weight:600; opacity:.6; display:block; letter-spacing:.05em; }
    .topic-empty { text-align:center; padding:28px 0; color:#94a3b8; font-size:14px; }
    @media(max-width:640px){.gr-hero{padding:20px}.gr-title{font-size:20px}.gr-card{padding:18px}}
  </style>
</head>
<body class="student-portal bg-[#f4f7fb] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col ml-[240px] max-[1024px]:ml-0">
    <?php include 'includes/topbar.php'; ?>

    <main class="gr-bg p-8 max-[640px]:p-4">
      <div class="max-w-2xl mx-auto">

        <!-- Back -->
        <a href="index.php" class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-indigo-600 font-semibold mb-6 transition">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          กลับหน้า Dashboard
        </a>

        <!-- Hero Card -->
        <div class="gr-hero mb-6">
          <div class="gr-badge">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            เตรียมตัวก่อนเรียน
          </div>
          <h1 class="gr-title"><?= htmlspecialchars($event['title']) ?></h1>
          <div class="gr-meta">
            <span>📅 <?= htmlspecialchars($dateStr) ?></span>
            <span>🕐 <?= htmlspecialchars($timeStr) ?></span>
            <?php if ($event['course_title']): ?>
              <span>📚 <?= htmlspecialchars($event['course_title']) ?></span>
            <?php endif; ?>
            <?php if ($event['teacher_name']): ?>
              <span>👩‍🏫 <?= htmlspecialchars($event['teacher_name']) ?></span>
            <?php endif; ?>
            <?php if ($event['location']): ?>
              <span>📍 <?= htmlspecialchars($event['location']) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Live Session PIN (if session already started) -->
        <?php if (!empty($event['live_session_id']) && $event['session_status'] === 'active'): ?>
        <div class="gr-card mb-4" style="border-color:#fbbf24; background:linear-gradient(135deg,#fffbeb,#fff);">
          <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
              <p class="font-800 text-amber-700 text-sm mb-1">🔴 ห้องเรียนสดเปิดแล้ว!</p>
              <span class="pin-label text-slate-500">PIN เข้าห้องเรียน</span>
              <div class="pin-badge mt-1"><?= htmlspecialchars($event['session_pin']) ?></div>
            </div>
            <a href="live-session.php?pin=<?= htmlspecialchars($event['session_pin']) ?>"
               class="btn-enter btn-enter-class">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
              เข้าห้องเรียนเลย
            </a>
          </div>
        </div>
        <?php endif; ?>

        <!-- Progress Summary -->
        <?php
          $doneCount  = count(array_filter($topics, fn($t) => $t['status'] === 'reviewed'));
          $totalCount = count($topics);
          $pct        = $totalCount > 0 ? (int)round($doneCount * 100 / $totalCount) : 0;
        ?>
        <div class="gr-card">
          <div class="flex items-center justify-between mb-2">
            <h2 class="font-800 text-lg text-slate-800">
              <?php if ($hasTopics): ?>📋 หัวข้อที่จะเรียน (<?= $doneCount ?>/<?= $totalCount ?>)
              <?php else: ?>✅ สถานะพร้อมเรียน<?php endif; ?>
            </h2>
            <span class="text-sm font-700 <?= $pct === 100 ? 'text-green-600' : 'text-indigo-600' ?>"><?= $pct ?>%</span>
          </div>
          <div class="progress-bar-wrap">
            <div class="progress-bar-fill" id="main-progress-fill" style="width:<?= $pct ?>%"></div>
          </div>
          <?php if ($pct === 100): ?>
            <p class="text-green-600 text-sm font-700 mt-3">🎉 เยี่ยมมาก! คุณทบทวนครบทุกหัวข้อแล้ว</p>
          <?php elseif ($isSoon): ?>
            <p class="text-amber-600 text-sm font-700 mt-3">⏰ คลาสจะเริ่มเร็วๆ นี้ — เตรียมตัวให้พร้อม!</p>
          <?php else: ?>
            <p class="text-slate-400 text-sm mt-3">ทบทวนหัวข้อด้านล่าง แล้วติ๊กว่าผ่านไปได้เลย ไม่จำเป็นต้องครบทุกหัวข้อก็เข้าเรียนได้</p>
          <?php endif; ?>
        </div>

        <!-- Topic Checklist -->
        <div class="gr-card" id="topic-list">
          <?php if (empty($topics)): ?>
            <div class="topic-empty">
              <p class="text-3xl mb-3">📚</p>
              <p>ยังไม่มีหัวข้อที่กำหนดไว้สำหรับคลาสนี้</p>
              <p class="mt-1 text-xs">ครูยังไม่ได้ระบุหัวข้อ — คุณสามารถเข้าเรียนได้เลย</p>
              <button onclick="markGeneralReady()" id="btn-general-ready"
                class="mt-4 btn-enter <?= ($topics[0]['status'] ?? '') === 'reviewed' ? 'btn-enter-class' : 'btn-secondary' ?>"
                data-done="<?= ($topics[0]['status'] ?? '') === 'reviewed' ? '1' : '0' ?>">
                <?= ($topics[0]['status'] ?? '') === 'reviewed' ? '✅ พร้อมเรียนแล้ว' : '👍 กดยืนยันว่าพร้อมเรียน' ?>
              </button>
            </div>
          <?php else: ?>
            <?php foreach ($topics as $i => $topic): ?>
            <div class="gr-topic-row" id="topic-row-<?= $i ?>">
              <button class="gr-check <?= $topic['status'] === 'reviewed' ? 'checked' : '' ?>"
                      onclick="toggleTopic(<?= $i ?>, '<?= addslashes($topic['topic_name']) ?>', this)"
                      id="check-<?= $i ?>" title="ติ๊กว่าทบทวนแล้ว">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                </svg>
              </button>
              <div class="flex-1">
                <p class="gr-topic-name <?= $topic['status'] === 'reviewed' ? 'line-through opacity-50' : '' ?>"
                   id="topic-name-<?= $i ?>">
                  <?= htmlspecialchars($topic['topic_name']) ?>
                </p>
                <?php if (!empty($topic['lesson_id'])): ?>
                <a href="../lesson.php?id=<?= (int)$topic['lesson_id'] ?>" target="_blank"
                   class="gr-topic-lesson mt-1">
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  ดูบทเรียนที่เกี่ยวข้อง
                </a>
                <?php endif; ?>
              </div>
              <span class="text-xs font-600 <?= $topic['status'] === 'reviewed' ? 'text-green-500' : 'text-slate-300' ?>"
                    id="status-<?= $i ?>">
                <?= $topic['status'] === 'reviewed' ? '✓ ทบทวนแล้ว' : 'ยังไม่ได้ทบทวน' ?>
              </span>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Bottom Actions -->
        <div class="flex items-center justify-between gap-4 mt-2 flex-wrap">
          <a href="index.php" class="btn-enter btn-secondary">← กลับ Dashboard</a>
          <?php if (!empty($event['live_session_id']) && $event['session_status'] === 'active'): ?>
            <a href="live-session.php?pin=<?= htmlspecialchars($event['session_pin']) ?>"
               class="btn-enter btn-enter-class">
              🔴 เข้าห้องเรียน (PIN: <?= htmlspecialchars($event['session_pin']) ?>)
            </a>
          <?php else: ?>
            <a href="live-session.php" class="btn-enter btn-secondary">
              🎓 ไปหน้าเข้าห้องเรียน
            </a>
          <?php endif; ?>
        </div>

      </div>
    </main>
  </div>
</div>

<div id="toast" style="display:none;position:fixed;bottom:24px;right:24px;background:#1e293b;color:#fff;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:700;z-index:999;box-shadow:0 8px 24px rgba(0,0,0,.25);transition:.3s;"></div>

<script>
const EVENT_ID = <?= $eventId ?>;

function showToast(msg, color='#22c55e') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = color;
  t.style.display = 'block';
  t.style.opacity = '1';
  setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.style.display='none', 300); }, 2200);
}

function updateProgressBar() {
  const checks = document.querySelectorAll('.gr-check.checked');
  const total  = document.querySelectorAll('.gr-check').length;
  const pct    = total > 0 ? Math.round(checks.length * 100 / total) : 0;
  document.getElementById('main-progress-fill').style.width = pct + '%';
}

function toggleTopic(idx, topicName, btn) {
  const isChecked = btn.classList.contains('checked');
  const newStatus = isChecked ? 'not_started' : 'reviewed';

  btn.classList.toggle('checked', !isChecked);
  const nameEl   = document.getElementById('topic-name-' + idx);
  const statusEl = document.getElementById('status-' + idx);
  if (nameEl) nameEl.classList.toggle('line-through', !isChecked);
  if (nameEl) nameEl.classList.toggle('opacity-50', !isChecked);
  if (statusEl) {
    statusEl.textContent = !isChecked ? '✓ ทบทวนแล้ว' : 'ยังไม่ได้ทบทวน';
    statusEl.className = 'text-xs font-600 ' + (!isChecked ? 'text-green-500' : 'text-slate-300');
  }
  updateProgressBar();

  fetch('get-ready-api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ event_id: EVENT_ID, topic_name: topicName, status: newStatus })
  }).then(r => r.json()).then(d => {
    if (d.success) showToast(!isChecked ? '✓ บันทึกแล้ว — ทบทวนหัวข้อนี้แล้ว!' : 'ยกเลิกการทบทวน');
    else showToast('บันทึกไม่สำเร็จ: ' + (d.error || ''), '#ef4444');
  }).catch(() => showToast('เกิดข้อผิดพลาด', '#ef4444'));
}

function markGeneralReady() {
  const btn  = document.getElementById('btn-general-ready');
  const done = btn.dataset.done === '1';
  const newStatus = done ? 'not_started' : 'reviewed';
  btn.dataset.done = done ? '0' : '1';
  btn.textContent = !done ? '✅ พร้อมเรียนแล้ว' : '👍 กดยืนยันว่าพร้อมเรียน';
  btn.className = 'mt-4 btn-enter ' + (!done ? 'btn-enter-class' : 'btn-secondary');

  fetch('get-ready-api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ event_id: EVENT_ID, topic_name: '_general', status: newStatus })
  }).then(r => r.json()).then(d => {
    if (d.success) showToast(!done ? '✅ ยืนยันพร้อมเรียนแล้ว!' : 'ยกเลิก');
  });
}
</script>
</body>
</html>
