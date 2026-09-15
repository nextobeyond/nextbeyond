<?php
$pageTitle = "บทความและเทคนิคการเรียน | Nextbeyond Compass";
$pageDesc = "คลังความรู้ เทคนิคการเรียน การเตรียมสอบ และข่าวสารสำหรับนักเรียนและผู้ปกครอง";
$currentPage = 'articles.php';
include 'includes/head.php';
include 'includes/header.php';
?>

<main id="main" class="pt-[80px]">
  
  <!-- Hero Section -->
  <section class="py-[60px] bg-surface-soft border-b border-[#e8ecf2]">
    <div class="container text-center max-w-[800px]">
      <p class="flex items-center justify-center gap-3 text-[12px] font-black tracking-[0.15em] text-pink-500 mb-4 uppercase">
        <span class="w-[20px] h-[2px] rounded-full bg-pink-500"></span> LEARNING ARTICLES <span class="w-[20px] h-[2px] rounded-full bg-pink-500"></span>
      </p>
      <h1 class="text-[clamp(32px,5vw,46px)] font-bold text-navy-950 tracking-[-0.03em] mb-4">
        บทความและเทคนิคการเรียน
      </h1>
      <p class="text-[#65738a] text-[16px] leading-[1.6] mb-8">
        คลังความรู้ เทคนิคการเรียน การเตรียมสอบ และข่าวสารสำหรับนักเรียนและผู้ปกครอง
      </p>
      
      <!-- Search -->
      <div class="relative max-w-[620px] mx-auto">
        <input type="text" placeholder="ค้นหาบทความ เช่น เทคนิคอ่านหนังสือ, ข้อสอบ TGAT..." class="w-full h-[56px] pl-[52px] pr-5 rounded-full border border-[#cbd5e1] text-[15px] text-navy-950 focus:border-pink-500 focus:ring-4 focus:ring-pink-500/20 outline-none transition-all shadow-[0_4px_20px_rgba(15,42,83,0.05)]">
        <svg class="w-6 h-6 text-[#94a3b8] absolute left-4 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <button class="absolute right-2 top-1/2 -translate-y-1/2 h-[40px] px-6 rounded-full bg-navy-950 text-white font-bold text-[14px] hover:bg-[#1a2333] transition-colors">ค้นหา</button>
      </div>
    </div>
  </section>

  <!-- Articles Content -->
  <section class="py-[80px]">
    <div class="container">
      
      <!-- Filters (Tabs) -->
      <div class="flex flex-wrap items-center gap-2 mb-10 pb-4 border-b border-[#e8ecf2]">
        <button class="px-5 py-2.5 rounded-full bg-navy-950 text-white font-bold text-[14px] transition-colors shadow-md">ทั้งหมด</button>
        <button class="px-5 py-2.5 rounded-full bg-white border border-[#cbd5e1] text-[#65738a] font-medium text-[14px] hover:bg-[#f1f5f9] hover:text-navy-950 transition-colors">ภาษาอังกฤษ</button>
        <button class="px-5 py-2.5 rounded-full bg-white border border-[#cbd5e1] text-[#65738a] font-medium text-[14px] hover:bg-[#f1f5f9] hover:text-navy-950 transition-colors">วิทยาศาสตร์</button>
        <button class="px-5 py-2.5 rounded-full bg-white border border-[#cbd5e1] text-[#65738a] font-medium text-[14px] hover:bg-[#f1f5f9] hover:text-navy-950 transition-colors">คณิตศาสตร์</button>
        <button class="px-5 py-2.5 rounded-full bg-white border border-[#cbd5e1] text-[#65738a] font-medium text-[14px] hover:bg-[#f1f5f9] hover:text-navy-950 transition-colors">เตรียมสอบ</button>
        <button class="px-5 py-2.5 rounded-full bg-white border border-[#cbd5e1] text-[#65738a] font-medium text-[14px] hover:bg-[#f1f5f9] hover:text-navy-950 transition-colors">สำหรับผู้ปกครอง</button>
      </div>
      
      <!-- Article Grid -->
      <div class="grid grid-cols-3 gap-8 max-[980px]:grid-cols-2 max-[640px]:grid-cols-1">
        
        <!-- Article 1 -->
        <article class="bg-white rounded-[20px] border border-[#e8ecf2] shadow-[0_4px_24px_rgba(15,42,83,0.03)] overflow-hidden flex flex-col transition-transform hover:-translate-y-1 hover:shadow-[0_12px_40px_rgba(15,42,83,0.08)] group cursor-pointer" onclick="window.location.href='article-details.php'">
          <div class="w-full aspect-[16/10] bg-[#f1f5f9] relative overflow-hidden">
            <img loading="lazy" decoding="async" src="https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=800&auto=format&fit=crop" alt="Article Cover" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
            <span class="absolute top-4 left-4 px-3 py-1 bg-white/90 backdrop-blur text-[11px] font-bold tracking-wider text-pink-500 rounded-full uppercase shadow-sm">เทคนิคการเรียน</span>
          </div>
          <div class="p-6 flex-1 flex flex-col">
            <h3 class="text-[18px] font-bold text-navy-950 mb-3 leading-[1.4] group-hover:text-pink-500 transition-colors">วางแผนอ่านหนังสืออย่างไรให้ทำได้จริง</h3>
            <p class="text-[#65738a] text-[14px] line-clamp-2 mb-5 flex-1">เปลี่ยนตารางอ่านหนังสือที่ทำได้แค่ 2 วันให้กลายเป็นวินัยที่ยั่งยืน ด้วยเทคนิคการแบ่งเวลาแบบ Time Blocking</p>
            <div class="flex items-center justify-between mt-auto pt-4 border-t border-[#e8ecf2]">
              <div class="flex items-center gap-2 text-[12px] font-medium text-[#94a3b8]">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 6 นาที
              </div>
              <span class="text-[14px] font-bold text-[#2369dd] flex items-center gap-1">อ่านต่อ <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></span>
            </div>
          </div>
        </article>

        <!-- Article 2 -->
        <article class="bg-white rounded-[20px] border border-[#e8ecf2] shadow-[0_4px_24px_rgba(15,42,83,0.03)] overflow-hidden flex flex-col transition-transform hover:-translate-y-1 hover:shadow-[0_12px_40px_rgba(15,42,83,0.08)] group cursor-pointer" onclick="window.location.href='article-details.php'">
          <div class="w-full aspect-[16/10] bg-[#f1f5f9] relative overflow-hidden">
            <img loading="lazy" decoding="async" src="https://images.unsplash.com/photo-1546410531-bb4caa6b424d?q=80&w=800&auto=format&fit=crop" alt="Article Cover" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
            <span class="absolute top-4 left-4 px-3 py-1 bg-white/90 backdrop-blur text-[11px] font-bold tracking-wider text-[#2369dd] rounded-full uppercase shadow-sm">ภาษาอังกฤษ</span>
          </div>
          <div class="p-6 flex-1 flex flex-col">
            <h3 class="text-[18px] font-bold text-navy-950 mb-3 leading-[1.4] group-hover:text-[#2369dd] transition-colors">เริ่มพูดอังกฤษจากประโยคง่าย ๆ</h3>
            <p class="text-[#65738a] text-[14px] line-clamp-2 mb-5 flex-1">รวมประโยคพื้นฐานที่ใช้ในชีวิตประจำวัน ช่วยให้คุณเริ่มสื่อสารได้อย่างมั่นใจ ไม่ต้องท่องจำแกรมมาร์</p>
            <div class="flex items-center justify-between mt-auto pt-4 border-t border-[#e8ecf2]">
              <div class="flex items-center gap-2 text-[12px] font-medium text-[#94a3b8]">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 5 นาที
              </div>
              <span class="text-[14px] font-bold text-[#2369dd] flex items-center gap-1">อ่านต่อ <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></span>
            </div>
          </div>
        </article>

        <!-- Article 3 -->
        <article class="bg-white rounded-[20px] border border-[#e8ecf2] shadow-[0_4px_24px_rgba(15,42,83,0.03)] overflow-hidden flex flex-col transition-transform hover:-translate-y-1 hover:shadow-[0_12px_40px_rgba(15,42,83,0.08)] group cursor-pointer" onclick="window.location.href='article-details.php'">
          <div class="w-full aspect-[16/10] bg-[#f1f5f9] relative overflow-hidden">
            <img loading="lazy" decoding="async" src="https://images.unsplash.com/photo-1606326608606-aa0b62935f2b?q=80&w=800&auto=format&fit=crop" alt="Article Cover" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
            <span class="absolute top-4 left-4 px-3 py-1 bg-white/90 backdrop-blur text-[11px] font-bold tracking-wider text-[#10b981] rounded-full uppercase shadow-sm">เตรียมสอบ</span>
          </div>
          <div class="p-6 flex-1 flex flex-col">
            <h3 class="text-[18px] font-bold text-navy-950 mb-3 leading-[1.4] group-hover:text-[#10b981] transition-colors">เช็กจุดอ่อนก่อนเริ่มตะลุยโจทย์</h3>
            <p class="text-[#65738a] text-[14px] line-clamp-2 mb-5 flex-1">ทำไมการทำโจทย์เยอะๆ ถึงอาจไม่ได้ผลลัพธ์ที่ดีเสมอไป? ลองมาวิเคราะห์จุดอ่อนตัวเองกันก่อน</p>
            <div class="flex items-center justify-between mt-auto pt-4 border-t border-[#e8ecf2]">
              <div class="flex items-center gap-2 text-[12px] font-medium text-[#94a3b8]">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 8 นาที
              </div>
              <span class="text-[14px] font-bold text-[#2369dd] flex items-center gap-1">อ่านต่อ <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></span>
            </div>
          </div>
        </article>

        <!-- Article 4 -->
        <article class="bg-white rounded-[20px] border border-[#e8ecf2] shadow-[0_4px_24px_rgba(15,42,83,0.03)] overflow-hidden flex flex-col transition-transform hover:-translate-y-1 hover:shadow-[0_12px_40px_rgba(15,42,83,0.08)] group cursor-pointer" onclick="window.location.href='article-details.php'">
          <div class="w-full aspect-[16/10] bg-[#f1f5f9] relative overflow-hidden">
            <img loading="lazy" decoding="async" src="https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?q=80&w=800&auto=format&fit=crop" alt="Article Cover" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
            <span class="absolute top-4 left-4 px-3 py-1 bg-white/90 backdrop-blur text-[11px] font-bold tracking-wider text-[#f59e0b] rounded-full uppercase shadow-sm">ผู้ปกครอง</span>
          </div>
          <div class="p-6 flex-1 flex flex-col">
            <h3 class="text-[18px] font-bold text-navy-950 mb-3 leading-[1.4] group-hover:text-[#f59e0b] transition-colors">ติดตามพัฒนาการลูกอย่างไรโดยไม่กดดัน</h3>
            <p class="text-[#65738a] text-[14px] line-clamp-2 mb-5 flex-1">เทคนิคสำหรับคุณพ่อคุณแม่ในการสังเกตและส่งเสริมลูกในการเรียนออนไลน์อย่างถูกวิธี</p>
            <div class="flex items-center justify-between mt-auto pt-4 border-t border-[#e8ecf2]">
              <div class="flex items-center gap-2 text-[12px] font-medium text-[#94a3b8]">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 7 นาที
              </div>
              <span class="text-[14px] font-bold text-[#2369dd] flex items-center gap-1">อ่านต่อ <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></span>
            </div>
          </div>
        </article>
        
        <!-- Article 5 -->
        <article class="bg-white rounded-[20px] border border-[#e8ecf2] shadow-[0_4px_24px_rgba(15,42,83,0.03)] overflow-hidden flex flex-col transition-transform hover:-translate-y-1 hover:shadow-[0_12px_40px_rgba(15,42,83,0.08)] group cursor-pointer" onclick="window.location.href='article-details.php'">
          <div class="w-full aspect-[16/10] bg-[#f1f5f9] relative overflow-hidden">
            <img loading="lazy" decoding="async" src="https://images.unsplash.com/photo-1532094349884-543bc11b234d?q=80&w=800&auto=format&fit=crop" alt="Article Cover" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
            <span class="absolute top-4 left-4 px-3 py-1 bg-white/90 backdrop-blur text-[11px] font-bold tracking-wider text-pink-500 rounded-full uppercase shadow-sm">วิทยาศาสตร์</span>
          </div>
          <div class="p-6 flex-1 flex flex-col">
            <h3 class="text-[18px] font-bold text-navy-950 mb-3 leading-[1.4] group-hover:text-pink-500 transition-colors">เรียนชีววิทยาให้เห็นภาพรวม</h3>
            <p class="text-[#65738a] text-[14px] line-clamp-2 mb-5 flex-1">วิชาชีวะเนื้อหาเยอะ จะเรียนอย่างไรให้จำได้เป็นระบบ ไม่ต้องท่องตัวหนังสือทีละบรรทัด</p>
            <div class="flex items-center justify-between mt-auto pt-4 border-t border-[#e8ecf2]">
              <div class="flex items-center gap-2 text-[12px] font-medium text-[#94a3b8]">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 5 นาที
              </div>
              <span class="text-[14px] font-bold text-[#2369dd] flex items-center gap-1">อ่านต่อ <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></span>
            </div>
          </div>
        </article>
        
        <!-- Article 6 -->
        <article class="bg-white rounded-[20px] border border-[#e8ecf2] shadow-[0_4px_24px_rgba(15,42,83,0.03)] overflow-hidden flex flex-col transition-transform hover:-translate-y-1 hover:shadow-[0_12px_40px_rgba(15,42,83,0.08)] group cursor-pointer" onclick="window.location.href='article-details.php'">
          <div class="w-full aspect-[16/10] bg-[#f1f5f9] relative overflow-hidden">
            <img loading="lazy" decoding="async" src="https://images.unsplash.com/photo-1632516643720-e7f5d7d6eca4?q=80&w=800&auto=format&fit=crop" alt="Article Cover" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
            <span class="absolute top-4 left-4 px-3 py-1 bg-white/90 backdrop-blur text-[11px] font-bold tracking-wider text-[#2369dd] rounded-full uppercase shadow-sm">คณิตศาสตร์</span>
          </div>
          <div class="p-6 flex-1 flex flex-col">
            <h3 class="text-[18px] font-bold text-navy-950 mb-3 leading-[1.4] group-hover:text-[#2369dd] transition-colors">วิธีฝึกโจทย์แบบไม่จำสูตรอย่างเดียว</h3>
            <p class="text-[#65738a] text-[14px] line-clamp-2 mb-5 flex-1">การทำความเข้าใจที่มาของสูตรช่วยให้พลิกแพลงการทำโจทย์คณิตศาสตร์ได้ดีขึ้น</p>
            <div class="flex items-center justify-between mt-auto pt-4 border-t border-[#e8ecf2]">
              <div class="flex items-center gap-2 text-[12px] font-medium text-[#94a3b8]">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 4 นาที
              </div>
              <span class="text-[14px] font-bold text-[#2369dd] flex items-center gap-1">อ่านต่อ <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></span>
            </div>
          </div>
        </article>

      </div>
      
      <!-- Load More -->
      <div class="mt-12 text-center">
        <button class="h-[48px] px-8 rounded-full border-2 border-[#cbd5e1] text-[#65738a] font-bold hover:border-[#2369dd] hover:text-[#2369dd] transition-colors inline-flex items-center gap-2">
          โหลดบทความเพิ่มเติม <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
      </div>

    </div>
  </section>

</main>

<?php include 'includes/footer.php'; ?>
