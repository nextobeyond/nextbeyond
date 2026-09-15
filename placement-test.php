<?php
$pageTitle = "แบบวัดระดับ | Nextbeyond Compass";
$pageDesc = "แบบวัดระดับออนไลน์ Nextbeyond Compass";
$currentPage = 'placement-test.php';
$placementScriptVersion = (string) filemtime(__DIR__ . '/assets/js/placement-test.js');
$extraHead = '<script defer src="assets/js/placement-test.js?v=' . rawurlencode($placementScriptVersion) . '"></script>';
include 'includes/head.php';
include 'includes/header.php';
?>

<main id="main">
  <section class="relative pt-[120px] pb-[100px] text-white overflow-hidden bg-navy-950">
    <!-- Background Gradient Glow -->
    <div class="absolute top-0 right-0 w-[60%] h-[100%] bg-pink-500/10 blur-[150px] rounded-full pointer-events-none"></div>

    <div class="container relative z-10">
      <div class="flex items-center justify-between gap-10 max-[980px]:flex-col max-[980px]:items-stretch">
        <!-- Left text content -->
        <div class="w-[55%] max-[980px]:w-full">
          <p class="flex items-center gap-3 text-[12px] font-black tracking-[0.15em] text-pink-500 mb-6 uppercase">
            <span class="w-[30px] h-[2px] rounded-full bg-pink-500"></span> PLACEMENT & PRACTICE
          </p>
          <h1 class="text-white text-[clamp(42px,5vw,56px)] font-bold mb-6 tracking-[-0.03em] leading-[1.2]">
            <span class="block">รู้จุดแข็ง เข้าใจจุดที่ต้อง</span>
            <span class="block">เสริม</span>
            <span class="block text-pink-500">ก่อนเริ่มเรียน</span>
          </h1>
          <p class="text-[#aebbd0] text-[16px] leading-[1.7] mb-8 max-w-[500px]">ทำแบบวัดระดับและรับภาพรวมผลการเรียน เพื่อใช้ชวางแผนให้ตรงกับผู้เรียนมากขึ้น</p>
          
          <a class="inline-flex items-center justify-center min-w-[160px] h-[52px] px-6 rounded-full text-white bg-pink-500 font-bold transition-all hover:-translate-y-0.5 hover:shadow-[0_10px_20px_rgba(231,45,130,0.3)] shadow-[0_4px_14px_rgba(231,45,130,0.4)]" href="#app">
            เลือกแบบวัดระดับ &rarr;
          </a>
        </div>
        
        <!-- Right Illustration -->
        <div class="w-[45%] max-[980px]:w-full flex justify-end max-[980px]:justify-start max-[980px]:mt-10">
          <div class="relative w-full max-w-[460px]">
            <!-- Example of the report students receive after a real attempt -->
            <div class="p-7 rounded-[20px] bg-[#243049]/80 backdrop-blur-md border border-white/5 shadow-[0_20px_60px_rgba(0,0,0,0.4)]">
              <!-- Header -->
              <div class="flex items-center gap-4 mb-8">
                <span class="w-[42px] h-[42px] rounded-xl bg-pink-500/10 text-pink-500 flex items-center justify-center shrink-0">
                  <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="M7 14l5-5 5 5"></path><path d="M17 9V4h-4"></path></svg>
                </span>
                <div>
                  <p class="text-[11px] text-[#8e9baf] font-bold tracking-[0.1em] uppercase mb-0.5">Learning Readiness</p>
                  <p class="text-white text-[16px] font-bold leading-none">ภาพรวมผลประเมิน</p>
                </div>
              </div>
              
              <!-- Content -->
              <div class="flex gap-8 items-center max-[400px]:flex-col max-[400px]:items-start">
                <!-- Circular Gauge -->
                <div class="relative w-[120px] h-[120px] shrink-0">
                  <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                    <circle cx="50" cy="50" r="42" fill="none" stroke="#33415c" stroke-width="10"></circle>
                    <circle cx="50" cy="50" r="42" fill="none" stroke="url(#pinkGradient)" stroke-width="10" stroke-dasharray="264" stroke-dashoffset="58" stroke-linecap="round"></circle>
                    <defs>
                      <linearGradient id="pinkGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#e11d48" />
                        <stop offset="100%" stop-color="#f472b6" />
                      </linearGradient>
                    </defs>
                  </svg>
                  <div class="absolute inset-0 flex items-center justify-center flex-col">
                    <strong class="text-white text-[28px] font-black leading-none tracking-tight">78<span class="text-[13px] font-bold text-[#8e9baf] ml-0.5">/100</span></strong>
                  </div>
                </div>
                
                <!-- Bars -->
                <div class="flex-1 w-full space-y-4">
                  <div>
                    <div class="flex justify-between text-[11px] font-medium mb-1.5"><span class="text-white">Vocabulary</span><span class="text-white">ดี</span></div>
                    <div class="h-1.5 rounded-full bg-[#33415c] overflow-hidden"><div class="h-full bg-gradient-to-r from-pink-600 to-pink-400 rounded-full w-[85%]"></div></div>
                  </div>
                  <div>
                    <div class="flex justify-between text-[11px] font-medium mb-1.5"><span class="text-white">Grammar</span><span class="text-white">ควรเสริม</span></div>
                    <div class="h-1.5 rounded-full bg-[#33415c] overflow-hidden"><div class="h-full bg-gradient-to-r from-pink-600 to-pink-400 rounded-full w-[55%]"></div></div>
                  </div>
                  <div>
                    <div class="flex justify-between text-[11px] font-medium mb-1.5"><span class="text-white">Reading</span><span class="text-white">ดี</span></div>
                    <div class="h-1.5 rounded-full bg-[#33415c] overflow-hidden"><div class="h-full bg-gradient-to-r from-pink-600 to-pink-400 rounded-full w-[80%]"></div></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Features Bar -->
  <section class="border-b border-line bg-white relative z-20" aria-label="จุดเด่น">
    <div class="container min-h-[110px] grid grid-cols-3 items-center max-[980px]:grid-cols-1 max-[980px]:py-[15px]">
      <div class="p-[25px_30px] border-r border-line flex items-center gap-5 max-[980px]:border-r-0 max-[980px]:border-b">
        <svg class="w-8 h-8 text-pink-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle></svg>
        <span><strong class="block text-[15px] text-navy-950 mb-1">เริ่มจากระดับที่เหมาะสม</strong><small class="block text-muted text-[13px]">ลดการเรียนซ้ำในสิ่งที่เข้าใจแล้ว</small></span>
      </div>
      <div class="p-[25px_30px] border-r border-line flex items-center gap-5 max-[980px]:border-r-0 max-[980px]:border-b">
        <svg class="w-8 h-8 text-pink-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 14 4-4"></path><path d="M3.34 19a10 10 0 1 1 17.32 0"></path></svg>
        <span><strong class="block text-[15px] text-navy-950 mb-1">เน้นทักษะที่ควรเร่งพัฒนา</strong><small class="block text-muted text-[13px]">ใช้เวลาเรียนได้ตรงจุดขึ้น</small></span>
      </div>
      <div class="p-[25px_30px] flex items-center gap-5">
        <svg class="w-8 h-8 text-pink-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
        <span><strong class="block text-[15px] text-navy-950 mb-1">ใช้เปรียบเทียบพัฒนาการ</strong><small class="block text-muted text-[13px]">วัดซ้ำหลังเรียนเพื่อดูความก้าวหน้า</small></span>
      </div>
    </div>
  </section>

  <!-- ── Test Workspace: My Tests, Test Doing, Test Results ── -->
  <section class="py-10 pb-20 bg-[#f6f8fc]" id="test-workspace">
    <div class="container" data-test-app>
      
      <!-- STEP 1: My Tests (List) -->
      <div class="grid grid-cols-[300px_1fr] items-start gap-6 max-[1024px]:grid-cols-1" data-test-step="list">
        <!-- Sidebar: Filters -->
        <aside class="sticky top-[98px] max-[1024px]:static bg-white rounded-[20px] shadow-[0_4px_24px_rgba(15,42,83,0.04)] border border-[#e8ecf2] p-5">
          <h3 class="text-[16px] font-bold text-navy-950 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            ตัวกรองข้อสอบ
          </h3>
          <div class="mb-5">
            <strong class="block text-[13px] text-[#65738a] mb-2">วิชาเรียน</strong>
            <div class="flex flex-col gap-2 text-[14px] text-navy-900 font-medium">
              <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="subject" value="english" class="accent-pink-500 w-4 h-4" onchange="filterPlacementTests()"> ภาษาอังกฤษ</label>
              <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="subject" value="biology" class="accent-pink-500 w-4 h-4" onchange="filterPlacementTests()"> ชีววิทยา</label>
              <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="subject" value="chemistry" class="accent-pink-500 w-4 h-4" onchange="filterPlacementTests()"> เคมี</label>
              <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="subject" value="ทั่วไป" class="accent-pink-500 w-4 h-4" onchange="filterPlacementTests()"> ทั่วไป</label>
            </div>
          </div>
          <div>
            <strong class="block text-[13px] text-[#65738a] mb-2">ประเภทข้อสอบ</strong>
            <div class="flex flex-col gap-2 text-[14px] text-navy-900 font-medium">
              <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="type" value="placement" class="accent-pink-500 w-4 h-4" onchange="filterPlacementTests()"> วัดระดับ (Placement)</label>
              <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="type" value="pretest" class="accent-pink-500 w-4 h-4" onchange="filterPlacementTests()"> ทดสอบก่อนเรียน (Pre-test)</label>
              <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="type" value="posttest" class="accent-pink-500 w-4 h-4" onchange="filterPlacementTests()"> หลังเรียน (Post-test)</label>
              <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="type" value="quiz" class="accent-pink-500 w-4 h-4" onchange="filterPlacementTests()"> แบบทดสอบ (Quiz)</label>
            </div>
          </div>
        </aside>
        
        <!-- Main Content: Test List -->
        <main>
          <!-- Tabs -->
          <div class="flex items-center gap-6 border-b border-[#e8ecf2] mb-6 text-[15px] font-bold">
            <button type="button" data-tests-tab="available" class="pb-3 text-[#2369dd] border-b-[3px] border-[#2369dd]">แบบทดสอบ (<span data-available-count>0</span>)</button>
            <button type="button" data-tests-tab="history" class="pb-3 text-[#65738a] hover:text-navy-950 border-b-[3px] border-transparent transition-colors">คะแนนย้อนหลัง (<span data-history-count>0</span>)</button>
          </div>
          
          <div class="flex flex-col gap-4">
            <!-- Item 1 -->
            <div id="placement-tests-container" class="space-y-4">
              <!-- Rendered by JS -->
            </div>
            <div id="test-history-container" class="hidden space-y-4"></div>
        </main>
      </div>

      <!-- Filled from the selected exam; no example score or question data is rendered. -->
      <div class="hidden" data-test-step="doing"></div>
      <div class="hidden" data-test-step="result"></div>
      
    </div>
  </section>

  <!-- Script for toggling steps -->
  <!-- Script for toggling steps and filtering -->
  <script>
    function switchTestStep(step) {
      document.querySelectorAll('[data-test-step]').forEach(el => {
        if(el.getAttribute('data-test-step') === step) {
          el.classList.remove('hidden');
        } else {
          el.classList.add('hidden');
        }
      });
      window.scrollTo({ top: document.getElementById('test-workspace')?.offsetTop - 80, behavior: 'smooth' });
    }

    function filterPlacementTests() {
      const selectedSubjects = Array.from(document.querySelectorAll('input[name="subject"]:checked')).map(cb => cb.value);
      const selectedTypes = Array.from(document.querySelectorAll('input[name="type"]:checked')).map(cb => cb.value);
      
      document.querySelectorAll('[data-test-item]').forEach(item => {
        const subjectMatch = selectedSubjects.length === 0 || selectedSubjects.includes(item.getAttribute('data-subject'));
        const typeMatch = selectedTypes.length === 0 || selectedTypes.includes(item.getAttribute('data-type'));
        
        if (subjectMatch && typeMatch) {
          item.style.display = 'flex';
        } else {
          item.style.display = 'none';
        }
      });
    }

    // Run filter on initial load
    document.addEventListener('DOMContentLoaded', filterPlacementTests);
  </script>

  <section class="py-[100px] bg-white">
    <div class="container text-center">
      <p class="flex items-center justify-center gap-4 text-[12px] font-black tracking-[0.15em] text-pink-500 mb-6 uppercase">
        <span class="w-[30px] h-[1px] bg-pink-500"></span>
        HOW IT WORKS
        <span class="w-[30px] h-[1px] bg-pink-500"></span>
      </p>
      <h2 class="text-[clamp(32px,4vw,44px)] font-bold text-navy-950 mb-[50px] tracking-[-0.03em]">จากแบบทดสอบสู่แผนการเรียน</h2>
      
      <div class="grid grid-cols-4 gap-6 mb-12 max-[980px]:grid-cols-2 max-[680px]:grid-cols-1 text-left">
        <!-- Step 1 -->
        <div class="bg-white border border-[#e2e8f0] rounded-[24px] p-7 shadow-[0_4px_24px_rgba(0,0,0,0.02)] transition-transform hover:-translate-y-1">
          <div class="flex items-start justify-between mb-16">
            <span class="w-10 h-10 rounded-full border-2 border-pink-500 text-pink-500 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </span>
            <span class="text-[28px] font-black text-[#cbd5e1]">01</span>
          </div>
          <h3 class="text-[17px] font-bold text-navy-950 mb-2">เลือกชุดที่เหมาะ</h3>
          <p class="text-[#6b7b93] text-[12px] leading-relaxed">เลือกวิชาและระดับที่ตรงกับผู้เรียน</p>
        </div>
        
        <!-- Step 2 -->
        <div class="bg-white border border-[#e2e8f0] rounded-[24px] p-7 shadow-[0_4px_24px_rgba(0,0,0,0.02)] transition-transform hover:-translate-y-1">
          <div class="flex items-start justify-between mb-16">
            <span class="w-10 h-10 rounded-lg border-2 border-pink-500 text-pink-500 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="16" r="1"></circle><path d="M12 8a2 2 0 0 1 2 2c0 1-1.5 1.5-1.5 1.5"></path></svg>
            </span>
            <span class="text-[28px] font-black text-[#cbd5e1]">02</span>
          </div>
          <h3 class="text-[17px] font-bold text-navy-950 mb-2">ทำแบบประเมิน</h3>
          <p class="text-[#6b7b93] text-[12px] leading-relaxed">ค่อย ๆ อ่านและตอบตามความเข้าใจจริง</p>
        </div>

        <!-- Step 3 -->
        <div class="bg-white border border-[#e2e8f0] rounded-[24px] p-7 shadow-[0_4px_24px_rgba(0,0,0,0.02)] transition-transform hover:-translate-y-1">
          <div class="flex items-start justify-between mb-16">
            <span class="text-pink-500 shrink-0">
              <svg class="w-10 h-10" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line><line x1="2" y1="20" x2="22" y2="20"></line></svg>
            </span>
            <span class="text-[28px] font-black text-[#cbd5e1]">03</span>
          </div>
          <h3 class="text-[17px] font-bold text-navy-950 mb-2">ดูภาพรวมผล</h3>
          <p class="text-[#6b7b93] text-[12px] leading-relaxed">เห็นทั้งจุดแข็งและหัวข้อที่ควรเสริม</p>
        </div>

        <!-- Step 4 -->
        <div class="bg-white border border-[#e2e8f0] rounded-[24px] p-7 shadow-[0_4px_24px_rgba(0,0,0,0.02)] transition-transform hover:-translate-y-1">
          <div class="flex items-start justify-between mb-16">
            <span class="w-10 h-10 rounded-full border-2 border-pink-500 text-pink-500 flex items-center justify-center shrink-0">
              <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"></circle><circle cx="12" cy="12" r="1"></circle></svg>
            </span>
            <span class="text-[28px] font-black text-[#cbd5e1]">04</span>
          </div>
          <h3 class="text-[17px] font-bold text-navy-950 mb-2">วางแผนต่อ</h3>
          <p class="text-[#6b7b93] text-[12px] leading-relaxed">ใช้ผลเพื่อเลือกคอร์สและลำดับการเรียน</p>
        </div>
      </div>
      
      <a class="inline-flex items-center justify-center min-w-[200px] h-[52px] px-8 rounded-full text-white bg-pink-500 font-bold text-[14px] transition-all hover:-translate-y-0.5 hover:shadow-[0_10px_20px_rgba(231,45,130,0.3)] shadow-[0_4px_14px_rgba(231,45,130,0.4)]" href="learning-path.php">
        ดูตัวอย่างเส้นทางการเรียน &rarr;
      </a>
    </div>
  </section>
</main>

<?php include 'includes/footer.php'; ?>
