<?php
$pageTitle = "คอร์สเรียน | Nextbeyond Compass";
$pageDesc = "รวมคอร์ส Nextbeyond Compass";
$currentPage = 'courses.php';
include 'includes/head.php';
include 'includes/header.php';
?>

<main id="main">
  <section class="relative pt-[60px] pb-[20px] text-center overflow-hidden bg-[#f6f8fc]">
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[300px] bg-gradient-to-b from-gray-200/50 to-transparent blur-[80px] rounded-full pointer-events-none"></div>
    <div class="container relative z-10 flex flex-col items-center">
      <p class="text-[11px] font-black tracking-[0.2em] text-[#65738a] mb-4 uppercase">NEXTBEYOND COMPASS</p>
      <h1 class="text-navy-950 text-[clamp(36px,5vw,48px)] font-black mb-4 tracking-[-0.02em] leading-[1.1]">COURSE CATALOG</h1>
      <p class="text-[#65738a] text-[16px]">ค้นหาคอร์สที่เหมาะกับคุณ</p>
    </div>
  </section>

  <section class="py-12 bg-[#f6f8fc]">
    <div class="container max-w-[820px]">
      <div class="min-h-[300px] px-8 py-14 flex flex-col items-center justify-center text-center bg-white border border-[#e8ecf2] rounded-[24px] shadow-[0_8px_28px_rgba(15,42,83,0.05)] max-[640px]:min-h-[250px] max-[640px]:px-5">
        <span class="w-16 h-16 mb-5 rounded-2xl bg-[#f1f5ff] text-[#2369dd] flex items-center justify-center" aria-hidden="true">
          <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
          </svg>
        </span>
        <h2 class="text-[24px] font-bold text-navy-950 mb-2 max-[640px]:text-[21px]">กำลังเตรียมคอร์สเรียน</h2>
        <p class="max-w-[480px] text-[15px] leading-7 text-[#65738a] m-0">คอร์สจริงจะเปิดให้เลือกที่หน้านี้เมื่อเนื้อหาพร้อมเผยแพร่</p>
      </div>
    </div>
  </section>

  <section class="py-[70px] bg-surface-soft">
    <div class="container">
      <div class="p-[42px] rounded-xl flex justify-between items-center gap-[35px] bg-white shadow-default max-[680px]:flex-col max-[680px]:items-stretch max-[680px]:p-6">
        <div>
          <p class="eyebrow">NEED A RECOMMENDATION?</p>
          <h2 class="text-[clamp(30px,4vw,47px)] tracking-[-0.035em] mb-4">ไม่แน่ใจว่าคอร์สไหนเหมาะ?</h2>
          <p class="text-muted m-0">เริ่มจากแบบวัดระดับ แล้วใช้ผลเพื่อเลือกแผนการเรียน</p>
        </div>
        <a class="btn btn-primary whitespace-nowrap" href="placement-test.php">วัดระดับก่อนเรียน →</a>
      </div>
    </div>
  </section>
</main>

<?php include 'includes/footer.php'; ?>
