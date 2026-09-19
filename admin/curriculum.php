<?php
require_once __DIR__ . '/includes/access.php';
$pageTitle='จัดการบทเรียน (Curriculum)'; $pageDesc='จัดการเนื้อหาและลำดับบทเรียนของแต่ละคอร์ส'; $currentPage='curriculum.php';
?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $pageTitle ?> - Next Beyond Admin</title><link rel="stylesheet" href="../assets/css/output.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="../assets/js/admin-guard.js"></script><script defer src="../assets/js/admin-curriculum.js"></script></head>
<body class="bg-[#f4f7fb] text-navy-950 font-sans antialiased"><div class="min-h-screen flex"><?php include 'includes/sidebar.php'; ?>
<div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0"><?php include 'includes/topbar.php'; ?>
<main class="flex-1 p-8 max-[640px]:p-4">
  <div class="mb-6 flex justify-between gap-4 max-[700px]:flex-col">
    <select id="curriculum-course" class="h-11 px-4 rounded-xl bg-white border border-[#dce4ef] min-w-[360px] max-[700px]:min-w-0"><option value="">กำลังโหลดคอร์ส...</option></select>
    <div class="flex gap-3">
      <a id="student-preview" href="#" target="_blank" class="hidden h-11 px-5 rounded-xl bg-white border border-[#dce4ef] text-[14px] font-bold items-center">ดูเนื้อหา</a>
      <button id="btn-open-worksheet-picker" type="button" disabled class="h-11 px-4 rounded-xl border border-[#dce4ef] bg-white hover:bg-slate-50 disabled:opacity-50 text-navy-900 text-[14px] font-bold flex items-center gap-2 transition">
        <svg class="w-4 h-4 text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        <span>+ เพิ่มใบงาน</span>
      </button>
      <button id="add-lesson" disabled class="h-11 px-5 rounded-xl bg-pink-500 disabled:bg-[#cbd5e1] text-white text-[14px] font-bold">＋ เพิ่มบทเรียน</button>
    </div>
  </div>
  <div id="no-courses" class="hidden bg-white rounded-[20px] border border-[#e8ecf2] p-12 text-center"><h2 class="font-bold">ยังไม่มีคอร์สเรียน</h2><p class="text-[#65738a] text-[13px] mt-1 mb-5">สร้างคอร์สก่อนจึงจะเพิ่มบทเรียนได้</p><a href="courses.php" class="inline-flex h-10 px-5 items-center bg-pink-500 text-white rounded-xl font-bold">ไปหน้าคอร์สเรียน</a></div>
  <div id="curriculum-workspace" class="grid grid-cols-[360px_1fr] gap-6 max-[1024px]:grid-cols-1 items-start">
    <section class="bg-white rounded-[20px] border border-[#e8ecf2] overflow-hidden"><div class="px-5 py-4 border-b"><h3 class="font-bold text-[14px]">ลำดับบทเรียน</h3></div><div id="lessons-list" class="p-3 space-y-2"><div class="p-8 text-center text-[#65738a]">เลือกคอร์สเพื่อดูบทเรียน</div></div></section>
    <section id="lesson-editor" class="hidden bg-white rounded-[20px] border border-[#e8ecf2] p-7">
      <h2 id="editor-title" class="text-[20px] font-bold mb-6">เพิ่มบทเรียน</h2>
      <form id="lesson-form"><input name="id" type="hidden">
        <div class="space-y-5">
          <div><label class="label">ชื่อบทเรียน *</label><input name="title" required class="field"></div>
          <div class="grid grid-cols-2 gap-5 max-[640px]:grid-cols-1">
            <div><label class="label">ประเภทเนื้อหา</label><select name="contentType" class="field"><option value="video">วิดีโอ</option><option value="document">เอกสาร</option><option value="worksheet">ใบงาน (Worksheet)</option><option value="quiz">แบบทดสอบ</option><option value="live">เรียนสด</option></select></div>
            <div><label class="label">ระยะเวลา (นาที)</label><input name="durationMinutes" type="number" min="0" class="field"></div>
          </div>
          <div><label class="label">URL เนื้อหา</label><input name="contentUrl" type="text" class="field" placeholder="https://..."><p class="text-[11px] text-[#94a3b8] mt-1">ใส่ลิงก์วิดีโอ ใบงาน เอกสาร ห้องเรียนสด หรือหน้าแบบทดสอบ</p></div>
          <label class="flex gap-2 text-[13px] font-bold"><input name="isPreview" type="checkbox"> อนุญาตให้ผู้เรียนดูตัวอย่างฟรี</label>
        </div>
        <div id="lesson-error" class="hidden mt-4 p-3 bg-red-50 text-red-600 rounded-xl text-[13px] font-bold"></div>
        <div class="flex justify-between mt-7 pt-5 border-t"><button id="delete-lesson" type="button" class="hidden h-10 px-4 text-red-500 font-bold">ลบบทเรียน</button><div class="ml-auto flex gap-3"><button id="cancel-editor" type="button" class="h-10 px-5 rounded-xl border font-bold">ยกเลิก</button><button id="save-lesson" class="h-10 px-6 rounded-xl bg-pink-500 text-white font-bold">บันทึกบทเรียน</button></div></div>
      </form>
    </section>
  </div>
