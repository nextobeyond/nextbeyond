<?php
$pageTitle = "บทเรียน | Nextbeyond Compass";
$pageDesc = "ห้องเรียนออนไลน์";
$isLoggedIn = true; // Simulate logged-in user
include 'includes/head.php';
include 'includes/header.php';
?>

<main class="bg-[#f6f8fc] min-h-screen pb-[100px]">
  
  <!-- Breadcrumb -->
  <div class="bg-white border-b border-[#e8ecf2]">
    <div class="container py-3.5 text-[13px] text-[#65738a] font-medium">
      <a href="#" class="hover:text-[#2369dd] transition-colors">คอร์สของฉัน</a> 
      <span class="mx-2 text-[#cbd5e1]">/</span> 
      <a href="#" class="hover:text-[#2369dd] transition-colors">English Communication Starter</a> 
      <span class="mx-2 text-[#cbd5e1]">/</span> 
      <span class="text-navy-950 font-bold">Lesson 4</span>
    </div>
  </div>

  <div class="container mt-6">
    <div class="grid grid-cols-[1fr_340px] gap-6 max-[1024px]:grid-cols-1 items-start">
      
      <!-- Left Column: Video Player -->
      <div class="flex flex-col">
        <div class="bg-white rounded-[16px] shadow-[0_4px_24px_rgba(15,42,83,0.04)] border border-[#e8ecf2] overflow-hidden mb-6">
          <div class="p-6 pb-5">
            <span class="inline-block text-[11px] font-black text-[#2369dd] tracking-[0.15em] uppercase mb-1.5 bg-[#e6f0ff] px-2.5 py-1 rounded-[6px]">LESSON 4 OF 8</span>
            <h1 class="text-[24px] font-bold text-navy-950 leading-tight">Asking for Help</h1>
          </div>
          
          <!-- Video Box Placeholder -->
          <div class="aspect-video bg-navy-950 relative flex items-center justify-center group overflow-hidden border-y border-[#1b2f4f]">
            <img src="https://images.unsplash.com/photo-1577896851231-70ef18881754?q=80&w=1200&auto=format&fit=crop" class="absolute inset-0 w-full h-full object-cover opacity-60 transition-transform duration-700 group-hover:scale-105" alt="Video cover">
            
            <!-- Custom Player Controls UI (Simulated) -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent flex flex-col justify-end p-5 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              <div class="w-full h-1.5 bg-white/30 rounded-full mb-4 cursor-pointer relative overflow-hidden group/timeline">
                <div class="absolute top-0 left-0 h-full w-[45%] bg-[#ff4a7a] rounded-full relative">
                  <div class="absolute right-0 top-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full shadow-md scale-0 group-hover/timeline:scale-100 transition-transform origin-center translate-x-1/2"></div>
                </div>
              </div>
              <div class="flex items-center justify-between text-white">
                <div class="flex items-center gap-5">
                  <button class="hover:text-[#ff4a7a] transition-colors"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></button>
                  <div class="flex items-center gap-3">
                    <button class="hover:text-[#ff4a7a] transition-colors"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg></button>
                  </div>
                  <span class="text-[13px] font-medium tracking-wide">12:34 / 28:00</span>
                </div>
                <div class="flex items-center gap-5">
                  <button class="text-[13px] font-bold hover:text-[#ff4a7a] transition-colors bg-white/20 px-2 py-0.5 rounded-[4px]">1.0x</button>
                  <button class="hover:text-[#ff4a7a] transition-colors"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg></button>
                </div>
              </div>
            </div>
            
            <!-- Big Play Button -->
            <button class="w-[72px] h-[72px] rounded-full bg-[#ff4a7a]/90 text-white flex items-center justify-center shadow-lg transition-transform hover:scale-110 z-10 backdrop-blur-sm">
              <svg class="w-8 h-8 ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            </button>
          </div>
          
          <div class="p-5 flex items-center justify-between max-[480px]:flex-col max-[480px]:gap-3 max-[480px]:items-stretch bg-[#f8fafc]">
            <button class="flex items-center justify-center gap-2 h-[44px] px-5 border-2 border-[#e8ecf2] rounded-full text-[14px] font-bold text-[#65738a] hover:text-navy-950 hover:border-[#cbd5e1] hover:bg-white transition-colors">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
              บทก่อนหน้า
            </button>
            <button class="flex items-center justify-center gap-2 h-[44px] px-5 bg-[#2369dd] rounded-full text-[14px] font-bold text-white shadow-[0_4px_12px_rgba(35,105,221,0.3)] hover:-translate-y-0.5 hover:shadow-[0_6px_16px_rgba(35,105,221,0.4)] transition-all">
              ทำกิจกรรมท้ายบท
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
          </div>
        </div>
        
        <!-- Tabs Section -->
        <div class="bg-white rounded-[16px] shadow-[0_4px_24px_rgba(15,42,83,0.04)] border border-[#e8ecf2] overflow-hidden">
          <div class="flex items-center border-b border-[#e8ecf2] px-2 overflow-x-auto hide-scrollbar bg-[#f8fafc]">
            <button class="whitespace-nowrap px-6 py-4 text-[14px] font-bold text-[#2369dd] border-b-[3px] border-[#2369dd] bg-white">รายละเอียดบทเรียน</button>
            <button class="whitespace-nowrap px-6 py-4 text-[14px] font-bold text-[#65738a] hover:text-navy-950 border-b-[3px] border-transparent transition-colors">เอกสาร</button>
            <button class="whitespace-nowrap px-6 py-4 text-[14px] font-bold text-[#65738a] hover:text-navy-950 border-b-[3px] border-transparent transition-colors">แบบฝึก</button>
            <button class="whitespace-nowrap px-6 py-4 text-[14px] font-bold text-[#65738a] hover:text-navy-950 border-b-[3px] border-transparent transition-colors">ถามครู</button>
          </div>
          <div class="p-8">
            <div class="grid grid-cols-2 gap-8 max-[640px]:grid-cols-1">
              <div>
                <h3 class="text-[18px] font-bold text-navy-950 mb-3">สิ่งที่จะได้เรียน</h3>
                <p class="text-[15px] text-[#65738a] leading-relaxed mb-4">ในบทเรียนนี้เราจะมาฝึกฝนการใช้ประโยคขอความช่วยเหลือในสถานการณ์ต่างๆ เช่น การถามทาง การขอให้ช่วยถือของ หรือการขอความช่วยเหลือเมื่อเกิดเหตุฉุกเฉิน</p>
                <ul class="space-y-3 text-[14px] text-[#65738a]">
                  <li class="flex items-start gap-2.5">
                    <div class="w-5 h-5 rounded-full bg-[#e6f0ff] flex items-center justify-center flex-shrink-0 mt-0.5">
                      <svg class="w-3 h-3 text-[#2369dd]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </div> 
                    <span>ประโยค Can you help me...? แบบสุภาพและเป็นธรรมชาติ</span>
                  </li>
                  <li class="flex items-start gap-2.5">
                    <div class="w-5 h-5 rounded-full bg-[#e6f0ff] flex items-center justify-center flex-shrink-0 mt-0.5">
                      <svg class="w-3 h-3 text-[#2369dd]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </div> 
                    <span>การตอบรับ (Sure, I'd love to) และการปฏิเสธอย่างสุภาพ (I'm sorry, but...)</span>
                  </li>
                  <li class="flex items-start gap-2.5">
                    <div class="w-5 h-5 rounded-full bg-[#e6f0ff] flex items-center justify-center flex-shrink-0 mt-0.5">
                      <svg class="w-3 h-3 text-[#2369dd]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </div> 
                    <span>คำศัพท์ที่จำเป็นเกี่ยวกับสถานที่และทิศทางเบื้องต้น</span>
                  </li>
                </ul>
              </div>
              <div>
                <div class="bg-[#f8fafc] border border-[#e8ecf2] rounded-[16px] p-5 shadow-sm">
                  <h3 class="text-[16px] font-bold text-navy-950 mb-4">ไฟล์ประกอบการเรียน</h3>
                  <div class="flex items-center justify-between bg-white border border-[#e8ecf2] rounded-[12px] p-3.5 shadow-sm transition-shadow hover:shadow-md cursor-pointer group">
                    <div class="flex items-center gap-3.5">
                      <div class="w-[42px] h-[42px] bg-[#fee2e2] text-[#ef4444] rounded-[10px] flex items-center justify-center font-bold text-[12px]">PDF</div>
                      <div>
                        <div class="text-[14px] font-bold text-navy-950 group-hover:text-[#2369dd] transition-colors">Worksheet_Lesson_04.pdf</div>
                        <div class="text-[12px] text-[#94a3b8] mt-0.5">2.4 MB</div>
                      </div>
                    </div>
                    <button class="w-9 h-9 rounded-full border-2 border-[#e8ecf2] flex items-center justify-center text-[#65738a] hover:bg-[#2369dd] hover:text-white hover:border-[#2369dd] transition-colors">
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: Sidebar Playlist -->
      <aside class="sticky top-[100px]">
        <div class="bg-white rounded-[16px] shadow-[0_4px_24px_rgba(15,42,83,0.04)] border border-[#e8ecf2] overflow-hidden flex flex-col max-h-[calc(100vh-120px)]">
          <div class="p-6 border-b border-[#e8ecf2] bg-white relative z-10 shadow-sm">
            <h3 class="text-[18px] font-black text-navy-950 mb-1.5">เนื้อหาในคอร์ส</h3>
            <div class="flex items-center justify-between text-[13px] text-[#65738a]">
              <span>เรียนจบแล้ว 3/8 บท</span>
              <span class="font-bold text-[#10b981]">38%</span>
            </div>
            <div class="w-full h-1.5 bg-[#f1f5f9] rounded-full mt-3 overflow-hidden">
              <div class="h-full bg-[#10b981] rounded-full" style="width: 38%"></div>
            </div>
          </div>
          
          <div class="overflow-y-auto hide-scrollbar p-3">
            <!-- Completed -->
            <a href="#" class="flex items-start gap-3.5 p-3 rounded-[10px] hover:bg-[#f8fafc] transition-colors group">
              <div class="w-5 h-5 rounded-full bg-[#10b981] text-white flex items-center justify-center flex-shrink-0 mt-0.5 shadow-sm">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3.5" d="M5 13l4 4L19 7"/></svg>
              </div>
              <div>
                <div class="text-[13px] font-bold text-[#65738a] group-hover:text-navy-950 transition-colors">Lesson 1 · Greetings</div>
                <div class="text-[11.5px] font-medium text-[#94a3b8] mt-1">14:20</div>
              </div>
            </a>
            
            <a href="#" class="flex items-start gap-3.5 p-3 rounded-[10px] hover:bg-[#f8fafc] transition-colors group">
              <div class="w-5 h-5 rounded-full bg-[#10b981] text-white flex items-center justify-center flex-shrink-0 mt-0.5 shadow-sm">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3.5" d="M5 13l4 4L19 7"/></svg>
              </div>
              <div>
                <div class="text-[13px] font-bold text-[#65738a] group-hover:text-navy-950 transition-colors">Lesson 2 · Personal Info</div>
                <div class="text-[11.5px] font-medium text-[#94a3b8] mt-1">18:45</div>
              </div>
            </a>
            
            <a href="#" class="flex items-start gap-3.5 p-3 rounded-[10px] hover:bg-[#f8fafc] transition-colors group">
              <div class="w-5 h-5 rounded-full bg-[#10b981] text-white flex items-center justify-center flex-shrink-0 mt-0.5 shadow-sm">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3.5" d="M5 13l4 4L19 7"/></svg>
              </div>
              <div>
                <div class="text-[13px] font-bold text-[#65738a] group-hover:text-navy-950 transition-colors">Lesson 3 · Daily Routine</div>
                <div class="text-[11.5px] font-medium text-[#94a3b8] mt-1">22:10</div>
              </div>
            </a>

            <!-- Active -->
            <div class="flex items-start gap-3.5 p-3.5 rounded-[12px] bg-[#e6f0ff] border border-[#bfdbfe] relative my-2 shadow-[0_4px_12px_rgba(35,105,221,0.08)]">
              <div class="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-[60%] bg-[#2369dd] rounded-r-full"></div>
              <div class="w-5 h-5 rounded-full bg-[#2369dd] text-white flex items-center justify-center flex-shrink-0 mt-0.5 shadow-md shadow-blue-500/30">
                <svg class="w-2.5 h-2.5 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
              </div>
              <div>
                <div class="text-[13.5px] font-bold text-[#2369dd]">Lesson 4 · Asking for Help</div>
                <div class="text-[11.5px] font-bold text-[#60a5fa] mt-1">28:00 (กำลังเรียน)</div>
              </div>
            </div>

            <!-- Upcoming -->
            <a href="#" class="flex items-start gap-3.5 p-3 rounded-[10px] hover:bg-[#f8fafc] transition-colors group">
              <div class="w-5 h-5 rounded-full border-[2.5px] border-[#cbd5e1] flex items-center justify-center flex-shrink-0 mt-0.5 group-hover:border-[#94a3b8] transition-colors"></div>
              <div>
                <div class="text-[13px] font-bold text-[#65738a] group-hover:text-navy-950 transition-colors">Lesson 5 · Clarification</div>
                <div class="text-[11.5px] font-medium text-[#94a3b8] mt-1">20:15</div>
              </div>
            </a>
            
            <a href="#" class="flex items-start gap-3.5 p-3 rounded-[10px] hover:bg-[#f8fafc] transition-colors group">
              <div class="w-5 h-5 rounded-full border-[2.5px] border-[#cbd5e1] flex items-center justify-center flex-shrink-0 mt-0.5 group-hover:border-[#94a3b8] transition-colors"></div>
              <div>
                <div class="text-[13px] font-bold text-[#65738a] group-hover:text-navy-950 transition-colors">Lesson 6 · Pronunciation</div>
                <div class="text-[11.5px] font-medium text-[#94a3b8] mt-1">15:30</div>
              </div>
            </a>
            
            <a href="#" class="flex items-start gap-3.5 p-3 rounded-[10px] hover:bg-[#f8fafc] transition-colors group">
              <div class="w-5 h-5 rounded-full border-[2.5px] border-[#cbd5e1] flex items-center justify-center flex-shrink-0 mt-0.5 group-hover:border-[#94a3b8] transition-colors"></div>
              <div>
                <div class="text-[13px] font-bold text-[#65738a] group-hover:text-navy-950 transition-colors">Lesson 7 · Final Practice</div>
                <div class="text-[11.5px] font-medium text-[#94a3b8] mt-1">25:00</div>
              </div>
            </a>
            
            <a href="#" class="flex items-start gap-3.5 p-3 rounded-[10px] bg-[#f8fafc] border border-[#f1f5f9] mt-2 group opacity-80 cursor-not-allowed">
              <div class="w-5 h-5 rounded-full border-[2.5px] border-[#cbd5e1] flex items-center justify-center flex-shrink-0 mt-0.5 bg-white">
                <svg class="w-2.5 h-2.5 text-[#cbd5e1]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              </div>
              <div>
                <div class="text-[13px] font-bold text-[#94a3b8]">Lesson 8 · Post-test</div>
                <div class="text-[11.5px] font-medium text-[#cbd5e1] mt-1">ทำแบบทดสอบเมื่อเรียนจบ</div>
              </div>
            </a>
            
          </div>
        </div>
      </aside>
      
    </div>
  </div>
