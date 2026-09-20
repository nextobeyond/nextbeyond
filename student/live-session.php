<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/live-sessions-helper.php';

ensureLiveSessionSchema($pdo);

$pageTitle = 'เข้าร่วมห้องเรียนสด (Live Session)';
$currentPage = 'live-session.php';

$currentStudentId = (int) ($currentUser['id'] ?? 0);
$studentName = trim($currentUser['first_name'] . ' ' . $currentUser['last_name']);
$studentFirstName = $currentUser['first_name'] ?: 'น้องๆ';

// Check if student is in an ongoing active session
$stmtActive = $pdo->prepare("
    SELECT s.*, sp.status AS participant_status, e.title AS exam_title, e.subject AS exam_subject
    FROM session_participants sp
    JOIN classroom_sessions s ON s.id = sp.session_id
    LEFT JOIN exams e ON e.id = s.exam_id
    WHERE sp.student_id = :uid AND s.status = 'active'
    ORDER BY s.started_at DESC LIMIT 1
");
$stmtActive->execute([':uid' => $currentStudentId]);
$currentActiveSession = $stmtActive->fetch();

$initialPin = preg_replace('/[^\d]/', '', (string)($_GET['pin'] ?? ''));
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> - Next Beyond</title>
  <link rel="stylesheet" href="../assets/css/output.css">
  <link rel="stylesheet" href="../assets/css/student-portal.css">
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700;800&family=Inter:wght@600;700;800;900&display=swap" rel="stylesheet">
  <script src="../assets/js/student-guard.js"></script>
  <style>
    .student-live-bg {
      background: radial-gradient(circle at 50% 10%, rgba(231, 45, 130, 0.08) 0%, rgba(99, 102, 241, 0.04) 50%, #f4f7fb 100%);
      min-height: calc(100vh - 64px);
    }
    .btn-join-gamified {
      background: linear-gradient(135deg, #e72d82 0%, #ff4b98 50%, #f43f5e 100%);
      color: #ffffff !important;
      box-shadow: 0 10px 24px -4px rgba(231, 45, 130, 0.5);
      transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .btn-join-gamified:hover:not(:disabled) {
      transform: translateY(-3px) scale(1.02);
      box-shadow: 0 14px 28px -4px rgba(231, 45, 130, 0.65);
    }
    .btn-join-gamified:active:not(:disabled) {
      transform: translateY(0) scale(0.98);
    }
    .btn-reenter-active {
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
      color: #ffffff !important;
      box-shadow: 0 8px 22px -2px rgba(16, 185, 129, 0.45);
      transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .btn-reenter-active:hover {
      transform: translateY(-2px) scale(1.02);
      box-shadow: 0 12px 26px -2px rgba(16, 185, 129, 0.6);
    }
    .pin-input-field {
      letter-spacing: 0.35em;
      font-feature-settings: "tnum";
      font-variant-numeric: tabular-nums;
      transition: all 0.25s ease;
      background: #ffffff;
      color: #1e1b4b;
      box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.04);
    }
    .pin-input-field:focus {
      border-color: #e72d82;
      box-shadow: 0 0 0 4px rgba(231, 45, 130, 0.15), inset 0 1px 2px rgba(0,0,0,0.02);
      transform: scale(1.01);
    }
    .card-gamified-pop {
      background: #ffffff;
      border: 1.5px solid #e2e8f0;
      box-shadow: 0 20px 45px -12px rgba(30, 27, 75, 0.09), 0 0 0 1px rgba(226, 232, 240, 0.6);
      transition: transform 0.2s ease;
    }
    .active-mission-card {
      background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
      border: 2px solid #34d399;
      box-shadow: 0 14px 32px -6px rgba(16, 185, 129, 0.22);
    }
    .bouncy-icon {
      animation: floatSlow 3.5s ease-in-out infinite;
    }
    @keyframes floatSlow {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(-7px) rotate(3deg); }
    }
    .sparkle-badge {
      background: linear-gradient(135deg, #fdf2f8 0%, #ede9fe 100%);
      border: 1px solid rgba(231, 45, 130, 0.2);
    }
  </style>
</head>
<body class="student-portal bg-[#f4f7fb] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0">
    <?php include 'includes/topbar.php'; ?>
    <main class="flex-1 p-6 sm:p-8 flex items-center justify-center student-live-bg">
      <div class="max-w-md w-full space-y-5">

        <!-- ACTIVE ONGOING MISSION CARD -->
        <?php if ($currentActiveSession): ?>
          <div class="active-mission-card p-6 rounded-3xl text-center space-y-4 relative overflow-hidden">
            <div class="flex items-center justify-center gap-2">
              <span class="w-3 h-3 rounded-full bg-emerald-500 animate-ping"></span>
              <span class="px-3 py-1 rounded-full bg-emerald-200/60 text-emerald-900 font-extrabold text-xs uppercase tracking-wide">
                🔴 คุณกำลังสอบอยู่ในห้องเรียนนี้
              </span>
            </div>

            <div class="space-y-1">
              <h3 class="text-xl font-black text-slate-900">
                <?= htmlspecialchars($currentActiveSession['title']) ?>
              </h3>
              <p class="text-xs text-emerald-900/80 font-medium">
                <?= htmlspecialchars((string) ($currentActiveSession['exam_title'] ?: 'แบบทดสอบประจำคาบ')) ?>
              </p>
            </div>

            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/80 border border-emerald-300 shadow-xs text-xs font-mono font-black text-emerald-800">
              <span>PIN:</span>
              <span class="tracking-widest text-sm">#<?= htmlspecialchars($currentActiveSession['session_pin']) ?></span>
            </div>

            <div class="pt-1">
              <a href="take-test.php?id=<?= (int) $currentActiveSession['exam_id'] ?>&sessionId=<?= urlencode($currentActiveSession['id']) ?>" class="btn-reenter-active w-full py-3.5 px-6 rounded-2xl font-black text-sm inline-flex items-center justify-center gap-2 cursor-pointer">
                <span>🚀 กลับเข้าทำข้อสอบต่อทันที</span>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
              </a>
            </div>
          </div>
        <?php endif; ?>

        <!-- MAIN PIN ENTRY CARD -->
        <div class="card-gamified-pop p-6 sm:p-8 rounded-3xl text-center space-y-6 relative overflow-hidden">
          
          <!-- Cute Welcoming Header -->
          <div class="space-y-2">
            <div class="w-20 h-20 rounded-3xl bg-gradient-to-tr from-pink-500 via-rose-500 to-indigo-500 text-white flex items-center justify-center text-4xl mx-auto shadow-xl shadow-pink-500/25 bouncy-icon">
              🎮
            </div>

            <div class="pt-1">
              <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full sparkle-badge text-pink-600 font-extrabold text-[11px] mb-1.5">
                <span>✨</span> <span>LIVE CLASSROOM QUIZ</span> <span>✨</span>
              </div>
              <h1 class="text-2xl sm:text-3xl font-black text-navy-950 tracking-tight">
                เข้าร่วมห้องเรียนสด
              </h1>
              <p class="text-xs sm:text-sm text-slate-500 font-medium max-w-xs mx-auto">
                สวัสดี <strong><?= htmlspecialchars($studentFirstName) ?></strong>! 👋<br>
                กรอกรหัส PIN 6 หลักที่คุณครูฉายหน้าห้องได้เลย
              </p>
            </div>
          </div>

          <!-- Form Area -->
          <form id="join-pin-form" class="space-y-4">
            <div id="join-error" class="hidden p-3.5 rounded-2xl bg-rose-50 text-rose-600 text-xs font-bold border border-rose-200 animate-shake"></div>

            <div class="space-y-2">
              <label for="session-pin" class="block text-xs font-black uppercase text-slate-600 tracking-wider">
                🔑 รหัส PIN 6 หลัก (Room PIN)
              </label>
              
              <div class="relative">
                <input
                  id="session-pin"
                  name="sessionPin"
                  type="text"
                  inputmode="numeric"
                  pattern="[0-9]*"
                  maxlength="6"
                  required
                  autocomplete="off"
                  placeholder="000000"
                  value="<?= htmlspecialchars($initialPin) ?>"
                  class="pin-input-field w-full text-center text-3xl sm:text-4xl font-mono font-black h-18 sm:h-20 rounded-2xl border-2 border-slate-300 outline-none text-navy-950"
                  autofocus
                >
              </div>

              <div class="flex items-center justify-center gap-1 text-[11px] text-slate-400 font-medium">
                <span>พิมพ์ครบ 6 หลัก ระบบจะเข้าห้องสอบอัตโนมัติทันที</span>
              </div>
            </div>

            <button
              type="submit"
              id="btn-join-session"
              class="btn-join-gamified w-full h-14 rounded-2xl font-black text-base flex items-center justify-center gap-2.5 cursor-pointer shadow-lg"
            >
              <span>🚀 ลุยเลย! เข้าร่วมห้องเรียน</span>
            </button>
          </form>

          <!-- 3 Kid-Friendly Guidance Badges -->
          <div class="grid grid-cols-2 gap-2.5 pt-4 border-t border-slate-100 text-[11px] text-slate-600">
            <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100/80 flex items-center gap-2.5 text-left">
              <span class="text-xl shrink-0">👀</span>
              <div>
                <strong class="block text-slate-800 font-bold">Eyes On Me</strong>
                <span class="text-[10px] text-slate-500">รอฟังคุณครูอธิบายเมื่อจอล็อก</span>
              </div>
            </div>

            <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100/80 flex items-center gap-2.5 text-left">
              <span class="text-xl shrink-0">⚡</span>
              <div>
                <strong class="block text-slate-800 font-bold">รู้ผลสอบสด</strong>
                <span class="text-[10px] text-slate-500">ส่งแล้วตรวจผลคะแนนทันที</span>
              </div>
            </div>
          </div>

          <!-- Bottom Friendly Tip -->
          <p class="text-[11px] text-slate-400">
            💡 ยังไม่ทราบรหัส PIN? สอบถามคุณครูผู้สอนประจำวิชาได้เลยครับ
          </p>

        </div>

      </div>
    </main>
  </div>
</div>

<script>
(() => {
  const form = document.getElementById("join-pin-form");
  const input = document.getElementById("session-pin");
  const btn = document.getElementById("btn-join-session");
  const errBox = document.getElementById("join-error");

  // Only allow digits & auto-submit on 6 digits
  input.addEventListener("input", (e) => {
    e.target.value = e.target.value.replace(/[^0-9]/g, "");
    if (e.target.value.length === 6) {
      setTimeout(() => form.requestSubmit(), 150);
    }
  });

  // Auto-submit if prefilled with valid 6-digit PIN
  if (input.value && input.value.length === 6) {
    setTimeout(() => form.requestSubmit(), 300);
  }

  form.onsubmit = async (e) => {
    e.preventDefault();
    errBox.classList.add("hidden");
    const pin = input.value.trim();
    if (pin.length !== 6) {
      errBox.textContent = "กรุณากรอกรหัส PIN ให้ครบ 6 หลักนะจ๊ะ";
      errBox.classList.remove("hidden");
      input.focus();
      return;
    }

    btn.disabled = true;
    btn.innerHTML = `<span class="animate-pulse">⏳ กำลังเชื่อมต่อห้องเรียนสด...</span>`;

    try {
      const res = await fetch("live-session-api.php?action=join", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ sessionPin: pin }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "ไม่สามารถเข้าร่วมห้องเรียนได้");
      
      btn.innerHTML = `<span>🎉 สำเร็จ! กำลังพาเข้าห้องสอบ...</span>`;
      window.location.href = data.redirectUrl;
    } catch (err) {
      errBox.textContent = err.message;
      errBox.classList.remove("hidden");
      btn.disabled = false;
      btn.innerHTML = `<span>🚀 ลุยเลย! เข้าร่วมห้องเรียน</span>`;
      input.select();
    }
  };
})();
</script>

<!-- ─── Phase 2: Understanding Check Panel ───────────────────────────── -->
<div id="understanding-panel" style="display:none; position:fixed; bottom:24px; left:50%; transform:translateX(-50%); z-index:200; width:min(480px, calc(100vw - 32px));">
  <div style="background:linear-gradient(135deg,#1e1b4b,#312e81); border:1px solid rgba(255,255,255,.18); border-radius:20px; padding:20px 22px; box-shadow:0 16px 48px rgba(0,0,0,.4); color:#fff; backdrop-filter:blur(12px);">
    <p style="font-size:11px; font-weight:700; opacity:.65; letter-spacing:.06em; margin-bottom:6px;" id="uc-topic-label">เช็กความเข้าใจ</p>
    <p style="font-size:16px; font-weight:800; margin-bottom:14px;">เข้าใจบทเรียนนี้ดีแค่ไหน? 🤔</p>
    <div style="display:flex; gap:10px;">
      <button onclick="submitUnderstanding('got_it')" id="uc-got-it"
        style="flex:1; padding:11px 0; border-radius:12px; border:none; background:#22c55e; color:#fff; font-size:13px; font-weight:800; cursor:pointer; transition:.15s;">
        👍 เข้าใจแล้ว
      </button>
      <button onclick="submitUnderstanding('somewhat')" id="uc-somewhat"
        style="flex:1; padding:11px 0; border-radius:12px; border:none; background:#f59e0b; color:#fff; font-size:13px; font-weight:800; cursor:pointer; transition:.15s;">
        😐 บางส่วน
      </button>
      <button onclick="submitUnderstanding('confused')" id="uc-confused"
        style="flex:1; padding:11px 0; border-radius:12px; border:none; background:#ef4444; color:#fff; font-size:13px; font-weight:800; cursor:pointer; transition:.15s;">
        🤷 ยังไม่เข้าใจ
      </button>
    </div>
    <button onclick="dismissUnderstanding()" style="position:absolute; top:12px; right:14px; background:none; border:none; color:rgba(255,255,255,.5); font-size:20px; cursor:pointer; line-height:1;">×</button>
  </div>
</div>

<script>
(function () {
  'use strict';
  let _ucSessionId = null;
  let _ucCurrentTopic = '';
  let _ucSubmitted = false;

  // Called by existing polling code when announcement contains _check_ prefix
  window.__p2_triggerCheck = function (sessionId, topicName) {
    _ucSessionId  = sessionId;
    _ucCurrentTopic = topicName || '';
    _ucSubmitted  = false;
    const panel = document.getElementById('understanding-panel');
    const label = document.getElementById('uc-topic-label');
    if (panel) {
      if (label) label.textContent = topicName ? '📌 หัวข้อ: ' + topicName : 'เช็กความเข้าใจ';
      panel.style.display = 'block';
      // Reset buttons
      ['uc-got-it','uc-somewhat','uc-confused'].forEach(id => {
        const b = document.getElementById(id);
        if (b) { b.disabled = false; b.style.opacity = '1'; }
      });
    }
  };

  window.submitUnderstanding = async function (val) {
    if (!_ucSessionId || _ucSubmitted) return;
    _ucSubmitted = true;
    ['uc-got-it','uc-somewhat','uc-confused'].forEach(id => {
      const b = document.getElementById(id);
      if (b) { b.disabled = true; b.style.opacity = '0.5'; }
    });
    try {
      await fetch('live-session-api.php?action=understanding_check', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sessionId: _ucSessionId, understanding: val, topicName: _ucCurrentTopic }),
      });
    } catch (_) { /* silently continue */ }
    setTimeout(dismissUnderstanding, 1200);
  };

  window.dismissUnderstanding = function () {
    const panel = document.getElementById('understanding-panel');
    if (panel) panel.style.display = 'none';
  };

  // Hook into polling: intercept announcement messages prefixed with "_check_"
  const _origFetch = window.fetch;
  // The existing live-session polling checks announcementMessage; we extend it here
  const _checkInterval = setInterval(function () {
    const ann = window.__currentAnnouncement;
    if (ann && ann.startsWith('_check_') && _ucSessionId) {
      const topic = ann.replace('_check_', '').trim();
      window.__p2_triggerCheck(_ucSessionId, topic);
      window.__currentAnnouncement = null; // consume
    }
  }, 2000);
})();
</script>
</body>
</html>
