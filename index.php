<?php
$pageTitle = "Nextbeyond Compass";
$preloadImage = 'assets/images/next-beyond-academy-hero.webp';
include 'includes/head.php';
include 'includes/header.php';
?>

<main id="main">
  <section class="relative min-h-[650px] flex items-center text-white bg-navy-950 max-[980px]:min-h-0">
    <!-- Full-width background image -->
    <div class="absolute inset-0 bg-cover bg-[center_right] max-[980px]:bg-center" style="background-image: url('assets/images/next-beyond-academy-hero.webp');"></div>
    
    <!-- Gradient overlay for text readability (solid left, transparent right) -->
    <div class="absolute inset-0 bg-gradient-to-r from-navy-950 from-45% to-transparent to-65% max-[980px]:from-60% max-[980px]:to-100%"></div>

    <!-- Content Container -->
    <div class="container relative z-10 flex min-h-[650px] max-[980px]:min-h-0 max-[980px]:flex-col">
      <!-- Left Column (Text) -->
      <div class="w-1/2 py-[100px] pr-[50px] flex flex-col justify-center max-[980px]:w-full max-[980px]:pr-0 max-[980px]:py-[80px]">
        <p class="flex items-center gap-3 text-[12px] font-black tracking-[0.15em] text-[#d6deea] mb-4">
          <span class="w-[30px] h-[2px] rounded-full bg-pink-500"></span> NEXTBEYOND COMPASS
        </p>
        <h1 class="text-white text-[clamp(42px,5vw,64px)] font-bold leading-[1.2] tracking-[-0.03em] mb-[18px]">
          <span class="block">เรียนอย่างเป็น</span>
          <span class="block">ระบบ</span>
          <span class="block text-pink-500">ไปได้ไกลกว่าเดิม</span>
        </h1>
        <p class="max-w-[480px] text-[#aebbd0] text-[16px] leading-[1.7] mb-8">ค้นหาจุดแข็ง วางแผนจากเป้าหมาย และพัฒนาอย่างต่อเนื่อง ด้วยเส้นทางการเรียนที่ออกแบบให้เหมาะกับผู้เรียนแต่ละคน</p>
        
        <div class="flex flex-wrap gap-4 mt-6">
          <a class="inline-flex items-center justify-center min-w-[160px] h-[52px] px-6 rounded-full text-white bg-pink-500 font-bold transition-all hover:-translate-y-0.5 hover:shadow-[0_10px_20px_rgba(231,45,130,0.3)] shadow-[0_4px_14px_rgba(231,45,130,0.4)]" href="courses.php">ดูคอร์สทั้งหมด &rarr;</a>
          <a class="inline-flex items-center justify-center min-w-[160px] h-[52px] px-6 rounded-full text-[#d4deef] border border-white/10 bg-white/5 font-bold transition-all hover:-translate-y-0.5 hover:bg-white/10" href="placement-test.php">
            <span class="mr-2 text-[12px] opacity-70">▶</span> ทดลองวัดระดับ
          </a>
        </div>
        
        <div class="mt-12 flex items-center gap-4 text-[#aebbd0] text-[13px]">
          <div class="flex">
            <span class="w-8 h-8 ml-0 grid place-items-center border-[2.5px] border-navy-950 rounded-full text-navy-950 bg-white text-[9px] font-black">NB</span>
            <span class="w-8 h-8 -ml-3 grid place-items-center border-[2.5px] border-navy-950 rounded-full text-pink-600 bg-pink-100 text-[9px] font-black">EN</span>
            <span class="w-8 h-8 -ml-3 grid place-items-center border-[2.5px] border-navy-950 rounded-full text-primary-600 bg-primary-100 text-[9px] font-black">SC</span>
          </div>
          <span class="leading-tight"><strong class="block text-white text-[14px] font-bold">ดูแลเป็นรายบุคคล</strong>ทั้งออนไลน์และออนไซต์</span>
        </div>
      </div>
      
      <!-- Right Column (Floating Widget) -->
      <div class="w-1/2 relative flex items-end justify-center pb-[70px] max-[980px]:w-full max-[980px]:h-auto max-[980px]:pb-[60px] max-[980px]:items-end max-[980px]:justify-start">
        <!-- Floating box -->
        <div class="relative z-10 w-[90%] max-w-[340px] p-[18px] border border-white/10 rounded-[14px] flex items-center gap-4 bg-[#1e273e]/70 backdrop-blur-md shadow-2xl ml-[10%] max-[980px]:ml-0 max-[980px]:max-w-full">
          <span class="w-[42px] h-[42px] shrink-0 grid place-items-center rounded-xl bg-white/5 text-pink-500 border border-white/10">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
          </span>
          <div><strong class="block text-[14px] text-white">เรียนตามเป้าหมายของผู้เรียน</strong><small class="block text-[#aebbd0] text-[11px] mt-0.5 tracking-wide">วัดระดับ &middot; วางแผน &middot; ติดตามผล</small></div>
        </div>
      </div>
    </div>
  </section>

  <!-- Features Bar -->
  <section class="relative border-b border-line bg-white z-20" aria-label="จุดเด่น">
    <!-- Subtle pink glow behind the second item -->
    <div class="absolute inset-y-0 left-1/4 w-[30%] bg-pink-400/10 blur-[40px] pointer-events-none"></div>
    
    <div class="container relative min-h-[110px] grid grid-cols-4 items-center max-[980px]:grid-cols-2 max-[980px]:py-[15px] max-[680px]:grid-cols-1">
      <div class="p-[20px_24px] border-r border-line flex items-start gap-4 max-[980px]:border-b-0 max-[680px]:border-r-0 max-[680px]:border-b">
        <svg class="w-6 h-6 mt-0.5 text-pink-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><path d="M9 14l2 2 4-4"></path></svg>
        <span><strong class="block text-[14px] text-navy-950 mb-1">ประเมินก่อนเรียน</strong><small class="block text-muted text-[12px] leading-snug tracking-wide">เริ่มจากระดับที่เหมาะสม</small></span>
      </div>
      <div class="p-[20px_24px] border-r border-line flex items-start gap-4 max-[980px]:border-r-0 max-[680px]:border-b">
        <svg class="w-6 h-6 mt-0.5 text-pink-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="19" r="3"></circle><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"></path><circle cx="18" cy="5" r="3"></circle></svg>
        <span><strong class="block text-[14px] text-navy-950 mb-1">มีแผนการเรียนชัดเจน</strong><small class="block text-muted text-[12px] leading-snug tracking-wide">รู้ว่ากำลังพัฒนาอะไร</small></span>
      </div>
      <div class="p-[20px_24px] border-r border-line flex items-start gap-4 max-[680px]:border-r-0 max-[680px]:border-b">
        <svg class="w-6 h-6 mt-0.5 text-pink-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        <span><strong class="block text-[14px] text-navy-950 mb-1">ดูแลเป็นรายบุคคล</strong><small class="block text-muted text-[12px] leading-snug tracking-wide">ปรับตามจังหวะของผู้เรียน</small></span>
      </div>
      <div class="p-[20px_24px] flex items-start gap-4">
        <svg class="w-6 h-6 mt-0.5 text-pink-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 3 3 21 21 21"></polyline><polyline points="3 17 9 11 13 15 21 7"></polyline><polyline points="14 7 21 7 21 14"></polyline></svg>
        <span><strong class="block text-[14px] text-navy-950 mb-1">ติดตามผลต่อเนื่อง</strong><small class="block text-muted text-[12px] leading-snug tracking-wide">นำผลไปใช้ในคลาสถัดไป</small></span>
      </div>
    </div>
  </section>

  <!-- Subjects Section -->
  <section class="relative py-[90px] bg-[#f8fafc] overflow-hidden max-[680px]:py-[68px]">
    <!-- Very subtle background glow -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[60%] h-[300px] bg-pink-300/10 blur-[80px] pointer-events-none rounded-full"></div>
    
    <div class="container relative z-10">
      <div class="flex justify-between items-end gap-10 max-w-[900px] mb-[45px] max-[680px]:flex-col max-[680px]:items-stretch">
        <div>
          <p class="flex items-center gap-3 text-[12px] font-black tracking-[0.15em] text-pink-500 mb-4">
            <span class="w-[30px] h-[2px] rounded-full bg-pink-500"></span> SUBJECTS
          </p>
          <h2 class="text-[clamp(30px,4vw,42px)] font-bold text-navy-950 tracking-[-0.02em] mb-4">เลือกวิชาที่อยากพัฒนา</h2>
        </div>
        <p class="max-w-[430px] text-[#6b7b93] font-medium leading-[1.6]">คอร์สที่ออกแบบให้เข้าใจง่าย เชื่อมพื้นฐานสู่การนำไปใช้จริงและการสอบ</p>
      </div>
      
      <div class="grid grid-cols-3 gap-6 max-[980px]:grid-cols-2 max-[680px]:grid-cols-1">
        
        <!-- English Card -->
        <a class="group relative min-h-[140px] p-[25px] border border-line rounded-2xl flex items-center gap-[20px] bg-white shadow-[0_4px_24px_rgba(0,0,0,0.03)] transition-all hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.08)]" href="courses.php">
          <span class="w-[60px] h-[60px] shrink-0 grid place-items-center rounded-2xl text-[24px] text-pink-500 bg-pink-50 transition-colors group-hover:bg-pink-100">
            <svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 8 6 6"></path><path d="m4 14 6-6 2-3"></path><path d="M2 5h12"></path><path d="M7 2h1"></path><path d="m22 22-5-10-5 10"></path><path d="M14 18h6"></path></svg>
          </span>
          <span class="flex-1">
            <strong class="block mb-[6px] text-navy-950 text-[19px] font-bold">ภาษาอังกฤษ</strong>
            <small class="block text-[#6b7b93] text-[12.5px]">สื่อสารได้จริง · เตรียมสอบอย่างเป็นระบบ</small>
          </span>
          <span class="text-[#aebbd0] transition-transform group-hover:translate-x-1 group-hover:text-navy-950">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
          </span>
        </a>
        
        <!-- Biology Card -->
        <a class="group relative min-h-[140px] p-[25px] border border-line rounded-2xl flex items-center gap-[20px] bg-white shadow-[0_4px_24px_rgba(0,0,0,0.03)] transition-all hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.08)]" href="courses.php">
          <span class="w-[60px] h-[60px] shrink-0 grid place-items-center rounded-2xl text-[24px] text-blue-500 bg-blue-50 transition-colors group-hover:bg-blue-100">
            <svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><ellipse cx="12" cy="12" rx="10" ry="4"></ellipse><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(60 12 12)"></ellipse><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(120 12 12)"></ellipse></svg>
          </span>
          <span class="flex-1">
            <strong class="block mb-[6px] text-navy-950 text-[19px] font-bold">ชีววิทยา</strong>
            <small class="block text-[#6b7b93] text-[12.5px]">เข้าใจภาพรวม · เชื่อมโยงทุกบท</small>
          </span>
          <span class="text-[#aebbd0] transition-transform group-hover:translate-x-1 group-hover:text-navy-950">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
          </span>
        </a>
        
        <!-- Chemistry Card -->
        <a class="group relative min-h-[140px] p-[25px] border border-line rounded-2xl flex items-center gap-[20px] bg-white shadow-[0_4px_24px_rgba(0,0,0,0.03)] transition-all hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.08)]" href="courses.php">
          <span class="w-[60px] h-[60px] shrink-0 grid place-items-center rounded-2xl text-[24px] text-purple-600 bg-purple-50 transition-colors group-hover:bg-purple-100">
            <svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 3h15"></path><path d="M10 3v7.364l-6.445 10.4A2 2 0 0 0 5.255 24h13.49a2 2 0 0 0 1.7-3.236L14 10.364V3"></path></svg>
          </span>
          <span class="flex-1">
            <strong class="block mb-[6px] text-navy-950 text-[19px] font-bold">เคมี</strong>
            <small class="block text-[#6b7b93] text-[12.5px]">ปูพื้นฐานแม่น · ฝึกโจทย์เป็นขั้นตอน</small>
          </span>
          <span class="text-[#aebbd0] transition-transform group-hover:translate-x-1 group-hover:text-navy-950">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
          </span>
        </a>
        
      </div>
    </div>
  </section>

  <section class="py-[90px] bg-[#f8fafc] max-[680px]:py-[68px]">
    <div class="container">
      <div class="max-w-[720px] mx-auto text-center mb-[45px]">
        <p class="flex items-center justify-center gap-3 text-[12px] font-black tracking-[0.15em] text-pink-500 mb-4 uppercase">
          <span class="w-[30px] h-[2px] rounded-full bg-pink-500"></span> RECOMMENDED COURSES <span class="w-[30px] h-[2px] rounded-full bg-pink-500"></span>
        </p>
        <h2 class="text-[clamp(30px,4vw,42px)] font-bold text-navy-950 tracking-[-0.02em] mb-4">เริ่มจากคอร์สที่เหมาะกับเป้าหมาย</h2>
        <p class="text-[#6b7b93] font-medium leading-[1.6]">ปูพื้นฐานให้แน่น ก่อนต่อยอดไปสู่ทักษะและโจทย์ที่ท้าทายขึ้น</p>
      </div>
      
      <div class="grid grid-cols-3 gap-[25px] max-[980px]:grid-cols-2 max-[680px]:grid-cols-1">
        
        <!-- English Card -->
        <article class="flex flex-col bg-white rounded-[24px] overflow-hidden shadow-[0_4px_24px_rgba(0,0,0,0.03)] border border-line transition-transform hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.08)]">
          <div class="relative h-[180px] p-[25px] flex flex-col justify-between text-white bg-gradient-to-br from-[#eb4f7e] to-[#d63a69] overflow-hidden">
            <!-- Concentric Circles Decoration -->
            <div class="absolute -bottom-[30px] -right-[30px] w-[140px] h-[140px] rounded-full border-[25px] border-white/10"></div>
            <div class="absolute -bottom-[60px] -right-[60px] w-[200px] h-[200px] rounded-full border-[25px] border-white/10"></div>
            
            <svg class="relative z-10 w-9 h-9" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 8 6 6"></path><path d="m4 14 6-6 2-3"></path><path d="M2 5h12"></path><path d="M7 2h1"></path><path d="m22 22-5-10-5 10"></path><path d="M14 18h6"></path></svg>
            <strong class="relative z-10 block text-[17px] font-bold">ภาษาอังกฤษ</strong>
          </div>
          <div class="flex-1 p-[25px] flex flex-col">
            <div class="flex flex-wrap gap-2 mb-4">
              <span class="px-[12px] py-[4px] rounded-full text-pink-600 bg-pink-50 text-[11px] font-extrabold tracking-wide">ภาษาอังกฤษ</span>
              <span class="px-[12px] py-[4px] rounded-full text-[#6b7b93] bg-[#f1f5f9] text-[11px] font-extrabold tracking-wide">เริ่มต้น</span>
            </div>
            <h3 class="text-[20px] font-bold text-navy-950 leading-snug mb-3">English Communication Starter</h3>
            <p class="text-[#6b7b93] text-[13.5px] leading-[1.6] mb-6">วางพื้นฐานการฟังและพูด ให้สื่อสารในชีวิตประจำวันได้อย่างมั่นใจ</p>
            
            <div class="mt-auto pt-4 flex items-center gap-2 text-[#6b7b93] text-[12.5px] font-medium">
              <svg class="w-[18px] h-[18px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
              <span>8 สัปดาห์ · 16 ชั่วโมง</span>
            </div>
          </div>
          <a class="block p-[20px_25px] border-t border-line transition-colors hover:bg-slate-50" href="courses.php">
            <div class="flex items-center justify-between text-navy-950 font-bold text-[14px]">
              ดูรายละเอียด
              <svg class="w-5 h-5 text-[#8e9baf]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </div>
          </a>
        </article>
        
        <!-- Biology Card -->
        <article class="flex flex-col bg-white rounded-[24px] overflow-hidden shadow-[0_4px_24px_rgba(0,0,0,0.03)] border border-line transition-transform hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.08)]">
          <div class="relative h-[180px] p-[25px] flex flex-col justify-between text-white bg-gradient-to-br from-[#3b82f6] to-[#2563eb] overflow-hidden">
            <!-- Concentric Circles Decoration -->
            <div class="absolute -bottom-[30px] -right-[30px] w-[140px] h-[140px] rounded-full border-[25px] border-white/10"></div>
            <div class="absolute -bottom-[60px] -right-[60px] w-[200px] h-[200px] rounded-full border-[25px] border-white/10"></div>
            
            <svg class="relative z-10 w-9 h-9" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><ellipse cx="12" cy="12" rx="10" ry="4"></ellipse><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(60 12 12)"></ellipse><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(120 12 12)"></ellipse></svg>
            <strong class="relative z-10 block text-[17px] font-bold">ชีววิทยา</strong>
          </div>
          <div class="flex-1 p-[25px] flex flex-col">
            <div class="flex flex-wrap gap-2 mb-4">
              <span class="px-[12px] py-[4px] rounded-full text-pink-600 bg-pink-50 text-[11px] font-extrabold tracking-wide">ชีววิทยา</span>
              <span class="px-[12px] py-[4px] rounded-full text-[#6b7b93] bg-[#f1f5f9] text-[11px] font-extrabold tracking-wide">มัธยมปลาย</span>
            </div>
            <h3 class="text-[20px] font-bold text-navy-950 leading-snug mb-3">Biology Foundation</h3>
            <p class="text-[#6b7b93] text-[13.5px] leading-[1.6] mb-6">เข้าใจแก่นสำคัญของชีววิทยา พร้อมเชื่อมบทเรียนสู่โจทย์สอบ</p>
            
            <div class="mt-auto pt-4 flex items-center gap-2 text-[#6b7b93] text-[12.5px] font-medium">
              <svg class="w-[18px] h-[18px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
              <span>เรียนสดออนไลน์ · มีเอกสาร</span>
            </div>
          </div>
          <a class="block p-[20px_25px] border-t border-line transition-colors hover:bg-slate-50" href="courses.php">
            <div class="flex items-center justify-between text-navy-950 font-bold text-[14px]">
              ดูรายละเอียด
              <svg class="w-5 h-5 text-[#8e9baf]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </div>
          </a>
        </article>
        
        <!-- Chemistry Card -->
        <article class="flex flex-col bg-white rounded-[24px] overflow-hidden shadow-[0_4px_24px_rgba(0,0,0,0.03)] border border-line transition-transform hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.08)]">
          <div class="relative h-[180px] p-[25px] flex flex-col justify-between text-white bg-gradient-to-br from-[#1e3a5f] to-[#122847] overflow-hidden">
            <!-- Concentric Circles Decoration -->
            <div class="absolute -bottom-[30px] -right-[30px] w-[140px] h-[140px] rounded-full border-[25px] border-white/10"></div>
            <div class="absolute -bottom-[60px] -right-[60px] w-[200px] h-[200px] rounded-full border-[25px] border-white/10"></div>
            
            <svg class="relative z-10 w-9 h-9" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 3h15"></path><path d="M10 3v7.364l-6.445 10.4A2 2 0 0 0 5.255 24h13.49a2 2 0 0 0 1.7-3.236L14 10.364V3"></path></svg>
            <strong class="relative z-10 block text-[17px] font-bold">เคมี</strong>
          </div>
          <div class="flex-1 p-[25px] flex flex-col">
            <div class="flex flex-wrap gap-2 mb-4">
              <span class="px-[12px] py-[4px] rounded-full text-pink-600 bg-pink-50 text-[11px] font-extrabold tracking-wide">เคมี</span>
              <span class="px-[12px] py-[4px] rounded-full text-[#6b7b93] bg-[#f1f5f9] text-[11px] font-extrabold tracking-wide">มัธยมปลาย</span>
            </div>
            <h3 class="text-[20px] font-bold text-navy-950 leading-snug mb-3">Chemistry Essentials</h3>
            <p class="text-[#6b7b93] text-[13.5px] leading-[1.6] mb-6">จัดระบบพื้นฐานเคมี ฝึกคิด วิเคราะห์ และแก้โจทย์อย่างมีเหตุผล</p>
            
            <div class="mt-auto pt-4 flex items-center gap-2 text-[#6b7b93] text-[12.5px] font-medium">
              <svg class="w-[18px] h-[18px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
              <span>เรียนสดออนไลน์ · มีแบบฝึกหัด</span>
            </div>
          </div>
          <a class="block p-[20px_25px] border-t border-line transition-colors hover:bg-slate-50" href="courses.php">
            <div class="flex items-center justify-between text-navy-950 font-bold text-[14px]">
              ดูรายละเอียด
              <svg class="w-5 h-5 text-[#8e9baf]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </div>
          </a>
        </article>
      </div>
      
      <!-- Bottom Action -->
      <div class="mt-12 text-center">
        <a class="inline-flex items-center gap-2 px-[24px] py-[13px] border border-line rounded-xl text-navy-950 font-bold text-[15px] bg-white shadow-soft transition-all hover:border-[#cbd5e1] hover:-translate-y-0.5 hover:shadow-default" href="courses.php">
          ดูคอร์สทั้งหมด 
          <svg class="w-4 h-4 text-[#8e9baf]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
        </a>
      </div>
    </div>
  </section>

  <section class="py-[90px] text-white bg-gradient-to-br from-navy-950 to-navy-800 max-[680px]:py-[68px]">
    <div class="container grid grid-cols-[0.9fr_1.1fr] items-center gap-[85px] max-[980px]:grid-cols-1 max-[980px]:gap-10">
      <div>
        <p class="eyebrow light">OUR APPROACH</p>
        <h2 class="text-white text-[clamp(30px,4vw,47px)] tracking-[-0.035em] mb-4">เรียนด้วยแผนที่ชัดเจน<br>ไม่ต้องลองผิดลองถูก</h2>
        <p class="text-[#bac9dd] mb-6">เราใช้ผลการเรียนจริงมาปรับบทเรียน เพื่อให้ทุกคลาสพาผู้เรียนเข้าใกล้เป้าหมายขึ้นอีกหนึ่งขั้น</p>
        <a class="btn btn-primary" href="learning-path.php">ดูเส้นทางการเรียน →</a>
      </div>
      <div class="grid gap-3.5">
        <div class="p-[21px] border border-white/15 rounded-[18px] flex gap-[18px] bg-white/5">
          <span class="w-[47px] h-[47px] shrink-0 grid place-items-center rounded-[15px] text-white bg-pink-500 font-black">01</span>
          <span><strong class="block">รู้ระดับปัจจุบัน</strong><small class="block text-[#b7c7da]">เริ่มด้วยแบบประเมินและพูดคุยเป้าหมาย</small></span>
        </div>
        <div class="p-[21px] border border-white/15 rounded-[18px] flex gap-[18px] bg-white/5">
          <span class="w-[47px] h-[47px] shrink-0 grid place-items-center rounded-[15px] text-white bg-pink-500 font-black">02</span>
          <span><strong class="block">วางแผนให้เหมาะกับคนเรียน</strong><small class="block text-[#b7c7da]">จัดลำดับเนื้อหาและทักษะที่ควรพัฒนาก่อน</small></span>
        </div>
        <div class="p-[21px] border border-white/15 rounded-[18px] flex gap-[18px] bg-white/5">
          <span class="w-[47px] h-[47px] shrink-0 grid place-items-center rounded-[15px] text-white bg-pink-500 font-black">03</span>
          <span><strong class="block">วัดผลและต่อยอดทุกคลาส</strong><small class="block text-[#b7c7da]">Feedback ที่นำไปใช้ต่อในครั้งถัดไปได้จริง</small></span>
        </div>
      </div>
    </div>
  </section>

  <section class="py-[70px] bg-surface-soft">
    <div class="container">
      <div class="p-[42px] rounded-xl flex justify-between items-center gap-[35px] bg-white shadow-default max-[680px]:flex-col max-[680px]:items-stretch max-[680px]:p-6">
        <div>
          <p class="eyebrow">START YOUR NEXT STEP</p>
          <h2 class="text-[clamp(30px,4vw,47px)] tracking-[-0.035em] mb-4">ยังไม่แน่ใจว่าควรเริ่มตรงไหน?</h2>
          <p class="text-muted m-0">ลองทำแบบวัดระดับเบื้องต้น แล้วดูเส้นทางที่เหมาะกับคุณ</p>
        </div>
        <a class="btn btn-primary whitespace-nowrap" href="placement-test.php">เริ่มวัดระดับฟรี →</a>
      </div>
    </div>
  </section>
</main>

<?php include 'includes/footer.php'; ?>