</main>

<?php include 'includes/footer.php'; ?>
<script>
  // Simulated lesson & course IDs for the mockup page
  const lessonId = <?= (int)($_GET['id'] ?? 1) ?>;
  const courseId = <?= (int)($_GET['course_id'] ?? 1) ?>;

  if (lessonId && courseId) {
    const apiPath = 'student/lesson-progress-api.php';
    
    // Start tracking
    fetch(apiPath, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'start', lesson_id: lessonId, course_id: courseId })
    }).catch(console.error);

    // Heartbeat every 30 seconds
    setInterval(() => {
      fetch(apiPath, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'heartbeat', lesson_id: lessonId, course_id: courseId })
      }).catch(console.error);
    }, 30000);

    // Bind "ทำกิจกรรมท้ายบท" button as completion trigger
    const completeBtn = document.querySelector('button.bg-\\[\\#2369dd\\]');
    if (completeBtn) {
      completeBtn.addEventListener('click', () => {
        fetch(apiPath, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'complete', lesson_id: lessonId, course_id: courseId })
        }).then(res => res.json()).then(data => {
            if (data.success) {
                alert('บันทึกความสำเร็จแล้ว! ความคืบหน้าจะถูกอัปเดตใน Roadmap (ถ้ามีภารกิจที่เกี่ยวข้อง)');
                // Go to next lesson or test...
            }
        }).catch(console.error);
      });
    }
  }
</script>
