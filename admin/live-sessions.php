<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/live-sessions-helper.php';

ensureLiveSessionSchema($pdo);

$pageTitle = 'Live Session ห้องเรียนสด';
$pageDesc = 'ควบคุมห้องเรียนสดแบบ Real-time, ระบบล็อกหน้าจอ Eyes On Me และสรุปผลสอบทันที';
$currentPage = 'live-sessions.php';

$currentUserId = (int) ($consoleUser['id'] ?? 0);
$isAdmin = $consoleUser['role'] === 'admin';

// Fetch active session if any
$stmtActive = $pdo->prepare("SELECT s.*, e.title AS exam_title, (SELECT COUNT(*) FROM session_participants WHERE session_id = s.id) AS student_count FROM classroom_sessions s LEFT JOIN exams e ON e.id = s.exam_id WHERE (s.teacher_id = :tid OR :isAdmin = 1) AND s.status = 'active' ORDER BY s.started_at DESC LIMIT 1");
$stmtActive->execute([':tid' => $currentUserId, ':isAdmin' => ($isAdmin ? 1 : 0)]);
$activeSession = $stmtActive->fetch();

// Fetch previous sessions
$stmtPrev = $pdo->prepare("SELECT s.*, e.title AS exam_title, (SELECT COUNT(*) FROM session_participants WHERE session_id = s.id) AS student_count FROM classroom_sessions s LEFT JOIN exams e ON e.id = s.exam_id WHERE (s.teacher_id = :tid OR :isAdmin = 1) AND s.status = 'closed' ORDER BY s.ended_at DESC LIMIT 30");
$stmtPrev->execute([':tid' => $currentUserId, ':isAdmin' => ($isAdmin ? 1 : 0)]);
$closedSessions = $stmtPrev->fetchAll();

// Calculate total historical stats
$totalCompletedSessions = count($closedSessions);
$totalHistoricalStudents = array_sum(array_map(fn($cs) => (int)$cs['student_count'], $closedSessions));

// Fetch available published exams
$exams = $pdo->query("SELECT id, title, subject, grade, time_limit_minutes FROM exams WHERE is_published = 1 AND status = 'active' ORDER BY title ASC")->fetchAll();

// Phase 2: Fetch scheduled calendar events for linking
$stmtEvents = $pdo->prepare("
    SELECT ce.id, ce.title, ce.event_date, ce.start_time, c.title AS course_title
    FROM calendar_events ce
    LEFT JOIN courses c ON c.id = ce.course_id
    WHERE (ce.teacher_id = :tid OR :isAdmin = 1)
      AND ce.status = 'scheduled'
      AND ce.event_date >= CURDATE()
    ORDER BY ce.event_date ASC, ce.start_time ASC
    LIMIT 25
");
$stmtEvents->execute([':tid' => $currentUserId, ':isAdmin' => ($isAdmin ? 1 : 0)]);
$calendarEvents = $stmtEvents->fetchAll();
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> - Next Beyond Admin</title>
  <link rel="stylesheet" href="../assets/css/output.css">
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Inter:wght@500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="../assets/js/admin-guard.js"></script>
  <style>
    /* Scoped custom styling for Live Sessions UI to guarantee high visual quality */
    .live-hero-bg {
      background: linear-gradient(135deg, #090e17 0%, #111a2e 45%, #19163a 100%);
    }
    .live-pulse-dot {
      display: inline-block;
      width: 10px;
      height: 10px;
      border-radius: 9999px;
      background-color: #10b981;
      box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
      animation: livePulse 1.8s infinite;
    }
    @keyframes livePulse {
      0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.8); }
      70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
      100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
    .btn-create-session {
      background: linear-gradient(135deg, #e72d82 0%, #d81b60 100%);
      color: #ffffff !important;
      box-shadow: 0 8px 24px -4px rgba(231, 45, 130, 0.45);
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .btn-create-session:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 28px -4px rgba(231, 45, 130, 0.6);
    }
    .btn-enter-room {
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
      color: #ffffff !important;
      box-shadow: 0 6px 20px -2px rgba(16, 185, 129, 0.4);
      transition: all 0.2s ease;
    }
    .btn-enter-room:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 24px -2px rgba(16, 185, 129, 0.55);
    }
    .btn-projector-view {
      background: #1e293b;
      color: #f1f5f9 !important;
      border: 1px solid rgba(255, 255, 255, 0.12);
      transition: all 0.2s ease;
    }
    .btn-projector-view:hover {
      background: #334155;
      color: #ffffff !important;
      transform: translateY(-1px);
    }
    .pin-display-card {
      background: linear-gradient(135deg, #fdf2f8 0%, #fff1f2 100%);
      border: 1.5px dashed #f472b6;
    }
    .badge-subtle {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.15);
    }
    .card-active-glow {
      box-shadow: 0 16px 40px -8px rgba(16, 185, 129, 0.15), 0 0 0 1px rgba(16, 185, 129, 0.25);
    }
    .card-feature-hover {
      transition: all 0.2s ease;
    }
    .card-feature-hover:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 24px -6px rgba(0, 0, 0, 0.06);
    }
  </style>