</main></div></div>

<!-- WORKSHEET PICKER MODAL (Sections 18 & 19) -->
<div id="worksheet-picker-modal" class="hidden fixed inset-0 z-50 bg-navy-950/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
  <div class="w-full max-w-[760px] bg-white rounded-2xl shadow-2xl overflow-hidden my-auto border border-[#e2e8f0] flex flex-col max-h-[85vh]">
    <div class="px-6 py-4 border-b border-[#e8ecf2] flex items-center justify-between">
      <div>
        <h2 class="text-[18px] font-black text-navy-950">เลือกใบงานจากคลัง (Worksheet Picker)</h2>
        <p class="text-[12px] text-[#64748b]">ค้นหาและเลือกใบงานที่ต้องการแนบในคอร์สเรียนนี้</p>
      </div>
      <button type="button" id="btn-close-picker" class="w-8 h-8 rounded-full hover:bg-slate-100 text-[22px] text-[#94a3b8] flex items-center justify-center leading-none">×</button>
    </div>

    <!-- Quick Action / Switcher bar -->
    <div class="px-6 py-3 bg-[#f8fafc] border-b border-[#e8ecf2] flex items-center justify-between gap-3 flex-wrap">
      <div class="flex items-center gap-2 text-[12px]">
        <span class="text-[#64748b] font-bold">ตัวเลือกอื่น:</span>
        <a href="ai-worksheet.php" target="_blank" class="text-pink-600 hover:text-pink-700 font-bold flex items-center gap-1">
          <span>✨ สร้างด้วย AI</span> ↗
        </a>
        <span>·</span>
        <a href="worksheets.php" target="_blank" class="text-navy-900 hover:text-pink-600 font-bold flex items-center gap-1">
          <span>+ สร้างเอง</span> ↗
        </a>
      </div>

      <div class="flex items-center gap-2">
        <label class="text-[12px] font-bold text-[#64748b]">วิธีแนบ:</label>
        <select id="picker-attach-mode" class="h-8 px-2.5 bg-white border border-[#dce4ef] rounded-lg text-[12px] text-navy-950 font-bold outline-none">
          <option value="original">ใช้ต้นฉบับ</option>
          <option value="duplicate">สร้างสำเนาสำหรับคลาสนี้</option>
        </select>
      </div>
    </div>

    <!-- Search & Filter Row -->
    <div class="p-4 border-b border-[#e8ecf2] flex gap-2">
      <input type="search" id="picker-search-input" placeholder="ค้นหาใบงาน หัวข้อ..." class="flex-1 h-10 px-3.5 bg-[#f8fafc] border border-[#dce4ef] rounded-xl text-[13px] outline-none focus:border-pink-500">
      <select id="picker-filter-subject" class="h-10 px-3 bg-[#f8fafc] border border-[#dce4ef] rounded-xl text-[13px] outline-none">
        <option value="">ทุกวิชา</option>
        <option value="คณิตศาสตร์">คณิตศาสตร์</option>
        <option value="ฟิสิกส์">ฟิสิกส์</option>
        <option value="เคมี">เคมี</option>
        <option value="ชีววิทยา">ชีววิทยา</option>
        <option value="ภาษาอังกฤษ">ภาษาอังกฤษ</option>
        <option value="ภาษาไทย">ภาษาไทย</option>
      </select>
    </div>

    <!-- Picker Worksheets List -->
    <div id="picker-results-container" class="p-4 overflow-y-auto divide-y divide-[#f1f5f9] flex-1">
      <div class="p-8 text-center text-[#64748b]">กำลังโหลดใบงานจากคลัง...</div>
    </div>
  </div>
</div>

<style>.label{display:block;font-size:13px;font-weight:700;color:#65738a;margin-bottom:8px}.field{width:100%;height:44px;padding:0 14px;border:1px solid #dce4ef;border-radius:12px;outline:none}.field:focus{border-color:#f54696}</style>
</body></html>
