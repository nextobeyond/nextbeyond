<?php $adminLinkPrefix = $adminLinkPrefix ?? ''; ?>
<header class="sticky top-0 z-30 flex items-center justify-between h-[72px] px-6 bg-white border-b border-[#e8ecf2] shadow-[0_4px_24px_rgba(15,42,83,0.02)]">
  <div class="flex items-center gap-4">
    <button type="button" aria-label="เปิดหรือปิดเมนูแอดมิน" aria-controls="adminSidebar" class="w-10 h-10 flex items-center justify-center rounded-[10px] border border-[#e8ecf2] text-navy-950 hidden max-[1024px]:flex hover:bg-[#f8fafc] transition-colors" onclick="document.getElementById('adminSidebar').classList.toggle('max-[1024px]:-translate-x-full'); document.getElementById('adminOverlay').classList.toggle('hidden');">
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div>
      <h2 class="text-[20px] font-bold text-navy-950 leading-tight max-[640px]:text-[17px]"><?= $pageTitle ?? 'Admin' ?></h2>
      <?php if(isset($pageDesc)): ?><p class="text-[13px] text-[#65738a] mt-0.5 max-[640px]:hidden"><?= $pageDesc ?></p><?php endif; ?>
    </div>
  </div>
  <div class="flex items-center gap-4">
    <a href="<?= htmlspecialchars($adminLinkPrefix, ENT_QUOTES, 'UTF-8') ?>../student/" target="_blank" class="text-[13px] font-bold text-pink-500 hover:text-pink-600 hidden sm:block transition-colors">ดูหน้า Student ↗</a>
    <a href="<?= htmlspecialchars($adminLinkPrefix, ENT_QUOTES, 'UTF-8') ?>../index.php" target="_blank" class="text-[13px] font-bold text-[#65738a] hover:text-navy-950 hidden sm:block transition-colors">เปิดเว็บไซต์ ↗</a>
    <div class="w-[1px] h-6 bg-[#e8ecf2] hidden sm:block"></div>
    <div class="relative group cursor-pointer">
      <div class="w-[42px] h-[42px] rounded-full bg-pink-100 text-pink-500 font-bold flex items-center justify-center shadow-sm border border-pink-200">AD</div>
      <div class="absolute right-0 top-full mt-2 w-48 bg-white rounded-xl shadow-lg border border-[#e8ecf2] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200">
        <div class="p-3 border-b border-[#e8ecf2]">
          <div class="font-bold text-navy-950 text-[14px]"><?= htmlspecialchars($consoleUser['first_name'] . ' ' . $consoleUser['last_name']) ?></div>
          <div class="text-[12px] text-[#65738a]"><?= $consoleUser['role'] === 'teacher' ? 'Teacher' : 'Administrator' ?></div>
        </div>
        <div class="p-2">
          <a href="#" class="block px-3 py-2 rounded-lg text-[13px] text-navy-950 hover:bg-[#f8fafc] transition-colors">ตั้งค่าโปรไฟล์</a>
          <a href="<?= htmlspecialchars($adminLinkPrefix, ENT_QUOTES, 'UTF-8') ?>../index.php" class="block px-3 py-2 rounded-lg text-[13px] text-red-500 hover:bg-red-50 transition-colors">ออกจากระบบ</a>
        </div>
      </div>
    </div>
  </div>
</header>
<!-- Overlay for mobile sidebar -->
<div id="adminOverlay" class="fixed inset-0 bg-navy-950/20 backdrop-blur-sm z-30 hidden min-[1025px]:hidden transition-opacity" onclick="document.getElementById('adminSidebar').classList.add('max-[1024px]:-translate-x-full'); this.classList.add('hidden');"></div>
