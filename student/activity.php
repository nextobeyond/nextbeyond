<?php
/**
 * student/activity.php
 * NEXTBEYOND V2 — Phase 3: Student Learning Activity Runner & Results
 * Supports Worksheet, Practice, Homework, Post-Test with Autosave & LocalStorage Fallback
 */
declare(strict_types=1);

$pageTitle   = 'กิจกรรมการเรียนรู้';
$currentPage = 'roadmap.php';
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/phase3-mastery-service.php';

$p3 = new Phase3MasteryService($pdo);
$currentStudentId = (int)($currentUser['id'] ?? 0);
$assignmentId = (int)($_GET['assignment_id'] ?? 0);
$submissionId = (int)($_GET['submission_id'] ?? 0);
$startNewAttempt = !empty($_GET['new_attempt']);

if ($assignmentId < 1 && $submissionId > 0) {
    $stmtFindAss = $pdo->prepare("SELECT assignment_id FROM activity_submissions WHERE id = ? AND student_id = ?");
    $stmtFindAss->execute([$submissionId, $currentStudentId]);
    $assignmentId = (int)$stmtFindAss->fetchColumn();
}

if ($assignmentId < 1) {
    header('Location: index.php');
    exit;
}

// 1. Access Control Check (Section 59, 71, 72)
if (!$p3->canStudentAccessAssignment($currentStudentId, $assignmentId)) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
      <meta charset="UTF-8"><title>ไม่มีสิทธิ์เข้าถึง - Nextbeyond</title>
      <link rel="stylesheet" href="../assets/css/output.css">
    </head>
    <body class="bg-[#f4f7fb] min-h-screen flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl p-8 max-w-md w-full text-center border border-slate-200 shadow-sm">
        <div class="w-16 h-16 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center mx-auto mb-4 text-2xl font-bold">🚫</div>
        <h2 class="text-xl font-bold text-navy-950 mb-2">ไม่มีสิทธิ์เข้าถึงกิจกรรมนี้</h2>
        <p class="text-sm text-slate-500 mb-6">กิจกรรมนี้ไม่ได้ถูกมอบหมายให้บัญชีของคุณ หรือยังไม่ได้ลงทะเบียนในวิชาดังกล่าว</p>
        <a href="index.php" class="inline-flex px-6 py-2.5 rounded-xl bg-navy-950 text-white font-bold text-sm hover:bg-slate-800 transition">กลับหน้าหลัก</a>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// 2. Fetch Assignment Details
$assignment = $p3->getAssignmentDetails($assignmentId);
if (!$assignment) {
    header('Location: index.php');
    exit;
}

