<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/question-search-service.php';

$worksheetId = (int) ($_GET['id'] ?? 0);
if (!$worksheetId) {
    header('Location: worksheets.php');
    exit;
}

// Ensure search index & distribution are primed
try {
    $searchEngine = new HybridSearchEngine($pdo);
    $distribution = $searchEngine->calculateWorksheetDistribution($worksheetId);
} catch (Throwable $e) {
    error_log('Worksheet distribution error: ' . $e->getMessage());
    $distribution = [
        'total' => 0,
        'topicBreakdown' => [],
        'difficultyBreakdown' => ['easy' => 0, 'medium' => 0, 'hard' => 0, 'expert' => 0],
    ];
}

// Fetch worksheet
try {
    $stmt = $pdo->prepare("
        SELECT w.*, f.name AS folder_name
        FROM worksheets w
        LEFT JOIN worksheet_folders f ON f.id = w.folder_id
        WHERE w.id = ?
    ");
    $stmt->execute([$worksheetId]);
    $worksheet = $stmt->fetch();
} catch (Throwable $e) {
    error_log('Fetch worksheet error: ' . $e->getMessage());
    $worksheet = null;
}

if (!$worksheet) {
    echo '<meta charset="utf-8"><p>ไม่พบข้อมูลใบงานนี้</p><a href="worksheets.php">กลับสู่คลังใบงาน</a>';
    exit;
}

$pageTitle = $worksheet['title'];
$pageDesc = 'รายละเอียดและตัวอย่างใบงานการเรียนรู้';
$currentPage = 'worksheets.php';

// Fetch questions
try {
    $qStmt = $pdo->prepare("
        SELECT * FROM worksheet_questions
        WHERE worksheet_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $qStmt->execute([$worksheetId]);
    $questions = $qStmt->fetchAll() ?: [];
} catch (Throwable $e) {
    error_log('Fetch worksheet questions error: ' . $e->getMessage());
    $questions = [];
}

foreach ($questions as &$q) {
    if (!empty($q['options'])) {
        $decoded = json_decode((string)$q['options'], true);
        $q['options'] = is_array($decoded) ? $decoded : [];
    } else {
        $q['options'] = [];
    }
}
unset($q);

// Fetch assignments / usage history
try {
    $assignStmt = $pdo->prepare("
        SELECT a.*, c.title AS course_title
        FROM worksheet_assignments a
        LEFT JOIN courses c ON c.id = a.course_id
        WHERE a.worksheet_id = ?
        ORDER BY a.created_at DESC
    ");
    $assignStmt->execute([$worksheetId]);
    $assignments = $assignStmt->fetchAll() ?: [];
} catch (Throwable $e) {
    error_log('Fetch worksheet assignments error: ' . $e->getMessage());
    $assignments = [];
}

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
              <!-- Add Question Dropdown (Section 8) -->
              <div class="relative inline-block text-left" id="add-q-dropdown-wrapper">
                <button type="button" id="btn-add-question-menu" class="h-9 px-3.5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white text-[12px] font-bold transition shadow-xs flex items-center gap-1.5">
                  <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                  <span>+ เพิ่มคำถาม</span>
                  <svg class="w-3.5 h-3.5 text-pink-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <!-- Dropdown Menu -->
                <div id="add-q-dropdown-menu" class="hidden absolute right-0 mt-1.5 w-64 bg-white rounded-2xl shadow-xl border border-[#e2e8f0] py-2 z-30 transform opacity-0 scale-95 transition-all duration-150">
                  <div class="px-3.5 py-1.5 text-[11px] font-bold text-[#94a3b8] uppercase tracking-wider">
                    ตัวเลือกการเพิ่มข้อสอบ
                  </div>
                  <button type="button" id="btn-open-question-search" class="w-full px-3.5 py-2.5 text-left text-[13px] font-bold text-navy-950 hover:bg-pink-50 hover:text-pink-600 transition flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-pink-100 text-pink-600 flex items-center justify-center text-sm shrink-0">🔍</span>
                    <div>
                      <div>ค้นจากคลังข้อสอบ</div>
                      <div class="text-[11px] font-normal text-[#64748b]">Hybrid Search ดึงโจทย์เดิมมาใช้</div>
                    </div>
                  </button>
                  <a href="ai-worksheet.php" class="w-full px-3.5 py-2.5 text-left text-[13px] font-bold text-navy-950 hover:bg-purple-50 hover:text-purple-600 transition flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center text-sm shrink-0">✨</span>
                    <div>
                      <div>สร้างด้วย AI</div>
                      <div class="text-[11px] font-normal text-[#64748b]">สร้างโจทย์ใหม่ตามบริบท</div>
                    </div>
                  </a>
                  <button type="button" id="btn-add-question" class="w-full px-3.5 py-2.5 text-left text-[13px] font-bold text-navy-950 hover:bg-slate-50 transition flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center text-sm shrink-0">✍️</span>
                    <div>
                      <div>สร้างเอง</div>
                      <div class="text-[11px] font-normal text-[#64748b]">พิมพ์คำถามและตัวเลือกเอง</div>
                    </div>
                  </button>
                  <button type="button" id="btn-import-question" onclick="alert('ระบบพร้อมรองรับการนำเข้าไฟล์ Word/Excel หรือคลังข้อสอบกลาง')" class="w-full px-3.5 py-2.5 text-left text-[13px] font-bold text-navy-950 hover:bg-slate-50 transition flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-sm shrink-0">📥</span>
                    <div>
                      <div>Import ข้อสอบ</div>
                      <div class="text-[11px] font-normal text-[#64748b]">นำเข้าจากไฟล์ภายนอก</div>
                    </div>
                  </button>
                </div>
              </div>

              <button type="button" id="btn-save-changes" class="h-9 px-4 rounded-xl bg-navy-950 hover:bg-navy-900 text-white text-[12px] font-bold transition shadow-xs flex items-center gap-1.5">
                <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>บันทึกการแก้ไข</span>
              </button>
            </div>
          </div>

          <!-- Questions Container -->
          <div id="questions-wrapper" class="space-y-4">
            <?php if (empty($questions)): ?>
              <div class="bg-white rounded-2xl border border-[#e8ecf2] p-12 text-center text-[#64748b]">
                <div class="w-12 h-12 rounded-2xl bg-pink-50 text-pink-500 flex items-center justify-center mx-auto mb-3 text-xl font-bold">📝</div>
                <p class="font-bold text-[16px] text-navy-950 mb-1">ยังไม่มีคำถามในใบงานนี้</p>
                <p class="text-[13px] mb-5">คุณสามารถค้นหาข้อสอบที่มีอยู่แล้วจากคลังข้อสอบ หรือเริ่มต้นเขียนคำถามใหม่</p>
                <div class="flex items-center justify-center gap-3">
                  <button type="button" onclick="window.SmartQuestionSearch?.open()" class="h-10 px-5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white text-[13px] font-bold transition shadow-xs flex items-center gap-2">
                    <span>🔍 ค้นจากคลังข้อสอบ</span>
                  </button>
                  <button type="button" onclick="document.getElementById('btn-add-question').click()" class="h-10 px-4 rounded-xl border border-[#dce4ef] text-[13px] font-bold text-navy-900 hover:bg-slate-50 transition">
                    <span>✍️ เริ่มเขียนคำถามเอง</span>
                  </button>
                </div>
              </div>
            <?php else: ?>
              <?php foreach ($questions as $idx => $q): ?>
                <div class="question-card bg-white rounded-2xl border border-[#e8ecf2] p-5 shadow-sm transition-all" data-id="<?= $q['id'] ?>" data-source-id="<?= $q['source_question_id'] ?? '' ?>" data-skill="<?= htmlspecialchars($q['skill'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-difficulty="<?= htmlspecialchars($q['difficulty'] ?? 'medium', ENT_QUOTES, 'UTF-8') ?>" data-order="<?= $idx + 1 ?>">
                  <div class="flex items-start gap-3">
                    <span class="w-8 h-8 rounded-xl bg-pink-50 text-pink-600 font-black text-[14px] flex items-center justify-center shrink-0">
                      <?= $idx + 1 ?>
                    </span>

                    <div class="flex-1 min-w-0">
                      <div class="flex items-center justify-between gap-2 mb-2 no-print">
                        <div class="flex items-center gap-1.5 flex-wrap">
                          <span class="ws-badge bg-slate-100 text-slate-700 text-[10px]">
                            <?= htmlspecialchars($q['question_type'], ENT_QUOTES, 'UTF-8') ?>
                          </span>
                          <?php if (!empty($q['source_question_id'])): ?>
                            <span class="ws-badge bg-blue-50 text-blue-700 text-[10px] font-bold flex items-center gap-1">
                              <span>🔗 คลังข้อสอบ #<?= (int)$q['source_question_id'] ?></span>
                            </span>
                          <?php endif; ?>
                        </div>
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

        <!-- Sidebar: Topic Distribution, Metadata & Usage History -->
        <aside class="no-print space-y-6">
          <!-- Topic & Difficulty Distribution Panel (Section 20) -->
          <div id="topic-distribution-card" class="bg-white rounded-2xl border border-[#e8ecf2] p-5 shadow-sm">
            <div class="flex items-center justify-between gap-2 mb-3 pb-2 border-b border-[#f1f5f9]">
              <h3 class="text-[14px] font-black text-navy-950 uppercase tracking-wider flex items-center gap-1.5">
                <span>📊 สัดส่วนหัวข้อ</span>
              </h3>
              <span id="dist-total-badge" class="px-2.5 py-0.5 rounded-full bg-pink-50 text-pink-600 font-black text-[12px]">
                <?= $distribution['total'] ?> ข้อ
              </span>
            </div>

            <!-- Topic breakdown items -->
            <div class="space-y-3 mb-4">
              <div class="text-[11px] font-bold text-[#64748b] uppercase tracking-wider flex items-center justify-between">
                <span>หัวข้อ / Topic</span>
                <span>จำนวนข้อ (%)</span>
              </div>
              <div id="dist-topic-list" class="space-y-2.5">
                <?php if (empty($distribution['topicBreakdown'])): ?>
                  <p class="text-[12px] text-[#94a3b8] italic">ยังไม่มีหัวข้อ</p>
                <?php else: ?>
                  <?php foreach ($distribution['topicBreakdown'] as $tb): ?>
                    <div class="text-[12px]">
                      <div class="flex justify-between items-center mb-1">
                        <span class="font-bold text-navy-950 truncate max-w-[170px]" title="<?= htmlspecialchars($tb['topic'], ENT_QUOTES, 'UTF-8') ?>">
                          <?= htmlspecialchars($tb['topic'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="text-[#64748b] font-bold">
                          <?= $tb['count'] ?> ข้อ <span class="text-pink-600 font-black">(<?= $tb['percent'] ?>%)</span>
                        </span>
                      </div>
                      <div class="w-full h-2 bg-[#f1f5f9] rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-pink-500 to-indigo-500 rounded-full transition-all duration-500" style="width: <?= min(100, max(6, $tb['percent'])) ?>%"></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <!-- Difficulty breakdown -->
            <div class="pt-3 border-t border-[#f1f5f9]">
              <div class="text-[11px] font-bold text-[#64748b] uppercase tracking-wider mb-2">สัดส่วนความยาก (Difficulty)</div>
              <div id="dist-diff-list" class="grid grid-cols-3 gap-2 text-center text-[11px]">
                <div class="p-2 rounded-xl bg-emerald-50/70 border border-emerald-100">
                  <div class="text-emerald-800 font-bold">ง่าย</div>
                  <div id="dist-count-easy" class="text-[14px] font-black text-emerald-950 mt-0.5"><?= $distribution['difficultyBreakdown']['easy'] ?? 0 ?></div>
                </div>
                <div class="p-2 rounded-xl bg-amber-50/70 border border-amber-100">
                  <div class="text-amber-800 font-bold">ปานกลาง</div>
                  <div id="dist-count-medium" class="text-[14px] font-black text-amber-950 mt-0.5"><?= $distribution['difficultyBreakdown']['medium'] ?? 0 ?></div>
                </div>
                <div class="p-2 rounded-xl bg-rose-50/70 border border-rose-100">
                  <div class="text-rose-800 font-bold">ยาก</div>
                  <div id="dist-count-hard" class="text-[14px] font-black text-rose-950 mt-0.5"><?= ($distribution['difficultyBreakdown']['hard'] ?? 0) + ($distribution['difficultyBreakdown']['expert'] ?? 0) ?></div>
                </div>
              </div>
            </div>
          </div>

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
        explanation: explanation,
        skill: card.dataset.skill || "",
        difficulty: card.dataset.difficulty || "medium",
        source_question_id: card.dataset.sourceId ? parseInt(card.dataset.sourceId, 10) : null
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

  // Dropdown Toggle for "+ เพิ่มคำถาม" Menu
  const btnMenu = document.getElementById("btn-add-question-menu");
  const menuDropdown = document.getElementById("add-q-dropdown-menu");
  if (btnMenu && menuDropdown) {
    btnMenu.addEventListener("click", e => {
      e.stopPropagation();
      const isHidden = menuDropdown.classList.contains("hidden");
      if (isHidden) {
        menuDropdown.classList.remove("hidden");
        requestAnimationFrame(() => {
          menuDropdown.classList.remove("opacity-0", "scale-95");
          menuDropdown.classList.add("opacity-100", "scale-100");
        });
      } else {
        menuDropdown.classList.add("opacity-0", "scale-95");
        menuDropdown.classList.remove("opacity-100", "scale-100");
        setTimeout(() => menuDropdown.classList.add("hidden"), 150);
      }
    });

    document.addEventListener("click", e => {
      if (!menuDropdown.contains(e.target) && !btnMenu.contains(e.target)) {
        menuDropdown.classList.add("opacity-0", "scale-95");
        menuDropdown.classList.remove("opacity-100", "scale-100");
        setTimeout(() => menuDropdown.classList.add("hidden"), 150);
      }
    });
  }

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

  // Expose Worksheet Context to Question Search Client Controller
  window.NB_CURRENT_WORKSHEET_ID = <?= $worksheetId ?>;
  window.NB_WORKSHEET_TOPIC = <?= json_encode($worksheet['topic'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
  window.NB_WORKSHEET_SUBJECT = <?= json_encode($worksheet['subject'] ?? '', JSON_UNESCAPED_UNICODE) ?>;

  window.refreshWorksheetView = function(data) {
    window.location.reload();
  };
})();
</script>

<!-- ========================================================================= -->
<!-- MODAL: SMART QUESTION SEARCH ("ค้นจากคลังข้อสอบ" - Sections 8 - 18) -->
<!-- ========================================================================= -->
<div id="smart-question-search-modal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-3 sm:p-6">
  <!-- Backdrop -->
  <div id="qs-modal-backdrop" class="fixed inset-0 bg-navy-950/70 backdrop-blur-xs transition-opacity cursor-pointer"></div>

  <!-- Modal Dialog -->
  <div class="relative w-full max-w-[1040px] max-h-[92vh] bg-[#f8fafc] rounded-3xl shadow-2xl border border-[#e2e8f0] flex flex-col overflow-hidden z-10 my-auto animate-in fade-in zoom-in duration-150">
    <!-- Header -->
    <div class="px-6 py-4 bg-white border-b border-[#e8ecf2] flex items-center justify-between gap-4 sticky top-0 z-20">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-pink-50 text-pink-600 flex items-center justify-center text-lg font-black shrink-0 shadow-xs">
          🔍
        </div>
        <div>
          <h2 class="text-[18px] font-black text-navy-950 flex items-center gap-2">
            <span>ค้นจากคลังข้อสอบ</span>
            <span class="px-2 py-0.5 rounded-full bg-pink-100 text-pink-700 text-[10px] font-bold">Hybrid Search</span>
          </h2>
          <p class="text-[12px] text-[#64748b]">ค้นหาข้อสอบเดิมด้วยภาษาธรรมชาติ, ระบบตัดคำ และ AI Vector Semantic Search</p>
        </div>
      </div>

      <button type="button" id="btn-close-qs-modal" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-[#64748b] hover:text-navy-950 flex items-center justify-center transition text-sm font-bold" title="ปิดหน้าต่าง">
        ✕
      </button>
    </div>

    <!-- Search Box & Filter Section -->
    <div class="p-6 bg-white border-b border-[#e8ecf2] space-y-4">
      <!-- Natural Language Search Input (Sections 7, 8) -->
      <div class="relative flex items-center">
        <div class="absolute left-4 pointer-events-none text-[#94a3b8]">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <input
          type="text"
          id="qs-query-input"
          placeholder="ค้นหาหัวข้อ รูปแบบโจทย์ หรือคำสำคัญ... เช่น Present Simple ม.2 daily routine หรือ โจทย์กรดเบส A-Level"
          class="w-full h-12 pl-12 pr-28 rounded-2xl bg-[#f8fafc] border border-[#dce4ef] text-[14px] text-navy-950 font-medium focus:bg-white focus:border-pink-500 focus:ring-2 focus:ring-pink-200 outline-none transition"
        />
        <button
          type="button"
          id="btn-run-qs-search"
          class="absolute right-1.5 h-9 px-4 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-[12px] transition shadow-xs flex items-center gap-1.5"
        >
          <span>ค้นหา</span>
        </button>
      </div>

      <!-- Quick Recent Queries / Search Chips (Section 31) -->
      <div class="flex items-center gap-2 flex-wrap text-[11px]">
        <span class="text-[#94a3b8] font-bold">ตัวอย่างค้นหาเร็ว:</span>
        <button type="button" class="qs-chip px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 text-[#475569] font-semibold transition" data-query="Present Simple ม.2 daily routine">
          ✨ Present Simple ม.2 daily routine
        </button>
        <button type="button" class="qs-chip px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 text-[#475569] font-semibold transition" data-query="Acid Base A-Level calculation">
          🧪 Acid Base A-Level calculation
        </button>
        <button type="button" class="qs-chip px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 text-[#475569] font-semibold transition" data-query="Passive Voice ม.4">
          📘 Passive Voice ม.4
        </button>
        <button type="button" class="qs-chip px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 text-[#475569] font-semibold transition" data-query="สมการกำลังสอง ม.3">
          📐 สมการกำลังสอง ม.3
        </button>
      </div>

      <!-- Metadata Filters Bar (Section 9) -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 pt-2 border-t border-[#f1f5f9]">
        <div>
          <label class="block text-[11px] font-bold text-[#64748b] mb-1 uppercase">วิชา (Subject)</label>
          <select id="qs-filter-subject" class="w-full h-9 px-2.5 rounded-xl bg-[#f8fafc] border border-[#dce4ef] text-[12px] font-bold text-navy-950 focus:border-pink-500 outline-none">
            <option value="">ทุกวิชา</option>
            <option value="ภาษาอังกฤษ">ภาษาอังกฤษ (English)</option>
            <option value="เคมี">เคมี (Chemistry)</option>
            <option value="ฟิสิกส์">ฟิสิกส์ (Physics)</option>
            <option value="ชีววิทยา">ชีววิทยา (Biology)</option>
            <option value="คณิตศาสตร์">คณิตศาสตร์ (Math)</option>
            <option value="วิทยาศาสตร์">วิทยาศาสตร์ (Science)</option>
            <option value="ภาษาไทย">ภาษาไทย (Thai)</option>
            <option value="สังคมศึกษา">สังคมศึกษา (Social)</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-bold text-[#64748b] mb-1 uppercase">ระดับชั้น (Level)</label>
          <select id="qs-filter-level" class="w-full h-9 px-2.5 rounded-xl bg-[#f8fafc] border border-[#dce4ef] text-[12px] font-bold text-navy-950 focus:border-pink-500 outline-none">
            <option value="">ทุกระดับชั้น</option>
            <option value="ม.1">มัธยมศึกษาปีที่ 1 (ม.1)</option>
            <option value="ม.2">มัธยมศึกษาปีที่ 2 (ม.2)</option>
            <option value="ม.3">มัธยมศึกษาปีที่ 3 (ม.3)</option>
            <option value="ม.4">มัธยมศึกษาปีที่ 4 (ม.4)</option>
            <option value="ม.5">มัธยมศึกษาปีที่ 5 (ม.5)</option>
            <option value="ม.6">มัธยมศึกษาปีที่ 6 (ม.6)</option>
            <option value="A-Level">A-Level / สอบเข้ามหาวิทยาลัย</option>
            <option value="ประถมศึกษา">ประถมศึกษา (ป.1 - ป.6)</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-bold text-[#64748b] mb-1 uppercase">ความยาก (Difficulty)</label>
          <select id="qs-filter-difficulty" class="w-full h-9 px-2.5 rounded-xl bg-[#f8fafc] border border-[#dce4ef] text-[12px] font-bold text-navy-950 focus:border-pink-500 outline-none">
            <option value="">ทั้งหมด (All)</option>
            <option value="easy">ง่าย (Easy)</option>
            <option value="medium">ปานกลาง (Medium)</option>
            <option value="hard">ยาก (Hard)</option>
            <option value="expert">ยากมาก / A-Level (Very Hard)</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-bold text-[#64748b] mb-1 uppercase">จำนวนข้อที่ต้องการ (Section 10)</label>
          <select id="qs-target-count" class="w-full h-9 px-2.5 rounded-xl bg-[#f8fafc] border border-[#dce4ef] text-[12px] font-bold text-navy-950 focus:border-pink-500 outline-none">
            <option value="5">5 ข้อ</option>
            <option value="10" selected>10 ข้อ</option>
            <option value="15">15 ข้อ</option>
            <option value="20">20 ข้อ</option>
            <option value="30">30 ข้อ</option>
          </select>
        </div>

        <div class="flex items-end pb-1 col-span-2 sm:col-span-1">
          <label class="inline-flex items-center gap-2 cursor-pointer select-none">
            <input type="checkbox" id="qs-exclude-existing" checked class="w-4 h-4 rounded text-pink-600 border-[#cbd5e1] focus:ring-pink-500">
            <span class="text-[11px] font-bold text-[#475569] leading-tight">ไม่แสดงข้อที่อยู่ในใบงานนี้แล้ว</span>
          </label>
        </div>
      </div>

      <!-- Intent Parsing Feedback Banner (Section 21) -->
      <div id="qs-intent-banner" class="hidden p-3 rounded-xl bg-pink-50/50 border border-pink-100 flex items-center justify-between gap-3 flex-wrap text-[12px]">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="font-bold text-pink-900">🎯 วิเคราะห์เจตนาค้นหา:</span>
          <div id="qs-intent-tags" class="flex items-center gap-1.5 flex-wrap"></div>
        </div>
        <span class="text-[11px] text-[#64748b]">MMR Diversity & Near-Duplicate Filtering เปิดใช้งาน</span>
      </div>
    </div>

    <!-- Candidate Results Actions Bar -->
    <div class="px-6 py-3 bg-[#f1f5f9]/80 border-b border-[#e2e8f0] flex items-center justify-between gap-3 flex-wrap">
      <div class="flex items-center gap-2">
        <span class="text-[13px] font-bold text-[#475569]">ผลการค้นหา:</span>
        <span id="qs-total-found-badge" class="px-2.5 py-0.5 rounded-full bg-white text-navy-950 font-black text-[12px] border border-[#cbd5e1] shadow-2xs">0 ข้อ</span>
        <span class="text-[11px] text-[#94a3b8]">(คัดกรองความซ้ำซ้อนด้วยค่าความคล้ายคลึง &gt; 0.88)</span>
      </div>

      <div class="flex items-center gap-2">
        <!-- Smart Select Button (Section 12) -->
        <button type="button" id="btn-qs-smart-select" class="h-8 px-3.5 rounded-xl bg-gradient-to-r from-pink-500 to-indigo-600 text-white font-bold text-[12px] hover:opacity-95 transition shadow-xs flex items-center gap-1.5">
          <span>✨ เลือกให้ฉัน (Smart Select)</span>
        </button>
        <button type="button" id="btn-qs-select-all" class="h-8 px-3 rounded-xl bg-white hover:bg-slate-50 border border-[#cbd5e1] text-navy-900 font-bold text-[12px] transition">
          เลือกทั้งหมด
        </button>
        <button type="button" id="btn-qs-clear-select" class="h-8 px-3 rounded-xl bg-white hover:bg-slate-50 border border-[#cbd5e1] text-[#64748b] font-bold text-[12px] transition">
          ล้างการเลือก
        </button>
      </div>
    </div>

    <!-- Results Scroll Container -->
    <div class="flex-1 p-6 overflow-y-auto space-y-4 min-h-[320px]">
      <!-- Loading State -->
      <div id="qs-loading" class="hidden py-16 text-center text-[#64748b]">
        <div class="w-10 h-10 border-3 border-pink-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="font-bold text-[14px]">กำลังสแกนคลังข้อสอบและวิเคราะห์ความสอดคล้องทางความหมาย...</p>
      </div>

      <!-- Insufficient Notice (Section 16) -->
      <div id="qs-insufficient-notice" class="hidden p-4 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-2 text-amber-900 font-bold text-[13px]">
          <span>⚠️</span>
          <span id="qs-insufficient-text">พบข้อสอบที่ตรงกับเงื่อนไข 6 ข้อ (ต้องการ 10 ข้อ)</span>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" id="btn-insufficient-use" class="h-8 px-3 rounded-xl bg-white border border-amber-300 text-amber-900 font-bold text-[12px] hover:bg-amber-100/50 transition">
            ใช้ข้อที่พบทั้งหมด
          </button>
          <a href="ai-worksheet.php" class="h-8 px-3 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-[12px] flex items-center gap-1 transition shadow-2xs">
            <span>✨ ให้ AI สร้างเพิ่ม</span>
          </a>
        </div>
      </div>

      <!-- Empty State (Section 32) -->
      <div id="qs-empty" class="hidden py-16 text-center text-[#64748b]">
        <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3 text-2xl">
          🔍
        </div>
        <h4 class="font-bold text-[16px] text-navy-950 mb-1">ยังไม่พบข้อสอบที่ตรงกับเงื่อนไขนี้</h4>
        <p class="text-[13px] text-[#64748b] max-w-[400px] mx-auto mb-4">ลองปรับคำค้นหา เลือกวิชาหรือระดับชั้น หรือให้ระบบ AI ช่วยสร้างโจทย์ใหม่ในหัวข้อนี้</p>
        <div class="flex items-center justify-center gap-3">
          <button type="button" onclick="document.getElementById('qs-query-input').value=''; SmartQuestionSearch.search();" class="h-9 px-4 rounded-xl border border-[#cbd5e1] bg-white hover:bg-slate-50 text-[12px] font-bold text-navy-900">
            ล้างตัวกรอง
          </button>
          <a href="ai-worksheet.php" class="h-9 px-4 rounded-xl bg-pink-500 hover:bg-pink-600 text-white text-[12px] font-bold shadow-xs flex items-center gap-1.5">
            <span>✨ ให้ AI สร้างข้อใหม่</span>
          </a>
        </div>
      </div>

      <!-- Results Container -->
      <div id="qs-results-container" class="space-y-3"></div>
    </div>

    <!-- Sticky Selection Footer (Section 11, 18) -->
    <div id="qs-sticky-bar" class="px-6 py-4 bg-white border-t border-[#e8ecf2] flex items-center justify-between gap-4 transition-all duration-300 transform translate-y-full opacity-0 pointer-events-none sticky bottom-0 z-20 shadow-xl">
      <div class="flex items-center gap-3">
        <div class="text-[14px] font-bold text-navy-950">
          เลือกแล้ว <span id="qs-selected-count" class="text-pink-600 font-black text-[16px]">0</span> / <span id="qs-target-display">10</span> ข้อ
        </div>
        <div class="hidden sm:flex items-center gap-2 border-l border-[#e2e8f0] pl-3">
          <span class="text-[12px] text-[#64748b] font-medium">ตำแหน่งแทรก:</span>
          <select id="qs-insert-position" class="h-8 px-2.5 rounded-lg bg-[#f8fafc] border border-[#dce4ef] text-[12px] font-bold text-navy-900 outline-none">
            <option value="end">ต่อท้ายใบงาน (แนะนำ)</option>
            <option value="start">แทรกไว้หน้าสุด</option>
          </select>
        </div>
      </div>

      <button type="button" id="btn-qs-insert" class="h-10 px-6 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-[13px] transition shadow-[0_4px_14px_rgba(231,45,130,0.3)] flex items-center gap-2">
        <span>+ เพิ่มเข้าใบงาน (<span id="btn-qs-insert-count">0</span> ข้อ)</span>
      </button>
    </div>
  </div>
</div>

<script src="../assets/js/admin-question-search.js"></script>
</body>
</html>
