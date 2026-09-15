<?php
$pageTitle = "เข้าสู่ระบบ / สมัครสมาชิก | Nextbeyond Compass";
$pageDesc = "เข้าสู่ระบบหรือสมัครบัญชีนักเรียนใหม่กับ Nextbeyond Compass";
$currentPage = 'auth.php';
$authScriptVersion = (string) filemtime(__DIR__ . '/assets/js/auth.js');
$extraHead = '<script defer src="assets/js/auth.js?v=' . $authScriptVersion . '"></script>';
include 'includes/head.php';
include 'includes/header.php';
?>

<main id="main" data-auth-app>

  <!-- ══════════════════════════════════════════════
       STEP 0 : LOGIN
  ═══════════════════════════════════════════════ -->
  <section data-step="login" class="min-h-[calc(100vh-78px)] bg-[#f6f8fc] flex items-center justify-center py-16 px-4">
    <div class="w-full max-w-[440px]">

      <!-- Logo -->
      <div class="text-center mb-8 flex flex-col items-center">
        <a href="index.php" class="inline-flex items-center gap-3 mb-6" aria-label="Nextbeyond Compass">
          <svg class="w-[46px] h-[46px] shrink-0" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <line x1="25" y1="32" x2="31" y2="8" stroke="#f54696" stroke-width="8" stroke-linecap="round" />
            <clipPath id="logo-clip-auth">
              <rect x="0" y="9" width="40" height="22" />
            </clipPath>
            <g clip-path="url(#logo-clip-auth)">
              <path d="M 8 36 L 15 4 L 27 36" fill="none" stroke="#0f172a" stroke-width="8.5" stroke-linejoin="miter" stroke-miterlimit="8" />
            </g>
          </svg>
          <span class="grid leading-[1.15] text-left">
            <strong class="text-navy-950 text-[18px] tracking-[0.04em]">NEXTBEYOND</strong>
            <small class="text-[#65738a] text-[11px] font-bold tracking-[0.18em]">COMPASS</small>
          </span>
        </a>
        <h1 class="text-[26px] font-bold text-navy-950 tracking-tight">ยินดีต้อนรับกลับ</h1>
        <p class="text-[#65738a] text-[15px]">เข้าสู่ระบบเพื่อเรียนต่อจากที่ค้างไว้</p>
      </div>

      <!-- Login Card -->
      <div class="border border-[#dce4ef] rounded-[22px] bg-white p-7 shadow-[0_12px_32px_rgba(15,42,83,.08)]">

        <!-- Tabs -->
        <div class="flex border-b border-[#e8ecf2] mb-6">
          <button class="flex-1 py-3 text-center text-[14px] font-bold border-b-2 border-pink-500 text-navy-950 transition-colors" data-tab="login-tab" data-active>เข้าสู่ระบบ</button>
          <button class="flex-1 py-3 text-center text-[14px] font-bold border-b-2 border-transparent text-[#94a3b8] hover:text-navy-950 transition-colors" data-tab="register-tab">สมัครสมาชิก</button>
        </div>

        <!-- Login Tab Content -->
        <div data-tab-content="login-tab">
          <div class="mb-4">
            <label class="block mb-1.5 text-[14px] font-bold text-navy-900" for="login-email">อีเมลหรือเบอร์โทรศัพท์</label>
            <input class="w-full h-12 px-4 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] focus:shadow-[0_0_0_3px_rgba(57,129,245,.14)] text-[15px]" type="text" id="login-email" placeholder="example@email.com">
          </div>
          <div class="mb-4">
            <label class="block mb-1.5 text-[14px] font-bold text-navy-900" for="login-password">รหัสผ่าน</label>
            <input class="w-full h-12 px-4 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] focus:shadow-[0_0_0_3px_rgba(57,129,245,.14)] text-[15px]" type="password" id="login-password" placeholder="••••••••">
          </div>
          <div class="flex items-center justify-between mb-6">
            <label class="flex items-center gap-2 text-[13px] text-[#65738a] cursor-pointer">
              <input type="checkbox" class="w-4 h-4 rounded border-[#dce4ef] accent-pink-500"> จดจำฉัน
            </label>
            <a href="#" class="text-[13px] text-[#2369dd] font-medium hover:underline">ลืมรหัสผ่าน?</a>
          </div>
          <button class="w-full h-12 rounded-xl bg-pink-500 text-white font-bold text-[15px] shadow-[0_8px_20px_rgba(231,45,130,.2)] transition-transform hover:-translate-y-0.5 mb-4" data-action="login">เข้าสู่ระบบ</button>
          <div class="text-center text-[13px] text-[#94a3b8] mb-4 hidden">หรือ</div>
          <button class="w-full h-12 rounded-xl border border-[#dce4ef] bg-white text-navy-950 font-bold text-[14px] flex items-center justify-center gap-3 transition-transform hover:-translate-y-0.5 hover:border-[#b8c5d8] hidden">
            <svg class="w-5 h-5" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
            เข้าสู่ระบบด้วย Google
          </button>
        </div>

        <!-- Register Tab Content (hidden by default) -->
        <div data-tab-content="register-tab" class="hidden">
          <div class="text-center py-6">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-pink-50 flex items-center justify-center">
              <svg class="w-8 h-8 text-pink-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM3 20a6 6 0 0 1 12 0v1H3v-1Z"/></svg>
            </div>
            <h3 class="text-[20px] font-bold text-navy-950 mb-2">สร้างบัญชีนักเรียนใหม่</h3>
            <p class="text-[#65738a] text-[14px] mb-6 max-w-[320px] mx-auto">กรอกข้อมูลเพียง 4 ขั้นตอน เพื่อเริ่มเรียนกับ Nextbeyond Compass</p>
            <button class="w-full h-12 rounded-xl bg-pink-500 text-white font-bold text-[15px] shadow-[0_8px_20px_rgba(231,45,130,.2)] transition-transform hover:-translate-y-0.5" data-goto="student">เริ่มสมัครสมาชิก →</button>
            <p class="text-[12px] text-[#94a3b8] mt-4">ผู้ปกครองสามารถช่วยสมัครและดูแลบัญชีให้นักเรียนได้</p>
          </div>
        </div>
      </div>

      <!-- Bottom Link -->
      <div class="text-center mt-6">
        <p class="text-[13px] text-[#94a3b8]">ยังไม่มีบัญชี? <button class="text-pink-500 font-bold hover:underline" data-goto="student">สมัครเลย</button></p>
      </div>
    </div>
  </section>

  <!-- ══════════════════════════════════════════════
       STEP 1 : STUDENT INFO
  ═══════════════════════════════════════════════ -->
  <section data-step="student" class="hidden bg-[#f6f8fc] py-10 pb-20">
    <div class="container max-w-[980px]">
      <p class="flex items-center gap-2.5 text-[12px] font-black tracking-[0.15em] text-[#2369dd] uppercase mb-1"><span class="w-6 h-[3px] rounded-full bg-pink-500"></span>STUDENT REGISTRATION · STEP 1</p>
      <h2 class="text-[28px] font-bold text-navy-950 tracking-tight mb-2">ข้อมูลนักเรียน</h2>
      <p class="text-[#65738a] text-[15px] mb-6">ข้อมูลส่วนนี้ใช้สร้างโปรไฟล์ผู้เรียน แนะนำคอร์ส และติดตามพัฒนาการให้ตรงระดับ</p>

      <!-- Stepper -->
      <div class="flex items-center gap-2 mb-8 overflow-x-auto pb-2" data-stepper>
        <div class="flex items-center gap-2 shrink-0"><span class="w-8 h-8 rounded-full bg-pink-500 text-white text-[13px] font-bold flex items-center justify-center">1</span><span class="text-[13px] font-bold text-navy-950">ข้อมูลนักเรียน</span></div>
        <div class="w-8 h-px bg-[#dce4ef] shrink-0"></div>
        <div class="flex items-center gap-2 shrink-0"><span class="w-8 h-8 rounded-full bg-[#e8ecf2] text-[#94a3b8] text-[13px] font-bold flex items-center justify-center">2</span><span class="text-[13px] text-[#94a3b8]">ข้อมูลผู้ปกครอง</span></div>
        <div class="w-8 h-px bg-[#dce4ef] shrink-0"></div>
        <div class="flex items-center gap-2 shrink-0"><span class="w-8 h-8 rounded-full bg-[#e8ecf2] text-[#94a3b8] text-[13px] font-bold flex items-center justify-center">3</span><span class="text-[13px] text-[#94a3b8]">ตั้งค่าบัญชี</span></div>
      </div>

      <div class="grid grid-cols-[1fr_320px] gap-6 items-start max-[1024px]:grid-cols-1">
        <!-- Form -->
        <div>
          <!-- Basic Info -->
          <div class="border border-[#dce4ef] rounded-[18px] bg-white p-6 shadow-[0_8px_24px_rgba(15,42,83,.06)] mb-4">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-[18px] font-bold text-navy-950">ข้อมูลพื้นฐาน</h3>
              <span class="text-[12px] text-[#94a3b8]">* จำเป็นต้องกรอก</span>
            </div>
            <div class="grid grid-cols-2 gap-4 max-[640px]:grid-cols-1">
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">ชื่อ *</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" type="text" data-field="firstName" placeholder="ชื่อจริง"></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">นามสกุล *</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" type="text" data-field="lastName" placeholder="นามสกุล"></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">ชื่อเล่น *</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" type="text" data-field="nickname" placeholder="ชื่อที่ต้องการให้ครูเรียก"></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">วันเดือนปีเกิด *</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" type="date" data-field="birthdate"></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">ระดับชั้นปัจจุบัน *</label><select class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" data-field="grade"><option value="">เลือกระดับชั้น</option><option>ป.4</option><option>ป.5</option><option>ป.6</option><option>ม.1</option><option>ม.2</option><option>ม.3</option><option>ม.4</option><option>ม.5</option><option>ม.6</option></select></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">โรงเรียน</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" type="text" data-field="school" placeholder="ชื่อโรงเรียน"></div>
            </div>
          </div>

          <!-- Goals -->
          <div class="border border-[#dce4ef] rounded-[18px] bg-white p-6 shadow-[0_8px_24px_rgba(15,42,83,.06)] mb-4">
            <h3 class="text-[18px] font-bold text-navy-950 mb-4">เป้าหมายการเรียน</h3>
            <label class="block mb-1.5 text-[13px] font-bold text-navy-900">วิชาที่สนใจ * เลือกได้มากกว่า 1 วิชา</label>
            <div class="flex flex-wrap gap-2 mb-4" data-chips="subjects">
              <button type="button" class="px-4 py-2 rounded-full border border-[#dce4ef] text-[13px] font-bold text-navy-950 bg-white transition-colors hover:border-pink-500 hover:text-pink-500 [&.active]:bg-pink-500 [&.active]:text-white [&.active]:border-pink-500" data-chip="ภาษาอังกฤษ">ภาษาอังกฤษ</button>
              <button type="button" class="px-4 py-2 rounded-full border border-[#dce4ef] text-[13px] font-bold text-navy-950 bg-white transition-colors hover:border-pink-500 hover:text-pink-500 [&.active]:bg-pink-500 [&.active]:text-white [&.active]:border-pink-500" data-chip="วิทยาศาสตร์">วิทยาศาสตร์</button>
              <button type="button" class="px-4 py-2 rounded-full border border-[#dce4ef] text-[13px] font-bold text-navy-950 bg-white transition-colors hover:border-pink-500 hover:text-pink-500 [&.active]:bg-pink-500 [&.active]:text-white [&.active]:border-pink-500" data-chip="คณิตศาสตร์">คณิตศาสตร์</button>
            </div>
            <label class="block mb-1.5 text-[13px] font-bold text-navy-900">เป้าหมายหลัก *</label>
            <select class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px] mb-4" data-field="goal"><option value="">เลือกเป้าหมาย</option><option>ปูพื้นฐาน</option><option>เพิ่มเกรด</option><option>เตรียมสอบ</option><option>สื่อสารได้</option></select>
            <label class="block mb-1.5 text-[13px] font-bold text-navy-900">สิ่งที่อยากพัฒนาเพิ่มเติม</label>
            <textarea class="w-full min-h-[80px] px-3.5 py-3 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px] resize-y" data-field="notes" placeholder="เช่น Grammar, Reading, คำนวณโจทย์ หรือการเตรียมสอบ"></textarea>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-between gap-4">
            <button class="h-[42px] px-5 rounded-xl border border-[#dce4ef] bg-white text-navy-950 font-bold text-[14px] transition-transform hover:-translate-y-0.5" data-goto="login">← กลับเข้าสู่ระบบ</button>
            <button class="h-[42px] px-6 rounded-xl bg-pink-500 text-white font-bold text-[14px] shadow-[0_8px_18px_rgba(231,45,130,.2)] transition-transform hover:-translate-y-0.5" data-goto="parent">บันทึกและไปขั้นถัดไป →</button>
          </div>
        </div>

        <!-- Sidebar -->
        <aside class="border border-[#dce4ef] rounded-[18px] bg-white p-6 shadow-[0_8px_24px_rgba(15,42,83,.06)] max-[1024px]:order-first">
          <h3 class="text-[16px] font-bold text-navy-950 mb-4">ทำไมต้องกรอกข้อมูลเหล่านี้?</h3>
          <div class="space-y-4">
            <div class="flex gap-3"><span class="w-8 h-8 shrink-0 rounded-lg bg-[#e8f1ff] flex items-center justify-center text-[#2369dd]"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span><div><p class="text-[13px] font-bold text-navy-950 mb-0.5">แนะนำคอร์สตรงระดับ</p><p class="text-[12px] text-[#65738a] mb-0">ระดับชั้นและเป้าหมายช่วยเลือกเนื้อหาที่เหมาะกับตัวคุณ</p></div></div>
            <div class="flex gap-3"><span class="w-8 h-8 shrink-0 rounded-lg bg-[#e8f1ff] flex items-center justify-center text-[#2369dd]"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span><div><p class="text-[13px] font-bold text-navy-950 mb-0.5">ข้อมูลปลอดภัย</p><p class="text-[12px] text-[#65738a] mb-0">เก็บเฉพาะในเบราว์เซอร์ ไม่ส่งออกภายนอก</p></div></div>
            <div class="flex gap-3"><span class="w-8 h-8 shrink-0 rounded-lg bg-[#e8f1ff] flex items-center justify-center text-[#2369dd]"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></span><div><p class="text-[13px] font-bold text-navy-950 mb-0.5">ติดตามพัฒนาการ</p><p class="text-[12px] text-[#65738a] mb-0">ดูความก้าวหน้าและรายงานผลให้ผู้ปกครอง</p></div></div>
          </div>
        </aside>
      </div>
    </div>
  </section>

  <!-- ══════════════════════════════════════════════
       STEP 2 : PARENT INFO
  ═══════════════════════════════════════════════ -->
  <section data-step="parent" class="hidden bg-[#f6f8fc] py-10 pb-20">
    <div class="container max-w-[980px]">
      <p class="flex items-center gap-2.5 text-[12px] font-black tracking-[0.15em] text-[#2369dd] uppercase mb-1"><span class="w-6 h-[3px] rounded-full bg-pink-500"></span>STUDENT REGISTRATION · STEP 2</p>
      <h2 class="text-[28px] font-bold text-navy-950 tracking-tight mb-2">ข้อมูลผู้ปกครอง</h2>
      <p class="text-[#65738a] text-[15px] mb-6">ใช้สำหรับติดต่อเรื่องตารางเรียน การชำระเงิน ความปลอดภัย และส่ง Feedback หลังเรียน</p>

      <!-- Stepper -->
      <div class="flex items-center gap-2 mb-8 overflow-x-auto pb-2">
        <div class="flex items-center gap-2 shrink-0"><span class="w-8 h-8 rounded-full bg-[#168765] text-white text-[13px] font-bold flex items-center justify-center">✓</span><span class="text-[13px] font-bold text-[#168765]">ข้อมูลนักเรียน</span></div>
        <div class="w-8 h-px bg-[#168765] shrink-0"></div>
        <div class="flex items-center gap-2 shrink-0"><span class="w-8 h-8 rounded-full bg-pink-500 text-white text-[13px] font-bold flex items-center justify-center">2</span><span class="text-[13px] font-bold text-navy-950">ข้อมูลผู้ปกครอง</span></div>
        <div class="w-8 h-px bg-[#dce4ef] shrink-0"></div>
        <div class="flex items-center gap-2 shrink-0"><span class="w-8 h-8 rounded-full bg-[#e8ecf2] text-[#94a3b8] text-[13px] font-bold flex items-center justify-center">3</span><span class="text-[13px] text-[#94a3b8]">ตั้งค่าบัญชี</span></div>
      </div>

      <div class="grid grid-cols-[1fr_320px] gap-6 items-start max-[1024px]:grid-cols-1">
        <div>
          <!-- Primary Guardian -->
          <div class="border border-[#dce4ef] rounded-[18px] bg-white p-6 shadow-[0_8px_24px_rgba(15,42,83,.06)] mb-4">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-[18px] font-bold text-navy-950">ผู้ปกครองหลัก</h3>
              <span class="text-[12px] text-[#94a3b8]">* จำเป็นสำหรับนักเรียนอายุต่ำกว่า 20 ปี</span>
            </div>
            <div class="grid grid-cols-2 gap-4 max-[640px]:grid-cols-1">
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">ชื่อ *</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" type="text" data-field="parentFirstName" placeholder="ชื่อจริงผู้ปกครอง"></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">นามสกุล *</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" type="text" data-field="parentLastName" placeholder="นามสกุลผู้ปกครอง"></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">ความสัมพันธ์ *</label><select class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" data-field="parentRelation"><option value="">เลือก</option><option>มารดา</option><option>บิดา</option><option>ผู้ดูแล</option></select></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">เบอร์โทรศัพท์ *</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" type="tel" data-field="parentPhone" placeholder="ใช้รับ OTP"></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">อีเมล *</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" type="email" data-field="parentEmail" placeholder="ใช้รับใบเสร็จและรายงาน"></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">LINE ID</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" type="text" data-field="parentLine" placeholder="สำหรับรับแจ้งเตือน"></div>
            </div>
            <label class="block mt-4 mb-1.5 text-[13px] font-bold text-navy-900">ช่องทางที่สะดวกให้ติดต่อ *</label>
            <div class="flex flex-wrap gap-2" data-chips="contact">
              <button type="button" class="px-4 py-2 rounded-full border border-[#dce4ef] text-[13px] font-bold text-navy-950 bg-white transition-colors [&.active]:bg-pink-500 [&.active]:text-white [&.active]:border-pink-500" data-chip="โทรศัพท์">โทรศัพท์</button>
              <button type="button" class="px-4 py-2 rounded-full border border-[#dce4ef] text-[13px] font-bold text-navy-950 bg-white transition-colors [&.active]:bg-pink-500 [&.active]:text-white [&.active]:border-pink-500" data-chip="LINE">LINE</button>
              <button type="button" class="px-4 py-2 rounded-full border border-[#dce4ef] text-[13px] font-bold text-navy-950 bg-white transition-colors [&.active]:bg-pink-500 [&.active]:text-white [&.active]:border-pink-500" data-chip="อีเมล">อีเมล</button>
            </div>
          </div>

          <!-- Emergency Contact -->
          <div class="border border-[#dce4ef] rounded-[18px] bg-white p-6 shadow-[0_8px_24px_rgba(15,42,83,.06)] mb-4">
            <h3 class="text-[18px] font-bold text-navy-950 mb-4">ที่อยู่และผู้ติดต่อฉุกเฉิน</h3>
            <label class="block mb-1.5 text-[13px] font-bold text-navy-900">ที่อยู่สำหรับติดต่อ</label>
            <textarea class="w-full min-h-[70px] px-3.5 py-3 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px] resize-y mb-4" data-field="address" placeholder="บ้านเลขที่ ถนน แขวง/ตำบล เขต/อำเภอ จังหวัด รหัสไปรษณีย์"></textarea>
            <div class="grid grid-cols-2 gap-4 max-[640px]:grid-cols-1">
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">ชื่อผู้ติดต่อฉุกเฉิน *</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" type="text" data-field="emergencyName" placeholder="กรณีติดต่อผู้ปกครองหลักไม่ได้"></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">ความสัมพันธ์</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" type="text" data-field="emergencyRelation" placeholder="ความสัมพันธ์กับนักเรียน"></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">เบอร์ฉุกเฉิน *</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" type="tel" data-field="emergencyPhone" placeholder="000-000-0000"></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">ช่วงเวลาที่สะดวก</label><select class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" data-field="emergencyTime"><option value="">เลือก</option><option>เช้า (8:00–12:00)</option><option>กลางวัน (12:00–17:00)</option><option>เย็น (17:00–21:00)</option></select></div>
            </div>
          </div>

          <!-- Notification Preferences -->
          <div class="border border-[#dce4ef] rounded-[18px] bg-[#f6f8fc] p-6 mb-4">
            <h3 class="text-[16px] font-bold text-navy-950 mb-3">การรับข้อมูลจากสถาบัน</h3>
            <div class="space-y-2.5">
              <label class="flex items-start gap-2.5 text-[13px] text-navy-950 cursor-pointer"><input type="checkbox" class="w-4 h-4 mt-0.5 rounded accent-pink-500" checked> รับตารางเรียนและการเปลี่ยนแปลงเวลาเรียน</label>
              <label class="flex items-start gap-2.5 text-[13px] text-navy-950 cursor-pointer"><input type="checkbox" class="w-4 h-4 mt-0.5 rounded accent-pink-500" checked> รับ Feedback และรายงานพัฒนาการ</label>
              <label class="flex items-start gap-2.5 text-[13px] text-navy-950 cursor-pointer"><input type="checkbox" class="w-4 h-4 mt-0.5 rounded accent-pink-500" checked> รับใบเสร็จและข้อมูลการชำระเงิน</label>
              <label class="flex items-start gap-2.5 text-[13px] text-[#65738a] cursor-pointer"><input type="checkbox" class="w-4 h-4 mt-0.5 rounded accent-pink-500"> รับข่าวสารและโปรโมชัน (ไม่บังคับ)</label>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-between gap-4">
            <button class="h-[42px] px-5 rounded-xl border border-[#dce4ef] bg-white text-navy-950 font-bold text-[14px] transition-transform hover:-translate-y-0.5" data-goto="student">← กลับ Step 1</button>
            <div class="flex items-center gap-2">
              <button class="h-[42px] px-5 rounded-xl border border-[#dce4ef] bg-white text-navy-950 font-bold text-[14px] transition-transform hover:-translate-y-0.5" data-goto="account">ข้ามขั้นตอนนี้</button>
              <button class="h-[42px] px-6 rounded-xl bg-pink-500 text-white font-bold text-[14px] shadow-[0_8px_18px_rgba(231,45,130,.2)] transition-transform hover:-translate-y-0.5" data-goto="account">บันทึกและไป Step 3 →</button>
            </div>
          </div>
        </div>

        <!-- Sidebar (same as step 1) -->
        <aside class="border border-[#dce4ef] rounded-[18px] bg-white p-6 shadow-[0_8px_24px_rgba(15,42,83,.06)] max-[1024px]:order-first">
          <h3 class="text-[16px] font-bold text-navy-950 mb-4">ทำไมต้องกรอกข้อมูลผู้ปกครอง?</h3>
          <div class="space-y-4">
            <div class="flex gap-3"><span class="w-8 h-8 shrink-0 rounded-lg bg-[#e8f1ff] flex items-center justify-center text-[#2369dd]"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg></span><div><p class="text-[13px] font-bold text-navy-950 mb-0.5">การติดต่อสำคัญ</p><p class="text-[12px] text-[#65738a] mb-0">แจ้งเปลี่ยนตาราง ส่ง Feedback และเรื่องฉุกเฉิน</p></div></div>
            <div class="flex gap-3"><span class="w-8 h-8 shrink-0 rounded-lg bg-[#e8f1ff] flex items-center justify-center text-[#2369dd]"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></span><div><p class="text-[13px] font-bold text-navy-950 mb-0.5">ใบเสร็จและรายงาน</p><p class="text-[12px] text-[#65738a] mb-0">ส่งเอกสารการเงินและพัฒนาการให้ผู้ปกครอง</p></div></div>
          </div>
        </aside>
      </div>
    </div>
  </section>

  <!-- ══════════════════════════════════════════════
       STEP 3 : ACCOUNT SETUP
  ═══════════════════════════════════════════════ -->
  <section data-step="account" class="hidden bg-[#f6f8fc] py-10 pb-20">
    <div class="container max-w-[980px]">
      <p class="flex items-center gap-2.5 text-[12px] font-black tracking-[0.15em] text-[#2369dd] uppercase mb-1"><span class="w-6 h-[3px] rounded-full bg-pink-500"></span>STUDENT REGISTRATION · STEP 3</p>
      <h2 class="text-[28px] font-bold text-navy-950 tracking-tight mb-2">ตั้งค่าบัญชี</h2>
      <p class="text-[#65738a] text-[15px] mb-6">เลือกผู้ใช้งานหลักและสร้างข้อมูลเข้าสู่ระบบ</p>

      <!-- Stepper -->
      <div class="flex items-center gap-2 mb-8 overflow-x-auto pb-2">
        <div class="flex items-center gap-2 shrink-0"><span class="w-8 h-8 rounded-full bg-[#168765] text-white text-[13px] font-bold flex items-center justify-center">✓</span><span class="text-[13px] font-bold text-[#168765]">ข้อมูลนักเรียน</span></div>
        <div class="w-8 h-px bg-[#168765] shrink-0"></div>
        <div class="flex items-center gap-2 shrink-0"><span class="w-8 h-8 rounded-full bg-[#168765] text-white text-[13px] font-bold flex items-center justify-center">✓</span><span class="text-[13px] font-bold text-[#168765]">ข้อมูลผู้ปกครอง</span></div>
        <div class="w-8 h-px bg-[#168765] shrink-0"></div>
        <div class="flex items-center gap-2 shrink-0"><span class="w-8 h-8 rounded-full bg-pink-500 text-white text-[13px] font-bold flex items-center justify-center">3</span><span class="text-[13px] font-bold text-navy-950">ตั้งค่าบัญชี</span></div>
      </div>

      <div class="grid grid-cols-[1fr_320px] gap-6 items-start max-[1024px]:grid-cols-1">
        <div>
          <!-- Account Owner -->
          <div class="border border-[#dce4ef] rounded-[18px] bg-white p-6 shadow-[0_8px_24px_rgba(15,42,83,.06)] mb-4">
            <h3 class="text-[18px] font-bold text-navy-950 mb-4">ใครเป็นผู้ดูแลบัญชีหลัก?</h3>
            <label class="block p-4 border-2 border-pink-500 rounded-xl bg-pink-50/50 mb-3 cursor-pointer">
              <div class="flex items-start gap-3"><input type="radio" name="accountOwner" value="parent" class="mt-1 accent-pink-500" checked><div><p class="text-[14px] font-bold text-navy-950 mb-0.5">ผู้ปกครองเป็นผู้ดูแล</p><p class="text-[12px] text-[#65738a] mb-0">เหมาะกับนักเรียนประถมและมัธยมต้น ผู้ปกครองเข้าสู่ระบบและติดตามผลได้</p></div></div>
            </label>
            <label class="block p-4 border border-[#dce4ef] rounded-xl bg-white cursor-pointer hover:border-[#b8c5d8]">
              <div class="flex items-start gap-3"><input type="radio" name="accountOwner" value="student" class="mt-1 accent-pink-500"><div><p class="text-[14px] font-bold text-navy-950 mb-0.5">นักเรียนดูแลบัญชีด้วยตนเอง</p><p class="text-[12px] text-[#65738a] mb-0">นักเรียนเข้าสู่ระบบด้วยอีเมลหรือเบอร์ของตน และยังเชื่อมผู้ปกครองรับรายงานได้</p></div></div>
            </label>
          </div>

          <!-- Login Credentials -->
          <div class="border border-[#dce4ef] rounded-[18px] bg-white p-6 shadow-[0_8px_24px_rgba(15,42,83,.06)] mb-4">
            <h3 class="text-[18px] font-bold text-navy-950 mb-4">ข้อมูลเข้าสู่ระบบ</h3>
            <label class="block mb-1.5 text-[13px] font-bold text-navy-900">อีเมลหรือเบอร์โทรศัพท์ *</label>
            <input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px] mb-1" type="text" data-field="accountEmail" placeholder="parent@example.com">
            <p class="text-[12px] text-[#65738a] mb-4">ใช้สำหรับเข้าสู่ระบบและกู้คืนบัญชี ควรเป็นช่องทางที่ใช้งานได้จริง</p>
            <div class="grid grid-cols-2 gap-4 max-[640px]:grid-cols-1">
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">รหัสผ่าน *</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" type="password" data-field="password" placeholder="••••••••"></div>
              <div><label class="block mb-1.5 text-[13px] font-bold text-navy-900">ยืนยันรหัสผ่าน *</label><input class="w-full h-[42px] px-3.5 border border-[#dce4ef] rounded-xl text-navy-950 bg-white outline-none focus:border-[#3981f5] text-[14px]" type="password" data-field="confirmPassword" placeholder="••••••••"></div>
            </div>
            <div class="mt-3 p-3 rounded-xl bg-[#f6f8fc] border border-[#e8ecf2]">
              <p class="text-[12px] text-[#65738a] mb-0">รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร และประกอบด้วยตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก และตัวเลข</p>
            </div>
          </div>

          <!-- Consent -->
          <div class="border border-[#dce4ef] rounded-[18px] bg-white p-6 shadow-[0_8px_24px_rgba(15,42,83,.06)] mb-4">
            <h3 class="text-[18px] font-bold text-navy-950 mb-3">ข้อกำหนดและความยินยอม</h3>
            <div class="space-y-2.5">
              <label class="flex items-start gap-2.5 text-[13px] text-navy-950 cursor-pointer"><input type="checkbox" class="w-4 h-4 mt-0.5 rounded accent-pink-500" required> ฉันเป็นผู้ปกครอง/ผู้ดูแลที่ได้รับอนุญาตให้สร้างบัญชีให้นักเรียน *</label>
              <label class="flex items-start gap-2.5 text-[13px] text-navy-950 cursor-pointer"><input type="checkbox" class="w-4 h-4 mt-0.5 rounded accent-pink-500" required> ยอมรับข้อกำหนดการใช้งานและนโยบายความเป็นส่วนตัว *</label>
              <label class="flex items-start gap-2.5 text-[13px] text-navy-950 cursor-pointer"><input type="checkbox" class="w-4 h-4 mt-0.5 rounded accent-pink-500" required> ยินยอมให้จัดเก็บและประมวลผลข้อมูลส่วนบุคคลเพื่อการจัดการเรียน (PDPA) *</label>
              <label class="flex items-start gap-2.5 text-[13px] text-[#65738a] cursor-pointer"><input type="checkbox" class="w-4 h-4 mt-0.5 rounded accent-pink-500"> ยินยอมรับข่าวสารและโปรโมชัน (ไม่บังคับ)</label>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-between gap-4">
            <button class="h-[42px] px-5 rounded-xl border border-[#dce4ef] bg-white text-navy-950 font-bold text-[14px] transition-transform hover:-translate-y-0.5" data-goto="parent">← กลับ Step 2</button>
            <button class="h-[42px] px-6 rounded-xl bg-pink-500 text-white font-bold text-[14px] shadow-[0_8px_18px_rgba(231,45,130,.2)] transition-transform hover:-translate-y-0.5" data-goto="success">ยืนยันและสร้างบัญชี →</button>
          </div>
        </div>

        <!-- Sidebar: Review -->
        <aside class="border border-[#dce4ef] rounded-[18px] bg-white p-6 shadow-[0_8px_24px_rgba(15,42,83,.06)] max-[1024px]:order-first">
          <h3 class="text-[16px] font-bold text-navy-950 mb-4">สรุปข้อมูลที่บันทึก</h3>
          <div class="space-y-3" data-review-summary>
            <div class="pb-3 border-b border-[#f1f5f9]"><p class="text-[11px] text-[#94a3b8] uppercase tracking-wider mb-0.5">นักเรียน</p><p class="text-[13px] font-bold text-navy-950 mb-0" data-review="studentName">—</p></div>
            <div class="pb-3 border-b border-[#f1f5f9]"><p class="text-[11px] text-[#94a3b8] uppercase tracking-wider mb-0.5">ระดับชั้น</p><p class="text-[13px] font-bold text-navy-950 mb-0" data-review="grade">—</p></div>
            <div class="pb-3 border-b border-[#f1f5f9]"><p class="text-[11px] text-[#94a3b8] uppercase tracking-wider mb-0.5">ผู้ปกครอง</p><p class="text-[13px] font-bold text-navy-950 mb-0" data-review="parentName">—</p></div>
            <div><p class="text-[11px] text-[#94a3b8] uppercase tracking-wider mb-0.5">เบอร์ติดต่อ</p><p class="text-[13px] font-bold text-navy-950 mb-0" data-review="parentPhone">—</p></div>
          </div>
          <div class="mt-4 pt-4 border-t border-[#f1f5f9]">
            <button class="text-[12px] text-[#2369dd] font-bold hover:underline" data-goto="student">แก้ไขข้อมูลนักเรียน</button>
            <span class="text-[#dce4ef] mx-2">|</span>
            <button class="text-[12px] text-[#2369dd] font-bold hover:underline" data-goto="parent">แก้ไขข้อมูลผู้ปกครอง</button>
          </div>
        </aside>
      </div>
    </div>
  </section>



  <!-- ══════════════════════════════════════════════
       STEP 5 : SUCCESS
  ═══════════════════════════════════════════════ -->
  <section data-step="success" class="hidden bg-[#f6f8fc] py-10 pb-20 min-h-[700px]">
    <div class="container max-w-[720px] text-center">
      <p class="flex items-center justify-center gap-2.5 text-[12px] font-black tracking-[0.15em] text-[#2369dd] uppercase mb-4"><span class="w-6 h-[3px] rounded-full bg-pink-500"></span>STUDENT REGISTRATION · COMPLETE</p>

      <div class="w-20 h-20 mx-auto mb-5 rounded-full bg-[#168765] text-white flex items-center justify-center text-[36px] font-black shadow-[0_8px_30px_rgba(22,135,101,.3)]">✓</div>
      <h2 class="text-[30px] font-bold text-navy-950 tracking-tight mb-3">สร้างบัญชีนักเรียนสำเร็จ</h2>
      <p class="text-[#65738a] text-[15px] mb-8">บัญชีได้รับการยืนยันแล้ว สามารถเข้าสู่ Dashboard ทำแบบวัดระดับ หรือเลือกคอร์สได้ทันที</p>

      <!-- Info Card -->
      <div class="border border-[#dce4ef] rounded-[18px] bg-white p-6 shadow-[0_8px_24px_rgba(15,42,83,.06)] text-left mb-6">
        <div class="flex items-center justify-between py-3 border-b border-[#f1f5f9]"><div><p class="text-[11px] text-[#94a3b8] mb-0.5">ชื่อนักเรียน</p><p class="text-[14px] font-bold text-navy-950 mb-0" data-success="name">—</p></div></div>
        <div class="flex items-center justify-between py-3 border-b border-[#f1f5f9]"><div><p class="text-[11px] text-[#94a3b8] mb-0.5">รหัสนักเรียน</p><p class="text-[14px] font-bold text-navy-950 mb-0" data-success="id">—</p></div><span class="px-2.5 py-1 rounded-full text-[11px] font-bold text-[#168765] bg-[#e8f5e9]">Active</span></div>
        <div class="flex items-center justify-between py-3 border-b border-[#f1f5f9]"><div><p class="text-[11px] text-[#94a3b8] mb-0.5">บัญชีเข้าสู่ระบบ</p><p class="text-[14px] font-bold text-navy-950 mb-0" data-success="account">—</p></div><span class="text-[12px] text-[#168765] font-medium">ยืนยันแล้ว</span></div>
        <div class="flex items-center justify-between py-3"><div><p class="text-[11px] text-[#94a3b8] mb-0.5">ผู้ปกครองที่เชื่อม</p><p class="text-[14px] font-bold text-navy-950 mb-0" data-success="parent">—</p></div><span class="text-[12px] text-[#65738a]">รับ Feedback</span></div>
      </div>

      <!-- Next Steps -->
      <div class="grid grid-cols-3 gap-4 text-left max-[640px]:grid-cols-1">
        <div class="border border-[#dce4ef] rounded-[18px] bg-white p-5 shadow-[0_8px_24px_rgba(15,42,83,.06)]">
          <h3 class="text-[16px] font-bold text-navy-950 mb-2">1. ทำแบบวัดระดับ</h3>
          <p class="text-[13px] text-[#65738a] mb-3">รู้จุดแข็งและจุดที่ควรพัฒนาก่อนเลือกคอร์ส</p>
          <a href="placement-test.php" class="inline-flex h-9 px-4 rounded-lg bg-pink-500 text-white text-[13px] font-bold items-center transition-transform hover:-translate-y-0.5">เริ่มวัดระดับ</a>
        </div>
        <div class="border border-[#dce4ef] rounded-[18px] bg-white p-5 shadow-[0_8px_24px_rgba(15,42,83,.06)]">
          <h3 class="text-[16px] font-bold text-navy-950 mb-2">2. เลือกคอร์ส</h3>
          <p class="text-[13px] text-[#65738a] mb-3">ค้นหาตามวิชา ระดับ และเป้าหมายที่กรอกไว้</p>
          <a href="courses.php" class="inline-flex h-9 px-4 rounded-lg border border-[#dce4ef] bg-white text-navy-950 text-[13px] font-bold items-center transition-transform hover:-translate-y-0.5">ดูคอร์ส</a>
        </div>
        <div class="border border-[#dce4ef] rounded-[18px] bg-white p-5 shadow-[0_8px_24px_rgba(15,42,83,.06)]">
          <h3 class="text-[16px] font-bold text-navy-950 mb-2">3. เปิด Dashboard</h3>
          <p class="text-[13px] text-[#65738a] mb-3">ดูตารางเรียน งาน คะแนน และ Feedback ในที่เดียว</p>
          <a href="placement-test.php?view=history#test-workspace" class="inline-flex h-9 px-4 rounded-lg border border-[#dce4ef] bg-white text-navy-950 text-[13px] font-bold items-center transition-transform hover:-translate-y-0.5">ดูคะแนนของฉัน</a>
        </div>
      </div>

      <p class="text-[12px] text-[#94a3b8] mt-6">บัญชีนี้ใช้เข้าสู่ระบบและดูประวัติคะแนนของนักเรียนได้</p>
    </div>
  </section>

</main>

<?php include 'includes/footer.php'; ?>