// 3. Handle New Attempt if requested and allowed
if ($startNewAttempt) {
    $maxAttempts = (int)($assignment['max_attempts'] ?? 1);
    $stmtAttempts = $pdo->prepare("SELECT COUNT(*) FROM activity_submissions WHERE assignment_id = ? AND student_id = ?");
    $stmtAttempts->execute([$assignmentId, $currentStudentId]);
    $attemptCount = (int)$stmtAttempts->fetchColumn();

    if ($maxAttempts === 0 || $attemptCount < $maxAttempts) {
        $nextAttemptNum = $attemptCount + 1;
        $stmtCreate = $pdo->prepare("
            INSERT INTO activity_submissions (
                assignment_id, student_id, attempt_number, status, started_at
            ) VALUES (?, ?, ?, 'in_progress', NOW())
        ");
        $stmtCreate->execute([$assignmentId, $currentStudentId, $nextAttemptNum]);
        $newSubId = (int)$pdo->lastInsertId();
        header("Location: activity.php?assignment_id={$assignmentId}&submission_id={$newSubId}");
        exit;
    }
}

// 4. Get or Create Current Submission
if ($submissionId > 0) {
    $stmtSub = $pdo->prepare("SELECT * FROM activity_submissions WHERE id = ? AND student_id = ? AND assignment_id = ?");
    $stmtSub->execute([$submissionId, $currentStudentId, $assignmentId]);
    $submission = $stmtSub->fetch(PDO::FETCH_ASSOC);
    if (!$submission) {
        $submission = $p3->getOrCreateSubmission($assignmentId, $currentStudentId);
    }
} else {
    $submission = $p3->getOrCreateSubmission($assignmentId, $currentStudentId);
}
$submissionId = (int)$submission['id'];
$submission['percentage'] = $submission['score_percent'] ?? $submission['percentage'] ?? null;

// 5. Fetch saved answers
$stmtSaved = $pdo->prepare("
    SELECT question_id, student_answer, is_correct, score, time_spent_seconds, hint_used, saved_at
    FROM activity_answers
    WHERE submission_id = ?
");
$stmtSaved->execute([$submissionId]);
$savedAnswers = [];
foreach ($stmtSaved->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $savedAnswers[(int)$row['question_id']] = [
        'answer' => (string)$row['student_answer'],
        'is_correct' => $row['is_correct'],
        'score' => (float)($row['score'] ?? 0),
        'time_spent' => (int)$row['time_spent_seconds'],
        'hint_used' => (bool)$row['hint_used'],
    ];
}

$activityType = (string)($assignment['activity_type'] ?? 'worksheet');
$typeLabels = [
    'worksheet' => ['label' => 'ใบงาน (Worksheet)', 'badge' => 'bg-sky-100 text-sky-800 border-sky-200', 'color' => '#0284c7'],
    'practice'  => ['label' => 'ฝึกฝน (Practice)',   'badge' => 'bg-indigo-100 text-indigo-800 border-indigo-200', 'color' => '#6366f1'],
    'homework'  => ['label' => 'การบ้าน (Homework)',  'badge' => 'bg-amber-100 text-amber-800 border-amber-200', 'color' => '#f59e0b'],
    'posttest'  => ['label' => 'แบบทดสอบหลังเรียน (Post-Test)', 'badge' => 'bg-purple-100 text-purple-800 border-purple-200', 'color' => '#8b5cf6'],
    'remediation' => ['label' => 'แนะนำให้ทบทวน', 'badge' => 'bg-pink-100 text-pink-800 border-pink-200', 'color' => '#ec4899'],
    'mastery_check' => ['label' => 'ตรวจความเข้าใจ (Mastery Check)', 'badge' => 'bg-emerald-100 text-emerald-800 border-emerald-200', 'color' => '#10b981'],
];
$currentTypeInfo = $typeLabels[$activityType] ?? $typeLabels['worksheet'];
$isPracticeMode = in_array($activityType, ['practice', 'remediation'], true);
$isPostTestMode = ($activityType === 'posttest');
$isCompleted = in_array($submission['status'], ['submitted', 'completed'], true);
$questions = $assignment['questions'] ?? [];
$totalQuestions = count($questions);

// Pre vs Post comparison for Post-Test
$prePostCompare = null;
if ($isPostTestMode && $isCompleted) {
    $topicName = (string)($assignment['topic_name'] ?? $assignment['topic'] ?? '');
    $courseId = (int)($assignment['course_id'] ?? 0);
    if ($courseId > 0 && $topicName !== '') {
        $prePostCompare = $p3->getPrePostComparison($currentStudentId, $courseId, $topicName);
    }
}

// Check attempt limits
$maxAttempts = (int)($assignment['max_attempts'] ?? 1);
$stmtAllAttempts = $pdo->prepare("SELECT id, attempt_number, status, score, max_score, score_percent AS percentage, submitted_at FROM activity_submissions WHERE assignment_id = ? AND student_id = ? ORDER BY attempt_number ASC");
$stmtAllAttempts->execute([$assignmentId, $currentStudentId]);
$allAttempts = $stmtAllAttempts->fetchAll(PDO::FETCH_ASSOC);
$canRetry = ($maxAttempts === 0 || count($allAttempts) < $maxAttempts);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($assignment['title']) ?> - Nextbeyond</title>
  <link rel="stylesheet" href="../assets/css/output.css?v=<?= filemtime(__DIR__ . '/../assets/css/output.css') ?>">
  <link rel="stylesheet" href="../assets/css/student-portal.css?v=<?= filemtime(__DIR__ . '/../assets/css/student-portal.css') ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="../assets/js/student-guard.js"></script>
  <style>
    .choice-card {
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .choice-card:hover {
      border-color: #f472b6;
      background-color: #fdf2f8;
    }
    .choice-card.selected {
      border-color: #ec4899;
      background-color: #fdf2f8;
      box-shadow: 0 0 0 2px rgba(236, 72, 153, 0.2);
    }
    .q-step-btn.answered {
      background-color: #3b82f6;
      color: #ffffff;
    }
    .q-step-btn.current {
      box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.4);
      border-color: #ec4899;
    }
  </style>
</head>
<body class="bg-[#f4f7fb] text-navy-950 font-sans antialiased min-h-screen flex flex-col">

  <!-- Top Sticky Header -->
  <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-slate-200 px-4 sm:px-6 py-3.5 shadow-2xs">
    <div class="max-w-4xl mx-auto flex items-center justify-between gap-4">
      <div class="flex items-center gap-3 min-w-0">
        <a href="index.php" class="w-9 h-9 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 flex items-center justify-center text-slate-600 transition shrink-0" title="กลับหน้าหลัก">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border <?= $currentTypeInfo['badge'] ?>">
              <?= $currentTypeInfo['label'] ?>
            </span>
            <?php if (!empty($assignment['course_title'])): ?>
              <span class="text-xs text-slate-500 font-medium truncate">📚 <?= htmlspecialchars($assignment['course_title']) ?></span>
            <?php endif; ?>
          </div>
          <h1 class="text-sm sm:text-base font-bold text-navy-950 truncate mt-0.5">
            <?= htmlspecialchars($assignment['title']) ?>
          </h1>
        </div>
      </div>

      <!-- Autosave Status Indicator -->
      <div class="flex items-center gap-2 shrink-0">
        <?php if (!$isCompleted): ?>
          <div id="autosave-badge" class="flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-semibold">
            <span id="save-dot" class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span id="save-text">พร้อมบันทึก</span>
          </div>
        <?php else: ?>
          <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold">
            ✓ ส่งคำตอบแล้ว
          </span>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- Main Content Container -->
  <main class="flex-1 max-w-4xl w-full mx-auto p-4 sm:p-6 pb-24">

    <?php if ($isCompleted): ?>
      <!-- ═══════════════════════════════════════════════════════════════════ -->
      <!-- RESULT / SUMMARY SCREEN (After Submission)                          -->
      <!-- ═══════════════════════════════════════════════════════════════════ -->
      <div class="space-y-6">
        
        <!-- Score Hero Card -->
        <div class="rounded-3xl bg-white border border-slate-200 p-6 sm:p-8 shadow-sm text-center relative overflow-hidden">
          <div class="max-w-md mx-auto space-y-3">
            <div class="inline-flex p-3 rounded-2xl <?= (float)$submission['percentage'] >= (float)($assignment['pass_score'] ?? 60) ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600' ?> text-3xl font-black">
              <?= (float)$submission['percentage'] >= (float)($assignment['pass_score'] ?? 60) ? '🎉' : '📖' ?>
            </div>
            <h2 class="text-xl sm:text-2xl font-black text-navy-950">
              <?= (float)$submission['percentage'] >= (float)($assignment['pass_score'] ?? 60) ? 'ทำได้ยอดเยี่ยม!' : 'พยายามได้ดีมาก!' ?>
            </h2>
            <p class="text-sm text-slate-500">
              คุณได้ทำกิจกรรม <span class="font-bold text-navy-900"><?= htmlspecialchars($assignment['title']) ?></span> เสร็จสิ้นแล้ว
            </p>

            <!-- Score Metric Display -->
            <div class="py-4 flex items-center justify-center gap-6">
              <div class="text-center">
                <span class="text-xs text-slate-400 font-bold block">คะแนนที่ได้</span>
                <span class="text-3xl sm:text-4xl font-black text-pink-600"><?= (float)$submission['score'] ?></span>
                <span class="text-sm text-slate-400 font-bold">/ <?= (float)$submission['max_score'] ?></span>
              </div>
              <div class="w-px h-12 bg-slate-200"></div>
              <div class="text-center">
                <span class="text-xs text-slate-400 font-bold block">คิดเป็นร้อยละ</span>
                <span class="text-3xl sm:text-4xl font-black text-navy-950"><?= round((float)$submission['percentage'], 1) ?>%</span>
              </div>
            </div>

            <!-- Pre vs Post Comparison Card (Acceptance Test #4) -->
            <?php if ($prePostCompare): ?>
              <div class="mt-4 p-4 rounded-2xl bg-gradient-to-r from-indigo-50 via-purple-50 to-pink-50 border border-indigo-200/80 text-left space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-indigo-900">📊 ผลการเรียนรู้เปรียบเทียบ (Pre vs Post-Test)</span>
                  <span class="text-xs font-extrabold px-2 py-0.5 rounded-full <?= $prePostCompare['improvement_points'] >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                    <?= $prePostCompare['improvement_points'] >= 0 ? '+' : '' ?><?= $prePostCompare['improvement_points'] ?> points
                  </span>
                </div>
                <div class="grid grid-cols-2 gap-3 text-center py-1">
                  <div class="bg-white/80 p-2.5 rounded-xl border border-indigo-100">
                    <span class="text-[11px] text-slate-500 font-semibold block">Pre-test (ก่อนเรียน)</span>
                    <strong class="text-base font-black text-slate-700"><?= $prePostCompare['pre_score'] ?>%</strong>
                  </div>
                  <div class="bg-white/80 p-2.5 rounded-xl border border-purple-100">
                    <span class="text-[11px] text-purple-700 font-semibold block">Post-test (หลังเรียน)</span>
                    <strong class="text-base font-black text-purple-900"><?= $prePostCompare['post_score'] ?>%</strong>
                  </div>
                </div>
                <p class="text-[11px] text-slate-500 italic text-center">
                  พัฒนาการในหัวข้อ <?= htmlspecialchars($assignment['topic_name'] ?? $assignment['topic'] ?? 'บทเรียนนี้') ?>
                </p>
              </div>
            <?php endif; ?>

            <!-- Action buttons -->
            <div class="pt-4 flex items-center justify-center gap-3 flex-wrap">
              <a href="index.php" class="px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs sm:text-sm font-bold transition">
                กลับหน้าหลัก
              </a>
              <a href="skill-map.php" class="px-5 py-2.5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white text-xs sm:text-sm font-bold shadow-md shadow-pink-500/20 transition">
                ดูแผนที่ทักษะ (Skill Map)
              </a>
              <?php if ($canRetry): ?>
                <a href="activity.php?assignment_id=<?= $assignmentId ?>&new_attempt=1" class="px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs sm:text-sm font-bold transition">
                  ทำซ้ำ (Attempt #<?= count($allAttempts) + 1 ?>)
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Question Review Breakdown -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="font-bold text-navy-950 text-base">ตรวจคำตอบ & คำอธิบาย</h3>
            <span class="text-xs text-slate-500"><?= $totalQuestions ?> ข้อ</span>
          </div>

          <div class="space-y-4">
            <?php foreach ($questions as $idx => $q): ?>
              <?php
                $qId = (int)$q['id'];
                $ansData = $savedAnswers[$qId] ?? null;
                $studentAnswer = $ansData ? (string)$ansData['answer'] : '';
                $isCorrect = $ansData ? (bool)$ansData['is_correct'] : false;
                $correctAnswer = (string)($q['correct_answer'] ?? '');
                $options = is_string($q['options']) ? json_decode($q['options'], true) : ($q['options'] ?? []);
                if (!is_array($options)) $options = [];
              ?>
              <div class="p-4 rounded-2xl border <?= $isCorrect ? 'border-emerald-200 bg-emerald-50/30' : 'border-rose-200 bg-rose-50/30' ?> space-y-3">
                <div class="flex items-start justify-between gap-3">
                  <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold <?= $isCorrect ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white' ?>">
                      <?= $isCorrect ? '✓' : '✕' ?>
                    </span>
                    <span class="font-bold text-xs text-slate-600">ข้อที่ <?= $idx + 1 ?></span>
                  </div>
                  <span class="text-xs font-bold <?= $isCorrect ? 'text-emerald-700' : 'text-rose-700' ?>">
                    <?= $isCorrect ? '+' . (float)($q['points'] ?? 1) . ' คะแนน' : '0 คะแนน' ?>
                  </span>
                </div>

                <div class="text-sm font-semibold text-navy-950">
                  <?= nl2br(htmlspecialchars($q['question_text'])) ?>
                </div>

                <!-- Choices review -->
                <div class="space-y-1.5 pt-1">
                  <?php if (!empty($options)): ?>
                    <?php foreach ($options as $optIdx => $opt): ?>
                      <?php
                        $optText = is_array($opt) ? ($opt['text'] ?? '') : (string)$opt;
                        $optKey = is_array($opt) ? ($opt['key'] ?? (string)$optIdx) : (string)$optIdx;
                        $isSelected = ($studentAnswer === $optKey || $studentAnswer === $optText);
                        $isOptCorrect = ($correctAnswer === $optKey || $correctAnswer === $optText);
                        
                        $optClass = 'border-slate-200 bg-white text-slate-700';
                        if ($isSelected && $isCorrect) {
                            $optClass = 'border-emerald-500 bg-emerald-100/60 font-bold text-emerald-900';
                        } elseif ($isSelected && !$isCorrect) {
                            $optClass = 'border-rose-500 bg-rose-100/60 font-bold text-rose-900';
                        } elseif ($isOptCorrect && !$isPostTestMode) {
                            $optClass = 'border-emerald-400 bg-emerald-50 text-emerald-800';
                        }
                      ?>
                      <div class="p-2.5 rounded-xl border text-xs flex items-center justify-between <?= $optClass ?>">
                        <span><?= htmlspecialchars($optText) ?></span>
                        <div class="flex items-center gap-1.5">
                          <?php if ($isSelected): ?>
                            <span class="text-[10px] px-2 py-0.5 rounded-md <?= $isCorrect ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white' ?>">คำตอบของคุณ</span>
                          <?php endif; ?>
                          <?php if ($isOptCorrect && !$isPostTestMode): ?>
                            <span class="text-[10px] px-2 py-0.5 rounded-md bg-emerald-600 text-white">คำตอบที่ถูก</span>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="p-2.5 rounded-xl border text-xs bg-white text-slate-700">
                      <span>คำตอบของคุณ: <b><?= htmlspecialchars($studentAnswer ?: '(ไม่ได้ตอบ)') ?></b></span>
                      <?php if (!$isPostTestMode): ?>
                        <span class="block text-slate-500 mt-1">เฉลย: <b><?= htmlspecialchars($correctAnswer) ?></b></span>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Explanation (if not posttest or already submitted) -->
                <?php if (!empty($q['explanation']) && !$isPostTestMode): ?>
                  <div class="p-3 rounded-xl bg-slate-100/80 text-xs text-slate-600 space-y-1">
                    <span class="font-bold text-slate-800 block">💡 คำอธิบาย:</span>
                    <p><?= nl2br(htmlspecialchars($q['explanation'])) ?></p>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div>

    <?php else: ?>
      <!-- ═══════════════════════════════════════════════════════════════════ -->
      <!-- ACTIVE ACTIVITY RUNNER (In Progress)                                -->
      <!-- ═══════════════════════════════════════════════════════════════════ -->
      
      <!-- Stepper / Question Pills Bar -->
      <div class="mb-5 bg-white rounded-2xl p-3 border border-slate-200 shadow-2xs">
        <div class="flex items-center justify-between mb-2 px-1">
          <span class="text-xs font-bold text-slate-500">
            คำถาม <span id="current-q-index-label">1</span> จาก <?= $totalQuestions ?>
          </span>
          <span class="text-xs font-extrabold text-pink-600" id="answered-count-label">
            ตอบแล้ว <?= count($savedAnswers) ?> / <?= $totalQuestions ?>
          </span>
        </div>
        <div class="flex items-center gap-1.5 overflow-x-auto py-1 scrollbar-thin">
          <?php foreach ($questions as $idx => $q): ?>
            <?php
              $qId = (int)$q['id'];
              $isAnswered = isset($savedAnswers[$qId]) && $savedAnswers[$qId]['answer'] !== '';
            ?>
            <button type="button"
                    class="q-step-btn w-8 h-8 rounded-xl text-xs font-bold border border-slate-200 flex items-center justify-center shrink-0 cursor-pointer transition-all <?= $idx === 0 ? 'current ' : '' ?><?= $isAnswered ? 'answered' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' ?>"
                    data-step-index="<?= $idx ?>"
                    id="step-btn-<?= $idx ?>">
              <?= $idx + 1 ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Question Cards Container -->
      <div id="questions-container" class="space-y-6">
        <?php foreach ($questions as $idx => $q): ?>
          <?php
            $qId = (int)$q['id'];
            $ansData = $savedAnswers[$qId] ?? null;
            $currentAnswer = $ansData ? (string)$ansData['answer'] : '';
            $options = is_string($q['options']) ? json_decode($q['options'], true) : ($q['options'] ?? []);
            if (!is_array($options)) $options = [];
            $qType = (string)($q['question_type'] ?? 'multipleChoice');
          ?>
          <div class="question-slide bg-white rounded-3xl border border-slate-200 p-5 sm:p-7 shadow-sm space-y-5 <?= $idx === 0 ? '' : 'hidden' ?>"
               data-index="<?= $idx ?>"
               data-question-id="<?= $qId ?>"
               id="q-slide-<?= $idx ?>">
            
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
              <span class="px-3 py-1 rounded-xl bg-slate-100 text-navy-950 font-black text-xs">
                ข้อที่ <?= $idx + 1 ?>
              </span>
              <div class="flex items-center gap-2">
                <?php if (!empty($q['difficulty'])): ?>
                  <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-600">
                    <?= htmlspecialchars($q['difficulty']) ?>
                  </span>
                <?php endif; ?>
                <span class="text-xs font-bold text-slate-500"><?= (float)($q['points'] ?? 1) ?> คะแนน</span>
              </div>
            </div>

            <!-- Question Text -->
            <div class="text-base sm:text-lg font-bold text-navy-950 leading-relaxed">
              <?= nl2br(htmlspecialchars($q['question_text'])) ?>
            </div>

            <!-- Optional Question Image -->
            <?php if (!empty($q['image_url'])): ?>
              <div class="rounded-2xl overflow-hidden border border-slate-200 max-w-md mx-auto">
                <img src="<?= htmlspecialchars($q['image_url']) ?>" alt="Question media" class="w-full object-cover">
              </div>
            <?php endif; ?>

            <!-- Interactive Answer Options -->
            <div class="space-y-2.5 pt-2">
              <?php if (!empty($options)): ?>
                <?php foreach ($options as $optIdx => $opt): ?>
                  <?php
                    $optText = is_array($opt) ? ($opt['text'] ?? '') : (string)$opt;
                    $optKey = is_array($opt) ? ($opt['key'] ?? (string)$optIdx) : (string)$optIdx;
                    $isSelected = ($currentAnswer === $optKey || $currentAnswer === $optText);
                  ?>
                  <label class="choice-card flex items-center gap-3 p-3.5 sm:p-4 rounded-2xl border border-slate-200 cursor-pointer <?= $isSelected ? 'selected' : 'bg-slate-50/50' ?>">
                    <input type="radio"
                           name="answer_<?= $qId ?>"
                           value="<?= htmlspecialchars($optKey) ?>"
                           data-opt-text="<?= htmlspecialchars($optText) ?>"
                           class="w-4 h-4 text-pink-600 focus:ring-pink-500 border-slate-300"
                           <?= $isSelected ? 'checked' : '' ?>>
                    <span class="text-sm sm:text-base text-navy-950 font-medium select-none flex-1">
                      <?= htmlspecialchars($optText) ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              <?php else: ?>
                <!-- Short Answer Text Input -->
                <div class="space-y-1.5">
                  <label class="text-xs font-bold text-slate-600">พิมพ์คำตอบของคุณ:</label>
                  <input type="text"
                         name="answer_<?= $qId ?>"
                         value="<?= htmlspecialchars($currentAnswer) ?>"
                         placeholder="ป้อนคำตอบที่นี่..."
                         class="short-answer-input w-full p-3.5 rounded-2xl border border-slate-200 focus:border-pink-500 focus:ring-2 focus:ring-pink-500/20 text-sm font-medium bg-white">
                </div>
              <?php endif; ?>
            </div>

            <!-- Practice Mode Hint (Section 18) -->
            <?php if ($isPracticeMode && !empty($q['hint'])): ?>
              <div class="pt-2">
                <button type="button"
                        class="btn-toggle-hint text-xs font-bold text-amber-600 hover:text-amber-700 flex items-center gap-1.5 cursor-pointer">
                  <span>💡 ดูคำใบ้</span>
                </button>
                <div class="hint-box hidden mt-2 p-3.5 rounded-2xl bg-amber-50 border border-amber-200 text-xs text-amber-800 leading-relaxed">
                  <?= nl2br(htmlspecialchars($q['hint'])) ?>
                </div>
              </div>
            <?php endif; ?>

          </div>
        <?php endforeach; ?>
      </div>

      <!-- Sticky Bottom Action Bar (Section 61 Mobile-friendly) -->
      <div class="fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur-md border-t border-slate-200 px-4 py-3 z-30 shadow-lg">
        <div class="max-w-4xl mx-auto flex items-center justify-between gap-3">
          <button type="button"
                  id="btn-prev"
                  class="px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs sm:text-sm font-bold transition flex items-center gap-1 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
            <span>‹ ก่อนหน้า</span>
          </button>

          <div class="flex items-center gap-2">
            <button type="button"
                    id="btn-submit"
                    class="px-5 py-2.5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white text-xs sm:text-sm font-black shadow-md shadow-pink-500/20 transition flex items-center gap-1.5 cursor-pointer">
              <span>ส่งคำตอบ</span>
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </button>
          </div>

          <button type="button"
                  id="btn-next"
                  class="px-4 py-2.5 rounded-xl bg-navy-950 hover:bg-slate-800 text-white text-xs sm:text-sm font-bold transition flex items-center gap-1 cursor-pointer">
            <span>ถัดไป ›</span>
          </button>
        </div>
      </div>

      <!-- Submit Confirmation Modal -->
      <div id="submit-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-100 text-center space-y-4">
          <div class="w-14 h-14 rounded-2xl bg-pink-50 text-pink-500 flex items-center justify-center mx-auto text-2xl">
            📝
          </div>
          <h3 class="text-lg font-bold text-navy-950">ยืนยันการส่งคำตอบ?</h3>
          <p id="submit-modal-desc" class="text-xs text-slate-500 leading-relaxed">
            ระบบจะตรวจคำตอบและบันทึกคะแนนเข้าสู่ระบบการเรียนรู้ของคุณ
          </p>
          <div class="flex items-center justify-center gap-2 pt-2">
            <button type="button" id="btn-cancel-submit" class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-xs font-bold hover:bg-slate-50 transition cursor-pointer">
              กลับไปตรวจ
            </button>
            <button type="button" id="btn-confirm-submit" class="flex-1 py-2.5 rounded-xl bg-pink-500 text-white text-xs font-black hover:bg-pink-600 shadow-md shadow-pink-500/20 transition cursor-pointer">
              ยืนยันส่ง
            </button>
          </div>
        </div>
      </div>

    <?php endif; ?>

  </main>

  <!-- Client-side Logic (Autosave, Stepper, LocalStorage Sync) -->
  <script>
    (function() {
      const isCompleted = <?= $isCompleted ? 'true' : 'false' ?>;
      if (isCompleted) return;

      const submissionId = <?= (int)$submissionId ?>;
      const assignmentId = <?= (int)$assignmentId ?>;
      const totalQuestions = <?= (int)$totalQuestions ?>;
      const storageKey = `nb_activity_sub_${submissionId}`;

      let currentIndex = 0;
      let answers = {};
      let pendingSync = {};
      let autosaveTimeout = null;

      // Initialize from server saved answers
      <?php foreach ($savedAnswers as $qId => $row): ?>
        answers[<?= (int)$qId ?>] = <?= json_encode((string)$row['answer']) ?>;
      <?php endforeach; ?>

      // Check LocalStorage fallback (Section 15, 73)
      try {
        const localData = JSON.parse(localStorage.getItem(storageKey) || '{}');
        if (localData && typeof localData === 'object') {
          for (const [qid, val] of Object.entries(localData)) {
            // Only adopt local if not already present from server
            if (!answers[qid]) {
              answers[qid] = val;
              pendingSync[qid] = val;
            }
          }
        }
      } catch (e) {
        console.warn('LocalStorage not available', e);
      }

      const slides = document.querySelectorAll('.question-slide');
      const stepBtns = document.querySelectorAll('.q-step-btn');
      const prevBtn = document.getElementById('btn-prev');
      const nextBtn = document.getElementById('btn-next');
      const submitBtn = document.getElementById('btn-submit');
      const saveDot = document.getElementById('save-dot');
      const saveText = document.getElementById('save-text');
      const currentQIndexLabel = document.getElementById('current-q-index-label');
      const answeredCountLabel = document.getElementById('answered-count-label');

      function updateUI() {
        slides.forEach((s, idx) => {
          if (idx === currentIndex) {
            s.classList.remove('hidden');
          } else {
            s.classList.add('hidden');
          }
        });

        stepBtns.forEach((btn, idx) => {
          btn.classList.toggle('current', idx === currentIndex);
        });

        if (currentQIndexLabel) currentQIndexLabel.textContent = (currentIndex + 1);
        if (prevBtn) prevBtn.disabled = (currentIndex === 0);
        if (nextBtn) {
          if (currentIndex === totalQuestions - 1) {
            nextBtn.textContent = 'ส่งคำตอบ ✓';
            nextBtn.classList.replace('bg-navy-950', 'bg-pink-500');
          } else {
            nextBtn.textContent = 'ถัดไป ›';
            nextBtn.classList.replace('bg-pink-500', 'bg-navy-950');
          }
        }

        updateAnsweredCount();
      }

      function updateAnsweredCount() {
        const answeredCount = Object.keys(answers).filter(k => answers[k] !== '').length;
        if (answeredCountLabel) {
          answeredCountLabel.textContent = `ตอบแล้ว ${answeredCount} / ${totalQuestions}`;
        }
      }

      function showQuestion(index) {
        if (index < 0 || index >= totalQuestions) return;
        currentIndex = index;
        updateUI();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }

      // Step navigation
      stepBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          showQuestion(parseInt(btn.dataset.stepIndex, 10));
        });
      });

      if (prevBtn) {
        prevBtn.addEventListener('click', () => {
          showQuestion(currentIndex - 1);
        });
      }

      if (nextBtn) {
        nextBtn.addEventListener('click', () => {
          if (currentIndex === totalQuestions - 1) {
            openSubmitModal();
          } else {
            showQuestion(currentIndex + 1);
          }
        });
      }

      // Autosave indicator status
      function setSaveStatus(status, text) {
        if (!saveDot || !saveText) return;
        if (status === 'saving') {
          saveDot.className = 'w-2 h-2 rounded-full bg-amber-400 animate-pulse';
          saveText.textContent = text || 'กำลังบันทึก...';
        } else if (status === 'saved') {
          saveDot.className = 'w-2 h-2 rounded-full bg-emerald-500';
          saveText.textContent = text || 'บันทึกแล้ว';
        } else if (status === 'offline') {
          saveDot.className = 'w-2 h-2 rounded-full bg-rose-500';
          saveText.textContent = text || 'บันทึกในเครื่อง (ออฟไลน์)';
        }
      }

      // Answer change handler
      function onAnswerChange(questionId, value) {
        answers[questionId] = value;
        pendingSync[questionId] = value;

        // Backup to LocalStorage
        try {
          localStorage.setItem(storageKey, JSON.stringify(answers));
        } catch (e) {}

        // Mark step button answered
        const slideEl = document.querySelector(`.question-slide[data-question-id="${questionId}"]`);
        if (slideEl) {
          const sIdx = parseInt(slideEl.dataset.index, 10);
          const btn = document.getElementById(`step-btn-${sIdx}`);
          if (btn) btn.classList.toggle('answered', value !== '');
        }

        updateAnsweredCount();
        setSaveStatus('saving');

        // Debounce autosave to server
        clearTimeout(autosaveTimeout);
        autosaveTimeout = setTimeout(() => {
          saveToServer(questionId, value);
        }, 350);
      }

      // Server autosave call
      async function saveToServer(questionId, value) {
        try {
          const res = await fetch('activity-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'autosave',
              submission_id: submissionId,
              question_id: questionId,
              student_answer: value,
              client_timestamp: new Date().toISOString()
            })
          });
          const data = await res.json();
          if (data.success) {
            delete pendingSync[questionId];
            setSaveStatus('saved');
          } else {
            setSaveStatus('offline', 'รอซิงก์ข้อมูล');
          }
        } catch (e) {
          setSaveStatus('offline', 'ออฟไลน์ (สำรองในเครื่อง)');
        }
      }

      // Radio input listeners
      document.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
          const nameParts = e.target.name.split('_');
          const qId = parseInt(nameParts[1], 10);
          const choiceCard = e.target.closest('.choice-card');
          const parentSlide = e.target.closest('.question-slide');

          if (parentSlide) {
            parentSlide.querySelectorAll('.choice-card').forEach(c => c.classList.remove('selected'));
          }
          if (choiceCard) choiceCard.classList.add('selected');

          onAnswerChange(qId, e.target.value);
        });
      });

      // Short answer listeners
      document.querySelectorAll('.short-answer-input').forEach(input => {
        input.addEventListener('input', (e) => {
          const nameParts = e.target.name.split('_');
          const qId = parseInt(nameParts[1], 10);
          onAnswerChange(qId, e.target.value.trim());
        });
      });

      // Hint toggle listeners (Section 18 Practice Mode)
      document.querySelectorAll('.btn-toggle-hint').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const box = e.currentTarget.nextElementSibling;
          if (box) box.classList.toggle('hidden');
        });
      });

      // Online event listener to sync pending answers (Section 73)
      window.addEventListener('online', async () => {
        const pendingKeys = Object.keys(pendingSync);
        if (pendingKeys.length > 0) {
          setSaveStatus('saving', 'กำลังซิงก์ข้อมูลที่ค้างอยู่...');
          const batch = pendingKeys.map(k => ({
            question_id: parseInt(k, 10),
            student_answer: pendingSync[k],
            client_timestamp: new Date().toISOString()
          }));
          try {
            const res = await fetch('activity-api.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                action: 'sync',
                submission_id: submissionId,
                answers: batch
              })
            });
            const d = await res.json();
            if (d.success) {
              pendingSync = {};
              setSaveStatus('saved');
            }
          } catch (err) {}
        }
      });

      // Submission Modal handling (Section 16 Submission Safety)
      const submitModal = document.getElementById('submit-modal');
      const btnCancelSubmit = document.getElementById('btn-cancel-submit');
      const btnConfirmSubmit = document.getElementById('btn-confirm-submit');
      const submitModalDesc = document.getElementById('submit-modal-desc');

      function openSubmitModal() {
        const answeredCount = Object.keys(answers).filter(k => answers[k] !== '').length;
        const unansweredCount = totalQuestions - answeredCount;
        if (unansweredCount > 0) {
          submitModalDesc.innerHTML = `<span class="text-amber-600 font-bold">⚠️ ยังไม่ได้ตอบ ${unansweredCount} ข้อ</span><br>ต้องการส่งคำตอบตอนนี้เลยหรือไม่?`;
        } else {
          submitModalDesc.innerHTML = `ตอบครบทั้ง ${totalQuestions} ข้อเรียบร้อยแล้ว<br>ระบบจะตรวจคะแนนทันที`;
        }
        if (submitModal) submitModal.classList.remove('hidden');
      }

      if (submitBtn) {
        submitBtn.addEventListener('click', openSubmitModal);
      }
      if (btnCancelSubmit) {
        btnCancelSubmit.addEventListener('click', () => {
          if (submitModal) submitModal.classList.add('hidden');
        });
      }

      if (btnConfirmSubmit) {
        btnConfirmSubmit.addEventListener('click', async () => {
          btnConfirmSubmit.disabled = true;
          btnConfirmSubmit.textContent = 'กำลังส่ง...';

          // Package all answers
          const finalBatch = Object.keys(answers).map(k => ({
            question_id: parseInt(k, 10),
            student_answer: answers[k],
            client_timestamp: new Date().toISOString()
          }));

          try {
            const res = await fetch('activity-api.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                action: 'submit',
                submission_id: submissionId,
                answers: finalBatch
              })
            });
            const result = await res.json();
            if (result.success) {
              // Clear local backup ONLY on confirmed submission (Section 16)
              try {
                localStorage.removeItem(storageKey);
              } catch (e) {}

              // Reload page to show result view
              window.location.href = `activity.php?assignment_id=${assignmentId}&submission_id=${submissionId}`;
            } else {
              alert(result.error || 'เกิดข้อผิดพลาดในการส่งคำตอบ');
              btnConfirmSubmit.disabled = false;
              btnConfirmSubmit.textContent = 'ยืนยันส่ง';
            }
          } catch (e) {
            alert('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ คำตอบถูกบันทึกไว้ในเครื่องแล้ว กรุณาลองใหม่อีกครั้ง');
            btnConfirmSubmit.disabled = false;
            btnConfirmSubmit.textContent = 'ยืนยันส่ง';
          }
        });
      }

      // Initial UI update
      updateUI();
    })();
  </script>
</body>
</html>
