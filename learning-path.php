<?php
$pageTitle = "Learning Path | Nextbeyond Compass";
$pageDesc = "สร้าง Learning Path ส่วนตัว";
$currentPage = 'learning-path.php';
$extraHead = '<script defer src="assets/js/learning-path.js"></script>';
include 'includes/head.php';
include 'includes/header.php';
?>

<main id="main">
  <!-- ── Hero ── -->
  <section class="page-intro relative overflow-hidden text-white" style="background:linear-gradient(128deg,#061633,#123d78)">
    <div class="absolute w-[420px] h-[420px] -right-[90px] -top-[220px] rounded-full pointer-events-none" style="background:rgba(245,70,150,.24);filter:blur(5px)"></div>
    <div class="absolute w-[380px] h-[380px] left-[35%] -bottom-[300px] rounded-full pointer-events-none" style="background:rgba(57,129,245,.3)"></div>
    <div class="container relative z-10 py-14 pb-12 grid grid-cols-[1fr_auto] items-end gap-10 max-[640px]:grid-cols-1 max-[640px]:py-10">
      <div>
        <p class="flex items-center gap-2.5 text-[12px] font-black tracking-[0.15em] text-[#b8d3ff] uppercase mb-2.5">
          <span class="w-[25px] h-[3px] rounded-full bg-pink-500"></span>PERSONAL LEARNING PATH
        </p>
        <h1 class="text-white text-[clamp(36px,5vw,58px)] font-bold tracking-[-0.04em] leading-[1.24] mb-3">เปลี่ยนเป้าหมาย<br><em class="text-pink-500 not-italic">เป็นแผนที่ทำต่อได้จริง</em></h1>
        <p class="max-w-[720px] text-[#c2d0e2] text-[17px] mb-0">เลือกวิชา ระดับ เป้าหมาย และเวลาที่มี ระบบจะสร้างลำดับการเรียน พร้อมบันทึกความคืบหน้าไว้ในอุปกรณ์นี้</p>
      </div>
      <div class="min-w-[230px] p-[17px] border border-white/[.16] rounded-[17px] bg-white/[.08] backdrop-blur-[10px] max-[640px]:min-w-0">
        <strong class="block text-white">วัดระดับ → วางแผน → ลงมือเรียน</strong>
        <small class="block text-[#b9c9de]">ปลดล็อกเนื้อหาทีละขั้นตามความคืบหน้า</small>
      </div>
    </div>
  </section>

  <!-- ── Workspace: Planner (left) + Output (right) ── -->
  <section class="py-10 pb-20 bg-[#f6f8fc]" id="planner">
    <div class="container grid grid-cols-[350px_1fr] items-start gap-6 max-[1024px]:grid-cols-1" data-learning-path-app>

      <!-- Planner Form (Left Panel) -->
      <form class="sticky top-[98px] p-6 border border-[#dce4ef] rounded-[22px] bg-white shadow-[0_12px_32px_rgba(15,42,83,.08)] max-[1024px]:static" data-plan-form>
        <p class="flex items-center gap-2.5 text-[12px] font-black tracking-[0.15em] text-[#2369dd] uppercase mb-2.5">
          <span class="w-[25px] h-[3px] rounded-full bg-pink-500"></span>PLAN SETTINGS
        </p>
        <h2 class="text-[27px] font-bold text-navy-950 tracking-[-0.025em] mb-2">ตั้งค่าแผนของคุณ</h2>
        <p class="text-[#65738a] text-[14px] mb-5">ใช้เวลาไม่ถึง 1 นาที แล้วเริ่มเรียนตามลำดับได้เลย</p>

        <div class="mb-4 p-3 rounded-xl text-[#2369dd] bg-[#e8f1ff] text-[13px] hidden" data-placement-note></div>

        <div class="mb-4">
          <label class="block mb-1.5 text-navy-900 text-[14px] font-bold" for="subject">วิชาที่ต้องการพัฒนา</label>
          <select class="w-full min-h-[48px] px-3.5 py-2 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] focus:shadow-[0_0_0_3px_rgba(57,129,245,.14)]" id="subject" name="subject">
            <option value="english">ภาษาอังกฤษ</option>
            <option value="biology">ชีววิทยา</option>
            <option value="chemistry">เคมี</option>
          </select>
        </div>

        <div class="mb-4">
          <label class="block mb-1.5 text-navy-900 text-[14px] font-bold" for="level">ระดับปัจจุบัน</label>
          <select class="w-full min-h-[48px] px-3.5 py-2 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] focus:shadow-[0_0_0_3px_rgba(57,129,245,.14)]" id="level" name="level">
            <option value="starter">เริ่มต้น / ต้องปูพื้นฐาน</option>
            <option value="foundation">มีพื้นฐาน / ต้องการพัฒนา</option>
            <option value="advanced">พร้อมต่อยอด / เตรียมสอบ</option>
          </select>
        </div>

        <div class="mb-4">
          <label class="block mb-1.5 text-navy-900 text-[14px] font-bold" for="goal">เป้าหมายหลัก</label>
          <select class="w-full min-h-[48px] px-3.5 py-2 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] focus:shadow-[0_0_0_3px_rgba(57,129,245,.14)]" id="goal" name="goal">
            <option value="confidence">เข้าใจและมั่นใจขึ้น</option>
            <option value="school">พัฒนาผลการเรียน</option>
            <option value="exam">เตรียมสอบและตะลุยโจทย์</option>
          </select>
        </div>

        <div class="mb-4">
          <label class="block mb-1.5 text-navy-900 text-[14px] font-bold" for="pace">จังหวะการเรียน</label>
          <select class="w-full min-h-[48px] px-3.5 py-2 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] focus:shadow-[0_0_0_3px_rgba(57,129,245,.14)]" id="pace" name="pace">
            <option value="light">ค่อยเป็นค่อยไป · 2 ชม./สัปดาห์</option>
            <option value="standard" selected>สมดุล · 3 ชม./สัปดาห์</option>
            <option value="intensive">เข้มข้น · 5 ชม./สัปดาห์</option>
          </select>
        </div>

        <button class="w-full min-h-[48px] px-[18px] border-0 rounded-[13px] inline-flex items-center justify-center gap-2 text-white bg-pink-500 font-bold shadow-[0_11px_24px_rgba(231,45,130,.23)] transition-transform hover:-translate-y-0.5" type="submit">สร้างแผนการเรียน →</button>
        <p class="mt-3 text-[#65738a] text-[11px]">ข้อมูลบันทึกใน Browser ของอุปกรณ์นี้เท่านั้น</p>
      </form>

      <!-- Output (Right Panel) -->
      <section class="min-w-0" data-plan-output aria-live="polite"></section>
    </div>
  </section>

  <!-- ── How It Works ── -->
  <section class="py-16 bg-white">
    <div class="container">
      <div class="max-w-[700px] mb-8">
        <p class="flex items-center gap-2.5 text-[12px] font-black tracking-[0.15em] text-[#2369dd] uppercase mb-2.5">
          <span class="w-[25px] h-[3px] rounded-full bg-pink-500"></span>HOW IT WORKS
        </p>
        <h2 class="text-navy-950 text-[36px] font-bold tracking-[-0.03em] mb-2">แผนที่ปรับตามผู้เรียนจริง</h2>
        <p class="text-[#65738a]">แต่ละแผนเริ่มจากพื้นฐาน เชื่อมไปสู่เป้าหมาย และจบด้วยการวัดผลอีกครั้ง</p>
      </div>
      <div class="grid grid-cols-3 gap-4 max-[1024px]:grid-cols-1">
        <article class="p-[22px] border border-[#dce4ef] rounded-[17px] bg-[#f6f8fc]">
          <span class="w-[39px] h-[39px] mb-3.5 grid place-items-center rounded-xl text-white bg-pink-500 font-black">01</span>
          <h3 class="text-navy-950 font-bold mb-1.5">เริ่มจากระดับปัจจุบัน</h3>
          <p class="text-[#65738a] text-[14px] mb-0">ใช้ผลวัดระดับหรือเลือกระดับที่ใกล้เคียง เพื่อไม่ให้บทเรียนง่ายหรือยากเกินไป</p>
        </article>
        <article class="p-[22px] border border-[#dce4ef] rounded-[17px] bg-[#f6f8fc]">
          <span class="w-[39px] h-[39px] mb-3.5 grid place-items-center rounded-xl text-white bg-pink-500 font-black">02</span>
          <h3 class="text-navy-950 font-bold mb-1.5">เรียงเนื้อหาให้ชัดเจน</h3>
          <p class="text-[#65738a] text-[14px] mb-0">แผนแบ่งเป็นขั้นสั้น ๆ พร้อมระยะเวลาและจำนวนบทเรียนที่คาดการณ์ได้</p>
        </article>
        <article class="p-[22px] border border-[#dce4ef] rounded-[17px] bg-[#f6f8fc]">
          <span class="w-[39px] h-[39px] mb-3.5 grid place-items-center rounded-xl text-white bg-pink-500 font-black">03</span>
          <h3 class="text-navy-950 font-bold mb-1.5">ติดตามและปรับรอบถัดไป</h3>
          <p class="text-[#65738a] text-[14px] mb-0">ติ๊กความคืบหน้า ปลดล็อกขั้นถัดไป และทำ Post-test เพื่อกำหนดเป้าหมายใหม่</p>
        </article>
      </div>
    </div>
  </section>
</main>

<?php include 'includes/footer.php'; ?>
