<?php
$currentPage = $currentPage ?? 'index.php';
?>
  <header class="sticky top-0 z-50 border-b border-white/5 bg-navy-950">
    <div class="container min-h-[78px] flex items-center gap-7">
      <a class="inline-flex items-center gap-3 min-w-[230px] max-[680px]:min-w-0" href="index.php" aria-label="Nextbeyond Compass หน้าแรก">
        <svg class="w-[38px] h-[38px] shrink-0" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <line x1="25" y1="32" x2="31" y2="8" stroke="#f54696" stroke-width="8" stroke-linecap="round" />
          <clipPath id="logo-clip">
            <rect x="0" y="9" width="40" height="22" />
          </clipPath>
          <g clip-path="url(#logo-clip)">
            <path d="M 8 36 L 15 4 L 27 36" fill="none" stroke="#ffffff" stroke-width="8.5" stroke-linejoin="miter" stroke-miterlimit="8" />
          </g>
        </svg>
        <span class="grid leading-[1.15]">
          <strong class="text-white text-[15px] tracking-[0.04em]">NEXTBEYOND</strong>
          <small class="text-[#8e9baf] text-[10px] font-bold tracking-[0.18em] max-[680px]:hidden">COMPASS</small>
        </span>
      </a>
      
      <button class="menu-toggle hidden max-[980px]:grid place-items-center w-[44px] h-[44px] ml-auto border border-white/20 rounded-xl text-white bg-transparent text-[21px]" type="button" data-menu-toggle aria-expanded="false" aria-label="เปิดเมนู">☰</button>
      
      <nav class="site-nav ml-auto flex items-center gap-6 max-[980px]:hidden max-[980px]:fixed max-[980px]:inset-x-0 max-[980px]:top-[78px] max-[980px]:bottom-auto max-[980px]:p-4 max-[980px]:pb-6 max-[980px]:border-b max-[980px]:border-line max-[980px]:flex-col max-[980px]:items-stretch max-[980px]:bg-navy-950 max-[980px]:shadow-default [&.open]:flex" data-site-nav aria-label="เมนูหลัก">
        <a class="<?= $currentPage === 'index.php' ? 'text-white border-b-2 border-pink-500' : 'text-[#a0aabf] border-b-2 border-transparent hover:text-white' ?> py-[28px] text-[14px] font-bold transition-colors max-[980px]:py-3" href="index.php">หน้าแรก</a>
        <a class="<?= $currentPage === 'courses.php' ? 'text-white border-b-2 border-pink-500' : 'text-[#a0aabf] border-b-2 border-transparent hover:text-white' ?> py-[28px] text-[14px] font-bold transition-colors max-[980px]:py-3" href="courses.php">คอร์สเรียน</a>
        <a class="<?= $currentPage === 'placement-test.php' ? 'text-white border-b-2 border-pink-500' : 'text-[#a0aabf] border-b-2 border-transparent hover:text-white' ?> py-[28px] text-[14px] font-bold transition-colors max-[980px]:py-3" href="placement-test.php">แบบวัดระดับ</a>
        <a class="<?= $currentPage === 'learning-path.php' ? 'text-white border-b-2 border-pink-500' : 'text-[#a0aabf] border-b-2 border-transparent hover:text-white' ?> py-[28px] text-[14px] font-bold transition-colors max-[980px]:py-3" href="learning-path.php">เส้นทางการเรียน</a>
        <a class="<?= $currentPage === 'free-learning.php' ? 'text-white border-b-2 border-pink-500' : 'text-[#a0aabf] border-b-2 border-transparent hover:text-white' ?> py-[28px] text-[14px] font-bold transition-colors max-[980px]:py-3" href="free-learning.php">เรียนฟรี</a>
        <a class="<?= $currentPage === 'articles.php' ? 'text-white border-b-2 border-pink-500' : 'text-[#a0aabf] border-b-2 border-transparent hover:text-white' ?> py-[28px] text-[14px] font-bold transition-colors max-[980px]:py-3" href="articles.php">บทความ</a>
        <a data-mobile-auth class="hidden max-[980px]:block text-[#a0aabf] hover:text-white py-3 text-[14px] font-bold" href="auth.php">เข้าสู่ระบบ</a>

      </nav>
      
      <div class="flex items-center gap-5 ml-4 max-[980px]:hidden">
        <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
          <a class="text-white hover:text-pink-400 text-[14px] font-bold transition-colors" href="#">คอร์สของฉัน</a>
          <a class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors relative" href="checkout.php" aria-label="ตะกร้าคอร์ส">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
            <span class="absolute -top-1 -right-1 w-4 h-4 bg-pink-500 rounded-full text-[10px] font-bold flex items-center justify-center">2</span>
          </a>
          <div class="relative group cursor-pointer ml-1">
            <div class="w-10 h-10 rounded-full bg-[#ff4a7a] text-white text-[14px] font-bold flex items-center justify-center border-2 border-transparent group-hover:border-white transition-all shadow-md">NK</div>
          </div>
        <?php else: ?>
          <a data-auth-link class="text-white hover:text-pink-400 text-[14px] font-bold transition-colors" href="auth.php">เข้าสู่ระบบ</a>
          <a class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors relative" href="checkout.php" aria-label="ตะกร้าคอร์ส">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
            <span class="absolute -top-1 -right-1 w-4 h-4 bg-pink-500 rounded-full text-[10px] font-bold flex items-center justify-center">2</span>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </header>
