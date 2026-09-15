  <footer class="pt-14 pb-6 text-[#9db0c8] bg-navy-950">
    <div class="container">
      <style>
        .footer-grid { display: grid; grid-template-columns: 1.4fr 0.8fr 0.8fr 0.8fr; gap: 55px; }
        @media (max-width: 980px) { .footer-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 680px) { .footer-grid { grid-template-columns: 1fr; gap: 28px; } }
      </style>
      <div class="footer-grid">
        <div class="max-[980px]:col-span-2 max-[680px]:col-span-1">
          <a class="inline-flex items-center gap-3 min-w-[230px] max-[680px]:min-w-0" href="index.php" aria-label="Nextbeyond Compass">
            <svg class="w-[38px] h-[38px] shrink-0" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <line x1="25" y1="32" x2="31" y2="8" stroke="#f54696" stroke-width="8" stroke-linecap="round" />
              <clipPath id="logo-clip-footer">
                <rect x="0" y="9" width="40" height="22" />
              </clipPath>
              <g clip-path="url(#logo-clip-footer)">
                <path d="M 8 36 L 15 4 L 27 36" fill="none" stroke="#ffffff" stroke-width="8.5" stroke-linejoin="miter" stroke-miterlimit="8" />
              </g>
            </svg>
            <span class="grid leading-[1.15]">
              <strong class="text-white text-[15px] tracking-[0.04em]">NEXTBEYOND</strong>
              <small class="text-[#8e9baf] text-[10px] font-bold tracking-[0.18em] max-[680px]:hidden">COMPASS</small>
            </span>
          </a>
          <p class="max-w-[450px] mt-[18px]">พื้นที่เรียนรู้ที่ช่วยให้ผู้เรียนเข้าใจพื้นฐาน วางแผนอย่างเป็นระบบ และเติบโตตามเป้าหมายของตัวเอง</p>
        </div>
        <div class="[&_strong]:block [&_strong]:mb-3 [&_strong]:text-white [&_a]:block [&_a]:mb-[7px] [&_a:hover]:text-white">
          <strong>การเรียนรู้</strong>
          <a href="courses.php">คอร์สทั้งหมด</a>
          <a href="learning-path.php">เส้นทางการเรียน</a>
          <a href="free-learning.php">เรียนฟรี</a>
        </div>
        <div class="[&_strong]:block [&_strong]:mb-3 [&_strong]:text-white [&_a]:block [&_a]:mb-[7px] [&_a:hover]:text-white">
          <strong>เครื่องมือและบทความ</strong>
          <a href="placement-test.php">แบบวัดระดับ</a>
          <a href="exam-guides.php">แนวข้อสอบ</a>
          <a href="articles.php">บทความ</a>
        </div>
        <div class="[&_strong]:block [&_strong]:mb-3 [&_strong]:text-white [&_a]:block [&_a]:mb-[7px] [&_a:hover]:text-white">
          <strong>ช่วยเหลือ</strong>
          <a href="contact-us.php">ติดต่อเรา</a>
          <a href="faq.php">คำถามที่พบบ่อย</a>
        </div>
      </div>
      <div class="mt-[38px] pt-5 border-t border-white/10 text-[#7287a2] text-[13px]">
        © <span data-current-year></span> Nextbeyond Compass. PHP & Tailwind version.
      </div>
    </div>
  </footer>
  <div class="toast fixed right-[22px] bottom-[22px] z-[100] px-[18px] py-[13px] rounded-xl text-white bg-navy-950 shadow-default translate-y-[30px] opacity-0 pointer-events-none transition-all duration-250 [&.show]:translate-y-0 [&.show]:opacity-100" data-toast role="status" aria-live="polite"></div>
</body>
</html>
