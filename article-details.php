<?php
$pageTitle = "วางแผนอ่านหนังสืออย่างไรให้ทำได้จริง | Nextbeyond Compass";
$pageDesc = "เทคนิคการแบ่งเวลาแบบ Time Blocking สำหรับนักเรียน";
$currentPage = 'articles.php'; // Keep articles nav active
include 'includes/head.php';
include 'includes/header.php';
?>

<main id="main" class="pt-[80px]">
  
  <!-- Breadcrumb -->
  <div class="bg-[#f8fafc] border-b border-[#e8ecf2] py-3">
    <div class="container">
      <div class="flex items-center gap-2 text-[13px] text-[#65738a] font-medium">
        <a href="index.php" class="hover:text-navy-950 transition-colors">หน้าแรก</a>
        <span class="text-[#cbd5e1]">/</span>
        <a href="articles.php" class="hover:text-navy-950 transition-colors">บทความ</a>
        <span class="text-[#cbd5e1]">/</span>
        <span class="text-navy-950">เทคนิคการเรียน</span>
      </div>
    </div>
  </div>

  <!-- Article Header -->
  <section class="pt-[60px] pb-[40px]">
    <div class="container max-w-[900px]">
      <div class="text-center mb-10">
        <div class="flex items-center justify-center gap-4 text-[12px] font-bold text-pink-500 uppercase tracking-wider mb-4">
          <span>เทคนิคการเรียน</span>
          <span class="w-1 h-1 rounded-full bg-[#cbd5e1]"></span>
          <span class="flex items-center gap-1.5 text-[#65738a]"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 6 นาที</span>
        </div>
        
        <h1 class="text-[clamp(32px,4vw,42px)] font-black text-navy-950 leading-[1.3] mb-6">
          วางแผนอ่านหนังสืออย่างไรให้ทำได้จริง
        </h1>
        
        <p class="text-[18px] text-[#65738a] leading-[1.6] max-w-[700px] mx-auto">
          เปลี่ยนตารางอ่านหนังสือที่ทำได้แค่ 2 วันให้กลายเป็นวินัยที่ยั่งยืน ด้วยเทคนิคการแบ่งเวลาแบบ Time Blocking ที่ใช้ได้จริงแม้วันที่ขี้เกียจ
        </p>
      </div>

      <div class="w-full aspect-[21/9] rounded-[24px] overflow-hidden bg-[#f1f5f9] shadow-[0_10px_40px_rgba(15,42,83,0.08)]">
        <img src="https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=1200&auto=format&fit=crop" alt="Article Hero" class="w-full h-full object-cover">
      </div>
    </div>
  </section>

  <!-- Article Body -->
  <section class="py-[60px]">
    <div class="container max-w-[1100px] grid grid-cols-[1fr_320px] gap-12 max-[1024px]:grid-cols-1">
      
      <!-- Main Content -->
      <article class="prose prose-lg prose-slate max-w-none text-navy-900">
        <p class="text-[20px] leading-[1.8] mb-8 font-medium">เคยไหมครับ... เวลาสอบใกล้เข้ามา เรามักจะจัดตารางอ่านหนังสือซะดิบดี เขียนลงสมุดอย่างสวยงาม ตั้งใจว่าจะอ่านยาวๆ วันละ 4 ชั่วโมง แต่พอทำจริงผ่านไปแค่ 2 วันก็ตบะแตก กลับไปเล่นมือถือเหมือนเดิม</p>
        
        <p>ปัญหานี้ไม่ได้เกิดจากความขี้เกียจเสมอไปครับ แต่มักเกิดจาก <strong>"การตั้งเป้าหมายที่ตึงเกินไป"</strong> และการไม่ได้เผื่อเวลาพักผ่อนให้ตัวเอง วันนี้เราจะมาเรียนรู้เทคนิค Time Blocking ที่จะช่วยให้การอ่านหนังสือเป็นเรื่องที่ทำได้จริง และไม่ทรมานตัวเองจนเกินไป</p>

        <h3 class="text-[24px] font-bold text-navy-950 mt-10 mb-4">1. กฎ 50/10 (The 50/10 Rule)</h3>
        <p>อย่าพยายามอ่านรวดเดียว 2 ชั่วโมง เพราะสมองของเราจะล้าเกินไป ลองเปลี่ยนมาใช้วิธี <strong>อ่าน 50 นาที พัก 10 นาที</strong> แทน</p>
        <ul class="space-y-2 mb-8 ml-6 list-disc">
          <li><strong>50 นาทีแรก:</strong> ปิดแจ้งเตือนมือถือ โฟกัสกับหนังสือตรงหน้า 100%</li>
          <li><strong>10 นาทีพัก:</strong> ลุกเดิน ยืดเส้นยืดสาย ดื่มน้ำ (แต่ยังไม่ควรเล่นโซเชียลมีเดีย เพราะจะทำให้ดึงตัวเองกลับมายาก)</li>
        </ul>

        <div class="my-10 p-8 bg-[#f8fafc] border border-[#e8ecf2] rounded-[20px]">
          <h4 class="text-[18px] font-bold text-navy-950 mb-3 flex items-center gap-2">
            <svg class="w-6 h-6 text-[#f59e0b]" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
            Pro Tip
          </h4>
          <p class="text-[15px] mb-0">หาก 50 นาทีรู้สึกนานเกินไปในช่วงเริ่มต้น ให้ลองเริ่มจาก Pomodoro Technique (อ่าน 25 นาที พัก 5 นาที) แล้วค่อยๆ เพิ่มเวลาเมื่อสมาธิดีขึ้น</p>
        </div>

        <h3 class="text-[24px] font-bold text-navy-950 mt-10 mb-4">2. จัดตารางแบบเผื่อเวลา "พัก" (Buffer Time)</h3>
        <p>หลายคนชอบจัดตารางแน่นเอี๊ยด เช่น 16:00-18:00 คณิตศาสตร์, 18:00-20:00 ภาษาอังกฤษ พอทำตามไม่ได้แค่ช่องเดียว ก็จะพาลเลิกทำไปเลย</p>
        <p><strong>วิธีแก้:</strong> ให้เว้นช่วงเวลา Buffer ไว้เสมอ เช่น แทรกเวลาพัก 30 นาทีระหว่างวิชา หรือปล่อยช่วงวันเสาร์เย็นให้ว่างไปเลย เพื่อชดเชยเวลาเผื่อเราทำตามตารางในวันธรรมดาไม่ทัน</p>

        <h3 class="text-[24px] font-bold text-navy-950 mt-10 mb-4">3. เน้น "เนื้อหา" มากกว่า "เวลา"</h3>
        <p>การวัดผลว่าวันนี้เราสำเร็จหรือไม่ ไม่ควรดูว่าเรานั่งโต๊ะครบ 3 ชั่วโมงไหม แต่ให้ดูว่าเราทำเป้าหมายเสร็จหรือยัง</p>
        <p>ตัวอย่างการตั้งเป้าหมายที่ดี:</p>
        <ul class="space-y-2 mb-8 ml-6 list-disc">
          <li><strong>แบบเก่า (ที่มักจะล้มเหลว):</strong> จะอ่านฟิสิกส์ 2 ชั่วโมง</li>
          <li><strong>แบบใหม่ (ทำได้จริง):</strong> จะทำโจทย์ฟิสิกส์เรื่องการเคลื่อนที่ 20 ข้อ พร้อมเช็กเฉลย</li>
        </ul>

        <div class="mt-12 p-8 bg-gradient-to-br from-[#123d78] to-[#061633] rounded-[24px] text-white">
          <h4 class="text-[20px] font-bold mb-4">สรุป 3 ขั้นตอนเริ่มต้นทันที</h4>
          <ol class="space-y-3 ml-4 list-decimal text-[16px] text-[#aebbd0]">
            <li><strong class="text-white">ลิสต์เป้าหมายรายสัปดาห์:</strong> เช่น สัปดาห์นี้ต้องทำโจทย์คณิต 50 ข้อ</li>
            <li><strong class="text-white">แบ่งลงวัน:</strong> กระจาย 50 ข้อลงไปในแต่ละวัน (วันละ 10 ข้อ)</li>
            <li><strong class="text-white">ลงมือทำด้วย 50/10:</strong> ใช้เวลาแค่ 1 ชั่วโมงต่อวันแบบมีสมาธิ</li>
          </ol>
        </div>
        
        <div class="mt-10 pt-8 border-t border-[#e8ecf2] flex items-center justify-between max-[640px]:flex-col max-[640px]:items-start max-[640px]:gap-6">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full overflow-hidden bg-[#e8ecf2]">
              <img src="https://i.pravatar.cc/150?img=68" alt="Author" class="w-full h-full object-cover">
            </div>
            <div>
              <p class="text-[14px] font-bold text-navy-950 mb-0.5">ครูพี่นัท (Nut)</p>
              <p class="text-[12px] text-[#65738a]">Academic Director, Next Beyond</p>
            </div>
          </div>
          
          <div class="flex items-center gap-3">
            <span class="text-[13px] font-bold text-[#65738a]">แชร์บทความ:</span>
            <button class="w-10 h-10 rounded-full bg-[#f1f5f9] flex items-center justify-center text-navy-950 hover:bg-[#2369dd] hover:text-white transition-colors">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg>
            </button>
            <button class="w-10 h-10 rounded-full bg-[#f1f5f9] flex items-center justify-center text-navy-950 hover:bg-[#1877f2] hover:text-white transition-colors">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
            </button>
          </div>
        </div>
      </article>

      <!-- Sidebar -->
      <aside class="space-y-6">
        
        <!-- Table of Contents -->
        <div class="bg-white rounded-[20px] shadow-sm border border-[#e8ecf2] p-6 sticky top-[98px]">
          <h3 class="text-[16px] font-bold text-navy-950 mb-4">สารบัญเนื้อหา</h3>
          <ul class="space-y-3 text-[14px]">
            <li><a href="#" class="text-pink-500 font-bold hover:underline transition-all">บทนำ</a></li>
            <li><a href="#" class="text-[#65738a] hover:text-navy-950 transition-colors">1. กฎ 50/10 (The 50/10 Rule)</a></li>
            <li><a href="#" class="text-[#65738a] hover:text-navy-950 transition-colors">2. จัดตารางแบบเผื่อเวลา "พัก"</a></li>
            <li><a href="#" class="text-[#65738a] hover:text-navy-950 transition-colors">3. เน้น "เนื้อหา" มากกว่า "เวลา"</a></li>
            <li><a href="#" class="text-[#65738a] hover:text-navy-950 transition-colors">สรุป 3 ขั้นตอนเริ่มต้นทันที</a></li>
          </ul>
        </div>

        <!-- CTA Widget -->
        <div class="bg-gradient-to-br from-pink-500 to-[#ff4a7a] rounded-[20px] p-6 text-white text-center shadow-[0_10px_30px_rgba(231,45,130,0.2)]">
          <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center mx-auto mb-4 backdrop-blur-sm">
            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
          </div>
          <h3 class="text-[18px] font-bold mb-2">อยากเริ่มวางแผนให้ตรงจุด?</h3>
          <p class="text-[14px] text-white/90 mb-5 leading-[1.6]">
            เช็กจุดแข็ง-จุดอ่อนของคุณก่อนเริ่ม ด้วยแบบทดสอบวัดระดับ (Placement Test)
          </p>
          <a href="placement-test.php" class="inline-flex items-center justify-center h-11 px-6 bg-white text-pink-500 rounded-full font-bold text-[14px] hover:bg-[#f8fafc] transition-colors shadow-sm">
            ทำแบบทดสอบฟรี
          </a>
        </div>
      </aside>

    </div>
  </section>

  <!-- Related Articles -->
  <section class="py-[80px] bg-[#f8fafc] border-t border-[#e8ecf2]">
    <div class="container">
      <div class="flex items-center justify-between mb-10 max-[640px]:flex-col max-[640px]:items-start max-[640px]:gap-4">
        <h2 class="text-[28px] font-black text-navy-950 tracking-tight">บทความที่เกี่ยวข้อง</h2>
        <a href="articles.php" class="h-10 px-6 rounded-full border border-[#cbd5e1] text-[#65738a] font-bold hover:border-[#2369dd] hover:text-[#2369dd] transition-colors inline-flex items-center gap-2 text-[14px]">
          ดูบทความทั้งหมด
        </a>
      </div>
      
      <div class="grid grid-cols-3 gap-6 max-[980px]:grid-cols-2 max-[640px]:grid-cols-1">
        
        <!-- Related 1 -->
        <article class="bg-white rounded-[20px] border border-[#e8ecf2] shadow-[0_4px_24px_rgba(15,42,83,0.03)] overflow-hidden flex flex-col transition-transform hover:-translate-y-1 hover:shadow-[0_12px_40px_rgba(15,42,83,0.08)] group cursor-pointer" onclick="window.location.href='article-details.php'">
          <div class="w-full aspect-[16/10] bg-[#f1f5f9] relative overflow-hidden">
            <img loading="lazy" decoding="async" src="https://images.unsplash.com/photo-1606326608606-aa0b62935f2b?q=80&w=800&auto=format&fit=crop" alt="Article Cover" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
            <span class="absolute top-4 left-4 px-3 py-1 bg-white/90 backdrop-blur text-[11px] font-bold tracking-wider text-[#10b981] rounded-full uppercase shadow-sm">เตรียมสอบ</span>
          </div>
          <div class="p-6 flex-1 flex flex-col">
            <h3 class="text-[17px] font-bold text-navy-950 mb-2 leading-[1.4] group-hover:text-[#10b981] transition-colors">เช็กจุดอ่อนก่อนเริ่มตะลุยโจทย์</h3>
            <div class="flex items-center justify-between mt-auto pt-4 border-t border-[#e8ecf2]">
              <div class="flex items-center gap-2 text-[12px] font-medium text-[#94a3b8]">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 8 นาที
              </div>
            </div>
          </div>
        </article>

        <!-- Related 2 -->
        <article class="bg-white rounded-[20px] border border-[#e8ecf2] shadow-[0_4px_24px_rgba(15,42,83,0.03)] overflow-hidden flex flex-col transition-transform hover:-translate-y-1 hover:shadow-[0_12px_40px_rgba(15,42,83,0.08)] group cursor-pointer" onclick="window.location.href='article-details.php'">
          <div class="w-full aspect-[16/10] bg-[#f1f5f9] relative overflow-hidden">
            <img loading="lazy" decoding="async" src="https://images.unsplash.com/photo-1546410531-bb4caa6b424d?q=80&w=800&auto=format&fit=crop" alt="Article Cover" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
            <span class="absolute top-4 left-4 px-3 py-1 bg-white/90 backdrop-blur text-[11px] font-bold tracking-wider text-[#2369dd] rounded-full uppercase shadow-sm">ภาษาอังกฤษ</span>
          </div>
          <div class="p-6 flex-1 flex flex-col">
            <h3 class="text-[17px] font-bold text-navy-950 mb-2 leading-[1.4] group-hover:text-[#2369dd] transition-colors">เริ่มพูดอังกฤษจากประโยคง่าย ๆ</h3>
            <div class="flex items-center justify-between mt-auto pt-4 border-t border-[#e8ecf2]">
              <div class="flex items-center gap-2 text-[12px] font-medium text-[#94a3b8]">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 5 นาที
              </div>
            </div>
          </div>
        </article>

        <!-- Related 3 -->
        <article class="bg-white rounded-[20px] border border-[#e8ecf2] shadow-[0_4px_24px_rgba(15,42,83,0.03)] overflow-hidden flex flex-col transition-transform hover:-translate-y-1 hover:shadow-[0_12px_40px_rgba(15,42,83,0.08)] group cursor-pointer max-[980px]:hidden max-[640px]:flex" onclick="window.location.href='article-details.php'">
          <div class="w-full aspect-[16/10] bg-[#f1f5f9] relative overflow-hidden">
            <img loading="lazy" decoding="async" src="https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?q=80&w=800&auto=format&fit=crop" alt="Article Cover" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
            <span class="absolute top-4 left-4 px-3 py-1 bg-white/90 backdrop-blur text-[11px] font-bold tracking-wider text-[#f59e0b] rounded-full uppercase shadow-sm">ผู้ปกครอง</span>
          </div>
          <div class="p-6 flex-1 flex flex-col">
            <h3 class="text-[17px] font-bold text-navy-950 mb-2 leading-[1.4] group-hover:text-[#f59e0b] transition-colors">ติดตามพัฒนาการลูกอย่างไรโดยไม่กดดัน</h3>
            <div class="flex items-center justify-between mt-auto pt-4 border-t border-[#e8ecf2]">
              <div class="flex items-center gap-2 text-[12px] font-medium text-[#94a3b8]">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 7 นาที
              </div>
            </div>
          </div>
        </article>
        
      </div>
    </div>
  </section>

</main>

<?php include 'includes/footer.php'; ?>