</head>
<body class="bg-[#f5f8fc] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0">
    <?php include 'includes/topbar.php'; ?>
    <main class="flex-1 p-6 max-[640px]:p-4">
      <div class="max-w-6xl mx-auto space-y-6">

        <!-- HERO BANNER -->
        <div class="live-hero-bg p-6 sm:p-8 rounded-3xl text-white shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-6 relative overflow-hidden">
          <div class="absolute -top-16 -right-16 w-64 h-64 bg-pink-500/10 rounded-full blur-3xl pointer-events-none"></div>
          <div class="absolute -bottom-16 -left-16 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

          <div class="relative z-10 max-w-xl space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-pink-500/20 text-pink-300 border border-pink-500/30 text-xs font-black tracking-wide uppercase">
                ⚡ Teacher Command Center
              </span>
              <?php if ($activeSession): ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-xs font-bold">
                  <span class="live-pulse-dot"></span> ถ่ายทอดสดอยู่ 1 ห้อง
                </span>
              <?php else: ?>
                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full badge-subtle text-slate-300 text-xs font-semibold">
                  พร้อมเปิดห้องเรียนใหม่
                </span>
              <?php endif; ?>
            </div>

            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">
              Live Session ห้องเรียนสด
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
              จัดการการสอบพร้อมกันในชั้นเรียนแบบ Real-Time ดูความคืบหน้าของนักเรียนสดรายข้อ และควบคุมสมาธิห้องเรียนด้วยระบบล็อกหน้าจอ <strong>Eyes On Me</strong>
            </p>
          </div>

          <div class="relative z-10 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shrink-0">
            <button type="button" id="btn-open-create-modal" class="btn-create-session px-6 py-3.5 rounded-2xl font-black text-sm flex items-center justify-center gap-2.5 cursor-pointer">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
              <span>สร้างห้องเรียนสดใหม่</span>
            </button>
          </div>
        </div>

        <!-- ACTIVE SESSION CARD (IF ACTIVE) -->
        <?php if ($activeSession): ?>
          <div class="p-6 sm:p-8 rounded-3xl bg-white border-2 border-emerald-400 card-active-glow space-y-6 relative overflow-hidden">
            <!-- Top Status Bar -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-100 pb-5">
              <div class="flex items-start gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center text-xl shrink-0 shadow-xs">
                  <span class="live-pulse-dot" style="width: 14px; height: 14px;"></span>
                </div>
                <div>
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-black uppercase tracking-wider">
                      ● LIVE NOW กำลังเปิดสอน
                    </span>
                    <span class="text-xs text-slate-400">•</span>
                    <span class="text-xs font-bold text-slate-500">
                      เริ่มเมื่อ <?= date('d/m/Y H:i', strtotime($activeSession['started_at'])) ?> น.
                    </span>
                  </div>
                  <h2 class="text-lg sm:text-xl font-black text-slate-900 mt-1">
                    <?= htmlspecialchars($activeSession['title']) ?>
                  </h2>
                  <p class="text-xs text-slate-600 mt-0.5 flex items-center gap-2">
                    <span>📖 แบบทดสอบ:</span>
                    <strong class="text-slate-800"><?= htmlspecialchars((string) ($activeSession['exam_title'] ?: 'แบบทดสอบประจำคาบ')) ?></strong>
                  </p>
                </div>
              </div>

              <!-- Action Buttons for Teacher -->
              <div class="flex flex-wrap items-center gap-2.5">
                <button type="button" id="btn-open-projector-view" class="btn-projector-view px-4 py-3 rounded-2xl text-xs sm:text-sm font-bold flex items-center gap-2 cursor-pointer shadow-xs">
                  <svg class="w-4 h-4 text-pink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                  <span>📺 ฉายขึ้นจอ (Projector)</span>
                </button>
                <a href="live-session-room.php?id=<?= urlencode($activeSession['id']) ?>" class="btn-enter-room px-6 py-3 rounded-2xl text-sm font-black flex items-center gap-2 cursor-pointer">
                  <span>🚀 เข้าสู่ห้องควบคุมสด</span>
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
              </div>
            </div>

            <!-- Main Interactive Row: PIN + Quick Link -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- PIN Display Box -->
              <div class="pin-display-card p-5 rounded-2xl flex flex-col justify-between space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-black uppercase text-pink-600 tracking-wider">
                    🔑 รหัส PIN เข้าร่วม
                  </span>
                  <span class="text-[10px] px-2 py-0.5 rounded-full bg-pink-200/60 text-pink-800 font-extrabold">
                    6 หลัก
                  </span>
                </div>
                <div class="flex items-center justify-between gap-2">
                  <div id="active-pin-text" class="text-3xl sm:text-4xl font-black font-mono tracking-widest text-pink-600 select-all">
                    <?= htmlspecialchars($activeSession['session_pin']) ?>
                  </div>
                  <button type="button" id="btn-copy-pin" data-pin="<?= htmlspecialchars($activeSession['session_pin']) ?>" class="px-3 py-1.5 rounded-xl bg-white hover:bg-pink-100 text-pink-700 font-bold text-xs border border-pink-300 cursor-pointer shadow-xs transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span id="copy-pin-status">คัดลอก</span>
                  </button>
                </div>
                <div class="text-[11px] text-slate-500">
                  ให้นักเรียนไปที่ <span class="font-bold text-slate-700">student/live-session</span> แล้วใส่ PIN นี้
                </div>
              </div>

              <!-- Student Link Copy Box -->
              <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col justify-between space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-black uppercase text-slate-600 tracking-wider">
                    🔗 ลิงก์ตรงสำหรับนักเรียน
                  </span>
                  <span class="text-[10px] text-slate-400 font-semibold">แชร์ในแชท / กลุ่มเรียน</span>
                </div>
                <?php
                  $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                  $studentUrl = $proto . '://' . $host . '/Nextbeyond/student/live-session.php?pin=' . urlencode($activeSession['session_pin']);
                ?>
                <div class="flex items-center gap-2">
                  <input type="text" readonly value="<?= htmlspecialchars($studentUrl) ?>" class="flex-1 h-10 px-3 rounded-xl bg-white border border-slate-300 text-xs font-mono text-slate-700 select-all truncate outline-none">
                  <button type="button" id="btn-copy-link" data-url="<?= htmlspecialchars($studentUrl) ?>" class="px-3 h-10 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs cursor-pointer shrink-0 transition-colors flex items-center gap-1">
                    <span id="copy-link-status">คัดลอกลิงก์</span>
                  </button>
                </div>
                <div class="text-[11px] text-slate-500">
                  นักเรียนคลิกแล้วจะกรอก PIN ให้อัตโนมัติทันที
                </div>
              </div>

              <!-- Status Pills Grid -->
              <div class="grid grid-cols-2 gap-2.5">
                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
                  <span class="text-[11px] font-bold text-slate-500">นักเรียนในห้อง</span>
                  <strong class="text-xl font-black text-slate-900 mt-1">
                    <?= number_format((int) $activeSession['student_count']) ?> <span class="text-xs font-medium text-slate-500">คน</span>
                  </strong>
                </div>

                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
                  <span class="text-[11px] font-bold text-slate-500">Eyes On Me</span>
                  <strong class="text-xs font-black mt-1 <?= $activeSession['eyes_on_me_enabled'] ? 'text-pink-600' : 'text-emerald-600' ?>">
                    <?= $activeSession['eyes_on_me_enabled'] ? '🔒 ล็อกจออยู่' : '🔓 ปล่อยทำปกติ' ?>
                  </strong>
                </div>

                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
                  <span class="text-[11px] font-bold text-slate-500">เวลาสอบ</span>
                  <strong class="text-xs font-black text-slate-800 mt-1">
                    <?= !empty($activeSession['time_limit_minutes']) ? (int) $activeSession['time_limit_minutes'] . ' นาที' : 'ไม่จำกัดเวลา' ?>
                  </strong>
                </div>

                <div class="p-2 rounded-2xl bg-rose-50/70 border border-rose-200 flex items-center justify-center">
                  <button type="button" data-close-session="<?= htmlspecialchars($activeSession['id']) ?>" class="w-full h-full py-2 px-1 rounded-xl text-rose-700 hover:bg-rose-100 font-bold text-xs cursor-pointer transition-colors text-center">
                    ⏹ สิ้นสุดห้องเรียน
                  </button>
                </div>
              </div>
            </div>
          </div>
        <?php else: ?>
          <!-- EMPTY STATE: READY TO START -->
          <div class="p-8 sm:p-10 rounded-3xl bg-white border border-slate-200 shadow-xs text-center space-y-4">
            <div class="w-16 h-16 rounded-3xl bg-gradient-to-tr from-pink-100 to-indigo-100 text-pink-600 flex items-center justify-center text-3xl mx-auto shadow-inner">
              📡
            </div>
            <div class="max-w-md mx-auto space-y-1">
              <h3 class="text-lg font-black text-slate-900">ยังไม่มีห้องเรียนสดที่เปิดอยู่ขณะนี้</h3>
              <p class="text-xs text-slate-500 leading-relaxed">
                เลือกแบบทดสอบและเปิดห้องเรียนสด เพื่อให้นักเรียนทำข้อสอบพร้อมกันในคาบเรียนแบบ Real-Time ได้ทันที
              </p>
            </div>
            <div class="pt-2">
              <button type="button" onclick="document.getElementById('btn-open-create-modal').click()" class="btn-create-session px-6 py-3 rounded-2xl font-bold text-sm inline-flex items-center gap-2 cursor-pointer">
                <span>⚡</span>
                <span>เปิดห้องเรียนสดแรกของคุณ</span>
              </button>
            </div>
          </div>
        <?php endif; ?>

        <!-- 3 VALUE FEATURE CARDS -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
          <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs card-feature-hover space-y-2">
            <div class="w-9 h-9 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center text-base font-black">
              🔑
            </div>
            <h4 class="font-bold text-slate-900 text-sm">เข้าห้องง่ายผ่าน PIN 6 หลัก</h4>
            <p class="text-slate-500 leading-relaxed">
              นักเรียนเปิดหน้าเว็บแล้วพิมพ์รหัส PIN 6 หลักเพื่อเข้าห้องสอบได้ทันทีใน 2 วินาที ไม่ต้องจำลิงก์ยาว
            </p>
          </div>

          <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs card-feature-hover space-y-2">
            <div class="w-9 h-9 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-base font-black">
              🔒
            </div>
            <h4 class="font-bold text-slate-900 text-sm">ล็อกหน้าจอ Eyes On Me</h4>
            <p class="text-slate-500 leading-relaxed">
              สั่งล็อกหน้าจอนักเรียนทั้งห้องหรือรายคนได้ทันทีเมื่อต้องการอธิบายข้อสอบ พร้อมส่งข้อความเตือนด่วน
            </p>
          </div>

          <div class="p-5 rounded-2xl bg-white border border-slate-200 shadow-xs card-feature-hover space-y-2">
            <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-base font-black">
              📊
            </div>
            <h4 class="font-bold text-slate-900 text-sm">สถิติสด Real-Time</h4>
            <p class="text-slate-500 leading-relaxed">
              อาจารย์เห็นภาพรวมทั้งห้องว่านักเรียนกำลังทำข้อไหน ใครทำเสร็จแล้ว และข้อไหนที่นักเรียนตอบผิดมากที่สุด
            </p>
          </div>
        </div>

        <!-- PREVIOUS SESSIONS TABLE -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
          <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                <span>ประวัติห้องเรียนสดย้อนหลัง</span>
                <span class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 text-xs font-bold">
                  <?= count($closedSessions) ?> คาบ
                </span>
              </h3>
              <p class="text-xs text-slate-400 mt-0.5">
                ประวัติการจัดสอบย้อนหลัง สามารถดูรายงานสรุปคะแนนและทักษะย้อนหลังได้ตลอดเวลา
              </p>
            </div>

            <div class="flex items-center gap-2">
              <?php if ($closedSessions): ?>
                <button type="button" id="btn-delete-all-closed" class="px-3.5 py-2 rounded-xl text-xs text-rose-600 hover:bg-rose-50 font-bold border border-rose-200 cursor-pointer transition-colors">
                  🗑️ ล้างประวัติทั้งหมด
                </button>
              <?php endif; ?>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-50/80 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                <tr>
                  <th class="py-3.5 px-5">PIN / ชื่อห้องเรียน</th>
                  <th class="py-3.5 px-4">แบบทดสอบที่ใช้</th>
                  <th class="py-3.5 px-4 text-center">นักเรียน</th>
                  <th class="py-3.5 px-4">วันและเวลาที่จัดสอบ</th>
                  <th class="py-3.5 px-5 text-right">การจัดการ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php if ($closedSessions): ?>
                  <?php foreach ($closedSessions as $cs): ?>
                    <tr class="hover:bg-slate-50/70 transition-colors">
                      <td class="py-4 px-5">
                        <div class="flex items-center gap-2">
                          <span class="font-mono font-black text-xs px-2.5 py-1 rounded-lg bg-pink-50 text-pink-700 border border-pink-200/60">
                            #<?= htmlspecialchars($cs['session_pin']) ?>
                          </span>
                          <strong class="text-slate-900 font-bold text-xs sm:text-sm">
                            <?= htmlspecialchars($cs['title']) ?>
                          </strong>
                        </div>
                      </td>
                      <td class="py-4 px-4 text-slate-700">
                        <div class="flex items-center gap-1.5">
                          <span class="text-slate-400">📝</span>
                          <span class="truncate max-w-xs font-medium"><?= htmlspecialchars((string) ($cs['exam_title'] ?: '—')) ?></span>
                        </div>
                      </td>
                      <td class="py-4 px-4 text-center">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-100 text-slate-800 font-bold text-xs">
                          👥 <?= number_format((int) $cs['student_count']) ?> คน
                        </span>
                      </td>
                      <td class="py-4 px-4 text-slate-500 font-medium">
                        <?= date('d/m/Y H:i', strtotime($cs['started_at'])) ?> น.
                        <?php if ($cs['ended_at']): ?>
                          <span class="text-slate-400 block text-[11px]">
                            ถึง <?= date('H:i', strtotime($cs['ended_at'])) ?> น.
                          </span>
                        <?php endif; ?>
                      </td>
                      <td class="py-4 px-5 text-right space-x-1.5 whitespace-nowrap">
                        <a href="live-session-room.php?id=<?= urlencode($cs['id']) ?>" class="px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs inline-flex items-center gap-1 transition-colors">
                          <span>📊 ดูรายงาน</span>
                        </a>
                        <button type="button" data-delete-session="<?= htmlspecialchars($cs['id']) ?>" class="px-2.5 py-1.5 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 font-bold text-xs cursor-pointer transition-colors" title="ลบรายการนี้">
                          ✕
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="5" class="py-12 text-center text-slate-400">
                      ยังไม่มีประวัติห้องเรียนสดย้อนหลัง
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </main>
  </div>
