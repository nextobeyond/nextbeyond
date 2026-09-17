<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/live-sessions-helper.php';

ensureLiveSessionSchema($pdo);

$sessionId = trim((string) ($_GET['id'] ?? ''));
if ($sessionId === '') {
    header('Location: live-sessions.php');
    exit;
}

$stmt = $pdo->prepare("SELECT s.*, CONCAT_WS(' ', u.first_name, u.last_name) AS teacher_name, e.title AS exam_title FROM classroom_sessions s JOIN users u ON u.id = s.teacher_id LEFT JOIN exams e ON e.id = s.exam_id WHERE s.id = :id LIMIT 1");
$stmt->execute([':id' => $sessionId]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: live-sessions.php?error=not_found');
    exit;
}

$pageTitle = 'Live Session — ' . htmlspecialchars($session['title']);
$currentPage = 'live-sessions.php';
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> - Next Beyond Teacher Command Center</title>
  <link rel="stylesheet" href="../assets/css/output.css">
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Inter:wght@500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="../assets/js/admin-guard.js"></script>
  <script defer src="../assets/js/live-session-teacher.js?v=<?= filemtime(__DIR__ . '/../assets/js/live-session-teacher.js') ?>"></script>
</head>
<body class="bg-[#f5f8fc] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0">
    <?php include 'includes/topbar.php'; ?>
    <main class="flex-1 p-6 max-[640px]:p-4">
      <div id="session-error-notice" class="hidden mb-4 p-4 rounded-xl bg-red-50 text-red-700 border border-red-200 text-xs font-bold"></div>

      <div class="max-w-6xl mx-auto space-y-4 pb-16">

        <!-- SECTION 1: Top Navigation Bar -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <a href="live-sessions.php" class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center transition-colors" title="กลับหน้ารายการ">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
              <div class="flex items-center gap-2">
                <span class="text-xs text-slate-500 font-bold uppercase tracking-wider">PIN ห้องเรียนสด:</span>
                <span id="live-pin-display" class="text-2xl font-black font-mono tracking-widest text-slate-900 select-all">
                  <?= htmlspecialchars($session['session_pin']) ?>
                </span>
                <button type="button" id="btn-copy-pin" class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors cursor-pointer" title="คัดลอก PIN">
                  <span id="pin-copy-icon">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
                  </span>
                  <span id="pin-copy-check" class="hidden text-emerald-600 font-bold">✓</span>
                </button>
              </div>
              <p id="live-session-title" class="text-xs text-slate-500 truncate mt-0.5">
                <?= htmlspecialchars($session['title']) ?> • ห้องเรียนมาตรฐาน
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 flex-wrap">
            <button type="button" id="btn-refresh-session" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold flex items-center gap-1.5 cursor-pointer transition-colors">
              <svg id="btn-refresh-icon" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              รีเฟรช
            </button>
            <a href="../student/live-session.php" target="_blank" class="px-3 py-2 rounded-xl bg-purple-50 hover:bg-purple-100 border border-purple-200 text-purple-700 text-xs font-bold flex items-center gap-1.5 transition-colors">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
              เปิดหน้านักเรียน
            </a>
            <div class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
              LIVE SESSION
            </div>
          </div>
        </div>

        <!-- SECTION 2: Session Mode Bar -->
        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.24 7.76a6 6 0 010 8.49m-8.48-.01a6 6 0 010-8.49m11.31-2.82a10 10 0 010 14.14m-14.14 0a10 10 0 010-14.14"/></svg>
            </div>
            <div>
              <h4 class="text-xs font-bold text-slate-800">แบบทดสอบประจำคาบ (Live Quiz)</h4>
              <p id="live-mode-desc" class="text-[11px] text-slate-500">
                <?= $session['has_time_limit'] ? '⏱️ ' . $session['time_limit_minutes'] . ' นาที' : '🔓 ไม่จำกัดเวลา' ?>
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 flex-wrap">
            <div id="live-announcement-badge" class="<?= $session['announcement_message'] ? '' : 'hidden' ?> px-3 py-1.5 rounded-xl bg-purple-100 text-purple-800 text-xs font-semibold flex items-center gap-2">
              <span>📢 มีประกาศสด: <b id="live-announcement-text"><?= htmlspecialchars((string) $session['announcement_message']) ?></b></span>
              <button type="button" id="btn-clear-announcement" class="text-purple-600 hover:text-purple-900 font-black cursor-pointer">✕</button>
            </div>
            <button type="button" data-open-modal="announcement" class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold flex items-center gap-1.5 cursor-pointer transition-colors">
              <span class="text-purple-600">🔔</span> ประกาศด่วน
            </button>
          </div>
        </div>

        <!-- SECTION 3: Eyes On Me Card -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-3">
          <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
              <div id="eyes-icon-wrap" class="w-10 h-10 rounded-xl flex items-center justify-center <?= $session['eyes_on_me_enabled'] ? 'bg-pink-100 text-pink-600' : 'bg-slate-100 text-slate-500' ?>">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="text-sm font-bold text-slate-900">👀 Eyes On Me</h3>
                  <span id="eyes-status-badge" class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $session['eyes_on_me_enabled'] ? 'bg-pink-100 text-pink-700' : 'bg-emerald-100 text-emerald-700' ?>">
                    <?= $session['eyes_on_me_enabled'] ? 'หน้าจอนักเรียนถูกล็อก' : 'นักเรียนทำข้อสอบได้ตามปกติ' ?>
                  </span>
                </div>
                <p id="eyes-desc" class="text-xs text-slate-500 mt-0.5">
                  <?= $session['eyes_on_me_enabled'] ? 'นักเรียนถูกหยุดชั่วคราว เพื่อดูกระดาน' : 'อนุญาตให้ทำข้อสอบและส่งคำตอบ' ?>
                </p>
              </div>
            </div>

            <!-- Global Toggle Switch -->
            <button type="button" id="eyes-on-me-toggle" class="relative inline-flex h-7 w-12 rounded-full border-2 border-transparent transition-colors cursor-pointer <?= $session['eyes_on_me_enabled'] ? 'bg-pink-500' : 'bg-slate-300' ?>">
              <span id="eyes-on-me-pin" class="inline-block h-6 w-6 rounded-full bg-white shadow-md transition-transform <?= $session['eyes_on_me_enabled'] ? 'translate-x-5' : 'translate-x-0' ?>"></span>
            </button>
          </div>

          <!-- Per-student lock accordion -->
          <div class="pt-2 border-t border-slate-100">
            <button type="button" id="btn-toggle-lock-accordion" class="text-xs font-semibold text-slate-600 flex items-center gap-1.5 cursor-pointer">
              <span id="lock-accordion-chevron">▼</span>
              ล็อกเป็นรายคน
              <span id="per-student-locked-badge" class="hidden px-2 rounded-full bg-pink-100 text-pink-700 text-[10px] font-bold"></span>
            </button>
            <div id="student-lock-accordion-body" class="hidden mt-3 p-3 rounded-xl bg-slate-50 border border-slate-200">
              <div id="student-lock-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                <!-- Loaded via JS -->
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION 4: Exam Overview Banner -->
        <button type="button" data-open-modal="exam_overview" class="w-full p-4 rounded-2xl bg-white hover:bg-slate-50 border border-slate-200 shadow-xs flex items-center justify-between text-left transition-colors cursor-pointer">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
            <div>
              <h4 class="text-xs sm:text-sm font-bold text-slate-900">ภาพรวมข้อสอบ (Exam Overview)</h4>
              <p id="exam-overview-qcount" class="text-[11px] text-slate-500">ดูข้อสอบทั้งหมดพร้อมเฉลย</p>
            </div>
          </div>
          <span class="text-slate-400 font-black text-lg">›</span>
        </button>

        <!-- SECTION 5: Student Action Grid (9 Buttons) -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <!-- 1. รายละเอียดนักเรียน -->
          <button type="button" data-open-modal="student_details" class="p-4 rounded-2xl bg-sky-50/80 hover:bg-sky-100/80 border border-sky-200/70 flex flex-col items-center justify-center space-y-2 cursor-pointer shadow-xs group transition-all">
            <div class="w-10 h-10 rounded-2xl bg-sky-500 text-white flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <span class="text-xs font-bold text-sky-950">รายละเอียดนักเรียน</span>
            <span class="text-[10px] text-sky-700">ความคืบหน้ารายคน</span>
          </button>

          <!-- 2. เช็คชื่อนักเรียน -->
          <button type="button" data-open-modal="attendance" class="p-4 rounded-2xl bg-cyan-50/80 hover:bg-cyan-100/80 border border-cyan-200/70 flex flex-col items-center justify-center space-y-2 cursor-pointer shadow-xs group transition-all">
            <div class="w-10 h-10 rounded-2xl bg-cyan-500 text-white flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <span class="text-xs font-bold text-cyan-950">เช็คชื่อนักเรียน</span>
            <span class="text-[10px] text-cyan-700">ดาวน์โหลด CSV</span>
          </button>

          <!-- 3. การแจ้งเตือนนักเรียน -->
          <button type="button" data-open-modal="announcement" class="p-4 rounded-2xl bg-pink-50/80 hover:bg-pink-100/80 border border-pink-200/70 flex flex-col items-center justify-center space-y-2 cursor-pointer shadow-xs group transition-all">
            <div class="w-10 h-10 rounded-2xl bg-pink-500 text-white flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </div>
            <span class="text-xs font-bold text-pink-950">การแจ้งเตือนสด</span>
            <span class="text-[10px] text-pink-700">ส่งประกาศด่วน</span>
          </button>

          <!-- 4. ศูนย์บทเรียนเสริม -->
          <button type="button" data-open-modal="remediation" class="p-4 rounded-2xl bg-amber-50/80 hover:bg-amber-100/80 border border-amber-200/70 flex flex-col items-center justify-center space-y-2 cursor-pointer shadow-xs group transition-all">
            <div class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            </div>
            <span class="text-xs font-bold text-amber-950">ศูนย์บทเรียนเสริม</span>
            <span class="text-[10px] text-amber-700">วิเคราะห์จุดติดขัด</span>
          </button>

          <!-- 5. การสอบ / สถิติ -->
          <button type="button" data-open-modal="exam_stats" class="p-4 rounded-2xl bg-purple-50/80 hover:bg-purple-100/80 border border-purple-200/70 flex flex-col items-center justify-center space-y-2 cursor-pointer shadow-xs group transition-all">
            <div class="w-10 h-10 rounded-2xl bg-purple-500 text-white flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            </div>
            <span class="text-xs font-bold text-purple-950">สถิติการสอบ</span>
            <span class="text-[10px] text-purple-700">คะแนนเฉลี่ย & การส่ง</span>
          </button>

          <!-- 6. แผนที่ทักษะของห้อง -->
          <button type="button" data-open-modal="skill_map" class="p-4 rounded-2xl bg-emerald-50/80 hover:bg-emerald-100/80 border border-emerald-200/70 flex flex-col items-center justify-center space-y-2 cursor-pointer shadow-xs group transition-all">
            <div class="w-10 h-10 rounded-2xl bg-emerald-500 text-white flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <span class="text-xs font-bold text-emerald-950">แผนที่ทักษะ</span>
            <span class="text-[10px] text-emerald-700">วิเคราะห์ตามหัวข้อ</span>
          </button>

          <!-- 7. สรุปคำถามที่ตอบผิด -->
          <button type="button" data-open-modal="missed_questions" class="p-4 rounded-2xl bg-orange-50/80 hover:bg-orange-100/80 border border-orange-200/70 flex flex-col items-center justify-center space-y-2 cursor-pointer shadow-xs group transition-all">
            <div class="w-10 h-10 rounded-2xl bg-orange-500 text-white flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <span class="text-xs font-bold text-orange-950">ข้อที่ตอบผิด</span>
            <span class="text-[10px] text-orange-700">จัดอันดับข้อหลอก</span>
          </button>

          <!-- 8. รีเซ็ตคำตอบนักเรียน -->
          <button type="button" data-open-modal="reset_attempt" class="p-4 rounded-2xl bg-slate-50/80 hover:bg-slate-100/80 border border-slate-200/70 flex flex-col items-center justify-center space-y-2 cursor-pointer shadow-xs group transition-all">
            <div class="w-10 h-10 rounded-2xl bg-slate-500 text-white flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </div>
            <span class="text-xs font-bold text-slate-950">รีเซ็ตคำตอบ</span>
            <span class="text-[10px] text-slate-600">อนุญาตให้สอบใหม่</span>
          </button>
        </div>

        <!-- SECTION 6: Live Student Progress Pills -->
        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-2.5">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
            <span class="text-xs font-bold text-slate-700">((•)) สถานะนักเรียนสด:</span>
          </div>
          <div id="live-student-pills" class="flex flex-wrap items-center gap-2">
            <!-- Loaded via JS -->
          </div>
        </div>
      </div>

      </div>
    </main>
  </div>
</div>

<!-- MODAL OVERLAY -->
<div id="live-modal-overlay" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
  <div class="bg-white rounded-3xl max-w-2xl sm:max-w-3xl w-full p-6 shadow-2xl border border-slate-100 space-y-4 max-h-[88vh] flex flex-col my-auto">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <div>
        <h3 id="modal-header-title" class="text-base font-bold text-slate-900">ชื่อ Modal</h3>
        <p id="modal-header-desc" class="text-xs text-slate-500">คำอธิบาย</p>
      </div>
      <button type="button" data-close-modal class="text-slate-400 hover:text-slate-600 font-black text-lg p-1 cursor-pointer">✕</button>
    </div>
    <div id="modal-body-content" class="flex-1 overflow-y-auto">
      <!-- Injected via JS -->
    </div>
  </div>
</div>

</body>
</html>
