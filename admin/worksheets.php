<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/access.php';

$pageTitle = 'คลังใบงาน';
$pageDesc = 'สร้าง ค้นหา และนำใบงานกลับมาใช้ซ้ำได้จากที่เดียว';
$currentPage = 'worksheets.php';
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - Next Beyond Admin</title>
  <link rel="stylesheet" href="../assets/css/output.css">
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Inter:wght@500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="../assets/js/admin-guard.js"></script>
  <script defer src="../assets/js/admin-worksheets.js?v=<?= time() ?>"></script>
  <style>
    .ws-chip { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700; }
    .ws-tab-active { background-color: #fce7f3; color: #db2777; font-weight: 700; }
  </style>
</head>
<body class="bg-[#f4f7fb] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 p-8 max-[640px]:p-4 max-w-[1600px] w-full mx-auto">
      <!-- Top Title & Primary Actions Header -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-[26px] font-black tracking-[-0.02em] text-navy-950">คลังใบงาน</h1>
            <span id="header-total-badge" class="px-2.5 py-0.5 text-[12px] font-bold rounded-full bg-pink-100 text-pink-600">0 รายการ</span>
          </div>
          <p class="text-[14px] text-[#65738a] mt-1 font-normal">สร้าง ค้นหา และนำใบงานกลับมาใช้ซ้ำได้จากที่เดียว</p>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
          <button type="button" id="btn-create-manual" class="h-11 px-4 rounded-xl bg-white border border-[#dce4ef] text-navy-900 font-bold text-[13px] hover:bg-slate-50 transition shadow-sm flex items-center gap-2">
            <svg class="w-4 h-4 text-[#65738a]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <span>+ สร้างใบงานเอง</span>
          </button>
          <a href="ai-worksheet.php" class="h-11 px-5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-[13px] transition shadow-[0_4px_14px_rgba(231,45,130,0.3)] flex items-center gap-2">
            <svg class="w-4 h-4 text-amber-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            <span>+ สร้างใบงานด้วย AI</span>
          </a>
        </div>
      </div>

      <!-- Folders Navigation Bar -->
      <div class="bg-white rounded-2xl border border-[#e8ecf2] p-2.5 mb-6 shadow-sm flex items-center justify-between gap-3 overflow-x-auto">
        <div class="flex items-center gap-1.5 min-w-max" id="folder-tab-container">
          <button type="button" data-folder="" class="folder-tab px-3.5 py-1.5 rounded-xl text-[13px] font-bold text-navy-900 bg-pink-50 text-pink-600 transition flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            <span>ทั้งหมด</span>
          </button>
        </div>
        <button type="button" id="btn-add-folder" class="shrink-0 px-3 py-1.5 rounded-xl border border-dashed border-[#cbd5e1] hover:border-pink-400 text-[#65738a] hover:text-pink-600 text-[12px] font-bold transition flex items-center gap-1">
          <span>+ โฟลเดอร์ใหม่</span>
        </button>
      </div>

      <!-- Search & Filters Container -->
      <div class="bg-white rounded-2xl border border-[#e8ecf2] p-4 mb-6 shadow-sm">
        <!-- Main Search Bar Row -->
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 mb-4">
          <div class="relative flex-1">
            <svg class="w-5 h-5 text-[#94a3b8] absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input id="ws-search-input" type="search" placeholder="ค้นหาชื่อใบงาน หัวข้อ หรือคำสำคัญ..." class="w-full h-11 pl-11 pr-4 bg-[#f8fafc] border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 placeholder-[#94a3b8] focus:bg-white focus:border-pink-500 focus:ring-2 focus:ring-pink-500/15 outline-none transition">
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <!-- View Mode Switcher -->
            <div class="inline-flex p-1 bg-[#f1f5f9] rounded-xl border border-[#e2e8f0]">
              <button type="button" id="view-mode-grid" class="p-2 rounded-lg text-[#64748b] hover:text-navy-900 transition" title="Grid View">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
              </button>
              <button type="button" id="view-mode-list" class="p-2 rounded-lg text-[#64748b] hover:text-navy-900 transition" title="List View">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
              </button>
            </div>

            <!-- Mobile Filter Toggle Button -->
            <button type="button" id="btn-mobile-filter" class="lg:hidden h-11 px-3.5 rounded-xl border border-[#dce4ef] bg-[#f8fafc] text-[13px] font-bold flex items-center gap-1.5">
              <svg class="w-4 h-4 text-[#65738a]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
              <span>ตัวกรอง</span>
            </button>
          </div>
        </div>

        <!-- Filter Selects Row (Desktop) -->
        <div id="filter-container" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2.5 pt-3 border-t border-[#f1f5f9]">
          <!-- Subject -->
          <div>
            <label class="block text-[11px] font-bold text-[#64748b] uppercase tracking-wider mb-1">วิชา</label>
            <select id="filter-subject" class="w-full h-9 px-2.5 bg-[#f8fafc] border border-[#dce4ef] rounded-lg text-[13px] text-navy-950 focus:border-pink-500 outline-none">
              <option value="">ทุกวิชา</option>
              <option value="คณิตศาสตร์">คณิตศาสตร์</option>
              <option value="ฟิสิกส์">ฟิสิกส์</option>
              <option value="เคมี">เคมี</option>
              <option value="ชีววิทยา">ชีววิทยา</option>
              <option value="ภาษาอังกฤษ">ภาษาอังกฤษ</option>
              <option value="ภาษาไทย">ภาษาไทย</option>
              <option value="วิทยาศาสตร์">วิทยาศาสตร์</option>
              <option value="สังคมศึกษา">สังคมศึกษา</option>
            </select>
          </div>

          <!-- Level / Grade -->
          <div>
            <label class="block text-[11px] font-bold text-[#64748b] uppercase tracking-wider mb-1">ระดับชั้น</label>
            <select id="filter-level" class="w-full h-9 px-2.5 bg-[#f8fafc] border border-[#dce4ef] rounded-lg text-[13px] text-navy-950 focus:border-pink-500 outline-none">
              <option value="">ทุกระดับชั้น</option>
              <option value="ป.1">ป.1</option><option value="ป.2">ป.2</option><option value="ป.3">ป.3</option>
              <option value="ป.4">ป.4</option><option value="ป.5">ป.5</option><option value="ป.6">ป.6</option>
              <option value="ม.1">ม.1</option><option value="ม.2">ม.2</option><option value="ม.3">ม.3</option>
              <option value="ม.4">ม.4</option><option value="ม.5">ม.5</option><option value="ม.6">ม.6</option>
              <option value="A-Level">A-Level</option>
            </select>
          </div>

          <!-- Type -->
          <div>
            <label class="block text-[11px] font-bold text-[#64748b] uppercase tracking-wider mb-1">ประเภท</label>
            <select id="filter-type" class="w-full h-9 px-2.5 bg-[#f8fafc] border border-[#dce4ef] rounded-lg text-[13px] text-navy-950 focus:border-pink-500 outline-none">
              <option value="">ทุกประเภท</option>
              <option value="Worksheet">Worksheet</option>
              <option value="Practice">Practice</option>
              <option value="Homework">Homework</option>
              <option value="Pre-Test">Pre-Test</option>
              <option value="Post-Test">Post-Test</option>
              <option value="Quiz">Quiz</option>
              <option value="Mock Practice">Mock Practice</option>
            </select>
          </div>

          <!-- Difficulty -->
          <div>
            <label class="block text-[11px] font-bold text-[#64748b] uppercase tracking-wider mb-1">ความยาก</label>
            <select id="filter-difficulty" class="w-full h-9 px-2.5 bg-[#f8fafc] border border-[#dce4ef] rounded-lg text-[13px] text-navy-950 focus:border-pink-500 outline-none">
              <option value="">ทุกระดับ</option>
              <option value="easy">ง่าย</option>
              <option value="medium">ปานกลาง</option>
              <option value="hard">ยาก</option>
              <option value="expert">ยากมาก</option>
            </select>
          </div>

          <!-- Created By / Source -->
          <div>
            <label class="block text-[11px] font-bold text-[#64748b] uppercase tracking-wider mb-1">สร้างโดย</label>
            <select id="filter-source" class="w-full h-9 px-2.5 bg-[#f8fafc] border border-[#dce4ef] rounded-lg text-[13px] text-navy-950 focus:border-pink-500 outline-none">
              <option value="">ทั้งหมด</option>
              <option value="ai">สร้างด้วย AI</option>
              <option value="manual">ครูสร้างเอง</option>
            </select>
          </div>

          <!-- Status & Clear Button -->
          <div class="flex items-end gap-2">
            <div class="flex-1">
              <label class="block text-[11px] font-bold text-[#64748b] uppercase tracking-wider mb-1">สถานะ</label>
              <select id="filter-status" class="w-full h-9 px-2.5 bg-[#f8fafc] border border-[#dce4ef] rounded-lg text-[13px] text-navy-950 focus:border-pink-500 outline-none">
                <option value="">ทั้งหมด</option>
                <option value="published">เผยแพร่แล้ว</option>
                <option value="draft">ฉบับร่าง</option>
                <option value="archived">จัดเก็บ (Archived)</option>
              </select>
            </div>
            <button type="button" id="btn-clear-filters" class="h-9 px-3 text-[12px] font-bold text-[#64748b] hover:text-pink-600 bg-[#f1f5f9] hover:bg-pink-50 rounded-lg transition whitespace-nowrap" title="ล้างตัวกรอง">
              ล้างตัวกรอง
            </button>
          </div>
        </div>
      </div>

      <!-- Worksheets Presentation Container -->
      <section id="worksheets-container">
        <!-- Loading State -->
        <div id="ws-loading" class="py-16 text-center text-[#64748b] bg-white rounded-2xl border border-[#e8ecf2]">
          <div class="inline-block w-8 h-8 border-3 border-pink-500 border-t-transparent rounded-full animate-spin mb-3"></div>
          <p class="font-medium text-[14px]">กำลังโหลดข้อมูลคลังใบงาน...</p>
        </div>

        <!-- Grid View Mode -->
        <div id="ws-grid" class="hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5"></div>

        <!-- List View Mode -->
        <div id="ws-list" class="hidden bg-white rounded-2xl border border-[#e8ecf2] shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[800px]">
              <thead class="bg-[#f8fafc] border-b border-[#e8ecf2] text-[12px] text-[#64748b] font-bold uppercase tracking-wider">
                <tr>
                  <th class="py-3.5 px-5">ชื่อใบงาน / ข้อมูล</th>
                  <th class="py-3.5 px-4">วิชา / ระดับ</th>
                  <th class="py-3.5 px-4">ความยาก / ประเภท</th>
                  <th class="py-3.5 px-4">จำนวนข้อ</th>
                  <th class="py-3.5 px-4">ผู้สร้าง</th>
                  <th class="py-3.5 px-4">อัปเดตล่าสุด</th>
                  <th class="py-3.5 px-5 text-right">การทำงาน</th>
                </tr>
              </thead>
              <tbody id="ws-list-tbody" class="divide-y divide-[#f1f5f9] text-[13px]"></tbody>
            </table>
          </div>
        </div>

        <!-- Empty State -->
        <div id="ws-empty" class="hidden bg-white rounded-2xl border border-[#e8ecf2] p-16 text-center shadow-sm">
          <div class="w-16 h-16 rounded-2xl bg-pink-50 text-pink-500 flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
          </div>
          <h3 class="text-[18px] font-bold text-navy-950 mb-1">ยังไม่มีใบงานในคลัง</h3>
          <p class="text-[14px] text-[#64748b] max-w-[420px] mx-auto mb-6">
            สร้างใบงานแรกด้วย AI เพื่อให้ระบบจัดสรรโจทย์พร้อมคำอธิบายอัตโนมัติ หรือเริ่มต้นเขียนคำถามเองได้ทันที
          </p>
          <div class="flex items-center justify-center gap-3">
            <a href="ai-worksheet.php" class="h-10 px-5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-[13px] transition shadow-[0_4px_12px_rgba(231,45,130,0.25)] flex items-center gap-2">
              <svg class="w-4 h-4 text-amber-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
              <span>สร้างด้วย AI</span>
            </a>
            <button type="button" onclick="document.getElementById('btn-create-manual').click()" class="h-10 px-5 rounded-xl border border-[#dce4ef] hover:bg-slate-50 text-navy-900 font-bold text-[13px] transition">
              สร้างเอง
            </button>
          </div>
        </div>
      </section>
    </main>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: PREVIEW WORKSHEET (Real Questions, Choices, Teacher/Student View) -->
<!-- ========================================================================= -->
<div id="preview-modal" class="hidden fixed inset-0 z-50 bg-navy-950/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
  <div class="w-full max-w-[920px] max-h-[90vh] bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden my-auto border border-[#e2e8f0]">
    <!-- Modal Header -->
    <div class="px-6 py-4 border-b border-[#e8ecf2] flex items-center justify-between gap-4 bg-white sticky top-0 z-10">
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 flex-wrap">
          <span id="preview-subject-badge" class="ws-chip bg-blue-50 text-blue-700"></span>
          <span id="preview-level-badge" class="ws-chip bg-slate-100 text-slate-700"></span>
          <span id="preview-source-badge" class="ws-chip bg-pink-50 text-pink-600"></span>
        </div>
        <h2 id="preview-title" class="text-[18px] font-black text-navy-950 mt-1 truncate"></h2>
      </div>

      <!-- Teacher / Student View Switcher -->
      <div class="flex items-center gap-3 shrink-0">
        <div class="inline-flex p-1 bg-[#f1f5f9] rounded-xl border border-[#e2e8f0] text-[12px] font-bold">
          <button type="button" id="btn-view-student" class="px-3 py-1.5 rounded-lg text-[#64748b] transition">
            มุมมองนักเรียน
          </button>
          <button type="button" id="btn-view-teacher" class="px-3 py-1.5 rounded-lg bg-white text-pink-600 shadow-sm transition">
            มุมมองครู (เฉลย)
          </button>
        </div>
        <button type="button" id="btn-close-preview" class="w-9 h-9 rounded-full hover:bg-[#f1f5f9] text-[24px] text-[#94a3b8] flex items-center justify-center leading-none">×</button>
      </div>
    </div>

    <!-- Modal Content Body -->
    <div class="p-6 overflow-y-auto space-y-6 flex-1 bg-[#f8fafc]">
      <!-- Metadata summary bar -->
      <div class="bg-white p-4 rounded-xl border border-[#e8ecf2] grid grid-cols-2 sm:grid-cols-4 gap-3 text-[12px]">
        <div>
          <span class="text-[#64748b] block font-bold">หัวข้อหลัก</span>
          <span id="preview-topic" class="font-bold text-navy-900 truncate block"></span>
        </div>
        <div>
          <span class="text-[#64748b] block font-bold">ความยาก</span>
          <span id="preview-diff" class="font-bold text-navy-900 block"></span>
        </div>
        <div>
          <span class="text-[#64748b] block font-bold">จำนวนข้อ</span>
          <span id="preview-count" class="font-bold text-navy-900 block"></span>
        </div>
        <div>
          <span class="text-[#64748b] block font-bold">ผู้สร้าง</span>
          <span id="preview-creator" class="font-bold text-navy-900 block"></span>
        </div>
      </div>

      <!-- Questions List -->
      <div id="preview-questions-list" class="space-y-4"></div>
    </div>

    <!-- Modal Footer Actions -->
    <div class="px-6 py-4 border-t border-[#e8ecf2] bg-white flex items-center justify-between gap-3 flex-wrap">
      <a id="preview-full-detail-link" href="#" class="text-[13px] font-bold text-pink-600 hover:text-pink-700 flex items-center gap-1">
        <span>ดูหน้ารายละเอียดและสถิติเต็ม</span> →
      </a>

      <div class="flex items-center gap-2.5">
        <button type="button" id="preview-btn-delete" class="h-10 px-4 rounded-xl border border-red-200 bg-red-50/60 hover:bg-red-600 hover:border-red-600 text-[13px] font-bold text-red-600 hover:text-white transition flex items-center gap-1.5" title="ลบใบงาน">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          <span>ลบใบงาน</span>
        </button>
        <button type="button" id="preview-btn-print" class="h-10 px-4 rounded-xl border border-[#dce4ef] text-[13px] font-bold hover:bg-slate-50 transition flex items-center gap-1.5">
          <svg class="w-4 h-4 text-[#64748b]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
          <span>พิมพ์ / ส่งออก PDF</span>
        </button>
        <button type="button" id="preview-btn-assign" class="h-10 px-5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white text-[13px] font-bold transition shadow-sm flex items-center gap-1.5">
          <svg class="w-4 h-4 text-pink-100" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
          <span>ใช้กับคลาส</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: CLASS ASSIGNMENT ("ใช้กับคลาส") -->
<!-- ========================================================================= -->
<div id="assign-modal" class="hidden fixed inset-0 z-50 bg-navy-950/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
  <div class="w-full max-w-[540px] bg-white rounded-2xl shadow-2xl overflow-hidden my-auto border border-[#e2e8f0]">
    <div class="px-6 py-4 border-b border-[#e8ecf2] flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="w-10 h-10 rounded-xl bg-pink-50 text-pink-600 flex items-center justify-center font-bold text-lg">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        </span>
        <div>
          <h2 class="text-[17px] font-bold text-navy-950">มอบหมายใบงานให้คลาส</h2>
          <p id="assign-worksheet-title" class="text-[12px] text-[#64748b] truncate max-w-[320px]"></p>
        </div>
      </div>
      <button type="button" id="btn-close-assign" class="w-8 h-8 rounded-full hover:bg-slate-100 text-[22px] text-[#94a3b8] flex items-center justify-center leading-none">×</button>
    </div>

    <form id="assign-form" class="p-6 space-y-4">
      <input type="hidden" id="assign-worksheet-id" name="worksheet_id">

      <div>
        <label class="block text-[13px] font-bold text-navy-900 mb-1.5">เลือกคอร์สเรียน</label>
        <select id="assign-course-select" name="course_id" class="w-full h-11 px-3.5 bg-white border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 focus:border-pink-500 outline-none">
          <option value="">-- ไม่ระบุคอร์ส / มอบหมายอิสระ --</option>
        </select>
      </div>

      <div>
        <label class="block text-[13px] font-bold text-navy-900 mb-1.5">ชื่อกลุ่ม / คลาสเรียน *</label>
        <input type="text" id="assign-class-name" name="class_name" required placeholder="เช่น เคมี ม.5 ห้อง 501 หรือ กลุ่มติวสอบ สอวน." class="w-full h-11 px-3.5 bg-white border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 focus:border-pink-500 outline-none">
      </div>

      <div>
        <label class="block text-[13px] font-bold text-navy-900 mb-1.5">กลุ่มเป้าหมาย</label>
        <div class="grid grid-cols-2 gap-3">
          <label class="flex items-center gap-2 p-3 border border-[#dce4ef] rounded-xl cursor-pointer hover:border-pink-400 transition" id="label-target-all">
            <input type="radio" name="target_type" value="all" checked class="text-pink-600 focus:ring-pink-500">
            <span class="text-[13px] font-bold text-navy-950">นักเรียนทั้งคลาส</span>
          </label>
          <label class="flex items-center gap-2 p-3 border border-[#dce4ef] rounded-xl cursor-pointer hover:border-pink-400 transition" id="label-target-selected">
            <input type="radio" name="target_type" value="selected" class="text-pink-600 focus:ring-pink-500">
            <span class="text-[13px] font-bold text-navy-950">เลือกเฉพาะบุคคล</span>
          </label>
        </div>
      </div>

      <!-- ======================================================= -->
      <!-- INDIVIDUAL STUDENT SELECTOR (Appears when 'selected')    -->
      <!-- ======================================================= -->
      <div id="assign-students-section" class="hidden space-y-2.5 pt-3 pb-2 border-t border-[#f1f5f9]">
        <div class="flex items-center justify-between">
          <label class="text-[13px] font-bold text-navy-900 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <span>เลือกนักเรียน</span>
          </label>
          <span id="assign-selected-count-badge" class="text-xs font-bold text-pink-600 bg-pink-50 border border-pink-200/60 px-2.5 py-0.5 rounded-full">
            เลือกแล้ว 0 คน
          </span>
        </div>

        <!-- Selected Student Chips Preview -->
        <div id="assign-selected-chips" class="hidden flex-wrap gap-1.5 max-h-16 overflow-y-auto"></div>

        <!-- Search Input -->
        <div class="relative">
          <input type="text" id="assign-student-search" placeholder="ค้นหาชื่อนักเรียน..." class="w-full h-10 pl-9 pr-3.5 bg-slate-50 border border-[#dce4ef] rounded-xl text-[13px] text-navy-950 focus:bg-white focus:border-pink-500 outline-none transition">
          <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
        </div>

        <!-- Select All Bar -->
        <div id="assign-select-all-bar" class="flex items-center justify-between px-3 py-1.5 bg-slate-50/80 rounded-lg border border-slate-100 text-[12px]">
          <label class="flex items-center gap-2 cursor-pointer select-none font-bold text-slate-700">
            <input type="checkbox" id="assign-select-all" class="w-4 h-4 rounded text-pink-600 focus:ring-pink-500 border-slate-300 cursor-pointer">
            <span>เลือกทั้งหมด</span>
          </label>
          <span id="assign-students-filtered-count" class="text-slate-400 font-medium">0 คน</span>
        </div>

        <!-- Scrollable Student List -->
        <div id="assign-students-list" class="max-h-[200px] overflow-y-auto space-y-1 pr-1 border border-slate-200/80 rounded-xl p-1.5 bg-slate-50/30 divide-y divide-slate-100">
          <!-- Dynamically populated rows -->
        </div>

        <!-- Loading State inside Student Selector -->
        <div id="assign-students-loading" class="hidden py-6 text-center">
          <div class="inline-block w-6 h-6 border-2 border-pink-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="text-xs text-slate-400 mt-2 font-medium">กำลังโหลดรายชื่อนักเรียน...</p>
        </div>

        <!-- Empty State -->
        <div id="assign-students-empty" class="hidden py-6 text-center bg-slate-50/60 rounded-xl border border-dashed border-slate-200">
          <svg class="w-8 h-8 text-slate-300 mx-auto mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
          </svg>
          <p id="assign-students-empty-text" class="text-xs font-bold text-slate-500">ยังไม่มีนักเรียนในคลาสนี้</p>
        </div>

        <!-- Error State with Retry Button -->
        <div id="assign-students-error" class="hidden py-4 text-center bg-red-50/50 rounded-xl border border-red-200">
          <p class="text-xs text-red-500 font-bold mb-2">ไม่สามารถโหลดรายชื่อนักเรียนได้ กรุณาลองใหม่อีกครั้ง</p>
          <button type="button" id="btn-retry-students" class="px-3 py-1 bg-pink-50 hover:bg-pink-100 text-pink-600 text-xs font-bold rounded-lg transition border border-pink-200">ลองใหม่</button>
        </div>
      </div>

      <div>
        <label class="block text-[13px] font-bold text-navy-900 mb-1.5">กำหนดส่ง (Due Date)</label>
        <input type="datetime-local" id="assign-due-date" name="due_date" class="w-full h-11 px-3.5 bg-white border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 focus:border-pink-500 outline-none">
      </div>

      <div id="assign-error" class="hidden p-3 rounded-xl bg-red-50 text-red-600 text-[13px] font-bold"></div>

      <div class="pt-3 flex justify-end gap-3 border-t border-[#f1f5f9]">
        <button type="button" id="btn-cancel-assign" class="h-10 px-5 rounded-xl border border-[#dce4ef] font-bold text-[13px] text-[#64748b] hover:bg-slate-50 transition">ยกเลิก</button>
        <button type="submit" id="btn-submit-assign" class="h-10 px-6 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-[13px] transition shadow-sm">ยืนยันการมอบหมาย</button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: MANUAL WORKSHEET CREATION / BASIC EDITOR -->
<!-- ========================================================================= -->
<div id="manual-create-modal" class="hidden fixed inset-0 z-50 bg-navy-950/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
  <div class="w-full max-w-[620px] bg-white rounded-2xl shadow-2xl overflow-hidden my-auto border border-[#e2e8f0]">
    <div class="px-6 py-4 border-b border-[#e8ecf2] flex items-center justify-between">
      <h2 class="text-[18px] font-bold text-navy-950">+ สร้างใบงานใหม่ (แบบกำหนดเอง)</h2>
      <button type="button" id="btn-close-manual" class="w-8 h-8 rounded-full hover:bg-slate-100 text-[22px] text-[#94a3b8] flex items-center justify-center leading-none">×</button>
    </div>

    <form id="manual-create-form" class="p-6 space-y-4">
      <div>
        <label class="block text-[13px] font-bold text-navy-900 mb-1.5">ชื่อใบงาน *</label>
        <input type="text" name="title" required placeholder="เช่น ทบทวนเรื่องกรด-เบส ม.5 ชุดที่ 1" class="w-full h-11 px-3.5 bg-white border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 focus:border-pink-500 outline-none">
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-[13px] font-bold text-navy-900 mb-1.5">วิชา *</label>
          <select name="subject" required class="w-full h-11 px-3.5 bg-white border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 focus:border-pink-500 outline-none">
            <option value="คณิตศาสตร์">คณิตศาสตร์</option>
            <option value="ฟิสิกส์">ฟิสิกส์</option>
            <option value="เคมี">เคมี</option>
            <option value="ชีววิทยา">ชีววิทยา</option>
            <option value="ภาษาอังกฤษ">ภาษาอังกฤษ</option>
            <option value="ภาษาไทย">ภาษาไทย</option>
            <option value="วิทยาศาสตร์">วิทยาศาสตร์</option>
            <option value="สังคมศึกษา">สังคมศึกษา</option>
          </select>
        </div>

        <div>
          <label class="block text-[13px] font-bold text-navy-900 mb-1.5">ระดับชั้น *</label>
          <select name="level" required class="w-full h-11 px-3.5 bg-white border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 focus:border-pink-500 outline-none">
            <option value="ป.1">ป.1</option><option value="ป.2">ป.2</option><option value="ป.3">ป.3</option>
            <option value="ป.4">ป.4</option><option value="ป.5">ป.5</option><option value="ป.6">ป.6</option>
            <option value="ม.1">ม.1</option><option value="ม.2">ม.2</option><option value="ม.3">ม.3</option>
            <option value="ม.4">ม.4</option><option value="ม.5" selected>ม.5</option><option value="ม.6">ม.6</option>
            <option value="A-Level">A-Level</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-[13px] font-bold text-navy-900 mb-1.5">ประเภทใบงาน</label>
          <select name="worksheet_type" class="w-full h-11 px-3.5 bg-white border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 focus:border-pink-500 outline-none">
            <option value="Worksheet">Worksheet</option>
            <option value="Practice">Practice</option>
            <option value="Homework">Homework</option>
            <option value="Pre-Test">Pre-Test</option>
            <option value="Post-Test">Post-Test</option>
            <option value="Quiz">Quiz</option>
            <option value="Mock Practice">Mock Practice</option>
          </select>
        </div>

        <div>
          <label class="block text-[13px] font-bold text-navy-900 mb-1.5">ระดับความยาก</label>
          <select name="difficulty" class="w-full h-11 px-3.5 bg-white border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 focus:border-pink-500 outline-none">
            <option value="easy">ง่าย</option>
            <option value="medium" selected>ปานกลาง</option>
            <option value="hard">ยาก</option>
            <option value="expert">ยากมาก</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-[13px] font-bold text-navy-900 mb-1.5">หัวข้อ / Concept (Topic)</label>
        <input type="text" name="topic" placeholder="เช่น การคำนวณ pH และสมดุลกรดเบส" class="w-full h-11 px-3.5 bg-white border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 focus:border-pink-500 outline-none">
      </div>

      <div>
        <label class="block text-[13px] font-bold text-navy-900 mb-1.5">คำอธิบายรายละเอียด</label>
        <textarea name="description" rows="3" placeholder="ระบุคำแนะนำ หรือคำชี้แจงสำหรับนักเรียน..." class="w-full p-3 bg-white border border-[#dce4ef] rounded-xl text-[14px] text-navy-950 focus:border-pink-500 outline-none"></textarea>
      </div>

      <div class="pt-3 flex justify-end gap-3 border-t border-[#f1f5f9]">
        <button type="button" id="btn-cancel-manual" class="h-10 px-5 rounded-xl border border-[#dce4ef] font-bold text-[13px] text-[#64748b] hover:bg-slate-50 transition">ยกเลิก</button>
        <button type="submit" class="h-10 px-6 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-[13px] transition shadow-sm">สร้างใบงานและไปหน้าแก้ไขโจทย์</button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- TOAST NOTIFICATION CONTAINER -->
<!-- ========================================================================= -->
<div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-2"></div>

</body>
</html>
