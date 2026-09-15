<?php
$pageTitle = "แนวข้อสอบ | Nextbeyond Compass";
$pageDesc = "แนวข้อสอบและกลยุทธ์การสอบ";
$currentPage = 'exam-guides.php';
$extraHead = '<script defer src="assets/js/exam-guides.js"></script>';
include 'includes/head.php';
include 'includes/header.php';
?>

<main id="main" data-filter-section>
  <section class="pt-[95px] pb-[85px] text-white bg-gradient-to-br from-navy-950 to-navy-800">
    <div class="container">
      <p class="eyebrow light">EXAM GUIDES</p>
      <h1 class="max-w-[850px] text-white text-[clamp(39px,5.5vw,66px)] mb-[22px] tracking-[-0.045em]"><span class="block">อ่านข้อสอบให้ขาด</span><em class="text-pink-500 not-italic">ก่อนลงมือทำ</em></h1>
      <p class="max-w-[720px] text-[#bfcee2] text-[18px]">รวมโครงสร้างข้อสอบ หัวข้อสำคัญ จุดพลาดที่พบบ่อย และแผนฝึกซ้อมสำหรับภาษาอังกฤษ ชีววิทยา และเคมี</p>
    </div>
  </section>

  <section class="py-[90px] bg-surface-soft max-[680px]:py-[68px]">
    <div class="container">
      <div class="relative z-10 -mt-[42px] mb-[45px] mx-auto p-[18px] border border-line rounded-[18px] flex items-center gap-3.5 bg-white shadow-default max-[680px]:flex-col max-[680px]:items-stretch">
        <label class="relative flex-1 min-w-[250px]">
          <span class="absolute left-[15px] top-[11px] text-muted">⌕</span>
          <input class="w-full h-[48px] pl-[43px] pr-4 border border-line rounded-[13px] text-ink bg-surface-soft outline-none focus:border-primary-500 focus:shadow-[0_0_0_3px_rgba(57,129,245,0.14)] transition-all" type="search" data-search placeholder="ค้นหาชื่อข้อสอบหรือหัวข้อ..." aria-label="ค้นหาแนวข้อสอบ">
        </label>
        <div class="flex flex-wrap gap-2 max-[680px]:w-full">
          <button class="min-h-[42px] px-[13px] py-2 border border-line rounded-[11px] text-[#56657b] bg-white font-[750] transition-colors [&.active]:border-primary-600 [&.active]:text-primary-600 [&.active]:bg-primary-100 hover:border-primary-600 hover:text-primary-600 hover:bg-primary-100 max-[680px]:flex-1 active" type="button" data-filter="all">ทั้งหมด</button>
          <button class="min-h-[42px] px-[13px] py-2 border border-line rounded-[11px] text-[#56657b] bg-white font-[750] transition-colors [&.active]:border-primary-600 [&.active]:text-primary-600 [&.active]:bg-primary-100 hover:border-primary-600 hover:text-primary-600 hover:bg-primary-100 max-[680px]:flex-1" type="button" data-filter="english">อังกฤษ</button>
          <button class="min-h-[42px] px-[13px] py-2 border border-line rounded-[11px] text-[#56657b] bg-white font-[750] transition-colors [&.active]:border-primary-600 [&.active]:text-primary-600 [&.active]:bg-primary-100 hover:border-primary-600 hover:text-primary-600 hover:bg-primary-100 max-[680px]:flex-1" type="button" data-filter="biology">ชีววิทยา</button>
          <button class="min-h-[42px] px-[13px] py-2 border border-line rounded-[11px] text-[#56657b] bg-white font-[750] transition-colors [&.active]:border-primary-600 [&.active]:text-primary-600 [&.active]:bg-primary-100 hover:border-primary-600 hover:text-primary-600 hover:bg-primary-100 max-[680px]:flex-1" type="button" data-filter="chemistry">เคมี</button>
        </div>
      </div>
      
      <p class="mb-5 text-muted font-bold" data-result-count></p>
      
      <div class="grid grid-cols-3 gap-5 max-[980px]:grid-cols-2 max-[680px]:grid-cols-1">
        
        <article class="p-[23px] border border-line rounded-[18px] bg-white shadow-soft [&[hidden]]:!hidden" data-filter-card data-category="english" data-search="A-Level English ภาษาอังกฤษ Reading Grammar Vocabulary">
          <div class="mb-[15px] flex items-center justify-between gap-3">
            <span class="inline-flex items-center px-2.5 py-[5px] rounded-full text-pink-600 bg-pink-100 text-[12px] font-extrabold">ภาษาอังกฤษ</span><span>90 นาที</span>
          </div>
          <h3 class="text-[21px] mb-2.5">A-Level English Blueprint</h3>
          <p class="text-muted">แบ่งเวลาทำ Reading, Grammar และ Vocabulary พร้อมวิธีตัดตัวเลือก</p>
          <div class="my-[18px] text-muted text-[13px]">ระดับ: มัธยมปลาย · 80 ข้อ</div>
          <details class="mt-[13px] pt-[13px] border-t border-line">
            <summary class="text-primary-600 font-[850] cursor-pointer">ดูหัวข้อที่ควรฝึก</summary>
            <ol class="pl-[21px] mt-2 text-muted list-decimal">
              <li>Context clues และคำเชื่อม</li>
              <li>โครงสร้างประโยคและ Tense</li>
              <li>อ่านใจความสำคัญก่อนรายละเอียด</li>
            </ol>
          </details>
          <button class="btn btn-outline mt-[14px] w-full" type="button" data-save-guide="alevel-en">☆ บันทึกแนวข้อสอบ</button>
        </article>
        
        <article class="p-[23px] border border-line rounded-[18px] bg-white shadow-soft [&[hidden]]:!hidden" data-filter-card data-category="english" data-search="English Communication ภาษาอังกฤษ สนทนา สถานการณ์">
          <div class="mb-[15px] flex items-center justify-between gap-3">
            <span class="inline-flex items-center px-2.5 py-[5px] rounded-full text-pink-600 bg-pink-100 text-[12px] font-extrabold">ภาษาอังกฤษ</span><span>40 นาที</span>
          </div>
          <h3 class="text-[21px] mb-2.5">Communication Situation Set</h3>
          <p class="text-muted">สถานการณ์สนทนา การตอบรับอย่างสุภาพ และ Functional Language</p>
          <div class="my-[18px] text-muted text-[13px]">ระดับ: เริ่มต้น–กลาง · 40 ข้อ</div>
          <details class="mt-[13px] pt-[13px] border-t border-line">
            <summary class="text-primary-600 font-[850] cursor-pointer">ดูหัวข้อที่ควรฝึก</summary>
            <ol class="pl-[21px] mt-2 text-muted list-decimal">
              <li>การขอความช่วยเหลือ</li>
              <li>การแสดงความคิดเห็น</li>
              <li>บทสนทนาในชีวิตประจำวัน</li>
            </ol>
          </details>
          <button class="btn btn-outline mt-[14px] w-full" type="button" data-save-guide="comm-en">☆ บันทึกแนวข้อสอบ</button>
        </article>
        
        <article class="p-[23px] border border-line rounded-[18px] bg-white shadow-soft [&[hidden]]:!hidden" data-filter-card data-category="biology" data-search="Biology ชีววิทยา เซลล์ พันธุศาสตร์ ระบบร่างกาย">
          <div class="mb-[15px] flex items-center justify-between gap-3">
            <span class="inline-flex items-center px-2.5 py-[5px] rounded-full text-primary-600 bg-primary-100 text-[12px] font-extrabold">ชีววิทยา</span><span>75 นาที</span>
          </div>
          <h3 class="text-[21px] mb-2.5">Biology Concept Map</h3>
          <p class="text-muted">ลำดับทบทวนจากเซลล์สู่ระบบร่างกาย พันธุศาสตร์ และระบบนิเวศ</p>
          <div class="my-[18px] text-muted text-[13px]">ระดับ: มัธยมปลาย · 60 ข้อ</div>
          <details class="mt-[13px] pt-[13px] border-t border-line">
            <summary class="text-primary-600 font-[850] cursor-pointer">ดูหัวข้อที่ควรฝึก</summary>
            <ol class="pl-[21px] mt-2 text-muted list-decimal">
              <li>อ่านกราฟและแผนภาพ</li>
              <li>วิเคราะห์การทดลอง</li>
              <li>เชื่อมกลไกกับผลลัพธ์</li>
            </ol>
          </details>
          <button class="btn btn-outline mt-[14px] w-full" type="button" data-save-guide="bio-map">☆ บันทึกแนวข้อสอบ</button>
        </article>
        
        <article class="p-[23px] border border-line rounded-[18px] bg-white shadow-soft [&[hidden]]:!hidden" data-filter-card data-category="biology" data-search="Biology Data ชีววิทยา กราฟ การทดลอง ตาราง">
          <div class="mb-[15px] flex items-center justify-between gap-3">
            <span class="inline-flex items-center px-2.5 py-[5px] rounded-full text-primary-600 bg-primary-100 text-[12px] font-extrabold">ชีววิทยา</span><span>45 นาที</span>
          </div>
          <h3 class="text-[21px] mb-2.5">Biology Data Analysis</h3>
          <p class="text-muted">ฝึกอ่านกราฟ ตาราง ตัวแปรต้น–ตาม และสรุปผลการทดลอง</p>
          <div class="my-[18px] text-muted text-[13px]">ระดับ: เตรียมสอบ · 30 ข้อ</div>
          <details class="mt-[13px] pt-[13px] border-t border-line">
            <summary class="text-primary-600 font-[850] cursor-pointer">ดูหัวข้อที่ควรฝึก</summary>
            <ol class="pl-[21px] mt-2 text-muted list-decimal">
              <li>ระบุตัวแปรควบคุม</li>
              <li>อ่านแนวโน้มข้อมูล</li>
              <li>แยกข้อสรุปกับการคาดการณ์</li>
            </ol>
          </details>
          <button class="btn btn-outline mt-[14px] w-full" type="button" data-save-guide="bio-data">☆ บันทึกแนวข้อสอบ</button>
        </article>
        
        <article class="p-[23px] border border-line rounded-[18px] bg-white shadow-soft [&[hidden]]:!hidden" data-filter-card data-category="chemistry" data-search="Chemistry เคมี อะตอม พันธะ โมล">
          <div class="mb-[15px] flex items-center justify-between gap-3">
            <span class="inline-flex items-center px-2.5 py-[5px] rounded-full text-[#7c55d6] bg-violet-100 text-[12px] font-extrabold">เคมี</span><span>75 นาที</span>
          </div>
          <h3 class="text-[21px] mb-2.5">Chemistry Calculation Guide</h3>
          <p class="text-muted">วิธีตั้งหน่วย อ่านสมการ และแก้โจทย์โมลอย่างเป็นขั้นตอน</p>
          <div class="my-[18px] text-muted text-[13px]">ระดับ: มัธยมปลาย · 50 ข้อ</div>
          <details class="mt-[13px] pt-[13px] border-t border-line">
            <summary class="text-primary-600 font-[850] cursor-pointer">ดูหัวข้อที่ควรฝึก</summary>
            <ol class="pl-[21px] mt-2 text-muted list-decimal">
              <li>หน่วยและเลขนัยสำคัญ</li>
              <li>อัตราส่วนจากสมการเคมี</li>
              <li>ตรวจความสมเหตุสมผลของคำตอบ</li>
            </ol>
          </details>
          <button class="btn btn-outline mt-[14px] w-full" type="button" data-save-guide="chem-calc">☆ บันทึกแนวข้อสอบ</button>
        </article>
        
        <article class="p-[23px] border border-line rounded-[18px] bg-white shadow-soft [&[hidden]]:!hidden" data-filter-card data-category="chemistry" data-search="Chemistry เคมี กรด เบส สมดุล">
          <div class="mb-[15px] flex items-center justify-between gap-3">
            <span class="inline-flex items-center px-2.5 py-[5px] rounded-full text-[#7c55d6] bg-violet-100 text-[12px] font-extrabold">เคมี</span><span>50 นาที</span>
          </div>
          <h3 class="text-[21px] mb-2.5">Acid–Base Quick Review</h3>
          <p class="text-muted">สรุป pH การแตกตัว และวิธีดูจุดสมมูลจากข้อมูลในโจทย์</p>
          <div class="my-[18px] text-muted text-[13px]">ระดับ: เตรียมสอบ · 35 ข้อ</div>
          <details class="mt-[13px] pt-[13px] border-t border-line">
            <summary class="text-primary-600 font-[850] cursor-pointer">ดูหัวข้อที่ควรฝึก</summary>
            <ol class="pl-[21px] mt-2 text-muted list-decimal">
              <li>กรด–เบสแก่และอ่อน</li>
              <li>คำนวณ pH เบื้องต้น</li>
              <li>อ่านกราฟไทเทรต</li>
            </ol>
          </details>
          <button class="btn btn-outline mt-[14px] w-full" type="button" data-save-guide="acid-base">☆ บันทึกแนวข้อสอบ</button>
        </article>

        <div class="col-span-full p-[55px_20px] border border-dashed border-line rounded-lg text-center text-muted bg-surface-soft [&[hidden]]:!hidden" data-empty hidden>
          <h3 class="text-[21px] mb-2.5 text-navy-950">ยังไม่พบแนวข้อสอบ</h3>
          <p class="m-0">ลองเปลี่ยนคำค้นหรือเลือก “ทั้งหมด”</p>
        </div>

      </div>
    </div>
  </section>

  <section class="py-[70px] bg-surface-soft">
    <div class="container">
      <div class="p-[42px] rounded-xl flex justify-between items-center gap-[35px] bg-white shadow-default max-[680px]:flex-col max-[680px]:items-stretch max-[680px]:p-6">
        <div>
          <p class="eyebrow">PRACTICE NOW</p>
          <h2 class="text-[clamp(30px,4vw,47px)] tracking-[-0.035em] mb-4">พร้อมเช็กพื้นฐานแล้วหรือยัง?</h2>
          <p class="text-muted m-0">เริ่มจากแบบวัดระดับสั้น ๆ แล้วนำผลไปสร้างแผนฝึกข้อสอบ</p>
        </div>
        <a class="btn btn-primary whitespace-nowrap" href="placement-test.php">เริ่มทำแบบทดสอบ →</a>
      </div>
    </div>
  </section>
</main>

<?php include 'includes/footer.php'; ?>
