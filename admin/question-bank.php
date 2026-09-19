<?php
require_once __DIR__ . '/includes/access.php';
$pageTitle='Question Bank (คลังข้อสอบ)'; $pageDesc='เก็บข้อสอบเป็นชุดและเปิดดูรายละเอียดคำถาม'; $currentPage='question-bank.php';
?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $pageTitle ?> - Next Beyond Admin</title><link rel="stylesheet" href="../assets/css/output.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="../assets/js/admin-guard.js"></script><script defer src="../assets/js/admin-question-bank.js?v=<?= rawurlencode((string) filemtime(__DIR__ . '/../assets/js/admin-question-bank.js')) ?>"></script></head>
<body class="bg-[#f4f7fb] text-navy-950 font-sans antialiased"><div class="min-h-screen flex"><?php include 'includes/sidebar.php'; ?>
<div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0"><?php include 'includes/topbar.php'; ?>
<main class="flex-1 p-8 max-[640px]:p-4">
<div class="mb-6 flex justify-between gap-4 max-[700px]:flex-col"><div class="flex gap-3"><a id="bank-back" href="question-bank.php" class="hidden h-11 px-4 items-center rounded-xl bg-white border border-[#dce4ef] font-bold">← กลับไปชุดข้อสอบ</a><a href="ai-exam-app/" class="h-11 px-5 rounded-xl bg-pink-500 text-white font-bold flex items-center">＋ สร้างด้วย AI</a></div><div class="flex gap-2"><input id="bank-search" type="search" placeholder="ค้นหาชุดข้อสอบ..." class="h-11 min-w-[280px] px-4 rounded-xl bg-white border border-[#dce4ef]"><select id="bank-subject" class="h-11 px-3 rounded-xl bg-white border border-[#dce4ef]"><option value="">ทุกวิชา</option></select></div></div>
<div class="grid grid-cols-3 gap-4 mb-6 max-[700px]:grid-cols-1"><div class="bg-white border rounded-2xl p-5"><div class="text-[12px] text-[#65738a] font-bold">ชุดข้อสอบ</div><div id="bank-set-count" class="text-[28px] font-black">0</div></div><div class="bg-white border rounded-2xl p-5"><div class="text-[12px] text-[#65738a] font-bold">คำถามในคลัง</div><div id="bank-question-count" class="text-[28px] font-black">0</div></div><div class="bg-white border rounded-2xl p-5"><div class="text-[12px] text-[#65738a] font-bold">สร้างโดย AI</div><div id="bank-ai-count" class="text-[28px] font-black">0</div></div></div>
<section id="sets-view"><div id="exam-sets" class="grid grid-cols-3 gap-5 max-[1200px]:grid-cols-2 max-[700px]:grid-cols-1"><div class="col-span-full bg-white rounded-[20px] border p-10 text-center text-[#65738a]">กำลังโหลด...</div></div></section>
<section id="questions-view" class="hidden"><div class="bg-white rounded-[20px] border overflow-hidden"><div class="px-6 py-5 border-b flex items-center justify-between gap-4 flex-wrap"><div><h2 id="set-title" class="text-[18px] font-bold"></h2><p id="set-meta" class="text-[12px] text-[#65738a]"></p></div><button type="button" id="bank-export-docx" class="h-10 px-4 rounded-xl bg-blue-50 text-blue-600 hover:bg-blue-100 font-bold text-[13px] flex items-center gap-2 transition"><svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>ส่งออก Word (.docx)</button></div><div class="overflow-x-auto"><table class="w-full text-left min-w-[850px]"><thead><tr class="bg-[#f8fafc] border-b"><th class="p-4">ลำดับ</th><th class="p-4">คำถาม / คำตอบ</th><th class="p-4">ทักษะ / ระดับ</th><th class="p-4">ตัวเลือก</th><th></th></tr></thead><tbody id="questions-tbody" class="divide-y"></tbody></table></div><div id="question-detail-count" class="p-4 border-t text-[13px] text-[#65738a]"></div></div></section>
</main></div></div>
<div id="question-modal" class="hidden fixed inset-0 z-50 bg-navy-950/55 p-4 items-center justify-center overflow-y-auto">
  <div class="w-full max-w-[820px] bg-white rounded-[24px] shadow-2xl my-auto overflow-hidden">
    <div class="px-6 py-5 border-b border-[#e8ecf2] flex items-center justify-between sticky top-0 bg-white z-10">
      <div><h2 class="text-[19px] font-bold">แก้ไขคำถาม</h2><p id="edit-question-number" class="text-[12px] text-[#65738a] mt-0.5"></p></div>
      <button type="button" data-close-question class="w-9 h-9 rounded-full hover:bg-[#f1f5f9] text-[25px] text-[#94a3b8]">×</button>
    </div>
    <form id="question-form" class="p-6 max-h-[calc(100vh-110px)] overflow-y-auto">
      <input name="id" type="hidden">
      <div><label class="qb-label">โจทย์คำถาม *</label><textarea name="questionText" rows="7" required class="qb-field h-auto py-3 leading-relaxed"></textarea></div>
      <div class="mt-5"><div class="flex items-center justify-between mb-2"><label class="qb-label mb-0">ตัวเลือกและคำตอบที่ถูกต้อง</label><span class="text-[11px] text-[#65738a]">เลือกวงกลมหน้าคำตอบที่ถูก</span></div><div id="edit-options" class="space-y-3"></div></div>
      <div class="grid grid-cols-2 gap-4 mt-5 max-[640px]:grid-cols-1">
        <div><label class="qb-label">ทักษะ</label><input name="skill" class="qb-field" placeholder="เช่น Grammar, Reading"></div>
        <div><label class="qb-label">ระดับความยาก</label><select name="difficulty" class="qb-field"><option value="">ไม่ระบุ</option><option value="easy">ง่าย</option><option value="medium">ปานกลาง</option><option value="hard">ยาก</option><option value="expert">ยากมาก</option></select></div>
      </div>
      <div class="mt-5"><label class="qb-label">คำอธิบายเฉลย</label><textarea name="explanation" rows="4" class="qb-field h-auto py-3"></textarea></div>
      <div id="question-form-error" class="hidden mt-4 p-3 rounded-xl bg-red-50 text-red-600 text-[13px] font-bold"></div>
      <div class="flex justify-end gap-3 mt-6 pt-5 border-t border-[#e8ecf2] sticky bottom-0 bg-white">
        <button type="button" data-close-question class="h-11 px-5 rounded-xl border border-[#dce4ef] font-bold">ยกเลิก</button>
        <button id="save-question" class="h-11 px-7 rounded-xl bg-pink-500 text-white font-bold shadow-[0_4px_12px_rgba(231,45,130,.25)]">บันทึกการแก้ไข</button>
      </div>
    </form>
  </div>
