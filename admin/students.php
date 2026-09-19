<?php 
require_once __DIR__ . '/includes/access.php'; 
$pageTitle = 'จัดการนักเรียนและสิทธิ์คอร์ส';
$pageDesc = 'ตรวจสอบรายชื่อ มอบสิทธิ์คอร์สรายบุคคล และจัดกลุ่มเรียน';
$currentPage = 'students.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> - Next Beyond Admin</title>
  <link rel="stylesheet" href="../assets/css/output.css">
  <script src="../assets/js/admin-guard.js"></script>
  <script defer src="../assets/js/admin-students.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin-students.js') ?>"></script>
</head>
<body class="bg-[#f4f7fb] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 ml-[240px] max-[1024px]:ml-0 flex flex-col min-w-0">
    <?php include 'includes/topbar.php'; ?>

    <main class="p-8 max-[640px]:p-4 max-w-[1600px] w-full mx-auto space-y-6">

      <!-- Top Metrics -->
      <div class="grid grid-cols-5 gap-4 max-[1200px]:grid-cols-3 max-[768px]:grid-cols-2 max-[480px]:grid-cols-1">
        <div class="bg-white p-5 rounded-[22px] border border-slate-200/80 shadow-sm flex flex-col justify-between">
          <div class="flex items-center justify-between text-[#65738a] text-xs font-semibold uppercase tracking-wider">
            <span>นักเรียนทั้งหมด</span>
            <span class="p-1.5 bg-blue-50 text-blue-600 rounded-lg">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </span>
          </div>
          <div class="mt-3">
            <div id="metric-total-students" class="text-3xl font-black text-navy-950">...</div>
            <div class="text-[12px] text-slate-500 mt-0.5">บัญชีผู้เรียนในระบบ</div>
          </div>
        </div>

        <div class="bg-white p-5 rounded-[22px] border border-slate-200/80 shadow-sm flex flex-col justify-between">
          <div class="flex items-center justify-between text-[#65738a] text-xs font-semibold uppercase tracking-wider">
            <span>สิทธิ์คอร์สที่ Active</span>
            <span class="p-1.5 bg-emerald-50 text-emerald-600 rounded-lg">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
          </div>
          <div class="mt-3">
            <div id="metric-active-enrollments" class="text-3xl font-black text-emerald-600">...</div>
            <div class="text-[12px] text-slate-500 mt-0.5">รวมทุกวิชาที่นักเรียนถือครอง</div>
          </div>
        </div>

        <div class="bg-white p-5 rounded-[22px] border border-slate-200/80 shadow-sm flex flex-col justify-between">
          <div class="flex items-center justify-between text-[#65738a] text-xs font-semibold uppercase tracking-wider">
            <span>คอร์สที่มีผู้เรียน</span>
            <span class="p-1.5 bg-pink-50 text-pink-600 rounded-lg">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </span>
          </div>
          <div class="mt-3">
            <div id="metric-courses-sold" class="text-3xl font-black text-pink-600">...</div>
            <div class="text-[12px] text-slate-500 mt-0.5">หลักสูตรที่มีการลงทะเบียน</div>
          </div>
        </div>

        <div class="bg-white p-5 rounded-[22px] border border-slate-200/80 shadow-sm flex flex-col justify-between">
          <div class="flex items-center justify-between text-[#65738a] text-xs font-semibold uppercase tracking-wider">
            <span>ทดลองเรียน (Trial)</span>
            <span class="p-1.5 bg-amber-50 text-amber-600 rounded-lg">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
          </div>
          <div class="mt-3">
            <div id="metric-trial-students" class="text-3xl font-black text-amber-600">...</div>
            <div class="text-[12px] text-slate-500 mt-0.5">สิทธิ์เรียนฟรี/ทดลอง</div>
          </div>
        </div>

        <div class="bg-white p-5 rounded-[22px] border border-slate-200/80 shadow-sm flex flex-col justify-between">
          <div class="flex items-center justify-between text-[#65738a] text-xs font-semibold uppercase tracking-wider">
            <span>ใกล้หมดอายุ (14 วัน)</span>
            <span class="p-1.5 bg-rose-50 text-rose-600 rounded-lg">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </span>
          </div>
          <div class="mt-3">
            <div id="metric-expiring-soon" class="text-3xl font-black text-rose-600">...</div>
            <div class="text-[12px] text-slate-500 mt-0.5">ต้องติดตามต่ออายุ</div>
          </div>
        </div>
      </div>

      <!-- Actions & Filters Bar -->
      <div class="bg-white p-4 rounded-[22px] border border-slate-200/80 shadow-sm flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3 flex-1 min-w-[320px]">
          <div class="relative flex-1 min-w-[220px]">
            <svg class="w-4 h-4 absolute left-3.5 top-3.5 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input id="student-search" type="text" class="w-full h-11 pl-10 pr-4 rounded-xl border border-slate-200 focus:outline-none focus:border-pink-500 text-sm placeholder:text-slate-400" placeholder="ค้นหาชื่อ, อีเมล, เบอร์โทร, วิชา, หรือห้องเรียน...">
          </div>
          <select id="grade-filter" class="h-11 px-3.5 rounded-xl border border-slate-200 text-sm font-medium bg-white focus:outline-none focus:border-pink-500 text-slate-700">
            <option value="">ทุกระดับชั้น</option>
            <option value="ม.4">ม.4</option>
            <option value="ม.5">ม.5</option>
            <option value="ม.6">ม.6</option>
          </select>
          <select id="enrollment-status-filter" class="h-11 px-3.5 rounded-xl border border-slate-200 text-sm font-medium bg-white focus:outline-none focus:border-pink-500 text-slate-700">
            <option value="">สถานะคอร์สทั้งหมด</option>
            <option value="has_active">มีสิทธิ์คอร์ส (Active)</option>
            <option value="no_active">ไม่มีสิทธิ์คอร์ส</option>
          </select>
        </div>

        <div class="flex items-center gap-2.5">
          <a href="course-matrix.php" class="h-11 px-4 rounded-xl border border-slate-200 hover:border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-semibold flex items-center gap-2 transition-colors">
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 10h16M4 14h16M4 18h16M9 6v12M15 6v12"/></svg>
            <span>ตารางสิทธิ์คอร์ส (Matrix)</span>
          </a>
          <button id="add-student" class="h-11 px-5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-sm shadow-[0_4px_12px_rgba(244,63,94,0.25)] flex items-center gap-2 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
            <span>เพิ่มนักเรียน</span>
          </button>
        </div>
      </div>

      <!-- Bulk Selection Banner (Hidden by default, shown when items are selected) -->
      <div id="bulk-bar" class="hidden bg-navy-950 text-white p-3.5 px-5 rounded-2xl shadow-lg flex flex-wrap items-center justify-between gap-3 animate-fade-in">
        <div class="flex items-center gap-3">
          <span class="w-2.5 h-2.5 rounded-full bg-pink-500 animate-pulse"></span>
          <span class="text-sm font-bold">เลือกนักเรียน <span id="bulk-selected-count" class="text-pink-400 text-base font-black">0</span> คน</span>
        </div>
        <div class="flex items-center gap-2.5">
          <button id="btn-bulk-assign-course" class="h-9 px-4 rounded-xl bg-pink-500 hover:bg-pink-600 text-white text-xs font-bold transition-colors flex items-center gap-1.5 shadow-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
            <span>มอบสิทธิ์คอร์สพร้อมกัน</span>
          </button>
          <button id="btn-bulk-assign-class" class="h-9 px-4 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-semibold transition-colors flex items-center gap-1.5 border border-white/20">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <span>กำหนดห้องเรียนพร้อมกัน</span>
          </button>
          <button id="btn-bulk-cancel" class="h-9 px-3 rounded-xl hover:bg-white/10 text-slate-300 text-xs transition-colors">
            ยกเลิก
          </button>
        </div>
      </div>

      <!-- Main Students Table -->
      <div class="bg-white rounded-[22px] border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1000px] text-left border-collapse">
            <thead>
              <tr class="bg-[#f8fafc] border-b border-slate-200 text-xs font-bold uppercase tracking-wider text-[#65738a]">
                <th class="p-4 w-10 text-center">
                  <input type="checkbox" id="select-all-students" class="w-4 h-4 rounded text-pink-500 focus:ring-pink-400 border-slate-300 cursor-pointer">
                </th>
                <th class="p-4">นักเรียน</th>
                <th class="p-4">ระดับชั้น</th>
                <th class="p-4">สิทธิ์คอร์ส (Active)</th>
                <th class="p-4">กลุ่มเรียน (Class Groups)</th>
                <th class="p-4">สถานะบัญชี</th>
                <th class="p-4 text-right">การจัดการ</th>
              </tr>
            </thead>
            <tbody id="students-body" class="divide-y divide-slate-100 text-sm">
              <tr>
                <td colspan="7" class="p-12 text-center text-slate-400">
                  <div class="inline-block animate-spin w-6 h-6 border-2 border-pink-500 border-t-transparent rounded-full mb-2"></div>
                  <div>กำลังโหลดข้อมูลนักเรียน...</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-[#65738a]">
          <div id="student-count">0 รายการ</div>
          <div class="text-slate-400">คลิกที่จำนวนคอร์สเพื่อดูรายชื่อวิชาแบบรวดเร็ว</div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- Modal: Add Student -->
<div id="student-modal" class="hidden fixed inset-0 z-50 bg-navy-950/60 backdrop-blur-xs p-4 items-center justify-center">
  <form id="student-form" class="w-full max-w-[540px] bg-white rounded-[24px] p-6 space-y-4 shadow-2xl border border-slate-100">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
      <h2 class="text-lg font-bold text-navy-950 flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-pink-500"></span>
        เพิ่มนักเรียนใหม่
      </h2>
      <button type="button" data-close class="text-slate-400 hover:text-slate-600 p-1">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">ชื่อ *</label>
        <input name="firstName" required placeholder="ชื่อจริง" class="field">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">นามสกุล *</label>
        <input name="lastName" required placeholder="นามสกุล" class="field">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">ชื่อเล่น</label>
        <input name="nickname" placeholder="เช่น ใบเตย, แคนดี้" class="field">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">ระดับชั้น</label>
        <select name="grade" class="field">
          <option value="ม.4">ม.4</option>
          <option value="ม.5" selected>ม.5</option>
          <option value="ม.6">ม.6</option>
        </select>
      </div>
    </div>

    <div>
      <label class="block text-xs font-semibold text-slate-600 mb-1">อีเมล *</label>
      <input name="email" type="email" required placeholder="student@example.com" class="field">
    </div>

    <div>
      <label class="block text-xs font-semibold text-slate-600 mb-1">เบอร์โทรศัพท์</label>
      <input name="phone" placeholder="08XXXXXXXX" class="field">
    </div>

    <div>
      <label class="block text-xs font-semibold text-slate-600 mb-1">รหัสผ่านเริ่มต้น *</label>
      <input name="password" type="password" minlength="8" required placeholder="อย่างน้อย 8 ตัวอักษร" class="field">
    </div>

    <div id="student-error" class="hidden text-rose-500 text-xs font-medium bg-rose-50 p-3 rounded-xl"></div>

    <div class="flex justify-end gap-2.5 pt-2 border-t border-slate-100">
      <button type="button" data-close class="h-10 px-5 border border-slate-200 rounded-xl text-slate-600 text-sm font-semibold hover:bg-slate-50 transition-colors">ยกเลิก</button>
      <button type="submit" class="h-10 px-6 bg-pink-500 hover:bg-pink-600 text-white rounded-xl text-sm font-bold shadow-md shadow-pink-500/20 transition-all">บันทึกข้อมูล</button>
    </div>
  </form>
</div>

<!-- Modal: Bulk Assign Course -->
<div id="bulk-assign-course-modal" class="hidden fixed inset-0 z-50 bg-navy-950/60 backdrop-blur-xs p-4 items-center justify-center">
  <form id="bulk-assign-course-form" class="w-full max-w-[580px] bg-white rounded-[24px] p-6 space-y-4 shadow-2xl border border-slate-100">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
      <h2 class="text-lg font-bold text-navy-950 flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-pink-500"></span>
        มอบสิทธิ์คอร์สให้นักเรียนที่เลือก
      </h2>
      <button type="button" class="btn-close-bulk-course text-slate-400 hover:text-slate-600 p-1">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/70 text-xs text-slate-700">
      <div class="font-bold text-navy-950 mb-1">นักเรียนที่เลือก (<span id="bulk-course-selected-count">0</span> คน):</div>
      <div id="bulk-course-selected-names" class="flex flex-wrap gap-1.5 max-h-[80px] overflow-y-auto"></div>
    </div>

    <div>
      <label class="block text-xs font-semibold text-slate-600 mb-1">เลือกคอร์สเรียน *</label>
      <select id="bulk-course-id" required class="field">
        <option value="">-- เลือกคอร์สเรียน --</option>
      </select>
    </div>

    <div>
      <label class="block text-xs font-semibold text-slate-600 mb-1">กลุ่มเรียน (Class Group) - ไม่บังคับ</label>
      <select id="bulk-class-group-id" class="field">
        <option value="">-- ไม่ระบุกลุ่มเรียน (เรียนออนไลน์ทั่วไป) --</option>
      </select>
      <p class="text-[11px] text-slate-400 mt-1">* หากเลือกกลุ่มเรียน นักเรียนจะถูกเพิ่มเข้ากลุ่มเรียนนั้นพร้อมตรวจสอบที่นั่งว่าง</p>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">ประเภทสิทธิ์ (Access Type)</label>
        <select id="bulk-access-type" class="field">
          <option value="paid" selected>ชำระเงินแล้ว (Paid)</option>
          <option value="trial">ทดลองเรียน (Trial)</option>
          <option value="scholarship">ทุนการศึกษา (Scholarship)</option>
          <option value="complimentary">ให้สิทธิ์พิเศษ (Complimentary)</option>
          <option value="manual">กำหนดเอง (Manual)</option>
          <option value="bundle">แพ็กเกจ (Bundle)</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">รูปแบบการเรียน (Mode)</label>
        <select id="bulk-learning-mode" class="field">
          <option value="online" selected>Online</option>
          <option value="hybrid">Hybrid</option>
          <option value="onsite">On-site</option>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">วันเริ่มสิทธิ์</label>
        <input type="date" id="bulk-start-date" class="field" value="<?= date('Y-m-d') ?>">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">ระยะเวลา / วันหมดอายุ</label>
        <select id="bulk-duration-preset" class="field">
          <option value="forever" selected>ไม่มีวันหมดอายุ (No Expiration)</option>
          <option value="30">30 วัน (1 เดือน)</option>
          <option value="90">90 วัน (3 เดือน)</option>
          <option value="365">365 วัน (1 ปี)</option>
          <option value="custom">กำหนดวันหมดอายุเอง...</option>
        </select>
      </div>
    </div>

    <div id="bulk-custom-end-date-wrapper" class="hidden">
      <label class="block text-xs font-semibold text-slate-600 mb-1">ระบุวันหมดอายุ</label>
      <input type="date" id="bulk-custom-end-date" class="field">
    </div>

    <div>
      <label class="block text-xs font-semibold text-slate-600 mb-1">หมายเหตุ / เหตุผลการมอบสิทธิ์</label>
      <input type="text" id="bulk-notes" placeholder="เช่น สิทธิ์โปรโมชันเปิดเทอม 2569" class="field">
    </div>

    <div id="bulk-course-feedback" class="hidden text-xs p-3 rounded-xl font-medium"></div>

    <div class="flex justify-end gap-2.5 pt-2 border-t border-slate-100">
      <button type="button" class="btn-close-bulk-course h-10 px-5 border border-slate-200 rounded-xl text-slate-600 text-sm font-semibold hover:bg-slate-50 transition-colors">ยกเลิก</button>
      <button type="submit" id="btn-submit-bulk-course" class="h-10 px-6 bg-pink-500 hover:bg-pink-600 text-white rounded-xl text-sm font-bold shadow-md shadow-pink-500/20 transition-all flex items-center gap-2">
        <span>ยืนยันการมอบสิทธิ์</span>
      </button>
    </div>
  </form>
</div>

<!-- Modal: Bulk Assign Class Group -->
<div id="bulk-assign-class-modal" class="hidden fixed inset-0 z-50 bg-navy-950/60 backdrop-blur-xs p-4 items-center justify-center">
  <form id="bulk-assign-class-form" class="w-full max-w-[540px] bg-white rounded-[24px] p-6 space-y-4 shadow-2xl border border-slate-100">
    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
      <h2 class="text-lg font-bold text-navy-950 flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-blue-600"></span>
        กำหนดห้องเรียน (Class Group) พร้อมกัน
      </h2>
      <button type="button" class="btn-close-bulk-class text-slate-400 hover:text-slate-600 p-1">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/70 text-xs text-slate-700">
      <div class="font-bold text-navy-950 mb-1">นักเรียนที่เลือก (<span id="bulk-class-selected-count">0</span> คน):</div>
      <div id="bulk-class-selected-names" class="flex flex-wrap gap-1.5 max-h-[80px] overflow-y-auto"></div>
    </div>

    <div>
      <label class="block text-xs font-semibold text-slate-600 mb-1">เลือกกลุ่มเรียน (Class Group) *</label>
      <select id="bulk-target-class-group-id" required class="field">
        <option value="">-- เลือกกลุ่มเรียน --</option>
      </select>
    </div>

    <div id="bulk-target-class-info" class="hidden p-3 rounded-xl bg-blue-50/70 border border-blue-200/60 text-xs text-blue-900 space-y-1">
      <div class="font-bold" id="bulk-class-info-title"></div>
      <div id="bulk-class-info-details" class="text-slate-600"></div>
    </div>

    <div id="bulk-class-feedback" class="hidden text-xs p-3 rounded-xl font-medium"></div>

    <div class="flex justify-end gap-2.5 pt-2 border-t border-slate-100">
      <button type="button" class="btn-close-bulk-class h-10 px-5 border border-slate-200 rounded-xl text-slate-600 text-sm font-semibold hover:bg-slate-50 transition-colors">ยกเลิก</button>
      <button type="submit" id="btn-submit-bulk-class" class="h-10 px-6 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold shadow-md shadow-blue-600/20 transition-all flex items-center gap-2">
        <span>บันทึกห้องเรียน</span>
      </button>
    </div>
  </form>
</div>

<!-- Quick View Popover for Active Courses -->
<div id="quick-courses-popover" class="hidden fixed z-40 bg-white rounded-2xl shadow-xl border border-slate-200/90 p-4 w-[340px] text-xs space-y-2 pointer-events-auto">
  <div class="flex items-center justify-between pb-2 border-b border-slate-100 font-bold text-navy-950">
    <span id="popover-student-name">คอร์สที่กำลังเรียน</span>
    <span id="popover-badge-count" class="px-2 py-0.5 rounded-full bg-pink-50 text-pink-600 font-extrabold text-[11px]"></span>
  </div>
  <div id="popover-courses-list" class="space-y-2 max-h-[220px] overflow-y-auto"></div>
  <div class="pt-2 border-t border-slate-100 text-right">
    <a id="popover-detail-link" href="#" class="text-pink-600 hover:text-pink-700 font-bold inline-flex items-center gap-1 text-[11px]">
      <span>ไปที่หน้าจัดการสิทธิ์คอร์ส</span>
      <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
    </a>
  </div>
</div>

<style>
.field {
  width: 100%;
  height: 42px;
  padding: 0 14px;
  border: 1px solid #dce4ef;
  border-radius: 12px;
  font-size: 13px;
  background-color: #ffffff;
  transition: border-color 0.15s ease;
}
.field:focus {
  outline: none;
  border-color: #f43f5e;
  box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.1);
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fadeIn 0.2s ease-out forwards;
}
</style>

</body>
</html>
