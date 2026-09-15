<?php
$pageTitle = "รายละเอียดคอร์ส | Nextbeyond Compass";
$pageDesc = "รายละเอียดคอร์ส English Communication Starter";
$currentPage = 'courses.php'; // Keep active menu on courses
include 'includes/head.php';
include 'includes/header.php';
?>

<main id="main">
  <!-- Breadcrumb -->
  <div class="bg-white border-b border-[#e8ecf2]">
    <div class="container py-3.5 text-[13px] text-[#65738a] font-medium">
      <a href="index.php" class="hover:text-[#2369dd] transition-colors">หน้าแรก</a> 
      <span class="mx-2 text-[#cbd5e1]">/</span> 
      <a href="courses.php" class="hover:text-[#2369dd] transition-colors">ภาษาอังกฤษ</a> 
      <span class="mx-2 text-[#cbd5e1]">/</span> 
      <span class="text-navy-950 font-bold">English Communication Starter</span>
    </div>
  </div>

  <!-- Hero Section -->
  <section class="bg-navy-950 py-[70px] text-white relative overflow-hidden">
    <!-- Glow -->
    <div class="absolute top-0 right-0 w-[600px] h-[600px] bg-pink-500/10 blur-[100px] rounded-full pointer-events-none -translate-y-1/2 translate-x-1/3"></div>
    <div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-blue-500/10 blur-[80px] rounded-full pointer-events-none translate-y-1/2 -translate-x-1/3"></div>

    <div class="container grid grid-cols-[1fr_480px] gap-12 items-center relative z-10 max-[1024px]:grid-cols-1 max-[1024px]:gap-10">
      <div>
        <span class="inline-block px-3.5 py-1.5 mb-5 rounded-full bg-[#1b2f4f] text-[#aebbd0] text-[11px] font-black tracking-[0.1em] uppercase border border-white/5">ภาษาอังกฤษ · BEGINNER</span>
        <h1 class="text-[clamp(32px,4vw,44px)] font-black mb-5 leading-[1.15] tracking-tight">English Communication Starter</h1>
        <p class="text-[17px] text-[#aebbd0] mb-8 leading-relaxed max-w-[600px]">เริ่มพูดภาษาอังกฤษจากสถานการณ์ใกล้ตัว วางพื้นฐานให้ใช้ได้จริงในชีวิตประจำวัน ผ่านการฝึกฝนแบบโต้ตอบและจำลองสถานการณ์จริง</p>
        
        <div class="flex flex-wrap items-center gap-3">
          <span class="flex items-center gap-2 bg-white/10 px-4 py-2.5 rounded-[10px] text-[13px] font-bold"><svg class="w-4 h-4 text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 16 ชั่วโมง</span>
          <span class="flex items-center gap-2 bg-white/10 px-4 py-2.5 rounded-[10px] text-[13px] font-bold"><svg class="w-4 h-4 text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> 8 สัปดาห์</span>
          <span class="flex items-center gap-2 bg-white/10 px-4 py-2.5 rounded-[10px] text-[13px] font-bold"><svg class="w-4 h-4 text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> มีเรียนย้อนหลัง</span>
        </div>
      </div>
      
      <div class="rounded-[20px] overflow-hidden shadow-[0_20px_40px_rgba(0,0,0,0.4)] aspect-video relative group cursor-pointer border border-white/10">
        <img src="https://images.unsplash.com/photo-1577896851231-70ef18881754?q=80&w=1000&auto=format&fit=crop" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" alt="Video Cover">
        <div class="absolute inset-0 bg-navy-950/30 flex items-center justify-center transition-colors group-hover:bg-navy-950/10">
          <div class="w-[70px] h-[70px] bg-white text-[#ff4a7a] rounded-full flex items-center justify-center shadow-[0_10px_30px_rgba(0,0,0,0.3)] transition-transform duration-300 group-hover:scale-110">
            <svg class="w-8 h-8 ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Main Content & Sidebar -->
  <section class="py-[60px] bg-[#f6f8fc]">
    <div class="container grid grid-cols-[1fr_360px] gap-10 items-start max-[1024px]:grid-cols-1">
      
      <!-- Left Column: Content -->
      <div>
        <!-- Tabs Sticky -->
        <div class="sticky top-[78px] z-40 bg-[#f6f8fc] pb-4 pt-2 -mt-2">
          <div class="flex items-center border-b border-[#dce4ef] overflow-x-auto hide-scrollbar gap-2">
            <a href="#overview" class="whitespace-nowrap px-5 py-3.5 text-[15px] font-bold text-[#ff4a7a] border-b-[3px] border-[#ff4a7a]">ภาพรวม</a>
            <a href="#curriculum" class="whitespace-nowrap px-5 py-3.5 text-[15px] font-bold text-[#65738a] hover:text-navy-950 border-b-[3px] border-transparent transition-colors">เนื้อหา</a>
            <a href="#instructor" class="whitespace-nowrap px-5 py-3.5 text-[15px] font-bold text-[#65738a] hover:text-navy-950 border-b-[3px] border-transparent transition-colors">ผู้สอน</a>
            <a href="#reviews" class="whitespace-nowrap px-5 py-3.5 text-[15px] font-bold text-[#65738a] hover:text-navy-950 border-b-[3px] border-transparent transition-colors">รีวิว</a>
          </div>
        </div>

        <!-- Overview -->
        <div id="overview" class="bg-white rounded-[20px] p-8 shadow-sm border border-[#e8ecf2] mt-6 scroll-mt-[150px]">
          <h3 class="text-[22px] font-black text-navy-950 mb-6">คอร์สนี้เหมาะกับใคร?</h3>
          <ul class="space-y-5">
            <li class="flex items-start gap-4">
              <div class="mt-0.5 flex-shrink-0 w-[26px] h-[26px] rounded-full bg-[#e6f0ff] text-[#2369dd] flex items-center justify-center"><svg class="w-[14px] h-[14px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></div>
              <div>
                <strong class="block text-[16px] text-navy-950 mb-1">ผู้เริ่มต้นที่ไม่มีพื้นฐานภาษาอังกฤษเลย</strong>
                <span class="text-[#65738a] text-[14.5px] leading-relaxed">สามารถเริ่มเรียนได้ตั้งแต่การออกเสียง การทักทาย และการสร้างประโยคพื้นฐานอย่างถูกต้อง</span>
              </div>
            </li>
            <li class="flex items-start gap-4">
              <div class="mt-0.5 flex-shrink-0 w-[26px] h-[26px] rounded-full bg-[#e6f0ff] text-[#2369dd] flex items-center justify-center"><svg class="w-[14px] h-[14px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></div>
              <div>
                <strong class="block text-[16px] text-navy-950 mb-1">นักเรียนประถม-มัธยมต้นที่ต้องการปูพื้นฐาน</strong>
                <span class="text-[#65738a] text-[14.5px] leading-relaxed">เตรียมความพร้อมสำหรับการเรียนไวยากรณ์ในระดับที่สูงขึ้น ลดความสับสนเวลาทำข้อสอบ</span>
              </div>
            </li>
            <li class="flex items-start gap-4">
              <div class="mt-0.5 flex-shrink-0 w-[26px] h-[26px] rounded-full bg-[#e6f0ff] text-[#2369dd] flex items-center justify-center"><svg class="w-[14px] h-[14px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></div>
              <div>
                <strong class="block text-[16px] text-navy-950 mb-1">ผู้ที่ต้องการสื่อสารในชีวิตประจำวันเบื้องต้น</strong>
                <span class="text-[#65738a] text-[14.5px] leading-relaxed">สามารถนำไปใช้โต้ตอบในสถานการณ์จริงได้อย่างมั่นใจ เช่น การซื้อของ การถามทาง หรือการแนะนำตัว</span>
              </div>
            </li>
          </ul>
        </div>

        <!-- Curriculum -->
        <div id="curriculum" class="bg-white rounded-[20px] p-8 shadow-sm border border-[#e8ecf2] mt-8 scroll-mt-[150px]">
          <h3 class="text-[22px] font-black text-navy-950 mb-8">สิ่งที่จะได้เรียน</h3>
          <div class="relative border-l-[3px] border-[#e8ecf2] ml-[11px] space-y-10 pb-4">
            
            <!-- Module 1 -->
            <div class="relative pl-10">
              <div class="absolute left-[-14px] top-[-2px] w-[25px] h-[25px] rounded-full bg-[#ff4a7a] border-4 border-white flex items-center justify-center">
                <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
              </div>
              <div class="text-[12px] font-black text-[#ff4a7a] mb-1.5 tracking-[0.15em] uppercase">Module 1</div>
              <h4 class="text-[18px] font-bold text-navy-950 mb-2">เริ่มต้นการสื่อสาร (Greetings & Intro)</h4>
              <p class="text-[#65738a] text-[15px] leading-relaxed">Greetings, personal information, introducing yourself and others. ฝึกการพูดโต้ตอบในสถานการณ์แรกพบ.</p>
            </div>
            
            <!-- Module 2 -->
            <div class="relative pl-10">
              <div class="absolute left-[-14px] top-[-2px] w-[25px] h-[25px] rounded-full bg-[#cbd5e1] border-4 border-white flex items-center justify-center">
                <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
              </div>
              <div class="text-[12px] font-black text-[#94a3b8] mb-1.5 tracking-[0.15em] uppercase">Module 2</div>
              <h4 class="text-[18px] font-bold text-navy-950 mb-2">ชีวิตประจำวัน (Daily Life)</h4>
              <p class="text-[#65738a] text-[15px] leading-relaxed">Daily routine, asking for help, directions and basic questions. การเอาตัวรอดในสถานการณ์ฉุกเฉินเบื้องต้น.</p>
            </div>
            
            <!-- Module 3 -->
            <div class="relative pl-10">
              <div class="absolute left-[-14px] top-[-2px] w-[25px] h-[25px] rounded-full bg-[#cbd5e1] border-4 border-white flex items-center justify-center">
                <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
              </div>
              <div class="text-[12px] font-black text-[#94a3b8] mb-1.5 tracking-[0.15em] uppercase">Module 3</div>
              <h4 class="text-[18px] font-bold text-navy-950 mb-2">ใช้งานจริง (Practical Scenarios)</h4>
              <p class="text-[#65738a] text-[15px] leading-relaxed">Clarification, pronunciation basics, and a final conversation task. บททดสอบรวบยอด.</p>
            </div>

          </div>
        </div>

        <!-- Instructor -->
        <div id="instructor" class="bg-white rounded-[20px] p-8 shadow-sm border border-[#e8ecf2] mt-8 scroll-mt-[150px]">
          <h3 class="text-[22px] font-black text-navy-950 mb-6">ครูผู้สอน</h3>
          <div class="flex items-start gap-6 max-[480px]:flex-col">
            <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=200&auto=format&fit=crop" class="w-[110px] h-[110px] rounded-full object-cover border-[5px] border-[#f1f5f9]" alt="Teacher">
            <div>
              <h4 class="text-[19px] font-bold text-navy-950 mb-1">Teacher Sarah</h4>
              <p class="text-[#2369dd] text-[14px] font-bold mb-3">ผู้เชี่ยวชาญด้าน Communication & Exam Prep</p>
              <p class="text-[#65738a] text-[15px] leading-[1.7]">ประสบการณ์สอนภาษาอังกฤษกว่า 8 ปี เน้นการสอนที่สนุก เข้าใจง่าย และสามารถนำไปประยุกต์ใช้ได้ทันที ไม่เน้นท่องจำกฎไวยากรณ์ตายตัว แต่เน้นความเข้าใจในโครงสร้างภาษาและการออกเสียงที่เป็นธรรมชาติ</p>
            </div>
          </div>
        </div>

        <!-- Reviews -->
        <div id="reviews" class="bg-white rounded-[20px] p-8 shadow-sm border border-[#e8ecf2] mt-8 scroll-mt-[150px]">
          <div class="flex items-center justify-between mb-8 max-[480px]:flex-col max-[480px]:items-start max-[480px]:gap-3">
            <h3 class="text-[22px] font-black text-navy-950">รีวิวจากผู้เรียน</h3>
            <div class="flex items-center gap-3 bg-[#fff8fa] px-4 py-2 rounded-[12px] border border-[#ffe0e8]">
              <span class="text-[22px] font-black text-navy-950 leading-none">4.9</span>
              <div class="flex text-[#ffb020] gap-0.5">
                <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
              </div>
              <span class="text-[#65738a] text-[13px] font-medium">(128 รีวิว)</span>
            </div>
          </div>
          
          <div class="space-y-6">
            <!-- Review 1 -->
            <div class="pb-6 border-b border-[#e8ecf2]">
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-[#f1f5f9] flex items-center justify-center text-[14px] font-bold text-[#65738a]">NK</div>
                  <div>
                    <div class="font-bold text-navy-950 text-[14px]">น้องก้อง, ด.ช. ก้องเกียรติ</div>
                    <div class="text-[12px] text-[#94a3b8]">เรียนจบแล้วเมื่อ 2 สัปดาห์ก่อน</div>
                  </div>
                </div>
                <div class="flex text-[#ffb020] gap-0.5">
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                </div>
              </div>
              <p class="text-[#65738a] text-[15px] leading-relaxed">"สอนสนุกมากครับ จากที่ไม่กล้าพูด ตอนนี้เริ่มตอบโต้ได้แบบไม่ต้องแปลในหัวก่อน ครู Sarah ใจดีและอธิบายเข้าใจง่ายมาก"</p>
            </div>
            
            <!-- Review 2 -->
            <div>
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-[#f1f5f9] flex items-center justify-center text-[14px] font-bold text-[#65738a]">PP</div>
                  <div>
                    <div class="font-bold text-navy-950 text-[14px]">คุณแม่พรพรรณ (ผู้ปกครอง)</div>
                    <div class="text-[12px] text-[#94a3b8]">เพิ่งเรียนไป 4 สัปดาห์</div>
                  </div>
                </div>
                <div class="flex text-[#ffb020] gap-0.5">
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                  <svg class="w-3.5 h-3.5" text-gray-300 fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                </div>
              </div>
              <p class="text-[#65738a] text-[15px] leading-relaxed">"ลูกชายชอบมากค่ะ ปกติไม่ยอมเรียนพิเศษภาษาอังกฤษเลย แต่คอร์สนี้บอกว่าสนุกและกล้าพูดกับชาวต่างชาติมากขึ้น แนะนำเลยค่ะ"</p>
            </div>
          </div>
          
          <button class="mt-6 w-full py-3 rounded-[10px] text-[#2369dd] font-bold text-[14px] bg-[#e6f0ff] hover:bg-[#d6e5ff] transition-colors">
            ดูรีวิวทั้งหมด
          </button>
        </div>
      </div>

      <!-- Right Column: Sticky Purchase Card -->
      <aside class="sticky top-[100px]">
        <div class="bg-white rounded-[20px] p-7 shadow-[0_15px_40px_rgba(15,42,83,0.06)] border border-[#e8ecf2]">
          <div class="text-[13px] text-[#65738a] font-bold uppercase tracking-wider mb-2">ราคาคอร์สเรียน</div>
          <div class="flex items-baseline gap-2 mb-3">
            <div class="text-[38px] font-black text-navy-950 leading-none">1,200</div>
            <div class="text-[18px] font-bold text-[#65738a]">บาท</div>
          </div>
          
          <div class="flex items-center gap-2.5 text-[13.5px] text-[#65738a] mb-6 pb-6 border-b border-[#e8ecf2] font-medium">
            <div class="w-8 h-8 rounded-full bg-[#f1f5f9] flex items-center justify-center flex-shrink-0 text-navy-950">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            เรียนครบ 16 ชั่วโมง · ดูย้อนหลังได้ 1 ปี
          </div>
          
          <!-- CTA Buttons -->
          <a href="checkout.php" class="flex items-center justify-center w-full h-[54px] bg-gradient-to-r from-[#ff4a7a] to-[#f53165] text-white rounded-[12px] font-bold text-[16px] shadow-[0_8px_20px_rgba(255,74,122,0.3)] transition-all hover:-translate-y-1 hover:shadow-[0_12px_25px_rgba(255,74,122,0.4)] mb-3">
            เพิ่มลงตะกร้า
          </a>
          <button class="flex items-center justify-center w-full h-[54px] bg-white border-[2px] border-[#e8ecf2] text-navy-950 rounded-[12px] font-bold text-[15px] transition-colors hover:border-[#cbd5e1] hover:bg-[#f8fafc]">
            ทดลองเรียนฟรี
          </button>
          
          <div class="mt-7 pt-7 border-t border-[#e8ecf2]">
            <div class="text-[14px] font-bold text-navy-950 mb-4">สิ่งที่จะได้รับในคอร์สนี้:</div>
            <ul class="space-y-3.5 text-[14px] text-[#65738a]">
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-[#2369dd] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> 
                <span>วิดีโอเนื้อหาความคมชัดสูงกว่า 16 ชั่วโมง</span>
              </li>
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-[#2369dd] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> 
                <span>แบบฝึกหัดโต้ตอบ 24 บทเรียน</span>
              </li>
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-[#2369dd] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> 
                <span>เอกสารประกอบการเรียน (PDF โหลดได้)</span>
              </li>
              <li class="flex items-start gap-3">
                <svg class="w-5 h-5 text-[#2369dd] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> 
                <span>ใบประกาศนียบัตรเมื่อเรียนจบ</span>
              </li>
            </ul>
          </div>
        </div>
      </aside>
    </div>
  </section>

  <!-- Related Courses -->
  <section class="py-[80px] bg-white border-t border-[#e8ecf2]">
    <div class="container max-w-[1140px]">
      <div class="flex items-center justify-between mb-10 max-[640px]:flex-col max-[640px]:items-start max-[640px]:gap-4">
        <div>
          <span class="block text-[12px] font-black text-[#ff4a7a] tracking-[0.15em] mb-2 uppercase">More to learn</span>
          <h2 class="text-[28px] font-black text-navy-950 leading-tight">คอร์สที่เกี่ยวข้อง</h2>
        </div>
        <a href="courses.php" class="text-[14px] font-bold text-[#2369dd] hover:underline">ดูคอร์สทั้งหมด →</a>
      </div>
      
      <div class="grid grid-cols-3 gap-6 max-[1024px]:grid-cols-2 max-[640px]:grid-cols-1">
        <!-- Related Card 1 -->
        <article class="bg-white border border-[#e8ecf2] rounded-[16px] overflow-hidden shadow-[0_4px_24px_rgba(15,42,83,0.05)] transition-transform hover:-translate-y-1 hover:shadow-lg flex flex-col group">
          <div class="relative h-[160px] bg-gray-200 overflow-hidden">
            <img loading="lazy" decoding="async" src="https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=800&auto=format&fit=crop" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="English Class">
            <span class="absolute top-3 left-3 px-2.5 py-1 rounded-full bg-[#ff4a7a] text-white text-[11px] font-bold shadow-md">ยอดนิยม</span>
          </div>
          <div class="p-5 flex-1 flex flex-col">
            <h3 class="text-[17px] font-bold text-navy-950 mb-3 leading-snug line-clamp-2">A-Level English Prep</h3>
            <div class="flex items-center gap-4 text-[#65738a] text-[12px] mb-4 mt-auto">
              <span class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 12 ชั่วโมง</span>
              <span class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg> ผู้เรียน</span>
            </div>
            <div class="flex items-center justify-between mt-2 pt-4 border-t border-[#f1f5f9]">
              <div class="text-[18px] font-black text-navy-950">1,490 ฿</div>
              <a href="course-details.php" class="h-8 px-4 inline-flex items-center justify-center rounded-full bg-[#ff4a7a] text-white text-[13px] font-bold transition-transform hover:scale-105">ดูคอร์ส</a>
            </div>
          </div>
        </article>
        
        <!-- Related Card 2 -->
        <article class="bg-white border border-[#e8ecf2] rounded-[16px] overflow-hidden shadow-[0_4px_24px_rgba(15,42,83,0.05)] transition-transform hover:-translate-y-1 hover:shadow-lg flex flex-col group">
          <div class="relative h-[160px] bg-gray-200 overflow-hidden">
            <img loading="lazy" decoding="async" src="https://images.unsplash.com/photo-1577896851231-70ef18881754?q=80&w=800&auto=format&fit=crop" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="Grammar Class">
          </div>
          <div class="p-5 flex-1 flex flex-col">
            <h3 class="text-[17px] font-bold text-navy-950 mb-3 leading-snug line-clamp-2">Grammar Foundation</h3>
            <div class="flex items-center gap-4 text-[#65738a] text-[12px] mb-4 mt-auto">
              <span class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 10 ชั่วโมง</span>
              <span class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg> ผู้เรียน</span>
            </div>
            <div class="flex items-center justify-between mt-2 pt-4 border-t border-[#f1f5f9]">
              <div class="text-[18px] font-black text-navy-950">990 ฿</div>
              <a href="course-details.php" class="h-8 px-4 inline-flex items-center justify-center rounded-full bg-[#ff4a7a] text-white text-[13px] font-bold transition-transform hover:scale-105">ดูคอร์ส</a>
            </div>
          </div>
        </article>
        
        <!-- Related Card 3 -->
        <article class="bg-white border border-[#e8ecf2] rounded-[16px] overflow-hidden shadow-[0_4px_24px_rgba(15,42,83,0.05)] transition-transform hover:-translate-y-1 hover:shadow-lg flex flex-col group">
          <div class="relative h-[160px] bg-gray-200 overflow-hidden">
            <img loading="lazy" decoding="async" src="https://images.unsplash.com/photo-1543269865-cbf427effbad?q=80&w=800&auto=format&fit=crop" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="Exam Prep">
            <span class="absolute top-3 left-3 px-2.5 py-1 rounded-full bg-[#2369dd] text-white text-[11px] font-bold shadow-md">เรียนฟรี</span>
          </div>
          <div class="p-5 flex-1 flex flex-col">
            <h3 class="text-[17px] font-bold text-navy-950 mb-3 leading-snug line-clamp-2">Exam Practice Hub</h3>
            <div class="flex items-center gap-4 text-[#65738a] text-[12px] mb-4 mt-auto">
              <span class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 5 ชั่วโมง</span>
              <span class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg> ผู้เรียน</span>
            </div>
            <div class="flex items-center justify-between mt-2 pt-4 border-t border-[#f1f5f9]">
              <div class="text-[18px] font-black text-[#2369dd]">ฟรี</div>
              <a href="course-details.php" class="h-8 px-4 inline-flex items-center justify-center rounded-full bg-[#ff4a7a] text-white text-[13px] font-bold transition-transform hover:scale-105">ดูคอร์ส</a>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>

</main>

<script>
  // Simple scrollspy and smooth scroll for tabs
  document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('.sticky.top-\\[78px\\] a');
    const sections = Array.from(tabs).map(tab => document.querySelector(tab.getAttribute('href')));
    
    // Smooth scroll on click
    tabs.forEach(tab => {
      tab.addEventListener('click', (e) => {
        e.preventDefault();
        const targetId = tab.getAttribute('href');
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
          targetElement.scrollIntoView({ behavior: 'smooth' });
        }
      });
    });

    // Update active tab on scroll
    window.addEventListener('scroll', () => {
      let current = '';
      const scrollPosition = window.scrollY + 160; // Offset for sticky header

      sections.forEach(section => {
        if (section) {
          const sectionTop = section.offsetTop;
          if (scrollPosition >= sectionTop) {
            current = '#' + section.getAttribute('id');
          }
        }
      });

      tabs.forEach(tab => {
        tab.classList.remove('text-[#ff4a7a]', 'border-[#ff4a7a]');
        tab.classList.add('text-[#65738a]', 'border-transparent');
        if (tab.getAttribute('href') === current) {
          tab.classList.remove('text-[#65738a]', 'border-transparent');
          tab.classList.add('text-[#ff4a7a]', 'border-[#ff4a7a]');
        }
      });
      
      // If at top, make first tab active
      if(window.scrollY < sections[0]?.offsetTop - 200 && tabs[0]) {
          tabs.forEach(t => {
            t.classList.remove('text-[#ff4a7a]', 'border-[#ff4a7a]');
            t.classList.add('text-[#65738a]', 'border-transparent');
          });
          tabs[0].classList.remove('text-[#65738a]', 'border-transparent');
          tabs[0].classList.add('text-[#ff4a7a]', 'border-[#ff4a7a]');
      }
    });
  });
</script>

<?php include 'includes/footer.php'; ?>
