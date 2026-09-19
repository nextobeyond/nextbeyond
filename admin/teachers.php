<?php
require_once __DIR__ . '/includes/access.php';
$pageTitle = 'คุณครูผู้สอน';
$pageDesc = 'จัดการรายชื่อคุณครู บัญชี และคอร์สที่สอน';
$currentPage = 'teachers.php';
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> - Next Beyond Admin</title>
  <link rel="stylesheet" href="../assets/css/output.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="../assets/js/admin-guard.js"></script>
  <script defer src="../assets/js/admin-teachers.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin-teachers.js') ?>"></script>
</head>
<body class="bg-[#f4f7fb] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0">
    <?php include 'includes/topbar.php'; ?>
    <main class="flex-1 p-8 max-[640px]:p-4">
      <div class="mb-6 flex items-center justify-between gap-4 max-[640px]:flex-col max-[640px]:items-stretch">
        <button id="add-teacher" class="h-10 px-5 rounded-xl bg-pink-500 text-white text-[14px] font-bold shadow-[0_4px_12px_rgba(231,45,130,.3)] flex items-center justify-center gap-2">＋ เพิ่มคุณครู</button>
        <div class="relative min-w-[280px] max-[640px]:min-w-0">
          <input id="teachers-search" type="search" placeholder="ค้นหาชื่อ อีเมล หรือวิชา..." class="w-full h-11 pl-10 pr-4 rounded-xl bg-white border border-[#dce4ef] outline-none focus:border-pink-500 text-[14px]">
          <svg class="w-4 h-4 text-[#94a3b8] absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
      </div>
      <div class="bg-white rounded-[20px] border border-[#e8ecf2] overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left min-w-[850px]">
            <thead><tr class="bg-[#f8fafc] border-b border-[#e8ecf2]">
              <th class="px-6 py-3 text-[12px] font-black text-[#65738a] uppercase">ข้อมูลคุณครู</th>
              <th class="px-4 py-3 text-[12px] font-black text-[#65738a] uppercase">วิชาที่สอน</th>
              <th class="px-4 py-3 text-[12px] font-black text-[#65738a] uppercase text-center">คอร์ส Active</th>
              <th class="px-4 py-3 text-[12px] font-black text-[#65738a] uppercase">สถานะบัญชี</th>
              <th class="px-4 py-3"></th>
            </tr></thead>
            <tbody id="teachers-tbody" class="divide-y divide-[#e8ecf2]"><tr><td colspan="5" class="p-10 text-center text-[#65738a]">กำลังโหลดข้อมูล...</td></tr></tbody>
          </table>
        </div>
        <div id="teachers-count" class="p-4 border-t border-[#e8ecf2] text-[13px] text-[#65738a]">0 รายการ</div>
      </div>
    </main>
  </div>
</div>

<div id="teacher-modal" class="hidden fixed inset-0 z-50 bg-navy-950/50 p-4 items-center justify-center">
  <div class="w-full max-w-[560px] bg-white rounded-[22px] shadow-2xl">
    <div class="px-6 py-5 border-b border-[#e8ecf2] flex items-center justify-between">
      <h2 id="modal-title" class="text-[18px] font-bold">เพิ่มคุณครูผู้สอน</h2>
      <button type="button" data-close-modal class="text-[24px] text-[#94a3b8] hover:text-navy-950 leading-none">×</button>
    </div>
    <form id="teacher-form" class="p-6">
      <input type="hidden" name="id" id="teacher-id" value="">
      <div class="grid grid-cols-2 gap-4 max-[560px]:grid-cols-1">
        <div>
          <label class="block text-[13px] font-bold mb-1.5">ชื่อ</label>
          <input name="firstName" placeholder="ชื่อ (หรือใส่แค่ชื่อเล่น)" class="w-full h-11 px-3.5 border border-[#dce4ef] rounded-xl outline-none focus:border-pink-500">
        </div>
        <div>
          <label class="block text-[13px] font-bold mb-1.5">นามสกุล</label>
          <input name="lastName" placeholder="เว้นว่างได้" class="w-full h-11 px-3.5 border border-[#dce4ef] rounded-xl outline-none focus:border-pink-500">
        </div>
        <div>
          <label class="block text-[13px] font-bold mb-1.5">ชื่อเล่น</label>
          <input name="nickname" placeholder="เช่น ครูบิ๊ก, ครูปอ" class="w-full h-11 px-3.5 border border-[#dce4ef] rounded-xl outline-none focus:border-pink-500">
        </div>
        <div>
          <label class="block text-[13px] font-bold mb-1.5">เบอร์โทรศัพท์</label>
          <input name="phone" type="tel" placeholder="08x-xxx-xxxx" class="w-full h-11 px-3.5 border border-[#dce4ef] rounded-xl outline-none focus:border-pink-500 text-[14px]">
        </div>
        <div>
          <label class="block text-[13px] font-bold mb-1.5">อีเมล</label>
          <input name="email" type="email" placeholder="teacher@example.com (เว้นว่างได้)" class="w-full h-11 px-3.5 border border-[#dce4ef] rounded-xl outline-none focus:border-pink-500 text-[14px]">
        </div>
        <div>
          <label id="password-label" class="block text-[13px] font-bold mb-1.5">รหัสผ่านเริ่มต้น</label>
          <input name="password" id="teacher-password" type="password" class="w-full h-11 px-3.5 border border-[#dce4ef] rounded-xl outline-none focus:border-pink-500 text-[14px]" placeholder="เว้นว่างได้ (ค่าเริ่มต้น: 12345678)">
        </div>
        <div class="col-span-2 max-[560px]:col-span-1">
          <label class="block text-[13px] font-bold mb-1.5">วิชาที่สอน <span class="text-[12px] text-[#65738a] font-normal">(เลือกได้มากกว่า 1 วิชา)</span></label>
          <input type="hidden" name="subjects" id="teacher-subjects-val" value="">
          <div id="subject-tags-box" class="min-h-[46px] p-2 border border-[#dce4ef] rounded-xl bg-white flex flex-wrap items-center gap-1.5 focus-within:border-pink-500 transition-colors">
            <div id="selected-subject-chips" class="flex flex-wrap items-center gap-1.5"></div>
            <input id="custom-subject-input" type="text" placeholder="พิมพ์ชื่อวิชาแล้วกด Enter หรือเลือกด้านล่าง..." class="flex-1 min-w-[180px] h-7 px-1.5 text-[13px] outline-none bg-transparent text-navy-950 placeholder-[#94a3b8]">
          </div>
          <div class="mt-2.5">
            <div class="text-[11px] font-bold text-[#65738a] mb-1.5 flex items-center justify-between">
              <span>คลิกเพื่อเลือกวิชาที่สอน:</span>
              <span class="text-[11px] font-normal text-[#94a3b8]">คลิกซ้ำเพื่อยกเลิก</span>
            </div>
            <div id="quick-subject-pills" class="flex flex-wrap gap-1.5"></div>
          </div>
        </div>
      </div>
      <div id="teacher-form-error" class="hidden mt-4 rounded-xl bg-red-50 text-red-600 px-4 py-3 text-[13px] font-bold"></div>
      <div class="flex justify-end gap-3 mt-6 pt-5 border-t border-[#e8ecf2]">
        <button type="button" data-close-modal class="h-10 px-5 rounded-xl border border-[#dce4ef] font-bold text-[14px]">ยกเลิก</button>
        <button id="save-teacher" class="h-10 px-6 rounded-xl bg-pink-500 text-white font-bold text-[14px]">บันทึกคุณครู</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