</div>
<style>.qb-label{display:block;font-size:13px;font-weight:700;color:#334155;margin-bottom:8px}.qb-field{width:100%;height:44px;padding-left:14px;padding-right:14px;border:1px solid #dce4ef;border-radius:12px;outline:none;background:#fff}.qb-field:focus{border-color:#f54696;box-shadow:0 0 0 3px rgba(245,70,150,.1)}</style>

<!-- Word Export Modal -->
<div id="export-docx-modal" class="hidden fixed inset-0 z-50 bg-navy-950/60 backdrop-blur-sm p-4 items-center justify-center overflow-y-auto">
  <div class="w-full max-w-[490px] bg-white rounded-[24px] shadow-2xl my-auto overflow-hidden border border-[#e2e8f0]">
    <div class="px-6 py-5 border-b border-[#e8ecf2] flex items-center justify-between bg-white">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-black text-[18px]">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <div>
          <h2 class="text-[18px] font-bold text-navy-950">ส่งออกข้อสอบเป็น Word</h2>
          <p id="export-exam-title" class="text-[12px] text-[#65738a] truncate max-w-[320px]"></p>
        </div>
      </div>
      <button type="button" id="close-export-modal" class="w-9 h-9 rounded-full hover:bg-[#f1f5f9] text-[24px] text-[#94a3b8] flex items-center justify-center leading-none">×</button>
    </div>
    <form id="export-docx-form" class="p-6">
      <input type="hidden" id="export-exam-id" name="examId">

      <!-- Document Type Selection -->
      <div class="mb-5">
        <label class="block text-[13px] font-bold text-[#1e293b] mb-2.5">ประเภทเอกสาร</label>
        <div class="grid grid-cols-2 gap-3">
          <label class="export-type-card flex items-start gap-3 p-3.5 border border-[#dce4ef] rounded-xl cursor-pointer hover:border-blue-400 transition has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50/50">
            <input type="radio" name="docType" value="student" checked class="mt-0.5 text-blue-600 focus:ring-blue-500">
            <div>
              <div class="text-[13px] font-bold text-navy-950">สำหรับนักเรียน</div>
              <div class="text-[11px] text-[#64748b] mt-0.5">มีโจทย์ ตัวเลือก และรูปประกอบ (ไม่มีเฉลย)</div>
            </div>
          </label>
          <label class="export-type-card flex items-start gap-3 p-3.5 border border-[#dce4ef] rounded-xl cursor-pointer hover:border-blue-400 transition has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50/50">
            <input type="radio" name="docType" value="teacher" class="mt-0.5 text-blue-600 focus:ring-blue-500">
            <div>
              <div class="text-[13px] font-bold text-navy-950">สำหรับครู</div>
              <div class="text-[11px] text-[#64748b] mt-0.5">แนบตารางเฉลยและคำอธิบายท้ายเล่ม</div>
            </div>
          </label>
        </div>
      </div>

      <!-- Options -->
      <div class="mb-5">
        <label class="block text-[13px] font-bold text-[#1e293b] mb-2.5">ตัวเลือก</label>
        <div class="space-y-2.5 bg-[#f8fafc] p-4 rounded-xl border border-[#edf2f7]">
          <label class="flex items-center gap-2.5 cursor-pointer text-[13px] text-[#334155] font-medium">
            <input type="checkbox" id="opt-show-title" checked class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500">
            <span>แสดงชื่อข้อสอบ</span>
          </label>
          <label class="flex items-center gap-2.5 cursor-pointer text-[13px] text-[#334155] font-medium">
            <input type="checkbox" id="opt-show-diagrams" checked class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500">
            <span>แสดงรูปประกอบ</span>
          </label>
          <label class="flex items-center gap-2.5 cursor-pointer text-[13px] text-[#334155] font-medium" id="lbl-show-answers">
            <input type="checkbox" id="opt-show-answers" disabled class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50">
            <span>แสดงเฉลย</span>
          </label>
          <label class="flex items-center gap-2.5 cursor-pointer text-[13px] text-[#334155] font-medium" id="lbl-show-explanations">
            <input type="checkbox" id="opt-show-explanations" disabled class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50">
            <span>แสดงคำอธิบาย</span>
          </label>
        </div>
      </div>

      <!-- Feedback Banner -->
      <div id="export-status-box" class="hidden mb-4 p-3 rounded-xl text-[13px] font-bold"></div>

      <!-- Footer Buttons -->
      <div class="flex justify-end gap-3 pt-4 border-t border-[#e8ecf2]">
        <button type="button" id="btn-cancel-export" class="h-11 px-5 rounded-xl border border-[#dce4ef] font-bold text-[13px] text-[#475569] hover:bg-slate-50 transition">
          ยกเลิก
        </button>
        <button type="submit" id="btn-submit-export" class="h-11 px-6 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-[13px] shadow-[0_4px_12px_rgba(37,99,235,.25)] flex items-center justify-center gap-2 transition disabled:opacity-60 disabled:cursor-wait">
          <span id="export-btn-icon">📄</span>
          <span id="export-btn-text">สร้างไฟล์ Word</span>
        </button>
      </div>
    </form>
  </div>
</div>
</body></html>
