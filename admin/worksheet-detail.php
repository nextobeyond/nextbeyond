<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/db.php';

$worksheetId = (int) ($_GET['id'] ?? 0);
if (!$worksheetId) {
    header('Location: worksheets.php');
    exit;
}

// Fetch worksheet
$stmt = $pdo->prepare("
    SELECT w.*, f.name AS folder_name
    FROM worksheets w
    LEFT JOIN worksheet_folders f ON f.id = w.folder_id
    WHERE w.id = ?
");
$stmt->execute([$worksheetId]);
$worksheet = $stmt->fetch();

if (!$worksheet) {
    echo '<meta charset="utf-8"><p>ไม่พบข้อมูลใบงานนี้</p><a href="worksheets.php">กลับสู่คลังใบงาน</a>';
    exit;
}

$pageTitle = $worksheet['title'];
$pageDesc = 'รายละเอียดและตัวอย่างใบงานการเรียนรู้';
$currentPage = 'worksheets.php';

// Fetch questions
$qStmt = $pdo->prepare("
    SELECT * FROM worksheet_questions
    WHERE worksheet_id = ?
    ORDER BY sort_order ASC, id ASC
");
$qStmt->execute([$worksheetId]);
$questions = $qStmt->fetchAll();

foreach ($questions as &$q) {
    if ($q['options']) {
        $q['options'] = json_decode($q['options'], true);
    }
}
unset($q);

// Fetch assignments / usage history
$assignStmt = $pdo->prepare("
    SELECT a.*, c.title AS course_title
    FROM worksheet_assignments a
    LEFT JOIN courses c ON c.id = a.course_id
    WHERE a.worksheet_id = ?
    ORDER BY a.created_at DESC
");
$assignStmt->execute([$worksheetId]);
$assignments = $assignStmt->fetchAll();

$isAutoPrint = !empty($_GET['print']);
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($worksheet['title'], ENT_QUOTES, 'UTF-8') ?> - Next Beyond Admin</title>
  <link rel="stylesheet" href="../assets/css/output.css">
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Inter:wght@500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="../assets/js/admin-guard.js"></script>
  <style>
    @media print {
      body { background: #fff !important; }
      #adminSidebar, #adminTopbar, .no-print { display: none !important; }
      main { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
      .print-border { border: 1px solid #ccc !important; box-shadow: none !important; }
    }
    .ws-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700; }
  </style>
</head>
<body class="bg-[#f4f7fb] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0">
    <div class="no-print">
      <?php include __DIR__ . '/includes/topbar.php'; ?>
    </div>

    <main class="flex-1 p-8 max-[640px]:p-4 max-w-[1400px] w-full mx-auto">
      <!-- Breadcrumb & Back Navigation -->
      <div class="no-print flex items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-2 text-[13px] font-bold text-[#64748b]">
          <a href="worksheets.php" class="hover:text-pink-600 transition flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>กลับไปคลังใบงาน</span>
          </a>
          <span>/</span>
          <span class="text-navy-900 truncate max-w-[300px]"><?= htmlspecialchars($worksheet['title'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="flex items-center gap-2.5">
          <button type="button" onclick="window.print()" class="h-10 px-4 rounded-xl border border-[#dce4ef] bg-white hover:bg-slate-50 text-[13px] font-bold text-navy-900 transition flex items-center gap-2">
            <svg class="w-4 h-4 text-[#64748b]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            <span>พิมพ์ / Export PDF</span>
          </button>
          <button type="button" id="btn-duplicate-ws" class="h-10 px-4 rounded-xl border border-[#dce4ef] bg-white hover:bg-slate-50 text-[13px] font-bold text-navy-900 transition flex items-center gap-2">
            <svg class="w-4 h-4 text-[#64748b]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            <span>สร้างสำเนา</span>
          </button>
          <button type="button" id="btn-assign-class" class="h-10 px-5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white text-[13px] font-bold transition shadow-sm flex items-center gap-2">
            <svg class="w-4 h-4 text-pink-100" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <span>ใช้กับคลาส</span>
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6 items-start">
        <!-- Main Content Area: Interactive Preview & Question Editor -->
        <div class="space-y-6">
          <!-- Header Card -->
          <div class="bg-white rounded-2xl border border-[#e8ecf2] p-6 shadow-sm">
            <div class="flex items-center gap-2 flex-wrap mb-3">
              <span class="ws-badge bg-blue-50 text-blue-700"><?= htmlspecialchars($worksheet['subject'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="ws-badge bg-slate-100 text-slate-700"><?= htmlspecialchars($worksheet['level'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="ws-badge bg-slate-100 text-slate-700"><?= htmlspecialchars($worksheet['worksheet_type'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="ws-badge bg-amber-50 text-amber-700 border border-amber-200/60">ความยาก: <?= htmlspecialchars($worksheet['difficulty'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php if ($worksheet['generation_source'] === 'ai'): ?>
                <span class="ws-badge bg-pink-50 text-pink-600">✨ AI Generated</span>
              <?php else: ?>
                <span class="ws-badge bg-slate-100 text-slate-700">✍️ ครูสร้างเอง</span>
              <?php endif; ?>
            </div>

            <h1 class="text-[24px] font-black text-navy-950 mb-2 leading-snug">
              <?= htmlspecialchars($worksheet['title'], ENT_QUOTES, 'UTF-8') ?>
            </h1>

            <?php if (!empty($worksheet['description'])): ?>
              <p class="text-[14px] text-[#64748b] leading-relaxed">
                <?= nl2br(htmlspecialchars($worksheet['description'], ENT_QUOTES, 'UTF-8')) ?>
              </p>
            <?php endif; ?>
          </div>

          <!-- Interactive View Controls Toolbar -->
          <div class="no-print bg-white rounded-2xl border border-[#e8ecf2] p-4 shadow-sm flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-2">
              <span class="text-[13px] font-bold text-[#64748b]">โหมดการแสดงผล:</span>
              <div class="inline-flex p-1 bg-[#f1f5f9] rounded-xl border border-[#e2e8f0] text-[12px] font-bold">
                <button type="button" id="toggle-student" class="px-3.5 py-1.5 rounded-lg text-[#64748b] transition">
                  มุมมองนักเรียน (ไม่มีเฉลย)
                </button>
                <button type="button" id="toggle-teacher" class="px-3.5 py-1.5 rounded-lg bg-white text-pink-600 shadow-sm transition">
                  มุมมองครู (แสดงเฉลยและทักษะ)
                </button>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <button type="button" id="btn-add-question" class="h-9 px-3.5 rounded-xl border border-[#dce4ef] text-[12px] font-bold text-navy-900 hover:bg-slate-50 transition flex items-center gap-1.5">
                <svg class="w-4 h-4 text-[#64748b]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ เพิ่มข้อใหม่</span>
              </button>
              <button type="button" id="btn-save-changes" class="h-9 px-4 rounded-xl bg-pink-500 hover:bg-pink-600 text-white text-[12px] font-bold transition shadow-sm">
                บันทึกการแก้ไข
              </button>
            </div>
          </div>

          <!-- Questions Container -->
          <div id="questions-wrapper" class="space-y-4">
            <?php if (empty($questions)): ?>
              <div class="bg-white rounded-2xl border border-[#e8ecf2] p-12 text-center text-[#64748b]">
                <p class="font-bold text-[16px] mb-2">ยังไม่มีคำถามในใบงานนี้</p>
                <p class="text-[13px] mb-4">คุณสามารถกดปุ่ม "+ เพิ่มข้อใหม่" ด้านบนเพื่อเริ่มเขียนคำถาม</p>
              </div>
            <?php else: ?>
              <?php foreach ($questions as $idx => $q): ?>
                <div class="question-card bg-white rounded-2xl border border-[#e8ecf2] p-5 shadow-sm transition-all" data-id="<?= $q['id'] ?>" data-order="<?= $idx + 1 ?>">
                  <div class="flex items-start gap-3">
                    <span class="w-8 h-8 rounded-xl bg-pink-50 text-pink-600 font-black text-[14px] flex items-center justify-center shrink-0">
                      <?= $idx + 1 ?>
                    </span>

                    <div class="flex-1 min-w-0">
                      <div class="flex items-center justify-between gap-2 mb-2 no-print">
                        <span class="ws-badge bg-slate-100 text-slate-700 text-[10px]">
                          <?= htmlspecialchars($q['question_type'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <div class="flex items-center gap-1">
                          <button type="button" class="btn-move-up p-1 text-[#94a3b8] hover:text-navy-950 transition" title="เลื่อนขึ้น">↑</button>
                          <button type="button" class="btn-move-down p-1 text-[#94a3b8] hover:text-navy-950 transition" title="เลื่อนลง">↓</button>
                          <button type="button" class="btn-delete-q p-1 text-red-400 hover:text-red-600 transition" title="ลบข้อนี้">🗑️</button>
                        </div>
                      </div>

                      <!-- Question Text (Editable) -->
                      <p class="q-text font-bold text-navy-950 text-[15px] leading-relaxed whitespace-pre-wrap mb-3 outline-none" contenteditable="true" data-field="question_text">
                        <?= htmlspecialchars($q['question_text'], ENT_QUOTES, 'UTF-8') ?>
                      </p>

                      <!-- Options (if multiple choice) -->
                      <?php if (!empty($q['options']) && is_array($q['options'])): ?>
                        <div class="options-container grid grid-cols-1 sm:grid-cols-2 gap-2.5 my-3">
                          <?php foreach ($q['options'] as $optIdx => $opt): 
                            $isCorrect = ($opt === $q['correct_answer'] || (string)$optIdx === (string)$q['correct_answer']);
                          ?>
                            <div class="opt-item p-3 rounded-xl border text-[13px] flex items-center gap-2.5 <?= $isCorrect ? 'opt-correct border-emerald-500 bg-emerald-50/70 text-emerald-950 font-bold' : 'border-[#e2e8f0] bg-[#f8fafc] text-navy-950' ?>" data-idx="<?= $optIdx ?>">
                              <span class="w-6 h-6 rounded-full border border-current flex items-center justify-center shrink-0 text-[11px] font-bold">
                                <?= chr(65 + $optIdx) ?>
                              </span>
                              <span class="opt-text flex-1 outline-none" contenteditable="true"><?= htmlspecialchars((string)$opt, ENT_QUOTES, 'UTF-8') ?></span>
                              <span class="correct-indicator <?= $isCorrect ? '' : 'hidden' ?> text-emerald-600 text-xs font-bold shrink-0">✓ เฉลย</span>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>

                      <!-- Teacher View Only Box (Answer, Explanation, Objective) -->
                      <div class="teacher-meta-box mt-3 pt-3 border-t border-[#f1f5f9] text-[12px] bg-[#fbfcfe] -mx-5 -mb-5 p-5 rounded-b-2xl space-y-2">
                        <?php if (!empty($q['correct_answer']) && empty($q['options'])): ?>
                          <div class="text-emerald-700 font-bold">
                            <span class="text-[#64748b]">เฉลยคำตอบ:</span>
                            <span class="outline-none" contenteditable="true" data-field="correct_answer"><?= htmlspecialchars($q['correct_answer'], ENT_QUOTES, 'UTF-8') ?></span>
                          </div>
                        <?php endif; ?>

                        <?php if (!empty($q['explanation'])): ?>
                          <div class="text-[#475569]">
                            <span class="font-bold text-navy-900">💡 คำอธิบายเฉลย:</span>
                            <span class="outline-none" contenteditable="true" data-field="explanation"><?= htmlspecialchars($q['explanation'], ENT_QUOTES, 'UTF-8') ?></span>
                          </div>
                        <?php endif; ?>

                        <div class="flex items-center gap-4 text-[11px] text-[#64748b] pt-1 flex-wrap">
                          <?php if (!empty($q['skill'])): ?>
                            <span>ทักษะ: <strong class="text-navy-900"><?= htmlspecialchars($q['skill'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                          <?php endif; ?>
                          <?php if (!empty($q['learning_objective'])): ?>
                            <span>วัตถุประสงค์: <strong class="text-navy-900"><?= htmlspecialchars($q['learning_objective'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Sidebar: Metadata & Usage History -->
        <aside class="no-print space-y-6">
          <!-- Metadata Card -->
          <div class="bg-white rounded-2xl border border-[#e8ecf2] p-5 shadow-sm">
            <h3 class="text-[14px] font-black text-navy-950 uppercase tracking-wider mb-4 pb-2 border-b border-[#f1f5f9]">
              ข้อมูลสรุปใบงาน
            </h3>

            <dl class="space-y-3 text-[13px]">
              <div>
                <dt class="text-[#64748b] font-bold text-[11px] uppercase">บทเรียน / Chapter</dt>
                <dd class="font-bold text-navy-900 mt-0.5"><?= htmlspecialchars($worksheet['chapter'] ?: '—', ENT_QUOTES, 'UTF-8') ?></dd>
              </div>
              <div>
                <dt class="text-[#64748b] font-bold text-[11px] uppercase">หัวข้อหลัก / Topic</dt>
                <dd class="font-bold text-navy-900 mt-0.5"><?= htmlspecialchars($worksheet['topic'] ?: '—', ENT_QUOTES, 'UTF-8') ?></dd>
              </div>
              <div>
                <dt class="text-[#64748b] font-bold text-[11px] uppercase">หัวข้อย่อย / Subtopic</dt>
                <dd class="font-bold text-navy-900 mt-0.5"><?= htmlspecialchars($worksheet['subtopic'] ?: '—', ENT_QUOTES, 'UTF-8') ?></dd>
              </div>
              <div>
                <dt class="text-[#64748b] font-bold text-[11px] uppercase">โฟลเดอร์</dt>
                <dd class="font-bold text-navy-900 mt-0.5"><?= htmlspecialchars($worksheet['folder_name'] ? '📁 ' . $worksheet['folder_name'] : 'ไม่มีโฟลเดอร์', ENT_QUOTES, 'UTF-8') ?></dd>
              </div>
              <div>
                <dt class="text-[#64748b] font-bold text-[11px] uppercase">ผู้สร้าง</dt>
                <dd class="font-bold text-navy-900 mt-0.5"><?= htmlspecialchars($worksheet['creator_name'], ENT_QUOTES, 'UTF-8') ?></dd>
              </div>
              <div>
                <dt class="text-[#64748b] font-bold text-[11px] uppercase">วันที่สร้าง</dt>
                <dd class="text-[#64748b] mt-0.5"><?= htmlspecialchars($worksheet['created_at'], ENT_QUOTES, 'UTF-8') ?></dd>
              </div>
              <div>
                <dt class="text-[#64748b] font-bold text-[11px] uppercase">อัปเดตล่าสุด</dt>
                <dd class="text-[#64748b] mt-0.5"><?= htmlspecialchars($worksheet['updated_at'], ENT_QUOTES, 'UTF-8') ?></dd>
              </div>
            </dl>
          </div>

          <!-- Usage History Card (Section 21) -->
          <div class="bg-white rounded-2xl border border-[#e8ecf2] p-5 shadow-sm">
            <div class="flex items-center justify-between gap-2 mb-3 pb-2 border-b border-[#f1f5f9]">
              <h3 class="text-[14px] font-black text-navy-950 uppercase tracking-wider">
                ประวัติการใช้งาน (Usage)
              </h3>
              <span class="px-2 py-0.5 rounded-full bg-pink-50 text-pink-600 font-bold text-[11px]">
                <?= count($assignments) ?> ครั้ง
              </span>
            </div>

            <?php if (empty($assignments)): ?>
              <p class="text-[13px] text-[#64748b] py-2">
                ยังไม่มีการมอบหมายหรือแนบใบงานนี้ในคอร์สใด
              </p>
            <?php else: ?>
              <ul class="divide-y divide-[#f1f5f9] text-[12px]">
                <?php foreach ($assignments as $a): ?>
                  <li class="py-2.5">
                    <div class="font-bold text-navy-950">
                      <?= htmlspecialchars($a['class_name'] ?: ($a['course_title'] ?: 'มอบหมายทั่วไป'), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php if (!empty($a['course_title'])): ?>
                      <div class="text-[11px] text-pink-600 font-medium">
                        คอร์ส: <?= htmlspecialchars($a['course_title'], ENT_QUOTES, 'UTF-8') ?>
                      </div>
                    <?php endif; ?>
                    <div class="text-[10px] text-[#94a3b8] mt-0.5">
                      เมื่อ <?= htmlspecialchars($a['created_at'], ENT_QUOTES, 'UTF-8') ?> โดย <?= htmlspecialchars($a['assigned_by_name'] ?: 'Admin', ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </aside>
      </div>
    </main>
  </div>
</div>

<script>
(() => {
  const wsId = <?= $worksheetId ?>;
  const isAutoPrint = <?= $isAutoPrint ? 'true' : 'false' ?>;

  if (isAutoPrint) {
    window.addEventListener("load", () => setTimeout(() => window.print(), 500));
  }

  // Teacher / Student View Toggle
  const toggleStudent = document.getElementById("toggle-student");
  const toggleTeacher = document.getElementById("toggle-teacher");

  function setView(mode) {
    const isTeacher = mode === "teacher";
    if (isTeacher) {
      toggleTeacher.classList.add("bg-white", "text-pink-600", "shadow-sm");
      toggleTeacher.classList.remove("text-[#64748b]");
      toggleStudent.classList.remove("bg-white", "text-pink-600", "shadow-sm");
      toggleStudent.classList.add("text-[#64748b]");
      document.querySelectorAll(".teacher-meta-box").forEach(b => b.classList.remove("hidden"));
      document.querySelectorAll(".opt-correct").forEach(b => b.classList.add("border-emerald-500", "bg-emerald-50/70", "font-bold"));
      document.querySelectorAll(".correct-indicator").forEach(b => b.classList.remove("hidden"));
    } else {
      toggleStudent.classList.add("bg-white", "text-pink-600", "shadow-sm");
      toggleStudent.classList.remove("text-[#64748b]");
      toggleTeacher.classList.remove("bg-white", "text-pink-600", "shadow-sm");
      toggleTeacher.classList.add("text-[#64748b]");
      document.querySelectorAll(".teacher-meta-box").forEach(b => b.classList.add("hidden"));
      document.querySelectorAll(".opt-correct").forEach(b => b.classList.remove("border-emerald-500", "bg-emerald-50/70", "font-bold"));
      document.querySelectorAll(".correct-indicator").forEach(b => b.classList.add("hidden"));
    }
  }

  toggleStudent.addEventListener("click", () => setView("student"));
  toggleTeacher.addEventListener("click", () => setView("teacher"));

  // Duplicate button
  document.getElementById("btn-duplicate-ws")?.addEventListener("click", async () => {
    if (!confirm("ต้องการสร้างสำเนาใบงานนี้เพื่อปรับปรุงใหม่ใช่หรือไม่?")) return;
    try {
      const res = await fetch("worksheets-api.php?action=duplicate", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: wsId })
      });
      const d = await res.json();
      if (d.success) {
        alert("สร้างสำเนาเรียบร้อยแล้ว");
        window.location.href = `worksheet-detail.php?id=${d.id}`;
      } else {
        alert(d.error || "เกิดข้อผิดพลาด");
      }
    } catch (e) {
      alert(e.message);
    }
  });

  // Assign class button
  document.getElementById("btn-assign-class")?.addEventListener("click", () => {
    const className = prompt("ระบุชื่อคลาส หรือกลุ่มเรียนที่ต้องการมอบหมาย:", "ม.5 ห้อง 501");
    if (!className) return;
    fetch("worksheets-api.php?action=assign", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ worksheet_id: wsId, class_name: className })
    }).then(r => r.json()).then(d => {
      if (d.success) {
        alert("มอบหมายให้คลาสเรียบร้อยแล้ว");
        window.location.reload();
      } else {
        alert(d.error || "เกิดข้อผิดพลาด");
      }
    });
  });

  // Save changes button (Collects all edited questions and saves)
  document.getElementById("btn-save-changes")?.addEventListener("click", async () => {
    const cards = document.querySelectorAll(".question-card");
    const questions = [];
    cards.forEach((card, idx) => {
      const qText = card.querySelector(".q-text")?.innerText.trim() || "";
      const optTexts = Array.from(card.querySelectorAll(".opt-text")).map(o => o.innerText.trim());
      const correctText = card.querySelector("[data-field='correct_answer']")?.innerText.trim() || "";
      const explanation = card.querySelector("[data-field='explanation']")?.innerText.trim() || "";

      questions.push({
        sort_order: idx + 1,
        question_type: optTexts.length ? "multipleChoice" : "shortAnswer",
        question_text: qText,
        options: optTexts,
        correct_answer: correctText || optTexts[0] || "",
        explanation: explanation
      });
    });

    try {
      const res = await fetch("worksheets-api.php?action=update", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          id: wsId,
          title: document.querySelector("h1").innerText.trim(),
          questions: questions
        })
      });
      const d = await res.json();
      if (d.success) {
        alert("บันทึกการแก้ไขเรียบร้อยแล้ว");
        window.location.reload();
      } else {
        alert(d.error || "เกิดข้อผิดพลาด");
      }
    } catch (e) {
      alert(e.message);
    }
  });

  // Question manipulation: Delete
  document.addEventListener("click", e => {
    const delBtn = e.target.closest(".btn-delete-q");
    if (delBtn) {
      if (confirm("ต้องการลบข้อนี้ใช่หรือไม่?")) {
        delBtn.closest(".question-card")?.remove();
      }
      return;
    }

    const upBtn = e.target.closest(".btn-move-up");
    if (upBtn) {
      const card = upBtn.closest(".question-card");
      const prev = card.previousElementSibling;
      if (prev && prev.classList.contains("question-card")) {
        card.parentNode.insertBefore(card, prev);
      }
      return;
    }

    const downBtn = e.target.closest(".btn-move-down");
    if (downBtn) {
      const card = downBtn.closest(".question-card");
      const next = card.nextElementSibling;
      if (next && next.classList.contains("question-card")) {
        card.parentNode.insertBefore(next, card);
      }
      return;
    }
  });

  // Add Question Button
  document.getElementById("btn-add-question")?.addEventListener("click", () => {
    const wrapper = document.getElementById("questions-wrapper");
    const count = wrapper.querySelectorAll(".question-card").length + 1;
    const newCard = document.createElement("div");
    newCard.className = "question-card bg-white rounded-2xl border border-[#e8ecf2] p-5 shadow-sm transition-all";
    newCard.innerHTML = `
      <div class="flex items-start gap-3">
        <span class="w-8 h-8 rounded-xl bg-pink-50 text-pink-600 font-black text-[14px] flex items-center justify-center shrink-0">
          ${count}
        </span>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between gap-2 mb-2 no-print">
            <span class="ws-badge bg-slate-100 text-slate-700 text-[10px]">multipleChoice</span>
            <div class="flex items-center gap-1">
              <button type="button" class="btn-move-up p-1 text-[#94a3b8] hover:text-navy-950 transition">↑</button>
              <button type="button" class="btn-move-down p-1 text-[#94a3b8] hover:text-navy-950 transition">↓</button>
              <button type="button" class="btn-delete-q p-1 text-red-400 hover:text-red-600 transition">🗑️</button>
            </div>
          </div>
          <p class="q-text font-bold text-navy-950 text-[15px] leading-relaxed whitespace-pre-wrap mb-3 outline-none border-b border-dashed border-pink-300 pb-1" contenteditable="true" data-field="question_text">
            คลิกเพื่อพิมพ์โจทย์คำถามใหม่...
          </p>
          <div class="options-container grid grid-cols-1 sm:grid-cols-2 gap-2.5 my-3">
            <div class="opt-item p-3 rounded-xl border border-emerald-500 bg-emerald-50/70 text-emerald-950 font-bold text-[13px] flex items-center gap-2.5" data-idx="0">
              <span class="w-6 h-6 rounded-full border border-current flex items-center justify-center shrink-0 text-[11px] font-bold">A</span>
              <span class="opt-text flex-1 outline-none" contenteditable="true">ตัวเลือกที่ถูกต้อง</span>
              <span class="correct-indicator text-emerald-600 text-xs font-bold shrink-0">✓ เฉลย</span>
            </div>
            <div class="opt-item p-3 rounded-xl border border-[#e2e8f0] bg-[#f8fafc] text-navy-950 text-[13px] flex items-center gap-2.5" data-idx="1">
              <span class="w-6 h-6 rounded-full border border-current flex items-center justify-center shrink-0 text-[11px] font-bold">B</span>
              <span class="opt-text flex-1 outline-none" contenteditable="true">ตัวเลือกหลอก 1</span>
            </div>
            <div class="opt-item p-3 rounded-xl border border-[#e2e8f0] bg-[#f8fafc] text-navy-950 text-[13px] flex items-center gap-2.5" data-idx="2">
              <span class="w-6 h-6 rounded-full border border-current flex items-center justify-center shrink-0 text-[11px] font-bold">C</span>
              <span class="opt-text flex-1 outline-none" contenteditable="true">ตัวเลือกหลอก 2</span>
            </div>
            <div class="opt-item p-3 rounded-xl border border-[#e2e8f0] bg-[#f8fafc] text-navy-950 text-[13px] flex items-center gap-2.5" data-idx="3">
              <span class="w-6 h-6 rounded-full border border-current flex items-center justify-center shrink-0 text-[11px] font-bold">D</span>
              <span class="opt-text flex-1 outline-none" contenteditable="true">ตัวเลือกหลอก 3</span>
            </div>
          </div>
          <div class="teacher-meta-box mt-3 pt-3 border-t border-[#f1f5f9] text-[12px] bg-[#fbfcfe] -mx-5 -mb-5 p-5 rounded-b-2xl space-y-2">
            <div class="text-[#475569]">
              <span class="font-bold text-navy-900">💡 คำอธิบายเฉลย:</span>
              <span class="outline-none" contenteditable="true" data-field="explanation">พิมพ์คำอธิบายเฉลยที่นี่...</span>
            </div>
          </div>
        </div>
      </div>
    `;
    wrapper.appendChild(newCard);
    newCard.scrollIntoView({ behavior: "smooth" });
  });
})();
</script>
</body>
</html>
