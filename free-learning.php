<?php
$pageTitle = "เรียนฟรี | Nextbeyond Compass";
$pageDesc = "บทเรียนฟรี Nextbeyond Compass";
$currentPage = 'free-learning.php';
include 'includes/head.php';
include 'includes/header.php';
?>

<main id="main" data-filter-section>
  <section class="pt-[95px] pb-[85px] text-white bg-gradient-to-br from-navy-950 to-navy-800">
    <div class="container">
      <p class="eyebrow light">FREE LEARNING HUB</p>
      <h1 class="max-w-[850px] text-white text-[clamp(39px,5.5vw,66px)] mb-[22px] tracking-[-0.045em]"><span class="block">เริ่มเรียนได้เลย</span><em class="text-pink-500 not-italic">ทีละบทอย่างมีเป้าหมาย</em></h1>
      <p class="max-w-[720px] text-[#bfcee2] text-[18px]">บทเรียนสั้น แบบฝึก และสรุปหัวข้อสำคัญสำหรับทดลองเรียนหรือทบทวนก่อนเข้าเรียนจริง</p>
    </div>
  </section>

  <section class="py-[90px] bg-surface-soft max-[680px]:py-[68px]">
    <div class="container">
      <div class="relative z-10 -mt-[42px] mb-[45px] mx-auto p-[18px] border border-line rounded-[18px] flex items-center gap-3.5 bg-white shadow-default max-[680px]:flex-col max-[680px]:items-stretch">
        <label class="relative flex-1 min-w-[250px]">
          <span class="absolute left-[15px] top-[11px] text-muted">⌕</span>
          <input class="w-full h-[48px] pl-[43px] pr-4 border border-line rounded-[13px] text-ink bg-surface-soft outline-none focus:border-primary-500 focus:shadow-[0_0_0_3px_rgba(57,129,245,0.14)] transition-all" type="search" data-search placeholder="ค้นหาบทเรียนหรือทักษะ..." aria-label="ค้นหาบทเรียน">
        </label>
        <div class="flex flex-wrap gap-2 max-[680px]:w-full">
          <button class="min-h-[42px] px-[13px] py-2 border border-line rounded-[11px] text-[#56657b] bg-white font-[750] transition-colors [&.active]:border-primary-600 [&.active]:text-primary-600 [&.active]:bg-primary-100 hover:border-primary-600 hover:text-primary-600 hover:bg-primary-100 max-[680px]:flex-1 active" type="button" data-filter="all">ทั้งหมด</button>
          <button class="min-h-[42px] px-[13px] py-2 border border-line rounded-[11px] text-[#56657b] bg-white font-[750] transition-colors [&.active]:border-primary-600 [&.active]:text-primary-600 [&.active]:bg-primary-100 hover:border-primary-600 hover:text-primary-600 hover:bg-primary-100 max-[680px]:flex-1" type="button" data-filter="english">อังกฤษ</button>
          <button class="min-h-[42px] px-[13px] py-2 border border-line rounded-[11px] text-[#56657b] bg-white font-[750] transition-colors [&.active]:border-primary-600 [&.active]:text-primary-600 [&.active]:bg-primary-100 hover:border-primary-600 hover:text-primary-600 hover:bg-primary-100 max-[680px]:flex-1" type="button" data-filter="biology">ชีววิทยา</button>
          <button class="min-h-[42px] px-[13px] py-2 border border-line rounded-[11px] text-[#56657b] bg-white font-[750] transition-colors [&.active]:border-primary-600 [&.active]:text-primary-600 [&.active]:bg-primary-100 hover:border-primary-600 hover:text-primary-600 hover:bg-primary-100 max-[680px]:flex-1" type="button" data-filter="chemistry">เคมี</button>
        </div>
      </div>
      
      <p class="mb-5 text-muted font-bold" data-result-count></p>
      
      <div class="grid grid-cols-3 gap-[22px] max-[980px]:grid-cols-2 max-[680px]:grid-cols-1">
        
        <article class="border border-line rounded-lg overflow-hidden bg-white shadow-soft transition-transform hover:-translate-y-1 hover:shadow-default [&[hidden]]:!hidden" data-filter-card data-category="english" data-search="ภาษาอังกฤษ Daily English Conversation สนทนา">
          <div class="min-h-[155px] p-[24px] flex items-end justify-between text-white bg-gradient-to-br from-pink-500 to-[#933a9e]">
            <span class="text-[48px] opacity-90">▶</span><span>12 นาที</span>
          </div>
          <div class="p-[24px]">
            <span class="inline-flex items-center px-2.5 py-[5px] rounded-full text-pink-600 bg-pink-100 text-[12px] font-extrabold mb-3">ภาษาอังกฤษ</span>
            <h3 class="text-[21px] mb-2.5">Daily English: Asking for Help</h3>
            <p class="text-muted">ประโยคสำคัญสำหรับขอความช่วยเหลือและขอให้พูดซ้ำอย่างสุภาพ</p>
            <div class="flex flex-wrap gap-x-4 gap-y-2 my-[18px] text-muted text-[13px]">วิดีโอ · Beginner</div>
            <a class="text-primary-600 font-[850]" href="placement-test.php">ทำแบบฝึกท้ายบท →</a>
          </div>
        </article>

        <article class="border border-line rounded-lg overflow-hidden bg-white shadow-soft transition-transform hover:-translate-y-1 hover:shadow-default [&[hidden]]:!hidden" data-filter-card data-category="english" data-search="ภาษาอังกฤษ Grammar Present Simple">
          <div class="min-h-[155px] p-[24px] flex items-end justify-between text-white bg-gradient-to-br from-[#7c55d6] to-navy-900">
            <span class="text-[48px] opacity-90">Aa</span><span>8 นาที</span>
          </div>
          <div class="p-[24px]">
            <span class="inline-flex items-center px-2.5 py-[5px] rounded-full text-pink-600 bg-pink-100 text-[12px] font-extrabold mb-3">ภาษาอังกฤษ</span>
            <h3 class="text-[21px] mb-2.5">Present Simple in Real Life</h3>
            <p class="text-muted">เข้าใจโครงสร้างผ่านกิจวัตรประจำวัน พร้อมตัวอย่างที่ใช้ได้จริง</p>
            <div class="flex flex-wrap gap-x-4 gap-y-2 my-[18px] text-muted text-[13px]">บทอ่าน · Beginner</div>
            <a class="text-primary-600 font-[850]" href="placement-test.php">ทำแบบฝึกท้ายบท →</a>
          </div>
        </article>

        <article class="border border-line rounded-lg overflow-hidden bg-white shadow-soft transition-transform hover:-translate-y-1 hover:shadow-default [&[hidden]]:!hidden" data-filter-card data-category="biology" data-search="ชีววิทยา เซลล์ Cell Biology organelles">
          <div class="min-h-[155px] p-[24px] flex items-end justify-between text-white bg-gradient-to-br from-primary-600 to-navy-900">
            <span class="text-[48px] opacity-90">◉</span><span>15 นาที</span>
          </div>
          <div class="p-[24px]">
            <span class="inline-flex items-center px-2.5 py-[5px] rounded-full text-primary-600 bg-primary-100 text-[12px] font-extrabold mb-3">ชีววิทยา</span>
            <h3 class="text-[21px] mb-2.5">Cell Organelles at a Glance</h3>
            <p class="text-muted">รู้หน้าที่ออร์แกเนลล์สำคัญและวิธีเชื่อมหน้าที่กับโครงสร้าง</p>
            <div class="flex flex-wrap gap-x-4 gap-y-2 my-[18px] text-muted text-[13px]">อินโฟกราฟิก · Foundation</div>
            <a class="text-primary-600 font-[850]" href="exam-guides.php">ดูแนวโจทย์ →</a>
          </div>
        </article>

        <article class="border border-line rounded-lg overflow-hidden bg-white shadow-soft transition-transform hover:-translate-y-1 hover:shadow-default [&[hidden]]:!hidden" data-filter-card data-category="biology" data-search="ชีววิทยา Genetics พันธุศาสตร์ จีโนไทป์">
          <div class="min-h-[155px] p-[24px] flex items-end justify-between text-white bg-gradient-to-br from-primary-600 to-navy-900">
            <span class="text-[48px] opacity-90">DNA</span><span>18 นาที</span>
          </div>
          <div class="p-[24px]">
            <span class="inline-flex items-center px-2.5 py-[5px] rounded-full text-primary-600 bg-primary-100 text-[12px] font-extrabold mb-3">ชีววิทยา</span>
            <h3 class="text-[21px] mb-2.5">Genetics: Dominant vs Recessive</h3>
            <p class="text-muted">แยกจีโนไทป์ ฟีโนไทป์ ลักษณะเด่นและด้อยด้วยตารางง่าย ๆ</p>
            <div class="flex flex-wrap gap-x-4 gap-y-2 my-[18px] text-muted text-[13px]">บทเรียน · Foundation</div>
            <a class="text-primary-600 font-[850]" href="exam-guides.php">ดูแนวโจทย์ →</a>
          </div>
        </article>

        <article class="border border-line rounded-lg overflow-hidden bg-white shadow-soft transition-transform hover:-translate-y-1 hover:shadow-default [&[hidden]]:!hidden" data-filter-card data-category="chemistry" data-search="เคมี อะตอม Atomic Structure">
          <div class="min-h-[155px] p-[24px] flex items-end justify-between text-white bg-gradient-to-br from-[#7c55d6] to-navy-900">
            <span class="text-[48px] opacity-90">⚛</span><span>14 นาที</span>
          </div>
          <div class="p-[24px]">
            <span class="inline-flex items-center px-2.5 py-[5px] rounded-full text-[#7c55d6] bg-violet-100 text-[12px] font-extrabold mb-3">เคมี</span>
            <h3 class="text-[21px] mb-2.5">Atomic Structure Starter</h3>
            <p class="text-muted">ทำความรู้จักโปรตอน นิวตรอน อิเล็กตรอน และเลขอะตอม</p>
            <div class="flex flex-wrap gap-x-4 gap-y-2 my-[18px] text-muted text-[13px]">บทเรียน · Beginner</div>
            <a class="text-primary-600 font-[850]" href="placement-test.php">เช็กความเข้าใจ →</a>
          </div>
        </article>

        <article class="border border-line rounded-lg overflow-hidden bg-white shadow-soft transition-transform hover:-translate-y-1 hover:shadow-default [&[hidden]]:!hidden" data-filter-card data-category="chemistry" data-search="เคมี โมล สมการ Calculation">
          <div class="min-h-[155px] p-[24px] flex items-end justify-between text-white bg-gradient-to-br from-pink-500 to-[#933a9e]">
            <span class="text-[48px] opacity-90">⚗</span><span>20 นาที</span>
          </div>
          <div class="p-[24px]">
            <span class="inline-flex items-center px-2.5 py-[5px] rounded-full text-[#7c55d6] bg-violet-100 text-[12px] font-extrabold mb-3">เคมี</span>
            <h3 class="text-[21px] mb-2.5">Mole Ratio Step by Step</h3>
            <p class="text-muted">อ่านสัมประสิทธิ์ในสมการและตั้งอัตราส่วนโมลแบบทีละขั้น</p>
            <div class="flex flex-wrap gap-x-4 gap-y-2 my-[18px] text-muted text-[13px]">แบบฝึก · Foundation</div>
            <a class="text-primary-600 font-[850]" href="exam-guides.php">ดูวิธีทำข้อสอบ →</a>
          </div>
        </article>

        <div class="col-span-full p-[55px_20px] border border-dashed border-line rounded-lg text-center text-muted bg-surface-soft [&[hidden]]:!hidden" data-empty hidden>
          <h3 class="text-[21px] mb-2.5 text-navy-950">ยังไม่พบบทเรียน</h3>
          <p class="m-0">ลองเปลี่ยนคำค้นหรือเลือก “ทั้งหมด”</p>
        </div>

      </div>
    </div>
  </section>

  <section class="py-[70px] bg-surface-soft">
    <div class="container">
      <div class="p-[42px] rounded-xl flex justify-between items-center gap-[35px] bg-white shadow-default max-[680px]:flex-col max-[680px]:items-stretch max-[680px]:p-6">
        <div>
          <p class="eyebrow">BUILD A ROUTINE</p>
          <h2 class="text-[clamp(30px,4vw,47px)] tracking-[-0.035em] mb-4">อยากเรียนต่อแบบมีลำดับ?</h2>
          <p class="text-muted m-0">เลือกเป้าหมายและเวลาที่มี แล้วให้ระบบจัดบทเรียนเป็น Learning Path</p>
        </div>
        <a class="btn btn-primary whitespace-nowrap" href="learning-path.php">สร้างแผนการเรียน →</a>
      </div>
    </div>
  </section>
</main>

<?php include 'includes/footer.php'; ?>
