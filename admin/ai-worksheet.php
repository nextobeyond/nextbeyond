<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ai-settings.php';

$pageTitle = 'AI สร้างใบงาน';
$pageDesc = 'สร้างใบงานการเรียนรู้อัตโนมัติด้วย AI พร้อมตรวจทานและปรับแต่งก่อนบันทึกเข้าคลัง';
$currentPage = 'ai-worksheet.php';

$hasApiKey = !empty(aiSettingsGetKey($pdo));
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - Next Beyond Admin</title>
  <link rel="stylesheet" href="../assets/css/output.css">
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Inter:wght@500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="../assets/js/admin-guard.js"></script>
  <script defer src="../assets/js/admin-ai-worksheet.js?v=<?= time() ?>"></script>
  <style>
    .ai-label { display: block; font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 6px; }
    .ai-field { width: 100%; height: 44px; padding: 0 14px; background: #fff; border: 1px solid #dce4ef; border-radius: 12px; font-size: 14px; color: #0b132b; outline: none; transition: all 0.2s; }
    .ai-field:focus { border-color: #f54696; box-shadow: 0 0 0 3px rgba(245,70,150,0.12); }
  </style>
</head>
<body class="bg-[#f4f7fb] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 p-8 max-[640px]:p-4 max-w-[1400px] w-full mx-auto">
      <!-- Top Title Header -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
          <div class="flex items-center gap-2.5">
            <span class="w-10 h-10 rounded-xl bg-pink-50 text-pink-600 flex items-center justify-center font-black">
              <svg class="w-5 h-5 text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            </span>
            <h1 class="text-[26px] font-black tracking-[-0.02em] text-navy-950">AI สร้างใบงาน</h1>
          </div>
          <p class="text-[14px] text-[#65738a] mt-1">ออกแบบใบงานการเรียนรู้ตรงตามหลักสูตร ตรวจทานและแก้ไขก่อนจัดเก็บเข้าคลังใบงาน</p>
        </div>

        <a href="worksheets.php" class="h-10 px-4 rounded-xl border border-[#dce4ef] bg-white hover:bg-slate-50 text-navy-900 font-bold text-[13px] transition flex items-center gap-2">
          <svg class="w-4 h-4 text-[#65738a]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
          <span>เปิดคลังใบงาน</span>
        </a>
      </div>

      <!-- Generator Layout Grid: Form on Left, Live Preview on Right -->
      <div class="grid grid-cols-1 lg:grid-cols-[440px_1fr] gap-6 items-start">
        <!-- AI Configuration Form Card -->
        <section class="bg-white rounded-2xl border border-[#e8ecf2] p-6 shadow-sm">
          <h2 class="text-[17px] font-bold text-navy-950 mb-4 pb-3 border-b border-[#f1f5f9] flex items-center justify-between">
            <span>ตั้งค่าเนื้อหาใบงาน</span>
            <span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700">Gemini AI พร้อมใช้งาน</span>
          </h2>

          <form id="ai-worksheet-form" class="space-y-4">
            <!-- Subject & Level -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="ai-label">วิชา *</label>
                <select id="ai-subject" name="subject" required class="ai-field">
                  <option value="เคมี" selected>เคมี</option>
                  <option value="ฟิสิกส์">ฟิสิกส์</option>
                  <option value="ชีววิทยา">ชีววิทยา</option>
                  <option value="คณิตศาสตร์">คณิตศาสตร์</option>
                  <option value="ภาษาอังกฤษ">ภาษาอังกฤษ</option>
                  <option value="ภาษาไทย">ภาษาไทย</option>
                  <option value="วิทยาศาสตร์">วิทยาศาสตร์</option>
                  <option value="สังคมศึกษา">สังคมศึกษา</option>
                </select>
              </div>

              <div>
                <label class="ai-label">ระดับชั้น *</label>
                <select id="ai-level" name="level" required class="ai-field">
                  <option value="ป.1">ป.1</option><option value="ป.2">ป.2</option><option value="ป.3">ป.3</option>
                  <option value="ป.4">ป.4</option><option value="ป.5">ป.5</option><option value="ป.6">ป.6</option>
                  <option value="ม.1">ม.1</option><option value="ม.2">ม.2</option><option value="ม.3">ม.3</option>
                  <option value="ม.4">ม.4</option><option value="ม.5" selected>ม.5</option><option value="ม.6">ม.6</option>
                  <option value="A-Level">A-Level</option>
                </select>
              </div>
            </div>

            <!-- Chapter -->
            <div>
              <label class="ai-label">บทเรียน (Chapter)</label>
              <input type="text" id="ai-chapter" name="chapter" placeholder="เช่น กรด-เบส หรือ พลศาสตร์ของไหล" class="ai-field">
            </div>

            <!-- Topic & Subtopic -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="ai-label">หัวข้อหลัก (Topic) *</label>
                <input type="text" id="ai-topic" name="topic" required value="Acid-Base" placeholder="เช่น การคำนวณ pH" class="ai-field">
              </div>
              <div>
                <label class="ai-label">หัวข้อย่อย (Subtopic)</label>
                <input type="text" id="ai-subtopic" name="subtopic" placeholder="เช่น Buffer Solutions" class="ai-field">
              </div>
            </div>

            <!-- Type & Difficulty -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="ai-label">ประเภทใบงาน</label>
                <select id="ai-type" name="worksheet_type" class="ai-field">
                  <option value="Worksheet" selected>Worksheet</option>
                  <option value="Practice">Practice</option>
                  <option value="Homework">Homework</option>
                  <option value="Pre-Test">Pre-Test</option>
                  <option value="Post-Test">Post-Test</option>
                  <option value="Quiz">Quiz</option>
                  <option value="Mock Practice">Mock Practice</option>
                </select>
              </div>

              <div>
                <label class="ai-label">ระดับความยาก</label>
                <select id="ai-difficulty" name="difficulty" class="ai-field">
                  <option value="easy">ง่าย</option>
                  <option value="medium" selected>ปานกลาง</option>
                  <option value="hard">ยาก</option>
                  <option value="expert">ยากมาก</option>
                </select>
              </div>
            </div>

            <!-- Question Count -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="ai-label mb-0">จำนวนข้อที่ต้องการ</label>
                <span id="count-display" class="text-[13px] font-bold text-pink-600">8 ข้อ</span>
              </div>
              <input type="range" id="ai-count" name="count" min="3" max="25" value="8" class="w-full accent-pink-500 cursor-pointer">
              <div class="flex justify-between text-[11px] text-[#94a3b8] mt-1">
                <span>3 ข้อ</span>
                <span>10 ข้อ</span>
                <span>25 ข้อ</span>
              </div>
            </div>

            <!-- Question Types Checklist -->
            <div>
              <label class="ai-label">รูปแบบคำถามที่รองรับ</label>
              <div class="grid grid-cols-2 gap-2 text-[12px]">
                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-[#dce4ef] bg-[#f8fafc] cursor-pointer hover:border-pink-300 transition">
                  <input type="checkbox" name="question_types[]" value="multipleChoice" checked class="text-pink-600 rounded">
                  <span class="font-bold text-navy-900">ปรนัย (4 ตัวเลือก)</span>
                </label>
                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-[#dce4ef] bg-[#f8fafc] cursor-pointer hover:border-pink-300 transition">
                  <input type="checkbox" name="question_types[]" value="shortAnswer" checked class="text-pink-600 rounded">
                  <span class="font-bold text-navy-900">อัตนัย / ตอบสั้น</span>
                </label>
                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-[#dce4ef] bg-[#f8fafc] cursor-pointer hover:border-pink-300 transition">
                  <input type="checkbox" name="question_types[]" value="trueFalse" class="text-pink-600 rounded">
                  <span class="font-bold text-navy-900">ถูก / ผิด (True/False)</span>
                </label>
                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-[#dce4ef] bg-[#f8fafc] cursor-pointer hover:border-pink-300 transition">
                  <input type="checkbox" name="question_types[]" value="essay" class="text-pink-600 rounded">
                  <span class="font-bold text-navy-900">อธิบาย / แสดงวิธีทำ</span>
                </label>
              </div>
            </div>

            <!-- Learning Objective -->
            <div>
              <label class="ai-label">เป้าหมายการเรียนรู้ (Learning Objective)</label>
              <input type="text" id="ai-objective" name="learning_objective" value="ฝึกวิเคราะห์และคำนวณค่า pH จากสารละลายจริง" placeholder="เช่น ฝึกวิเคราะห์และคำนวณค่า pH" class="ai-field">
            </div>

            <!-- Optional Custom Instructions -->
            <div>
              <label class="ai-label">คำสั่งเพิ่มเติมสำหรับ AI (Optional)</label>
              <textarea id="ai-instructions" name="instructions" rows="3" placeholder="เช่น เน้นโจทย์แนวประยุกต์สอบเข้ามหาวิทยาลัย มีตัวเลขที่คำนวณลงตัว..." class="ai-field h-auto py-2.5 leading-relaxed"></textarea>
            </div>

            <div id="ai-form-error" class="hidden p-3 rounded-xl bg-red-50 text-red-600 text-[13px] font-bold"></div>

            <!-- Generate Button -->
            <button type="submit" id="btn-generate-ai" class="w-full h-12 rounded-xl bg-pink-500 hover:bg-pink-600 active:scale-[0.99] text-white font-bold text-[14px] transition shadow-[0_4px_16px_rgba(231,45,130,0.3)] flex items-center justify-center gap-2 mt-2">
              <svg class="w-5 h-5 text-amber-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
              <span id="btn-generate-text">สร้างใบงานด้วย AI (Generate)</span>
            </button>
          </form>
        </section>

        <!-- Live Review & Preview Container -->
        <section class="space-y-4">
          <!-- Placeholder State (Before Generation) -->
          <div id="preview-placeholder" class="bg-white rounded-2xl border border-[#e8ecf2] p-12 text-center shadow-sm">
            <div class="w-16 h-16 rounded-2xl bg-pink-50 text-pink-500 flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            </div>
            <h3 class="text-[18px] font-bold text-navy-950 mb-1">ยังไม่มีผลลัพธ์การสร้างใบงาน</h3>
            <p class="text-[14px] text-[#64748b] max-w-[420px] mx-auto">
              กรอกข้อมูลหัวข้อ ระดับชั้น และจำนวนข้อที่ต้องการทางซ้ายมือ แล้วกดปุ่ม <strong>"สร้างใบงานด้วย AI"</strong> ระบบจะประมวลผลและนำมาแสดงเพื่อตรวจทานที่นี่
            </p>
          </div>

          <!-- Loading State (During Generation) -->
          <div id="preview-loading" class="hidden bg-white rounded-2xl border border-[#e8ecf2] p-16 text-center shadow-sm">
            <div class="inline-block w-10 h-10 border-4 border-pink-500 border-t-transparent rounded-full animate-spin mb-4"></div>
            <h3 class="text-[18px] font-bold text-navy-950 mb-1">AI กำลังวิเคราะห์และร่างคำถาม...</h3>
            <p class="text-[14px] text-[#64748b]">ระบบกำลังสร้างโจทย์ ตัวเลือก คำตอบ และคำอธิบายเฉลยอย่างละเอียด</p>
          </div>

          <!-- Active Preview & Review Container -->
          <div id="preview-active" class="hidden space-y-4">
            <!-- Review Action Bar Sticky -->
            <div class="bg-white rounded-2xl border border-[#e8ecf2] p-4 shadow-sm flex items-center justify-between gap-3 flex-wrap sticky top-2 z-20">
              <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-[13px] font-bold text-navy-950">พร้อมตรวจทานและบันทึก</span>
                <span id="live-question-count-badge" class="px-2 py-0.5 rounded-full bg-pink-100 text-pink-600 font-bold text-[11px]">0 ข้อ</span>
              </div>

              <div class="flex items-center gap-2">
                <button type="button" id="btn-save-draft" class="h-10 px-4 rounded-xl border border-[#dce4ef] text-[13px] font-bold text-navy-900 hover:bg-slate-50 transition">
                  บันทึกฉบับร่าง
                </button>
                <button type="button" id="btn-save-library" class="h-10 px-5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white text-[13px] font-bold transition shadow-sm flex items-center gap-1.5">
                  <svg class="w-4 h-4 text-pink-100" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                  <span>บันทึกเข้าคลังใบงาน</span>
                </button>
              </div>
            </div>

            <!-- Editable Header Info Card -->
            <div class="bg-white rounded-2xl border border-[#e8ecf2] p-5 shadow-sm">
              <label class="block text-[11px] font-bold text-[#64748b] uppercase tracking-wider mb-1">ชื่อใบงาน (คลิกเพื่อแก้ไข)</label>
              <h2 id="generated-title" class="text-[20px] font-black text-navy-950 outline-none hover:bg-slate-50 p-1.5 rounded-lg transition" contenteditable="true">
                ชื่อใบงาน
              </h2>
              <div class="flex items-center gap-2 flex-wrap mt-3 text-[11px]">
                <span id="gen-badge-subject" class="px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 font-bold"></span>
                <span id="gen-badge-level" class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 font-bold"></span>
                <span id="gen-badge-type" class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 font-bold"></span>
                <span id="gen-badge-diff" class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 font-bold"></span>
              </div>
            </div>

            <!-- Generated Questions List -->
            <div id="generated-questions-list" class="space-y-4"></div>
          </div>
        </section>
      </div>
    </main>
  </div>
</div>

<!-- Saved Success Modal / Action Prompt (Section 16) -->
<div id="success-modal" class="hidden fixed inset-0 z-50 bg-navy-950/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
  <div class="w-full max-w-[460px] bg-white rounded-2xl shadow-2xl p-6 text-center my-auto border border-[#e2e8f0]">
    <div class="w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-4 font-black text-2xl">
      ✓
    </div>
    <h3 class="text-[20px] font-black text-navy-950 mb-1">บันทึกใบงานเข้าคลังแล้ว</h3>
    <p class="text-[14px] text-[#64748b] mb-6">
      ใบงานพร้อมสำหรับการนำไปแนบในคอร์สเรียน EP หรือมอบหมายให้นักเรียนได้ทันที
    </p>

    <div class="grid grid-cols-2 gap-3">
      <a id="btn-open-worksheet" href="#" class="h-11 px-4 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-[13px] flex items-center justify-center transition shadow-sm">
        เปิดใบงาน
      </a>
      <button type="button" id="btn-create-another" class="h-11 px-4 rounded-xl border border-[#dce4ef] hover:bg-slate-50 text-navy-900 font-bold text-[13px] transition">
        สร้างอีกชุด
      </button>
    </div>
  </div>
</div>

<div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-2"></div>
</body>
</html>
