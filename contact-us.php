<?php
require_once __DIR__ . '/includes/settings-service.php';
$schoolName = getSystemSetting('school_name', 'Nextbeyond Compass');
$schoolPhone = getSystemSetting('school_phone', '02-123-4567');
$schoolEmail = getSystemSetting('school_email', 'contact@nextbeyond.com');
$schoolAddress = getSystemSetting('school_address', 'อาคาร Next Beyond ชั้น 3 เขตปทุมวัน กรุงเทพมหานคร 10330');

$pageTitle = "ติดต่อเรา | " . htmlspecialchars($schoolName);
$pageDesc = "ติดต่อสอบถามข้อมูลคอร์สเรียน ขอคำปรึกษา หรือแจ้งปัญหาการใช้งาน " . htmlspecialchars($schoolName);
$currentPage = 'contact-us.php';
include 'includes/head.php';
include 'includes/header.php';
?>

<main id="main" class="pt-[80px]">
  
  <!-- Hero Section -->
  <section class="py-[60px] bg-surface-soft border-b border-[#e8ecf2]">
    <div class="container text-center max-w-[800px]">
      <p class="flex items-center justify-center gap-3 text-[12px] font-black tracking-[0.15em] text-pink-500 mb-4 uppercase">
        <span class="w-[20px] h-[2px] rounded-full bg-pink-500"></span> GET IN TOUCH <span class="w-[20px] h-[2px] rounded-full bg-pink-500"></span>
      </p>
      <h1 class="text-[clamp(32px,5vw,46px)] font-bold text-navy-950 tracking-[-0.03em] mb-4">
        ติดต่อเรา
      </h1>
      <p class="text-[#65738a] text-[16px] leading-[1.6] mb-8">
        มีข้อสงสัยเกี่ยวกับคอร์สเรียน ต้องการคำปรึกษา หรือพบปัญหาการใช้งาน ทีมงานของเราพร้อมดูแลและให้คำแนะนำอย่างเต็มที่
      </p>
    </div>
  </section>

  <!-- Contact Info & Form -->
  <section class="py-20 bg-white">
    <div class="container max-w-[1000px]">
      <div class="grid grid-cols-[1fr_1.2fr] gap-12 max-[800px]:grid-cols-1">
        
        <!-- Info Column -->
        <div>
          <h2 class="text-[24px] font-bold text-navy-950 mb-6">ช่องทางการติดต่อ</h2>
          
          <div class="space-y-6">
            <div class="flex gap-4">
              <div class="w-12 h-12 shrink-0 rounded-2xl bg-pink-50 flex items-center justify-center text-pink-500">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </div>
              <div>
                <h3 class="text-[15px] font-bold text-navy-950 mb-1">ที่ตั้งสถาบัน</h3>
                <p class="text-[14px] text-[#65738a] leading-[1.6]"><?= nl2br(htmlspecialchars($schoolAddress)) ?></p>
              </div>
            </div>

            <div class="flex gap-4">
              <div class="w-12 h-12 shrink-0 rounded-2xl bg-pink-50 flex items-center justify-center text-pink-500">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
              </div>
              <div>
                <h3 class="text-[15px] font-bold text-navy-950 mb-1">โทรศัพท์</h3>
                <p class="text-[14px] text-[#65738a] leading-[1.6]"><?= htmlspecialchars($schoolPhone) ?><br><span class="text-xs text-slate-400">(เวลาทำการ จันทร์-ศุกร์ 09:00 - 18:00)</span></p>
              </div>
            </div>

            <div class="flex gap-4">
              <div class="w-12 h-12 shrink-0 rounded-2xl bg-[#e8f1ff] flex items-center justify-center text-[#2369dd]">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              </div>
              <div>
                <h3 class="text-[15px] font-bold text-navy-950 mb-1">อีเมล</h3>
                <p class="text-[14px] text-[#65738a] leading-[1.6]"><?= htmlspecialchars($schoolEmail) ?></p>
              </div>
            </div>

            <div class="flex gap-4">
              <div class="w-12 h-12 shrink-0 rounded-2xl bg-[#e6f4ea] flex items-center justify-center text-[#168765]">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
              </div>
              <div>
                <h3 class="text-[15px] font-bold text-navy-950 mb-1">LINE Official</h3>
                <p class="text-[14px] text-[#65738a] leading-[1.6]">@nextbeyond<br>(ตอบกลับรวดเร็วที่สุด)</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Form Column -->
        <div class="border border-[#e8ecf2] rounded-3xl p-8 shadow-[0_12px_40px_rgba(15,42,83,0.06)]">
          <h2 class="text-[22px] font-bold text-navy-950 mb-6">ฝากข้อความถึงเรา</h2>
          <form onsubmit="event.preventDefault(); alert('ขอบคุณที่ติดต่อเรา ทีมงานจะรีบตอบกลับโดยเร็วที่สุดครับ'); this.reset();">
            <div class="grid grid-cols-2 gap-4 mb-4 max-[500px]:grid-cols-1">
              <div>
                <label class="block text-[13px] font-bold text-navy-950 mb-1.5">ชื่อ-นามสกุล *</label>
                <input type="text" required class="w-full h-12 px-4 rounded-xl border border-[#dce4ef] bg-[#f6f8fc] focus:bg-white focus:border-[#2369dd] focus:ring-2 focus:ring-[#2369dd]/10 outline-none transition-all text-[15px] text-navy-950">
              </div>
              <div>
                <label class="block text-[13px] font-bold text-navy-950 mb-1.5">เบอร์โทรศัพท์ *</label>
                <input type="tel" required class="w-full h-12 px-4 rounded-xl border border-[#dce4ef] bg-[#f6f8fc] focus:bg-white focus:border-[#2369dd] focus:ring-2 focus:ring-[#2369dd]/10 outline-none transition-all text-[15px] text-navy-950">
              </div>
            </div>
            <div class="mb-4">
              <label class="block text-[13px] font-bold text-navy-950 mb-1.5">อีเมล</label>
              <input type="email" class="w-full h-12 px-4 rounded-xl border border-[#dce4ef] bg-[#f6f8fc] focus:bg-white focus:border-[#2369dd] focus:ring-2 focus:ring-[#2369dd]/10 outline-none transition-all text-[15px] text-navy-950">
            </div>
            <div class="mb-4">
              <label class="block text-[13px] font-bold text-navy-950 mb-1.5">เรื่องที่ต้องการติดต่อ *</label>
              <select required class="w-full h-12 px-4 rounded-xl border border-[#dce4ef] bg-[#f6f8fc] focus:bg-white focus:border-[#2369dd] focus:ring-2 focus:ring-[#2369dd]/10 outline-none transition-all text-[15px] text-navy-950 appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2212%22%20height%3D%228%22%20viewBox%3D%220%200%2012%208%22%20fill%3D%22none%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M1%201.5L6%206.5L11%201.5%22%20stroke%3D%22%2365738A%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E')] bg-[length:12px_8px] bg-no-repeat bg-[position:right_16px_center]">
                <option value="">เลือกหัวข้อ</option>
                <option value="courses">สอบถามข้อมูลคอร์สเรียน</option>
                <option value="technical">แจ้งปัญหาการใช้งาน</option>
                <option value="payment">สอบถามเรื่องการชำระเงิน</option>
                <option value="other">เรื่องอื่นๆ</option>
              </select>
            </div>
            <div class="mb-6">
              <label class="block text-[13px] font-bold text-navy-950 mb-1.5">รายละเอียด *</label>
              <textarea required rows="4" class="w-full px-4 py-3 rounded-xl border border-[#dce4ef] bg-[#f6f8fc] focus:bg-white focus:border-[#2369dd] focus:ring-2 focus:ring-[#2369dd]/10 outline-none transition-all text-[15px] text-navy-950 resize-y"></textarea>
            </div>
            <button type="submit" class="w-full h-12 rounded-xl bg-pink-500 text-white font-bold text-[15px] shadow-[0_8px_20px_rgba(231,45,130,.2)] transition-transform hover:-translate-y-0.5">
              ส่งข้อความ
            </button>
          </form>
        </div>

      </div>
    </div>
  </section>

</main>

<?php include 'includes/footer.php'; ?>