</div>

<!-- PROJECTOR VIEW MODAL (FOR SCREEN SHARING) -->
<?php if ($activeSession): ?>
<div id="projector-modal-overlay" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
  <div class="bg-gradient-to-b from-slate-900 via-indigo-950 to-slate-950 text-white rounded-3xl max-w-2xl w-full p-8 shadow-2xl border border-purple-500/30 text-center space-y-6 my-auto relative overflow-hidden">
    <button type="button" id="btn-close-projector-modal" class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white flex items-center justify-center font-black cursor-pointer">✕</button>

    <div class="space-y-2">
      <span class="px-3.5 py-1 rounded-full bg-pink-500/20 text-pink-300 border border-pink-500/30 text-xs font-black uppercase tracking-wider">
        📺 PROJECTOR ARENA • โหมดฉายหน้าห้อง
      </span>
      <h2 class="text-2xl sm:text-3xl font-black text-white"><?= htmlspecialchars($activeSession['title']) ?></h2>
      <p class="text-sm text-slate-300">ให้นักเรียนเข้าเว็บไซต์และกรอกรหัส PIN ด้านล่างนี้เพื่อเริ่มสอบ</p>
    </div>

    <!-- Huge PIN display -->
    <div class="py-8 px-6 rounded-3xl bg-slate-950/80 border-2 border-pink-500/40 shadow-inner space-y-2">
      <div class="text-xs uppercase font-extrabold tracking-widest text-slate-400">ROOM PIN</div>
      <div class="text-6xl sm:text-7xl font-black font-mono tracking-widest text-pink-400 drop-shadow-[0_0_20px_rgba(231,45,130,0.6)]">
        <?= htmlspecialchars($activeSession['session_pin']) ?>
      </div>
    </div>

    <div class="flex flex-col sm:flex-row items-center justify-center gap-4 text-xs text-slate-300">
      <div class="p-3 rounded-2xl bg-white/5 border border-white/10">
        🌐 เว็บไซต์: <strong class="text-white font-mono">student/live-session.php</strong>
      </div>
      <div class="p-3 rounded-2xl bg-white/5 border border-white/10">
        ⏱️ เวลาสอบ: <strong class="text-white"><?= !empty($activeSession['time_limit_minutes']) ? (int) $activeSession['time_limit_minutes'] . ' นาที' : 'ไม่จำกัดเวลา' ?></strong>
      </div>
    </div>

    <div class="pt-2">
      <a href="live-session-room.php?id=<?= urlencode($activeSession['id']) ?>" class="btn-enter-room px-8 py-3.5 rounded-2xl text-sm font-black inline-flex items-center gap-2 cursor-pointer shadow-lg">
        <span>🚀 สลับไปยังห้องควบคุมสด (Command Center)</span>
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- CREATE LIVE SESSION MODAL -->
<div id="create-modal-overlay" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
  <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-slate-100 space-y-5 my-auto">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3.5">
      <div>
        <h3 class="text-lg font-black text-slate-900">สร้างห้องเรียนสด (New Live Session)</h3>
        <p class="text-xs text-slate-500">สร้างรหัส PIN 6 หลักเพื่อให้นักเรียนทำข้อสอบพร้อมกันในคาบเรียน</p>
      </div>
      <button type="button" id="btn-close-create-modal" class="text-slate-400 hover:text-slate-600 font-black text-lg p-1.5 cursor-pointer">✕</button>
    </div>

    <form id="create-session-form" class="space-y-4 text-xs">
      <div>
        <label class="block font-bold text-slate-800 mb-1.5">ชื่อห้องเรียนสด *</label>
        <input name="title" required placeholder="เช่น แบบทดสอบกลางภาค คาบเรียนที่ 1..." class="w-full h-11 px-4 rounded-xl border border-slate-300 outline-none focus:border-pink-500 text-sm font-semibold transition-colors">
      </div>

      <div>
        <label class="block font-bold text-slate-800 mb-1.5">เลือกแบบทดสอบที่ใช้สอบ (ไม่บังคับ)</label>
        <select name="examId" class="w-full h-11 px-3 rounded-xl border border-slate-300 outline-none focus:border-pink-500 text-sm font-semibold bg-white transition-colors">
          <option value="">-- ไม่กำหนดแบบทดสอบ (เน้นการสอน/บรรยายสด) --</option>
          <?php foreach ($exams as $ex): ?>
            <option value="<?= $ex['id'] ?>">
              <?= htmlspecialchars($ex['title']) ?> (<?= htmlspecialchars((string) $ex['subject']) ?>, <?= htmlspecialchars((string) $ex['grade']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Phase 2: Calendar Event Linker -->
      <div>
        <label class="block font-bold text-slate-800 mb-1.5">📅 เชื่อมโยงกับคาบเรียนในปฏิทิน (Phase 2)</label>
        <select name="calendarEventId" id="create-calendar-event" class="w-full h-11 px-3 rounded-xl border border-slate-300 outline-none focus:border-pink-500 text-xs bg-white">
          <option value="">-- ไม่เชื่อมโยง (สร้างห้องเรียนสดอิสระ) --</option>
          <?php foreach ($calendarEvents as $cev): ?>
            <option value="<?= (int)$cev['id'] ?>">
              <?= htmlspecialchars($cev['event_date']) ?> <?= substr($cev['start_time'], 0, 5) ?> น. - <?= htmlspecialchars($cev['title']) ?><?= !empty($cev['course_title']) ? ' ('.htmlspecialchars($cev['course_title']).')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="text-[11px] text-slate-500 mt-1">หากเชื่อมโยง ระบบจะดึงหัวข้อการเตรียมตัว และบันทึกผลความเข้าใจลง Learning Profile โดยอัตโนมัติ</p>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-slate-800 mb-1.5">ระดับการศึกษา</label>
          <select name="educationStage" class="w-full h-11 px-3 rounded-xl border border-slate-300 outline-none focus:border-pink-500 text-xs bg-white">
            <option value="m1">มัธยมศึกษาตอนต้น (ม.1 - ม.3)</option>
            <option value="m4">มัธยมศึกษาตอนปลาย (ม.4 - ม.6)</option>
            <option value="university" selected>เตรียมสอบมหาวิทยาลัย (TCAS)</option>
          </select>
        </div>

        <div>
          <label class="block font-bold text-slate-800 mb-1.5">จำกัดเวลา (นาที)</label>
          <input name="timeLimitMinutes" type="number" min="1" max="300" value="30" class="w-full h-11 px-4 rounded-xl border border-slate-300 outline-none focus:border-pink-500 text-xs font-semibold">
        </div>
      </div>

      <div class="space-y-2 pt-2 border-t border-slate-100">
        <label class="flex items-center gap-2 cursor-pointer font-semibold text-slate-700">
          <input name="hasTimeLimit" type="checkbox" checked class="w-4 h-4 rounded text-pink-600 focus:ring-pink-500">
          เปิดระบบนับเวลาถอยหลัง
        </label>
        <label class="flex items-center gap-2 cursor-pointer font-semibold text-slate-700">
          <input name="allowLateJoin" type="checkbox" checked class="w-4 h-4 rounded text-pink-600 focus:ring-pink-500">
          อนุญาตให้นักเรียนเข้าสายได้หลังจากเริ่มเซสชันแล้ว
        </label>
      </div>

      <div class="pt-3 flex items-center justify-end gap-2.5">
        <button type="button" id="btn-cancel-create" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold cursor-pointer transition-colors">
          ยกเลิก
        </button>
        <button type="submit" id="btn-submit-create" class="btn-create-session px-6 py-2.5 rounded-xl font-bold cursor-pointer">
          🚀 เปิดห้องเรียนสดทันที
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(() => {
  const modal = document.getElementById("create-modal-overlay");
  const openBtn = document.getElementById("btn-open-create-modal");
  const closeBtn = document.getElementById("btn-close-create-modal");
  const cancelBtn = document.getElementById("btn-cancel-create");
  const form = document.getElementById("create-session-form");

  const toggleModal = (show) => {
    modal.classList.toggle("hidden", !show);
    if (show) form.reset();
  };

  if (openBtn) openBtn.onclick = () => toggleModal(true);
  if (closeBtn) closeBtn.onclick = () => toggleModal(false);
  if (cancelBtn) cancelBtn.onclick = () => toggleModal(false);

  // Phase 2: Check URL for preselected calendar event
  const urlParams = new URLSearchParams(window.location.search);
  const preselectedCalId = urlParams.get("calendarEventId");
  if (preselectedCalId) {
    toggleModal(true);
    const calSelect = document.getElementById("create-calendar-event");
    if (calSelect) calSelect.value = preselectedCalId;
  }

  // Projector Modal
  const projectorModal = document.getElementById("projector-modal-overlay");
  const openProjectorBtn = document.getElementById("btn-open-projector-view");
  const closeProjectorBtn = document.getElementById("btn-close-projector-modal");
  if (openProjectorBtn && projectorModal) {
    openProjectorBtn.onclick = () => projectorModal.classList.remove("hidden");
  }
  if (closeProjectorBtn && projectorModal) {
    closeProjectorBtn.onclick = () => projectorModal.classList.add("hidden");
  }

  // Copy PIN button
  const copyPinBtn = document.getElementById("btn-copy-pin");
  if (copyPinBtn) {
    copyPinBtn.onclick = async () => {
      const pin = copyPinBtn.dataset.pin;
      try {
        await navigator.clipboard.writeText(pin);
        const status = document.getElementById("copy-pin-status");
        if (status) {
          const orig = status.textContent;
          status.textContent = "✓ คัดลอกแล้ว!";
          setTimeout(() => { status.textContent = orig; }, 1800);
        }
      } catch (err) {
        prompt("กรุณาคัดลอกรหัส PIN นี้:", pin);
      }
    };
  }

  // Copy Link button
  const copyLinkBtn = document.getElementById("btn-copy-link");
  if (copyLinkBtn) {
    copyLinkBtn.onclick = async () => {
      const url = copyLinkBtn.dataset.url;
      try {
        await navigator.clipboard.writeText(url);
        const status = document.getElementById("copy-link-status");
        if (status) {
          const orig = status.textContent;
          status.textContent = "✓ คัดลอกแล้ว!";
          setTimeout(() => { status.textContent = orig; }, 1800);
        }
      } catch (err) {
        prompt("กรุณาคัดลอกลิงก์สำหรับนักเรียน:", url);
      }
    };
  }

  form.onsubmit = async (e) => {
    e.preventDefault();
    const submitBtn = document.getElementById("btn-submit-create");
    submitBtn.disabled = true;
    submitBtn.textContent = "กำลังเปิดห้องเรียน...";

    const fd = new FormData(form);
    const body = {
      title: fd.get("title"),
      examId: fd.get("examId"),
      educationStage: fd.get("educationStage"),
      timeLimitMinutes: fd.get("timeLimitMinutes"),
      hasTimeLimit: fd.get("hasTimeLimit") !== null,
      allowLateJoin: fd.get("allowLateJoin") !== null,
      calendarEventId: fd.get("calendarEventId") || null,
    };

    try {
      const res = await fetch("live-sessions-api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "สร้างห้องเรียนไม่สำเร็จ");
      window.location.href = "live-session-room.php?id=" + encodeURIComponent(data.sessionId);
    } catch (err) {
      alert(err.message);
      submitBtn.disabled = false;
      submitBtn.textContent = "🚀 เปิดห้องเรียนสดทันที";
    }
  };

  document.body.onclick = async (e) => {
    const closeBtn = e.target.closest("[data-close-session]");
    if (closeBtn && confirm("ต้องการสิ้นสุดห้องเรียนสดนี้ใช่หรือไม่? เมื่อสิ้นสุดแล้วนักเรียนจะไม่สามารถทำข้อสอบต่อได้")) {
      const sid = closeBtn.dataset.closeSession;
      await fetch("live-sessions-api.php", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ sessionId: sid, status: "closed" }),
      });
      location.reload();
    }

    const delBtn = e.target.closest("[data-delete-session]");
    if (delBtn && confirm("ต้องการลบข้อมูลห้องเรียนนี้ใช่หรือไม่?")) {
      const sid = delBtn.dataset.deleteSession;
      await fetch("live-sessions-api.php?sessionId=" + encodeURIComponent(sid), { method: "DELETE" });
      location.reload();
    }

    const delAllBtn = e.target.closest("#btn-delete-all-closed");
    if (delAllBtn && confirm("ต้องการลบประวัติห้องเรียนที่ปิดแล้วทั้งหมดใช่หรือไม่?")) {
      await fetch("live-sessions-api.php?action=delete_all_closed", { method: "DELETE" });
      location.reload();
    }
  };
})();
</script>
</body>
</html>

