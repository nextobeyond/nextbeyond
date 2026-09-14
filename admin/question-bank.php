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
<section id="questions-view" class="hidden"><div class="bg-white rounded-[20px] border overflow-hidden"><div class="px-6 py-5 border-b"><h2 id="set-title" class="text-[18px] font-bold"></h2><p id="set-meta" class="text-[12px] text-[#65738a]"></p></div><div class="overflow-x-auto"><table class="w-full text-left min-w-[850px]"><thead><tr class="bg-[#f8fafc] border-b"><th class="p-4">ลำดับ</th><th class="p-4">คำถาม / คำตอบ</th><th class="p-4">ทักษะ / ระดับ</th><th class="p-4">ตัวเลือก</th><th></th></tr></thead><tbody id="questions-tbody" class="divide-y"></tbody></table></div><div id="question-detail-count" class="p-4 border-t text-[13px] text-[#65738a]"></div></div></section>
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
</body></html>
