<?php
require_once __DIR__ . '/includes/access.php';
$pageTitle = 'คลังข้อสอบและการประเมินผล';
$pageDesc = 'จัดการข้อสอบมาตรฐาน แบบทดสอบ และข้อสอบที่สร้างด้วย AI';
$currentPage = 'tests.php';
?>
<!DOCTYPE html><html lang="th"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $pageTitle ?> - Next Beyond Admin</title>
<link rel="stylesheet" href="../assets/css/output.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="../assets/js/admin-guard.js"></script><script defer src="../assets/js/admin-tests.js?v=<?= rawurlencode((string) filemtime(__DIR__ . '/../assets/js/admin-tests.js')) ?>"></script>
<style>
.exam-main{padding:30px;background:#f4f7fb;min-height:calc(100vh - 72px)}.exam-hero{position:relative;overflow:hidden;border-radius:24px;padding:30px;background:linear-gradient(125deg,#102b58 0%,#263f78 52%,#6b54d9 100%);color:#fff;box-shadow:0 18px 45px rgba(15,42,83,.16);display:flex;align-items:center;justify-content:space-between;gap:28px}.exam-hero:after{content:"";position:absolute;width:280px;height:280px;right:18%;top:-160px;border-radius:50%;background:rgba(255,255,255,.09)}.hero-copy,.hero-actions{position:relative;z-index:1}.hero-tag{display:inline-flex;align-items:center;gap:7px;padding:6px 11px;border:1px solid rgba(255,255,255,.24);border-radius:99px;background:rgba(255,255,255,.1);font-size:11px;font-weight:800}.exam-hero h1{font-size:26px;color:#fff;margin-top:12px}.exam-hero p{font-size:13px;color:#d8e2f4;margin-top:7px;max-width:650px;line-height:1.7}.hero-actions{display:flex;gap:10px;flex-wrap:wrap}.exam-btn{height:43px;padding:0 17px;border:1px solid #dce4ef;border-radius:12px;background:#fff;color:#253d61;font-size:13px;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:7px;cursor:pointer;transition:.16s}.exam-btn:hover{transform:translateY(-1px);box-shadow:0 7px 18px rgba(15,42,83,.1)}.exam-btn:disabled{cursor:wait;opacity:.6}.exam-btn.primary{border-color:#f54696;background:#f54696;color:#fff}.hero-actions .exam-btn{border-color:rgba(255,255,255,.25);background:rgba(255,255,255,.13);color:#fff;backdrop-filter:blur(4px)}.hero-actions .exam-btn.primary{background:#f54696;border-color:#f54696}.exam-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin:20px 0}.stat{padding:17px 19px;border:1px solid #e4eaf2;border-radius:17px;background:#fff}.stat strong{display:block;font-size:25px;color:#102b58}.stat span{font-size:11px;color:#718097;font-weight:700}.exam-toolbar{display:flex;justify-content:space-between;gap:14px;margin:20px 0}.search-wrap{position:relative;flex:1;max-width:430px}.search-wrap input{width:100%;height:44px;padding:0 16px 0 42px;border:1px solid #dce4ef;border-radius:13px;background:#fff;outline:none;font-size:13px}.search-wrap svg{position:absolute;left:14px;top:14px;width:16px;color:#8491a5}.filters{display:flex;gap:9px;flex-wrap:wrap}.filters select{height:44px;padding:0 34px 0 13px;border:1px solid #dce4ef;border-radius:12px;background:#fff;color:#53647d;font-size:12px;font-weight:700}.exam-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.exam-card{position:relative;display:flex;flex-direction:column;min-height:350px;padding:20px;border:1px solid #e4eaf2;border-radius:20px;background:#fff;box-shadow:0 5px 22px rgba(15,42,83,.045);transition:.18s}.exam-card:hover{transform:translateY(-2px);border-color:#cad5e4;box-shadow:0 13px 32px rgba(15,42,83,.09)}.card-top{display:flex;align-items:center;justify-content:space-between;gap:8px}.type-badge{padding:5px 9px;border-radius:8px;font-size:10px;font-weight:900;text-transform:uppercase}.type-quiz{background:#eaf2ff;color:#2563be}.type-placement{background:#f1edff;color:#7149ca}.type-pretest{background:#eafaf3;color:#16845d}.type-posttest{background:#fff3e5;color:#c76a14}.ai-badge{font-size:10px;font-weight:900;color:#dc2e7c}.exam-title{font-size:16px;font-weight:900;color:#102b58;margin-top:16px;line-height:1.45}.exam-desc{font-size:12px;color:#718097;line-height:1.6;margin-top:7px;min-height:39px}.exam-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:17px 0}.meta{padding:9px 7px;border-radius:10px;background:#f7f9fc;text-align:center}.meta b{display:block;font-size:13px;color:#28415f}.meta small{font-size:9px;color:#8491a5}.card-settings{margin-top:auto;padding-top:13px;border-top:1px solid #edf1f6}.setting-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;font-size:11px;color:#65738a;font-weight:700}.time-setting{display:grid;grid-template-columns:1fr 78px 58px;align-items:center;gap:7px;margin-bottom:11px}.time-setting label{font-size:11px;color:#65738a;font-weight:800}.time-setting input{width:100%;height:34px;border:1px solid #dce4ef;border-radius:9px;padding:0 9px;color:#28415f;font-size:12px;font-weight:800;outline:none}.time-setting input:focus{border-color:#f54696;box-shadow:0 0 0 3px rgba(245,70,150,.1)}.time-setting button{height:34px;border:0;border-radius:9px;background:#eef3f9;color:#28415f;font-size:10px;font-weight:900;cursor:pointer}.time-setting button:hover{background:#e1e9f3}.time-setting button:disabled{cursor:wait;opacity:.6}.switch{width:39px;height:22px;border:0;border-radius:20px;background:#cbd5e1;padding:2px;cursor:pointer}.switch:after{content:"";display:block;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.15);transition:.15s}.switch.on{background:#f54696}.switch.on:after{transform:translateX(17px)}.card-actions{display:flex;gap:8px;margin-top:12px}.card-actions .exam-btn{height:37px;padding:0 12px;font-size:11px;flex:1}.more-btn{flex:0 0 37px!important;padding:0!important}.empty-state{grid-column:1/-1;padding:65px 20px;border:1px dashed #cbd5e1;border-radius:20px;background:#fff;text-align:center;color:#718097}.notice{display:none;margin-bottom:18px;padding:13px 16px;border:1px solid #bde9d0;border-radius:12px;background:#effcf5;color:#18714f;font-size:12px;font-weight:800}.notice.show{display:block}@media(max-width:1200px){.exam-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:760px){.exam-main{padding:16px}.exam-hero{padding:23px;flex-direction:column;align-items:flex-start}.exam-hero h1{font-size:22px}.exam-stats{grid-template-columns:repeat(2,1fr)}.exam-toolbar{flex-direction:column}.search-wrap{max-width:none}.exam-grid{grid-template-columns:1fr}.hero-actions{width:100%}.hero-actions .exam-btn{flex:1}}
.export-dropdown-container{position:relative;flex:1}.export-dropdown-container .exam-btn{width:100%}.export-menu{position:absolute;left:0;bottom:calc(100% + 4px);width:180px;background:#fff;border:1px solid #dce4ef;border-radius:14px;box-shadow:0 12px 30px rgba(15,42,83,.15);z-index:30;padding:5px;display:none}.export-menu.show{display:block}.export-menu-item{width:100%;text-align:left;padding:8px 12px;border:0;background:transparent;border-radius:9px;font-size:12px;font-weight:700;color:#28415f;cursor:pointer;display:flex;align-items:center;gap:7px;transition:.12s}.export-menu-item:hover{background:#edf4fc;color:#2563eb}
</style></head>
<body class="bg-[#f4f7fb] text-navy-950 font-sans antialiased"><div class="min-h-screen flex"><?php include 'includes/sidebar.php'; ?><div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0"><?php include 'includes/topbar.php'; ?>
<main class="exam-main"><div id="tests-created-notice" class="notice"></div>
<section class="exam-hero"><div class="hero-copy"><span class="hero-tag">✦ คลังข้อสอบมาตรฐาน & ข้อสอบ AI</span><h1>จัดการแบบทดสอบและการประเมินผล</h1><p>สร้างคลังข้อสอบ ตรวจคำถาม กำหนดเวลาและสิทธิ์การเข้าถึง พร้อมเผยแพร่ให้นักเรียนทำผ่าน Student Portal</p></div><div class="hero-actions"><a class="exam-btn" href="calendar.php">▣ ดูตารางสอบ</a><a class="exam-btn" href="question-bank.php">☷ Question Bank</a><a class="exam-btn primary" href="ai-exam-app/">✦ สร้างข้อสอบ AI</a></div></section>
<section class="exam-stats"><div class="stat"><strong id="stat-total">0</strong><span>ข้อสอบทั้งหมด</span></div><div class="stat"><strong id="stat-published">0</strong><span>กำลังเผยแพร่</span></div><div class="stat"><strong id="stat-questions">0</strong><span>คำถามในคลัง</span></div><div class="stat"><strong id="stat-attempts">0</strong><span>การทำข้อสอบทั้งหมด</span></div></section>
<div class="exam-toolbar"><div class="search-wrap"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg><input id="tests-search" type="search" placeholder="ค้นหาชื่อ วิชา หรือระดับ..."></div><div class="filters"><select id="subject-filter"><option value="">ทุกวิชา</option></select><select id="tests-type-filter"><option value="">ทุกประเภท</option><option value="placement">Placement Test</option><option value="pretest">Pre-test</option><option value="quiz">Quiz</option><option value="posttest">Post-test</option></select><select id="status-filter"><option value="">ทุกสถานะ</option><option value="published">เผยแพร่แล้ว</option><option value="draft">ยังไม่เผยแพร่</option></select></div></div>
<section id="exam-grid" class="exam-grid"><div class="empty-state">กำลังโหลดคลังข้อสอบ...</div></section><div id="tests-count" style="margin-top:16px;font-size:12px;color:#718097">0 รายการ</div>
</main></div></div>

<!-- Word Export Modal -->
<div id="export-docx-modal" class="hidden fixed inset-0 z-50 bg-navy-950/60 backdrop-blur-sm p-4 items-center justify-center overflow-y-auto">
  <div class="w-full max-w-[490px] bg-white rounded-[24px] shadow-2xl my-auto overflow-hidden border border-[#e2e8f0]">
    <div class="px-6 py-5 border-b border-[#e8ecf2] flex items-center justify-between bg-white">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-black text-[18px]">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <div>
          <h2 class="text-[18px] font-bold text-navy-950">ส่งออกข้อสอบเป็น Word</h2>
          <p id="export-exam-title" class="text-[12px] text-[#65738a] truncate max-w-[320px]"></p>
        </div>
      </div>
      <button type="button" id="close-export-modal" class="w-9 h-9 rounded-full hover:bg-[#f1f5f9] text-[24px] text-[#94a3b8] flex items-center justify-center leading-none">×</button>
    </div>
    <form id="export-docx-form" class="p-6">
      <input type="hidden" id="export-exam-id" name="examId">

      <!-- Document Type Selection -->
      <div class="mb-5">
        <label class="block text-[13px] font-bold text-[#1e293b] mb-2.5">ประเภทเอกสาร</label>
        <div class="grid grid-cols-2 gap-3">
          <label class="export-type-card flex items-start gap-3 p-3.5 border border-[#dce4ef] rounded-xl cursor-pointer hover:border-blue-400 transition has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50/50">
            <input type="radio" name="docType" value="student" checked class="mt-0.5 text-blue-600 focus:ring-blue-500">
            <div>
              <div class="text-[13px] font-bold text-navy-950">สำหรับนักเรียน</div>
              <div class="text-[11px] text-[#64748b] mt-0.5">มีโจทย์ ตัวเลือก และรูปประกอบ (ไม่มีเฉลย)</div>
            </div>
          </label>
          <label class="export-type-card flex items-start gap-3 p-3.5 border border-[#dce4ef] rounded-xl cursor-pointer hover:border-blue-400 transition has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50/50">
            <input type="radio" name="docType" value="teacher" class="mt-0.5 text-blue-600 focus:ring-blue-500">
            <div>
              <div class="text-[13px] font-bold text-navy-950">สำหรับครู</div>
              <div class="text-[11px] text-[#64748b] mt-0.5">แนบตารางเฉลยและคำอธิบายท้ายเล่ม</div>
            </div>
          </label>
        </div>
      </div>

      <!-- Options -->
      <div class="mb-5">
        <label class="block text-[13px] font-bold text-[#1e293b] mb-2.5">ตัวเลือก</label>
        <div class="space-y-2.5 bg-[#f8fafc] p-4 rounded-xl border border-[#edf2f7]">
          <label class="flex items-center gap-2.5 cursor-pointer text-[13px] text-[#334155] font-medium">
            <input type="checkbox" id="opt-show-title" checked class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500">
            <span>แสดงชื่อข้อสอบ</span>
          </label>
          <label class="flex items-center gap-2.5 cursor-pointer text-[13px] text-[#334155] font-medium">
            <input type="checkbox" id="opt-show-diagrams" checked class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500">
            <span>แสดงรูปประกอบ</span>
          </label>
          <label class="flex items-center gap-2.5 cursor-pointer text-[13px] text-[#334155] font-medium" id="lbl-show-answers">
            <input type="checkbox" id="opt-show-answers" disabled class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50">
            <span>แสดงเฉลย</span>
          </label>
          <label class="flex items-center gap-2.5 cursor-pointer text-[13px] text-[#334155] font-medium" id="lbl-show-explanations">
            <input type="checkbox" id="opt-show-explanations" disabled class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 disabled:opacity-50">
            <span>แสดงคำอธิบาย</span>
          </label>
        </div>
      </div>

      <!-- Feedback Banner -->
      <div id="export-status-box" class="hidden mb-4 p-3 rounded-xl text-[13px] font-bold"></div>

      <!-- Footer Buttons -->
      <div class="flex justify-end gap-3 pt-4 border-t border-[#e8ecf2]">
        <button type="button" id="btn-cancel-export" class="h-11 px-5 rounded-xl border border-[#dce4ef] font-bold text-[13px] text-[#475569] hover:bg-slate-50 transition">
          ยกเลิก
        </button>
        <button type="submit" id="btn-submit-export" class="h-11 px-6 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-[13px] shadow-[0_4px_12px_rgba(37,99,235,.25)] flex items-center justify-center gap-2 transition disabled:opacity-60 disabled:cursor-wait">
          <span id="export-btn-icon">📄</span>
          <span id="export-btn-text">สร้างไฟล์ Word</span>
        </button>
      </div>
    </form>
  </div>
</div>
</body></html>
