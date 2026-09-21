<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/enrollments-service.php';

$studentId = (int)($_GET['id'] ?? 0);
if (!$studentId) {
    header('Location: students.php');
    exit;
}

$service = new EnrollmentService($pdo);

// Fetch Student Profile
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(DISTINCT CASE WHEN e.status IN ('active', 'trial') THEN e.id END) AS active_courses_count
    FROM users u
    LEFT JOIN enrollments e ON e.user_id = u.id AND e.status IN ('active', 'trial')
    WHERE u.id = ? AND u.role = 'student'
    GROUP BY u.id
");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo '<meta charset="utf-8"><p>ไม่พบข้อมูลนักเรียน</p><a href="students.php">กลับไปรายชื่อนักเรียน</a>';
    exit;
}

$pageTitle = "จัดการสิทธิ์คอร์ส — " . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
$pageDesc = "กำหนดสิทธิ์คอร์สเรียน จัดกลุ่มเรียน และตารางเรียนของนักเรียน";
$currentPage = 'students.php';

// Fetch Enrollments, Schedule & Audit Logs
$enrollments = $service->getStudentCourseAccess($studentId);
$schedule = $service->getStudentCombinedSchedule($studentId);
$auditLogs = $service->getStudentAuditLogs($studentId);

// Learning Journey Service Integration (Phase 1)
require_once __DIR__ . '/../includes/learning-journey-service.php';
$journeyService = new LearningJourneyService($pdo);
$learningGoals = $journeyService->getStudentLearningGoals($studentId, null, false);
$topicMasteries = $journeyService->getStudentTopicMastery($studentId);
$nextAction = $journeyService->getStudentNextAction($studentId);
$upcomingActions = $journeyService->getStudentUpcomingList($studentId);

// Phase 3: Recent Learning Evidence & Mastery Data
require_once __DIR__ . '/../includes/phase3-mastery-service.php';
$p3Admin = new Phase3MasteryService($pdo);
$stmtRecentEv = $pdo->prepare("
    SELECT le.*, c.title AS course_title
    FROM learning_evidence le
    LEFT JOIN courses c ON c.id = le.course_id
    WHERE le.student_id = ?
    ORDER BY le.occurred_at DESC
    LIMIT 10
");
$stmtRecentEv->execute([$studentId]);
$teacherRecentEvidence = $stmtRecentEv->fetchAll(PDO::FETCH_ASSOC);

// Phase 4: interpret existing mastery/evidence as actionable gaps.
require_once __DIR__ . '/../includes/phase4-adaptive-service.php';
$gapService = new \NextBeyond\Adaptive\GapAnalysisService($pdo);
try {
    $gapService->recalculateForStudent($studentId);
    $learningGaps = $gapService->getStudentGaps($studentId, null, true);
} catch (Throwable $e) {
    $learningGaps = []; // Phase 1–3 remain usable until the additive Phase 4 migration is installed.
}

// Phase 5: human-support queue and auditable learning timeline.
try {
    $stmtInterventions = $pdo->prepare("SELECT * FROM teacher_interventions WHERE student_id=? ORDER BY created_at DESC LIMIT 20");
    $stmtInterventions->execute([$studentId]);
    $studentInterventions = $stmtInterventions->fetchAll(PDO::FETCH_ASSOC);
    $stmtTimeline = $pdo->prepare("SELECT * FROM learning_audit_log WHERE student_id=? ORDER BY created_at DESC,id DESC LIMIT 30");
    $stmtTimeline->execute([$studentId]);
    $learningTimeline = $stmtTimeline->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $studentInterventions = [];
    $learningTimeline = [];
}

$courseProfiles = [];
foreach ($enrollments as $en) {
    $cId = (int)$en['course_id'];
    $prof = $journeyService->getStudentLearningProfile($studentId, $cId);
    if ($prof) {
        $courseProfiles[$cId] = $prof;
    }
}

// Fetch All Active Courses for the Assignment Modal
$allCourses = $pdo->query("SELECT id, title, subject, level, price FROM courses WHERE status = 'active' ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> - Next Beyond Admin</title>
  <link rel="stylesheet" href="../assets/css/output.css?v=<?= filemtime(__DIR__ . '/../assets/css/output.css') ?>">
  <script src="../assets/js/admin-guard.js"></script>
</head>
<body class="bg-[#f4f7fb] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 p-8 max-[640px]:p-4 max-w-[1400px] w-full mx-auto space-y-6">
      <!-- Breadcrumb Navigation -->
      <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-2 text-[13px] font-bold text-[#64748b]">
          <a href="students.php" class="hover:text-pink-600 transition flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>รายชื่อนักเรียน</span>
          </a>
          <span>/</span>
          <span class="text-navy-950 font-black"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ($student['nickname'] ? ' (' . $student['nickname'] . ')' : '')) ?></span>
        </div>

        <div class="flex items-center gap-2.5">
          <a href="course-matrix.php" class="h-10 px-4 rounded-xl border border-[#dce4ef] bg-white hover:bg-slate-50 text-[13px] font-bold text-navy-900 transition flex items-center gap-1.5 shadow-xs">
            <span>📊 Course Access Matrix</span>
          </a>
          <button type="button" id="btn-open-assign-modal" class="h-10 px-5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-[13px] transition shadow-[0_4px_12px_rgba(231,45,130,0.25)] flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>+ เพิ่มสิทธิ์คอร์ส</span>
          </button>
        </div>
      </div>

      <!-- Student Profile Overview Card (Section 3) -->
      <div class="bg-white rounded-3xl border border-[#e8ecf2] p-6 shadow-sm flex items-center justify-between gap-6 flex-wrap">
        <div class="flex items-center gap-4 min-w-[260px]">
          <?php
          $rawAvatar = (string)($student['avatar_url'] ?? '');
          $detailAvatar = '';
          if (!empty($rawAvatar)) {
              if (str_starts_with($rawAvatar, 'data:') || str_starts_with($rawAvatar, 'http://') || str_starts_with($rawAvatar, 'https://')) {
                  $detailAvatar = $rawAvatar;
              } else {
                  $clean = ltrim($rawAvatar, '/');
                  if (str_starts_with($clean, '../')) {
                      $clean = substr($clean, 3);
                  }
                  $detailAvatar = '../' . $clean;
              }
          }
          $initialChar = mb_substr(!empty($student['nickname']) ? $student['nickname'] : $student['first_name'], 0, 1);
          ?>
          <?php if (!empty($detailAvatar)): ?>
            <div class="w-16 h-16 rounded-2xl overflow-hidden shadow-md shrink-0 border border-slate-200 bg-slate-100 relative flex items-center justify-center">
              <img src="<?= htmlspecialchars($detailAvatar) ?>" alt="<?= htmlspecialchars($student['first_name']) ?>" class="w-full h-full object-cover" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
              <div class="w-full h-full bg-gradient-to-tr from-pink-500 to-indigo-600 text-white font-black text-2xl items-center justify-center hidden">
                <?= htmlspecialchars($initialChar) ?>
              </div>
            </div>
          <?php else: ?>
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-pink-500 to-indigo-600 text-white font-black text-2xl flex items-center justify-center shadow-md shrink-0">
              <?= htmlspecialchars($initialChar) ?>
            </div>
          <?php endif; ?>
          <div>
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <h1 class="text-[22px] font-black text-navy-950">
                <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                <?php if (!empty($student['nickname'])): ?>
                  <span class="text-pink-600 font-bold text-[18px]">(<?= htmlspecialchars($student['nickname']) ?>)</span>
                <?php endif; ?>
              </h1>
              <span class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-800 text-[11px] font-black border border-slate-200">
                <?= htmlspecialchars($student['grade'] ?: 'ม.5') ?>
              </span>
              <span class="px-2.5 py-0.5 rounded-full <?= $student['is_active'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500' ?> text-[11px] font-bold">
                <?= $student['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </div>
            <div class="flex items-center gap-4 text-[13px] text-[#64748b] flex-wrap">
              <span>📧 <?= htmlspecialchars($student['email']) ?></span>
              <?php if (!empty($student['phone'])): ?>
                <span>📞 <?= htmlspecialchars($student['phone']) ?></span>
              <?php endif; ?>
              <span>🆔 รหัสนักเรียน: #<?= $student['id'] ?></span>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-4">
          <div class="p-3.5 rounded-2xl bg-pink-50/70 border border-pink-100 text-center min-w-[120px]">
            <div class="text-[11px] font-bold text-pink-700 uppercase">คอร์สที่กำลังเรียน</div>
            <div class="text-[24px] font-black text-pink-600 leading-tight mt-0.5">
              <?= count($enrollments) ?>
            </div>
          </div>
          <div class="p-3.5 rounded-2xl bg-blue-50/70 border border-blue-100 text-center min-w-[120px]">
            <div class="text-[11px] font-bold text-blue-700 uppercase">กลุ่มเรียน (Classes)</div>
            <div class="text-[24px] font-black text-blue-600 leading-tight mt-0.5">
              <?= count(array_filter(array_column($enrollments, 'class_group_name'))) ?>
            </div>
          </div>
        </div>
      </div>

      <?php if ($nextAction): ?>
      <!-- Next Action Banner (Section 13) -->
      <div class="bg-gradient-to-r from-navy-950 via-[#162746] to-navy-950 rounded-3xl p-6 text-white shadow-md flex items-center justify-between gap-6 flex-wrap border border-white/10">
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-full bg-pink-500/20 text-pink-400 text-[11px] font-black border border-pink-500/30 uppercase tracking-wider">🎯 Next Action (สิ่งที่ควรทำต่อ)</span>
            <span class="text-[12px] text-[#8e9baf] font-bold"><?= htmlspecialchars($nextAction['course_title']) ?></span>
          </div>
          <h2 class="text-[20px] font-black tracking-tight text-white"><?= htmlspecialchars($nextAction['title']) ?></h2>
          <p class="text-[13px] text-[#aebbd0]">หัวข้อ: <strong class="text-white"><?= htmlspecialchars($nextAction['topic']) ?></strong> · ประเภท: <?= htmlspecialchars($nextAction['completion_type']) ?></p>
        </div>
        <div class="flex items-center gap-3">
          <a href="../<?= htmlspecialchars($nextAction['action_url']) ?>" target="_blank" class="h-10 px-5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-[13px] transition flex items-center gap-1.5 shadow-[0_4px_14px_rgba(231,45,130,0.4)]">
            <span>ดูเนื้อหาภารกิจ &rarr;</span>
          </a>
        </div>
      </div>
      <?php endif; ?>

      <!-- Main Two-Column Layout -->
      <div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-6 items-start">
        <!-- Left Column: Goals, Profile, Mastery & Course Access -->
        <div class="space-y-6">

          <!-- Section: Learning Goals (เป้าหมายการเรียน) -->
          <div class="bg-white rounded-3xl border border-[#e8ecf2] p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3 mb-6 pb-4 border-b border-[#f1f5f9] flex-wrap">
              <div>
                <h2 class="text-[18px] font-black text-navy-950 flex items-center gap-2">
                  <span>🎯 เป้าหมายการเรียน (Learning Goals)</span>
                  <span class="px-2.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-[11px] font-bold">
                    <?= count($learningGoals) ?> เป้าหมาย
                  </span>
                </h2>
                <p class="text-[12px] text-[#64748b] mt-0.5">กำหนดเป้าหมายคะแนน การสอบแข่งขัน และระยะเวลาตามรายวิชา</p>
              </div>
              <button type="button" onclick="openGoalModal()" class="h-9 px-4 rounded-xl border border-indigo-200 text-indigo-600 hover:bg-indigo-50 text-[12px] font-bold transition flex items-center gap-1.5">
                <span>+ ตั้งเป้าหมาย</span>
              </button>
            </div>

            <?php if (empty($learningGoals)): ?>
              <div class="py-8 text-center text-[#64748b]">
                <p class="font-bold text-[14px] text-navy-950 mb-1">ยังไม่ได้ตั้งเป้าหมายการเรียน</p>
                <p class="text-[12px] mb-3">ตั้งเป้าหมายคะแนน A-Level หรือเกรดเฉลี่ยเพื่อวางแผนการเรียนรู้</p>
                <button type="button" onclick="openGoalModal()" class="h-8 px-4 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-[12px] font-bold transition">
                  + ตั้งเป้าหมายแรก
                </button>
              </div>
            <?php else: ?>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($learningGoals as $g): ?>
                  <?php
                    $isArchived = $g['status'] === 'archived';
                    $priorityColor = match($g['priority']) {
                        'high' => 'bg-rose-50 text-rose-700 border-rose-200',
                        'medium' => 'bg-amber-50 text-amber-700 border-amber-200',
                        default => 'bg-slate-50 text-slate-700 border-slate-200',
                    };
                  ?>
                  <div class="p-4 rounded-2xl border border-[#e8ecf2] <?= $isArchived ? 'bg-slate-50 opacity-60' : 'bg-white' ?> shadow-2xs space-y-3 relative group">
                    <div class="flex items-start justify-between gap-2">
                      <div>
                        <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold"><?= htmlspecialchars($g['course_title']) ?></span>
                        <h4 class="text-[15px] font-black text-navy-950 mt-1"><?= htmlspecialchars($g['goal_name']) ?></h4>
                      </div>
                      <div class="flex items-center gap-1.5">
                        <span class="px-2 py-0.5 rounded-full border text-[10px] font-black <?= $priorityColor ?> uppercase"><?= htmlspecialchars($g['priority']) ?></span>
                        <?php if ($isArchived): ?>
                          <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 text-[10px] font-bold">Archived</span>
                        <?php endif; ?>
                      </div>
                    </div>

                    <div class="flex items-center justify-between text-[12px] pt-1">
                      <div>
                        <span class="text-[#64748b]">เป้าหมายคะแนน:</span>
                        <strong class="text-pink-600 font-black text-[15px] ml-1"><?= $g['target_score'] !== null ? htmlspecialchars((string)$g['target_score']) : '-' ?></strong>
                        <span class="text-[10px] text-[#94a3b8]">/ 100</span>
                      </div>
                      <div class="text-[#64748b] text-[11px]">
                        <?= !empty($g['target_date']) ? 'ภายใน ' . date('d/m/Y', strtotime($g['target_date'])) : 'ไม่กำหนดเวลา' ?>
                      </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#f1f5f9] text-[11px]">
                      <button type="button" onclick='editGoal(<?= json_encode($g, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="px-2.5 py-1 rounded-lg text-slate-600 hover:bg-slate-100 font-bold">แก้ไข</button>
                      <?php if (!$isArchived): ?>
                        <button type="button" onclick="archiveGoal(<?= (int)$g['id'] ?>)" class="px-2.5 py-1 rounded-lg text-rose-600 hover:bg-rose-50 font-bold">จัดเก็บ</button>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Section: Learning Profile & Diagnostic Baseline -->
          <div class="bg-white rounded-3xl border border-[#e8ecf2] p-6 shadow-sm">
            <div class="mb-6 pb-4 border-b border-[#f1f5f9]">
              <h2 class="text-[18px] font-black text-navy-950 flex items-center gap-2">
                <span>📊 ภาพรวมทักษะวิชาการ (Learning Profile & Diagnostic)</span>
              </h2>
              <p class="text-[12px] text-[#64748b] mt-0.5">สถานะความเชี่ยวชาญ Baseline จากแบบทดสอบวัดระดับ จุดเด่น และจุดที่ต้องเร่งเสริม</p>
            </div>

            <?php if (empty($courseProfiles)): ?>
              <div class="py-8 text-center text-[#64748b]">
                <p class="font-bold text-[14px] text-navy-950 mb-1">ยังไม่มีข้อมูล Learning Profile</p>
                <p class="text-[12px]">ระบบจะประมวลผลอัตโนมัติเมื่อนักเรียนทำแบบวัดระดับ Diagnostic หรือแบบทดสอบในคอร์ส</p>
              </div>
            <?php else: ?>
              <div class="space-y-6">
                <?php foreach ($courseProfiles as $cId => $prof): ?>
                  <div class="p-5 rounded-2xl bg-[#f8fafc] border border-[#edf2f7] space-y-4">
                    <div class="flex items-center justify-between gap-4 flex-wrap pb-3 border-b border-white">
                      <div>
                        <span class="text-[11px] font-black uppercase text-pink-600 tracking-wider"><?= htmlspecialchars($prof['course_subject']) ?></span>
                        <h3 class="text-[16px] font-black text-navy-950"><?= htmlspecialchars($prof['course_title']) ?></h3>
                      </div>
                      <div class="flex items-center gap-4">
                        <div class="text-right">
                          <span class="text-[10px] uppercase font-bold text-[#64748b]">Diagnostic Baseline</span>
                          <div class="text-[18px] font-black text-navy-900"><?= htmlspecialchars((string)$prof['baseline_score']) ?>%</div>
                        </div>
                        <div class="text-right">
                          <span class="text-[10px] uppercase font-bold text-pink-600">Current Mastery</span>
                          <div class="text-[18px] font-black text-pink-600"><?= htmlspecialchars((string)$prof['current_mastery']) ?>%</div>
                        </div>
                      </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <!-- Strengths -->
                      <div class="bg-white p-4 rounded-xl border border-emerald-100 shadow-2xs space-y-2">
                        <h4 class="text-[12px] font-black text-emerald-800 flex items-center gap-1.5 uppercase">
                          <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                          <span>จุดเด่น (Strengths)</span>
                        </h4>
                        <div class="flex flex-wrap gap-1.5">
                          <?php if (empty($prof['strengths'])): ?>
                            <span class="text-[11px] text-[#94a3b8]">อยู่ระหว่างการประเมิน</span>
                          <?php else: ?>
                            <?php foreach ($prof['strengths'] as $st): ?>
                              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-bold">
                                <span><?= htmlspecialchars($st['topic']) ?></span>
                                <span class="text-[10px] text-emerald-600 font-black"><?= $st['score'] ?>%</span>
                              </span>
                            <?php endforeach; ?>
                          <?php endif; ?>
                        </div>
                      </div>

                      <!-- Needs Improvement -->
                      <div class="bg-white p-4 rounded-xl border border-amber-100 shadow-2xs space-y-2">
                        <h4 class="text-[12px] font-black text-amber-800 flex items-center gap-1.5 uppercase">
                          <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                          <span>จุดที่ต้องเสริม (Needs Improvement)</span>
                        </h4>
                        <div class="flex flex-wrap gap-1.5">
                          <?php if (empty($prof['needs_improvement'])): ?>
                            <span class="text-[11px] text-[#94a3b8]">ไม่มีหัวข้อที่ต้องเสริม</span>
                          <?php else: ?>
                            <?php foreach ($prof['needs_improvement'] as $ni): ?>
                              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-900 border border-amber-200 text-[11px] font-bold">
                                <span><?= htmlspecialchars($ni['topic']) ?></span>
                                <span class="text-[10px] text-amber-700 font-black"><?= $ni['score'] ?>%</span>
                              </span>
                            <?php endforeach; ?>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Section: Topic Mastery & Skill Map (Phase 3 Extended — Section 36) -->
          <?php if (!empty($topicMasteries)): ?>
          <div class="bg-white rounded-3xl border border-[#e8ecf2] p-6 shadow-sm">
            <div class="mb-4 pb-3 border-b border-[#f1f5f9] flex items-center justify-between flex-wrap gap-2">
              <div>
                <h2 class="text-[18px] font-black text-navy-950 flex items-center gap-2">
                  <span>🗺️ แผนที่ทักษะและการประเมิน (Teacher Skill Map)</span>
                  <span class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[11px] font-bold">
                    <?= count($topicMasteries) ?> หัวข้อ
                  </span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">วิเคราะห์จากหลักฐานการเรียนรู้หลายกิจกรรม (Diagnostic, In-Class, Worksheets, Post-Test)</p>
              </div>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left text-[12px]">
                <thead>
                  <tr class="border-b border-[#f1f5f9] text-[#64748b]">
                    <th class="py-2.5 px-3 font-black">วิชา</th>
                    <th class="py-2.5 px-3 font-black">หัวข้อการเรียนรู้</th>
                    <th class="py-2.5 px-3 font-black w-48">ระดับ Mastery</th>
                    <th class="py-2.5 px-3 font-black text-center">หลักฐาน (Evidence)</th>
                    <th class="py-2.5 px-3 font-black">กิจกรรมล่าสุด</th>
                    <th class="py-2.5 px-3 font-black text-right">ประเมินล่าสุด</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#f8fafc]">
                  <?php foreach ($topicMasteries as $tm): ?>
                    <?php
                      $mScore = (float)$tm['mastery_score'];
                      $evCount = (int)($tm['evidence_count'] ?? 1);
                      $conf = match(true) {
                          $evCount >= 6 => ['label' => 'สูง', 'class' => 'bg-emerald-100 text-emerald-800'],
                          $evCount >= 3 => ['label' => 'ปานกลาง', 'class' => 'bg-blue-100 text-blue-800'],
                          default       => ['label' => 'เริ่มต้น', 'class' => 'bg-slate-100 text-slate-600'],
                      };
                    ?>
                    <tr class="hover:bg-slate-50/80 transition">
                      <td class="py-2.5 px-3 font-bold text-navy-950"><?= htmlspecialchars($tm['subject']) ?></td>
                      <td class="py-2.5 px-3 font-bold text-navy-900"><?= htmlspecialchars($tm['topic_name']) ?></td>
                      <td class="py-2.5 px-3">
                        <div class="flex items-center gap-2">
                          <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full <?= $mScore >= 80 ? 'bg-emerald-500' : ($mScore >= 65 ? 'bg-amber-500' : 'bg-rose-500') ?>" style="width: <?= min(100, max(0, $mScore)) ?>%"></div>
                          </div>
                          <span class="font-black text-[11px] <?= $mScore >= 80 ? 'text-emerald-700' : ($mScore >= 65 ? 'text-amber-700' : 'text-rose-700') ?> w-9 text-right"><?= $mScore ?>%</span>
                        </div>
                      </td>
                      <td class="py-2.5 px-3 text-center">
                        <span class="inline-flex items-center gap-1">
                          <b class="text-navy-950"><?= $evCount ?></b>
                          <span class="px-1.5 py-0.2 rounded text-[10px] font-semibold <?= $conf['class'] ?>"><?= $conf['label'] ?></span>
                        </span>
                      </td>
                      <td class="py-2.5 px-3">
                        <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold uppercase"><?= htmlspecialchars($tm['source']) ?></span>
                      </td>
                      <td class="py-2.5 px-3 text-right text-[#94a3b8]"><?= date('d/m/y H:i', strtotime($tm['last_assessed_at'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php endif; ?>

          <!-- Phase 4: Learning Gaps and teacher-approved remediation -->
          <div class="bg-white rounded-3xl border border-[#e8ecf2] p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3 mb-4 pb-3 border-b border-[#f1f5f9]">
              <div>
                <h2 class="text-[18px] font-black text-navy-950">🎯 Learning Gaps</h2>
                <p class="text-xs text-slate-400 mt-0.5">ตีความจาก Mastery, จำนวนหลักฐาน, ความต่อเนื่อง และผลล่าสุด — ไม่ได้ตัดสินจากข้อเดียว</p>
              </div>
              <span class="px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 text-[11px] font-black"><?= count(array_filter($learningGaps, fn($g) => $g['status'] !== 'resolved')) ?> กำลังติดตาม</span>
            </div>
            <?php if (empty($learningGaps)): ?>
              <div class="py-6 text-center text-sm text-slate-500">ยังมีหลักฐานไม่เพียงพอสำหรับระบุ Learning Gap</div>
            <?php else: ?>
              <div class="space-y-3">
                <?php foreach ($learningGaps as $gap): ?>
                  <?php
                    $severityClass = match($gap['severity']) {
                        'critical' => 'bg-rose-100 text-rose-800 border-rose-200',
                        'high' => 'bg-orange-100 text-orange-800 border-orange-200',
                        'moderate' => 'bg-amber-100 text-amber-800 border-amber-200',
                        default => 'bg-blue-50 text-blue-700 border-blue-200',
                    };
                    $trendLabel = match($gap['trend']) {'improving'=>'กำลังดีขึ้น','declining'=>'ลดลง',default=>'คงที่'};
                  ?>
                  <div class="p-4 rounded-2xl border border-slate-200 <?= $gap['status'] === 'resolved' ? 'opacity-60 bg-slate-50' : 'bg-white' ?> flex items-center justify-between gap-4 flex-wrap">
                    <div class="min-w-[260px]">
                      <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-[15px] font-black text-navy-950"><?= htmlspecialchars($gap['topic_name']) ?></h3>
                        <?php if (!empty($gap['skill_name'])): ?><span class="text-[11px] text-slate-500">→ <?= htmlspecialchars($gap['skill_name']) ?></span><?php endif; ?>
                        <span class="px-2 py-0.5 rounded-full border text-[10px] font-black uppercase <?= $severityClass ?>"><?= htmlspecialchars($gap['severity']) ?></span>
                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[10px] font-bold"><?= htmlspecialchars($gap['status']) ?></span>
                      </div>
                      <div class="flex gap-4 mt-2 text-[11px] text-slate-500 flex-wrap">
                        <span>Mastery <b class="text-navy-950"><?= round((float)$gap['mastery_score'], 1) ?>%</b></span>
                        <span>หลักฐาน <b class="text-navy-950"><?= (int)$gap['evidence_count'] ?></b></span>
                        <span>Confidence <b class="text-navy-950"><?= htmlspecialchars(ucfirst($gap['confidence'])) ?></b></span>
                        <span>แนวโน้ม <b class="text-navy-950"><?= $trendLabel ?></b></span>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <button type="button" onclick="showGapEvidence(<?= (int)$gap['id'] ?>)" class="h-9 px-3 rounded-xl border border-slate-200 text-[12px] font-bold hover:bg-slate-50">ดูรายละเอียด</button>
                      <?php if ($gap['status'] !== 'resolved'): ?>
                        <button type="button" onclick='openRemediationBuilder(<?= json_encode([
                            'id'=>(int)$gap['id'],'course_id'=>(int)$gap['course_id'],'topic'=>$gap['topic_name'],
                            'skill'=>$gap['skill_name'],'mastery'=>(float)$gap['mastery_score']
                        ], JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' class="h-9 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-[12px] font-bold">สร้างแบบฝึกเฉพาะจุด</button>
                        <button type="button" onclick='openMasteryCheckBuilder(<?= json_encode([
                            'id'=>(int)$gap['id'],'course_id'=>(int)$gap['course_id'],'topic'=>$gap['topic_name'],
                            'skill'=>$gap['skill_name'],'mastery'=>(float)$gap['mastery_score']
                        ], JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' class="h-9 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-[12px] font-bold">สร้าง Mastery Check</button>
                        <button type="button" onclick="createIntervention(<?= (int)$gap['id'] ?>)" class="h-9 px-3 rounded-xl border border-amber-300 text-amber-800 text-[12px] font-bold">ส่งให้ครูดูแล</button>
                        <button type="button" onclick="changeGapState('resolve_gap',<?= (int)$gap['id'] ?>)" class="h-9 px-3 rounded-xl border border-slate-300 text-slate-700 text-[12px] font-bold">ครูยืนยันผ่าน</button>
                      <?php else: ?>
                        <button type="button" onclick="changeGapState('reopen_gap',<?= (int)$gap['id'] ?>)" class="h-9 px-3 rounded-xl border border-slate-300 text-slate-700 text-[12px] font-bold">เปิดติดตามอีกครั้ง</button>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($studentInterventions || $learningTimeline): ?>
          <div class="bg-white rounded-3xl border border-[#e8ecf2] p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3 mb-4 pb-3 border-b"><div><h2 class="text-[18px] font-black">🤝 การช่วยเหลือและ Timeline</h2><p class="text-xs text-slate-400">แสดงเหตุผล การตัดสินใจ และการติดตาม โดยไม่ตีตราผู้เรียน</p></div></div>
            <?php if ($studentInterventions): ?><div class="grid md:grid-cols-2 gap-3 mb-4"><?php foreach (array_slice($studentInterventions,0,6) as $item): ?><div class="p-3 rounded-xl border text-xs"><div class="flex justify-between gap-2"><b><?= htmlspecialchars($item['topic_name']) ?></b><span class="font-bold text-indigo-600"><?= htmlspecialchars($item['status']) ?></span></div><p class="mt-1 text-slate-500"><?= htmlspecialchars(str_replace('_',' ',(string)$item['recommended_action'])) ?><?= $item['outcome'] ? ' · '.htmlspecialchars((string)$item['outcome']) : '' ?></p></div><?php endforeach; ?></div><?php endif; ?>
            <div class="space-y-2"><?php foreach (array_slice($learningTimeline,0,10) as $event): ?><div class="flex gap-3 text-xs"><time class="w-28 shrink-0 text-slate-400"><?= date('d/m/y H:i',strtotime($event['created_at'])) ?></time><div><b><?= htmlspecialchars(str_replace('_',' ',(string)$event['event_type'])) ?></b><p class="text-slate-500"><?= htmlspecialchars((string)$event['summary']) ?></p></div></div><?php endforeach; ?></div>
          </div>
          <?php endif; ?>

          <!-- Section: Recent Learning Evidence Log (Phase 3 Extended — Section 45) -->
          <?php if (!empty($teacherRecentEvidence)): ?>
          <div class="bg-white rounded-3xl border border-[#e8ecf2] p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-[#f1f5f9] pb-3">
              <div>
                <h2 class="text-[18px] font-black text-navy-950 flex items-center gap-2">
                  <span>📜 หลักฐานการเรียนรู้ล่าสุด (Recent Learning Evidence)</span>
                  <span class="px-2.5 py-0.5 rounded-full bg-pink-100 text-pink-700 text-[11px] font-bold">
                    <?= count($teacherRecentEvidence) ?> รายการ
                  </span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">ประวัติผลลัพธ์จากการตอบคำถามในคาบ ใบงาน และแบบทดสอบจริง</p>
              </div>
            </div>

            <div class="space-y-2">
              <?php foreach ($teacherRecentEvidence as $ev): ?>
                <?php
                  $pct = round((float)$ev['score'], 1);
                  $isPassed = $pct >= 60.0;
                  $typeInfo = match($ev['source_type']) {
                      'diagnostic'     => ['label' => 'แบบวัดพื้นฐาน', 'badge' => 'bg-slate-100 text-slate-700'],
                      'get_ready'      => ['label' => 'เตรียมตัวก่อนเรียน', 'badge' => 'bg-blue-50 text-blue-700'],
                      'in_class_check' => ['label' => 'เช็คความเข้าใจสด', 'badge' => 'bg-indigo-50 text-indigo-700'],
                      'worksheet'      => ['label' => 'ใบงาน (Worksheet)', 'badge' => 'bg-sky-50 text-sky-700'],
                      'practice'       => ['label' => 'ฝึกฝน (Practice)', 'badge' => 'bg-emerald-50 text-emerald-700'],
                      'homework'       => ['label' => 'การบ้าน (Homework)', 'badge' => 'bg-amber-50 text-amber-800'],
                      'posttest'       => ['label' => 'วัดผลหลังเรียน (Post-Test)', 'badge' => 'bg-purple-50 text-purple-700'],
                      default          => ['label' => $ev['source_type'], 'badge' => 'bg-slate-100 text-slate-700'],
                  };
                ?>
                <div class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 bg-slate-50/50 text-xs hover:border-slate-200 transition">
                  <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-xl flex items-center justify-center font-bold <?= $isPassed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                      <?= $isPassed ? '✓' : '•' ?>
                    </span>
                    <div>
                      <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $typeInfo['badge'] ?>"><?= $typeInfo['label'] ?></span>
                        <strong class="text-navy-950 font-bold"><?= htmlspecialchars($ev['topic_name']) ?></strong>
                      </div>
                      <span class="text-[11px] text-slate-400 block mt-0.5">
                        <?= htmlspecialchars($ev['course_title'] ?? '') ?> · น้ำหนัก <?= round((float)$ev['weight'] * 100) ?>%
                      </span>
                    </div>
                  </div>

                  <div class="text-right">
                    <span class="text-xs font-black <?= $pct >= 80 ? 'text-emerald-600' : ($pct >= 60 ? 'text-navy-950' : 'text-rose-600') ?>">
                      <?= $pct ?>%
                    </span>
                    <span class="text-[10px] text-slate-400 block">
                      <?= date('d/m/Y H:i', strtotime($ev['occurred_at'])) ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Section: Course Access -->
          <div class="bg-white rounded-3xl border border-[#e8ecf2] p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3 mb-6 pb-4 border-b border-[#f1f5f9] flex-wrap">
              <div>
                <h2 class="text-[18px] font-black text-navy-950 flex items-center gap-2">
                  <span>สิทธิ์คอร์ส (Course Access)</span>
                  <span class="px-2.5 py-0.5 rounded-full bg-pink-100 text-pink-700 text-[11px] font-bold">
                    <?= count($enrollments) ?> รายการ
                  </span>
                </h2>
                <p class="text-[12px] text-[#64748b] mt-0.5">คอร์สที่นักเรียนได้รับสิทธิ์เข้าเรียน เนื้อหา และตารางสอน</p>
              </div>

              <button type="button" onclick="document.getElementById('btn-open-assign-modal').click()" class="h-9 px-4 rounded-xl border border-pink-200 text-pink-600 hover:bg-pink-50 text-[12px] font-bold transition flex items-center gap-1.5">
                <span>+ เพิ่มคอร์ส</span>
              </button>
            </div>

            <!-- Enrollment Cards List -->
            <div class="space-y-4" id="enrollment-list-container">
              <?php if (empty($enrollments)): ?>
                <div class="py-12 text-center text-[#64748b]">
                  <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3 text-xl">📚</div>
                  <p class="font-bold text-[15px] text-navy-950 mb-1">ยังไม่มีสิทธิ์ในคอร์สเรียนใด</p>
                  <p class="text-[13px] max-w-[340px] mx-auto mb-4">กดปุ่ม "+ เพิ่มคอร์ส" ด้านบนเพื่อมอบสิทธิ์การเรียนให้นักเรียน</p>
                  <button type="button" onclick="document.getElementById('btn-open-assign-modal').click()" class="h-9 px-4 rounded-xl bg-pink-500 hover:bg-pink-600 text-white text-[12px] font-bold transition shadow-xs">
                    + มอบสิทธิ์คอร์สแรก
                  </button>
                </div>
              <?php else: ?>
                <?php foreach ($enrollments as $en): ?>
                  <?php
                    $isExpired = (!empty($en['expires_at']) && strtotime($en['expires_at']) < time()) || $en['status'] === 'expired';
                    $isTrial = $en['status'] === 'trial' || $en['access_type'] === 'trial';
                    $isPaused = $en['status'] === 'paused';
                    $isRevoked = $en['status'] === 'revoked';

                    // Status Badge
                    $statusBadge = '<span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-black border border-emerald-200">Active (กำลังเรียน)</span>';
                    if ($isTrial) {
                        $statusBadge = '<span class="px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 text-[11px] font-black border border-blue-200">⏱️ Trial (ทดลองเรียน)</span>';
                    } elseif ($isExpired) {
                        $statusBadge = '<span class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[11px] font-black border border-slate-200">⚠️ Expired (หมดอายุ)</span>';
                    } elseif ($isPaused) {
                        $statusBadge = '<span class="px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[11px] font-black border border-amber-200">⏸️ Paused (พักสิทธิ์)</span>';
                    } elseif ($isRevoked) {
                        $statusBadge = '<span class="px-2.5 py-0.5 rounded-full bg-rose-50 text-rose-700 text-[11px] font-black border border-rose-200">🚫 Revoked (ยกเลิกสิทธิ์)</span>';
                    }

                    // Mode Badge
                    $mode = strtolower($en['learning_mode'] ?? 'online');
                    $modeBadge = '<span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold">Online</span>';
                    if ($mode === 'onsite') $modeBadge = '<span class="px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 text-[10px] font-bold">On-site</span>';
                    elseif ($mode === 'hybrid') $modeBadge = '<span class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 text-[10px] font-bold">Hybrid</span>';

                    // Access Type Badge
                    $accType = strtolower($en['access_type'] ?? 'paid');
                    $accTypeBadge = '<span class="px-2 py-0.5 rounded-md bg-pink-50 text-pink-700 text-[10px] font-bold">Paid</span>';
                    if ($accType === 'scholarship') $accTypeBadge = '<span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 text-[10px] font-bold">Scholarship</span>';
                    elseif ($accType === 'complimentary') $accTypeBadge = '<span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 text-[10px] font-bold">Complimentary</span>';
                    elseif ($accType === 'bundle') $accTypeBadge = '<span class="px-2 py-0.5 rounded-md bg-purple-50 text-purple-800 text-[10px] font-bold">Bundle</span>';
                  ?>
                  <div class="en-card rounded-2xl border border-[#e8ecf2] p-5 hover:border-pink-300 transition-all bg-white shadow-2xs space-y-3" data-id="<?= $en['id'] ?>">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                      <div>
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                          <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold"><?= htmlspecialchars($en['subject'] ?: 'วิชา') ?></span>
                          <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold"><?= htmlspecialchars($en['level'] ?: 'ม.5') ?></span>
                          <?= $statusBadge ?>
                          <?= $modeBadge ?>
                          <?= $accTypeBadge ?>
                        </div>
                        <h3 class="text-[17px] font-black text-navy-950">
                          <?= htmlspecialchars($en['course_title']) ?>
                        </h3>
                      </div>

                      <div class="text-right text-[12px] text-[#64748b]">
                        <span class="text-[11px] uppercase font-bold text-[#94a3b8]">หมดอายุ</span>
                        <div class="font-bold text-navy-900">
                          <?= !empty($en['expires_at']) ? date('d/m/Y', strtotime($en['expires_at'])) : 'ไม่จำกัดเวลา' ?>
                        </div>
                      </div>
                    </div>

                    <!-- Class Group & Schedule Details (Section 12, 13) -->
                    <div class="p-3 rounded-xl bg-[#f8fafc] border border-[#edf2f7] flex items-center justify-between gap-4 flex-wrap text-[12px]">
                      <div class="flex items-center gap-3 flex-wrap">
                        <span class="font-bold text-[#475569]">กลุ่มเรียน:</span>
                        <?php if (!empty($en['class_group_name'])): ?>
                          <span class="px-2.5 py-0.5 rounded-lg bg-pink-100 text-pink-700 font-bold flex items-center gap-1">
                            <span>👥 <?= htmlspecialchars($en['class_group_name']) ?></span>
                          </span>
                          <?php if (!empty($en['schedule_text'])): ?>
                            <span class="text-[#64748b] flex items-center gap-1">
                              <span>🕒 <?= htmlspecialchars($en['schedule_text']) ?></span>
                            </span>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="text-[#94a3b8] italic">ยังไม่ได้จัดกลุ่มเรียน</span>
                        <?php endif; ?>
                      </div>

                      <button type="button" class="btn-change-class text-pink-600 hover:text-pink-700 font-bold text-[11px]" data-en-id="<?= $en['id'] ?>" data-course-id="<?= $en['course_id'] ?>" data-current-cg="<?= $en['class_group_id'] ?? '' ?>">
                        เปลี่ยนกลุ่มเรียน →
                      </button>
                    </div>

                    <!-- Action Bar (Sections 22, 23) -->
                    <div class="flex items-center justify-between gap-3 pt-2 border-t border-[#f1f5f9] flex-wrap text-[12px]">
                      <div class="flex items-center gap-2">
                        <span class="text-[11px] font-bold text-[#94a3b8]">ต่ออายุเร็ว:</span>
                        <button type="button" class="btn-quick-extend px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 font-bold text-[11px] text-[#475569] transition" data-id="<?= $en['id'] ?>" data-days="30">
                          + 30 วัน
                        </button>
                        <button type="button" class="btn-quick-extend px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 font-bold text-[11px] text-[#475569] transition" data-id="<?= $en['id'] ?>" data-days="90">
                          + 90 วัน
                        </button>
                      </div>

                      <div class="flex items-center gap-2">
                        <?php if ($en['status'] === 'active'): ?>
                          <button type="button" class="btn-update-status px-2.5 py-1 rounded-lg border border-amber-300 bg-amber-50/60 text-amber-800 hover:bg-amber-100 text-[11px] font-bold transition" data-id="<?= $en['id'] ?>" data-status="paused">
                            ⏸️ พักสิทธิ์
                          </button>
                        <?php elseif ($en['status'] === 'paused'): ?>
                          <button type="button" class="btn-update-status px-2.5 py-1 rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 text-[11px] font-bold transition" data-id="<?= $en['id'] ?>" data-status="active">
                            ▶️ เปิดสิทธิ์
                          </button>
                        <?php endif; ?>

                        <?php if ($en['status'] !== 'revoked'): ?>
                          <button type="button" class="btn-update-status px-2.5 py-1 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 text-[11px] font-bold transition" data-id="<?= $en['id'] ?>" data-status="revoked">
                            🚫 ยกเลิกสิทธิ์
                          </button>
                        <?php else: ?>
                          <button type="button" class="btn-update-status px-2.5 py-1 rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 text-[11px] font-bold transition" data-id="<?= $en['id'] ?>" data-status="active">
                            คืนสิทธิ์
                          </button>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Section: Audit Log History (Section 41) -->
          <div class="bg-white rounded-3xl border border-[#e8ecf2] p-6 shadow-sm">
            <h3 class="text-[16px] font-black text-navy-950 mb-3 pb-2 border-b border-[#f1f5f9] flex items-center justify-between">
              <span>ประวัติการแก้ไขสิทธิ์ (Audit Log)</span>
              <span class="text-[11px] text-[#94a3b8] font-normal">บันทึกทุกการจัดสรรและเปลี่ยนกลุ่ม</span>
            </h3>

            <?php if (empty($auditLogs)): ?>
              <p class="text-[13px] text-[#94a3b8] py-3">ยังไม่มีประวัติการทำรายการ</p>
            <?php else: ?>
              <div class="divide-y divide-[#f1f5f9] text-[12px] max-h-[300px] overflow-y-auto pr-2">
                <?php foreach ($auditLogs as $log): ?>
                  <div class="py-2.5 flex items-start justify-between gap-3">
                    <div>
                      <div class="font-bold text-navy-950">
                        <?= htmlspecialchars($log['details']) ?>
                      </div>
                      <div class="text-[11px] text-[#64748b]">
                        <?= htmlspecialchars($log['course_title'] ?: 'คอร์สเรียน') ?> · โดย <?= htmlspecialchars($log['performed_by_name'] ?: 'Admin') ?>
                      </div>
                    </div>
                    <span class="text-[10px] text-[#94a3b8] shrink-0">
                      <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Right Column: Combined Schedule Preview (Section 34) -->
        <aside class="space-y-6">
          <div class="bg-white rounded-3xl border border-[#e8ecf2] p-6 shadow-sm sticky top-6">
            <div class="flex items-center justify-between gap-2 mb-4 pb-3 border-b border-[#f1f5f9]">
              <div>
                <h3 class="text-[16px] font-black text-navy-950">
                  ตารางเรียนรวม (Schedule)
                </h3>
                <p class="text-[11px] text-[#64748b]">รวมตารางจากทุกคอร์สที่ได้รับสิทธิ์</p>
              </div>
              <span class="px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 text-[11px] font-bold">
                <?= count($schedule) ?> คาบ/สัปดาห์
              </span>
            </div>

            <?php if (empty($schedule)): ?>
              <div class="py-8 text-center text-[#94a3b8] text-[13px]">
                <p>ยังไม่มีคาบเรียนในตาราง</p>
                <p class="text-[11px] mt-1">จัดกลุ่มเรียนให้นักเรียนเพื่อแสดงตาราง</p>
              </div>
            <?php else: ?>
              <div class="space-y-3">
                <?php foreach ($schedule as $sc): ?>
                  <div class="p-3.5 rounded-2xl bg-[#f8fafc] border border-[#e2e8f0] text-[12px] space-y-1">
                    <div class="flex items-center justify-between font-bold text-navy-950">
                      <span class="text-pink-600 font-black">📅 <?= htmlspecialchars($sc['schedule_day']) ?></span>
                      <span class="text-[#64748b]"><?= htmlspecialchars($sc['schedule_time']) ?></span>
                    </div>
                    <div class="font-black text-navy-950 text-[13px]">
                      <?= htmlspecialchars($sc['course_title']) ?>
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-[#64748b]">
                      <span>กลุ่ม: <strong><?= htmlspecialchars($sc['class_group_name']) ?></strong></span>
                      <?php if (!empty($sc['room'])): ?>
                        <span>ห้อง: <?= htmlspecialchars($sc['room']) ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </aside>
      </div>
    </main>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: ASSIGN COURSE ACCESS (Section 4, 5, 6, 7, 8) -->
<!-- ========================================================================= -->
<div id="assign-modal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-navy-950/60 backdrop-blur-xs cursor-pointer" onclick="closeAssignModal()"></div>
  <div class="relative w-full max-w-[560px] bg-white rounded-3xl shadow-2xl border border-[#e2e8f0] p-6 z-10 my-auto animate-in fade-in zoom-in duration-150">
    <div class="flex items-center justify-between mb-4 pb-3 border-b border-[#f1f5f9]">
      <h3 class="text-[18px] font-black text-navy-950 flex items-center gap-2">
        <span>+ กำหนดสิทธิ์คอร์สเรียน</span>
      </h3>
      <button type="button" onclick="closeAssignModal()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-[#64748b] flex items-center justify-center font-bold">✕</button>
    </div>

    <form id="form-assign-course" class="space-y-4 text-[13px]">
      <input type="hidden" name="student_id" value="<?= $studentId ?>">

      <!-- Student Name (Readonly) -->
      <div>
        <label class="block font-bold text-[#475569] mb-1">นักเรียน</label>
        <div class="h-10 px-3.5 rounded-xl bg-[#f8fafc] border border-[#dce4ef] flex items-center font-bold text-navy-950">
          <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ($student['nickname'] ? ' (' . $student['nickname'] . ')' : '')) ?> — <?= htmlspecialchars($student['grade'] ?: 'ม.5') ?>
        </div>
      </div>

      <!-- Course Selection -->
      <div>
        <label class="block font-bold text-[#475569] mb-1">เลือกคอร์สเรียน *</label>
        <select name="course_id" id="modal-select-course" required class="w-full h-11 px-3.5 rounded-xl border border-[#dce4ef] bg-white font-bold text-navy-950 focus:border-pink-500 outline-none">
          <option value="">-- เลือกคอร์สเรียน --</option>
          <?php foreach ($allCourses as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?> (<?= htmlspecialchars($c['subject'] ?: '') ?> · <?= htmlspecialchars($c['level'] ?: '') ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Class Group Selection (Dynamic) -->
      <div>
        <div class="flex items-center justify-between mb-1">
          <label class="font-bold text-[#475569]">กลุ่มเรียน (Class Group)</label>
          <span id="modal-cg-capacity" class="text-[11px] text-[#94a3b8]"></span>
        </div>
        <select name="class_group_id" id="modal-select-cg" class="w-full h-11 px-3.5 rounded-xl border border-[#dce4ef] bg-white font-bold text-navy-950 focus:border-pink-500 outline-none">
          <option value="">-- ไม่ระบุกลุ่มเรียน (เรียนออนไลน์ทั่วไป) --</option>
        </select>
      </div>

      <!-- Access Type & Learning Mode -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-[#475569] mb-1">ประเภทสิทธิ์ (Access Type)</label>
          <select name="access_type" class="w-full h-10 px-3 rounded-xl border border-[#dce4ef] font-bold text-navy-950 outline-none">
            <option value="paid">Paid (ชำระเงินปกติ)</option>
            <option value="trial">Trial (ทดลองเรียน 7 วัน)</option>
            <option value="scholarship">Scholarship (ทุนการศึกษา)</option>
            <option value="complimentary">Complimentary (ให้สิทธิ์ฟรี)</option>
            <option value="manual">Manual (กำหนดเฉพาะกิจ)</option>
            <option value="bundle">Bundle (รวมแพ็กเกจ)</option>
          </select>
        </div>
        <div>
          <label class="block font-bold text-[#475569] mb-1">โหมดการเรียน</label>
          <select name="learning_mode" class="w-full h-10 px-3 rounded-xl border border-[#dce4ef] font-bold text-navy-950 outline-none">
            <option value="online">Online</option>
            <option value="onsite">On-site</option>
            <option value="hybrid">Hybrid</option>
          </select>
        </div>
      </div>

      <!-- Expiration Date Preset -->
      <div>
        <label class="block font-bold text-[#475569] mb-1">วันหมดอายุสิทธิ์</label>
        <div class="flex items-center gap-2 mb-2">
          <button type="button" onclick="setExpirePreset(7)" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 font-bold text-[11px]">7 วัน</button>
          <button type="button" onclick="setExpirePreset(30)" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 font-bold text-[11px]">30 วัน</button>
          <button type="button" onclick="setExpirePreset(90)" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 font-bold text-[11px]">90 วัน</button>
          <button type="button" onclick="setExpirePreset(365)" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 font-bold text-[11px]">1 ปี</button>
          <button type="button" onclick="document.getElementById('modal-end-date').value=''" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 font-bold text-[11px]">ไม่มีหมดอายุ</button>
        </div>
        <input type="date" name="end_date" id="modal-end-date" class="w-full h-10 px-3 rounded-xl border border-[#dce4ef] font-bold text-navy-950 outline-none">
      </div>

      <!-- Notes -->
      <div>
        <label class="block font-bold text-[#475569] mb-1">หมายเหตุ</label>
        <input type="text" name="notes" placeholder="เช่น รหัสนักเรียนเก่า, โปรโมชั่นเปิดเทอม" class="w-full h-10 px-3.5 rounded-xl border border-[#dce4ef] text-navy-950 outline-none">
      </div>

      <div id="assign-modal-error" class="hidden p-3 rounded-xl bg-rose-50 text-rose-700 text-[12px] font-bold"></div>

      <div class="flex items-center justify-end gap-3 pt-3 border-t border-[#f1f5f9]">
        <button type="button" onclick="closeAssignModal()" class="h-10 px-4 rounded-xl border border-[#dce4ef] hover:bg-slate-50 font-bold">ยกเลิก</button>
        <button type="submit" id="btn-submit-assign" class="h-10 px-6 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold transition shadow-xs">บันทึกสิทธิ์คอร์ส</button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: CHANGE CLASS GROUP (Section 25) -->
<!-- ========================================================================= -->
<div id="change-class-modal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-navy-950/60 backdrop-blur-xs cursor-pointer" onclick="closeChangeClassModal()"></div>
  <div class="relative w-full max-w-[460px] bg-white rounded-3xl shadow-2xl border border-[#e2e8f0] p-6 z-10 my-auto animate-in fade-in zoom-in duration-150">
    <h3 class="text-[17px] font-black text-navy-950 mb-3 pb-2 border-b border-[#f1f5f9]">ย้ายกลุ่มเรียน (Change Class Group)</h3>
    <form id="form-change-class" class="space-y-4 text-[13px]">
      <input type="hidden" name="enrollment_id" id="cc-enrollment-id">
      <div>
        <label class="block font-bold text-[#475569] mb-1">เลือกกลุ่มเรียนใหม่</label>
        <select name="class_group_id" id="cc-select-cg" required class="w-full h-11 px-3 rounded-xl border border-[#dce4ef] font-bold text-navy-950 outline-none"></select>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeChangeClassModal()" class="h-9 px-4 rounded-xl border font-bold">ยกเลิก</button>
        <button type="submit" class="h-9 px-5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold">ยืนยันการย้าย</button>
      </div>
    </form>
  </div>
</div>

<!-- Phase 4: Personalized Remediation Builder -->
<div id="remediation-modal" class="hidden fixed inset-0 z-[60] overflow-y-auto p-4">
  <div class="fixed inset-0 bg-navy-950/60 backdrop-blur-xs" onclick="closeRemediationBuilder()"></div>
  <div class="relative w-full max-w-[900px] mx-auto my-8 bg-white rounded-3xl shadow-2xl border border-slate-200 p-6 z-10">
    <div class="flex justify-between gap-4 pb-4 border-b">
      <div><p id="rem-builder-label" class="text-[11px] font-black uppercase text-indigo-600">Personalized Remediation Builder</p><h3 id="rem-title" class="text-xl font-black text-navy-950"></h3><p id="rem-meta" class="text-xs text-slate-500 mt-1"></p></div>
      <button type="button" onclick="closeRemediationBuilder()" class="w-9 h-9 rounded-full hover:bg-slate-100">✕</button>
    </div>
    <div class="grid md:grid-cols-5 gap-3 my-4 text-[12px]">
      <label class="font-bold">จำนวนข้อ<input id="rem-count" type="number" min="1" max="50" value="10" class="mt-1 w-full h-10 px-3 rounded-xl border"></label>
      <label class="font-bold md:col-span-2">ระดับความยาก<div class="mt-2 flex gap-3"><label><input class="rem-difficulty" type="checkbox" value="easy" checked> Easy</label><label><input class="rem-difficulty" type="checkbox" value="medium" checked> Medium</label><label><input class="rem-difficulty" type="checkbox" value="hard"> Hard</label></div></label>
      <label class="font-bold">โจทย์ที่เคยทำ<select id="rem-seen-policy" class="mt-1 w-full h-10 px-2 rounded-xl border"><option value="prefer_unseen">Prefer Unseen</option><option value="unseen_only">Unseen Only</option><option value="allow_repeat">Allow Repeat</option><option value="retry_incorrect">Retry Incorrect</option></select></label>
      <label class="font-bold">กำหนดส่ง (ไม่บังคับ)<input id="rem-due-at" type="datetime-local" class="mt-1 w-full h-10 px-2 rounded-xl border"></label>
    </div>
    <div class="flex items-center justify-between gap-3 mb-3"><button id="rem-smart-select" type="button" class="h-10 px-4 rounded-xl bg-indigo-600 text-white font-bold">เลือกโจทย์ให้อัตโนมัติ</button><p id="rem-status" class="text-xs font-bold text-slate-500"></p></div>
    <div id="rem-results" class="space-y-2 max-h-[420px] overflow-y-auto"></div>
    <div id="rem-shortfall" class="hidden mt-3 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold"></div>
    <div id="rem-error" class="hidden mt-3 p-3 rounded-xl bg-rose-50 text-rose-700 text-xs font-bold"></div>
    <div class="flex justify-end gap-2 pt-4 mt-4 border-t"><button type="button" onclick="closeRemediationBuilder()" class="h-10 px-4 rounded-xl border font-bold">ยกเลิก</button><button id="rem-assign" type="button" disabled class="h-10 px-5 rounded-xl bg-pink-500 disabled:opacity-40 text-white font-bold">ตรวจแล้วและมอบหมาย</button></div>
  </div>
</div>

<div id="gap-evidence-modal" class="hidden fixed inset-0 z-[70] overflow-y-auto p-4">
  <div class="fixed inset-0 bg-navy-950/60" onclick="document.getElementById('gap-evidence-modal').classList.add('hidden')"></div>
  <div class="relative w-full max-w-[680px] mx-auto my-12 bg-white rounded-3xl shadow-2xl p-6 z-10"><div class="flex justify-between"><h3 class="text-lg font-black">หลักฐานของ Learning Gap</h3><button onclick="document.getElementById('gap-evidence-modal').classList.add('hidden')">✕</button></div><div id="gap-evidence-content" class="mt-4 space-y-2"></div></div>
</div>

<script>
(() => {
  const assignModal = document.getElementById("assign-modal");
  const changeClassModal = document.getElementById("change-class-modal");
  const courseSelect = document.getElementById("modal-select-course");
  const cgSelect = document.getElementById("modal-select-cg");
  const formAssign = document.getElementById("form-assign-course");
  const errorBox = document.getElementById("assign-modal-error");

  window.closeAssignModal = () => {
    assignModal.classList.add("hidden");
    formAssign.reset();
    errorBox.classList.add("hidden");
  };

  window.closeChangeClassModal = () => {
    changeClassModal.classList.add("hidden");
  };

  window.setExpirePreset = days => {
    const d = new Date();
    d.setDate(d.getDate() + days);
    document.getElementById("modal-end-date").value = d.toISOString().split("T")[0];
  };

  document.getElementById("btn-open-assign-modal")?.addEventListener("click", () => {
    assignModal.classList.remove("hidden");
  });

  // Load Class Groups when Course selection changes
  courseSelect?.addEventListener("change", async () => {
    const cId = courseSelect.value;
    cgSelect.innerHTML = '<option value="">-- ไม่ระบุกลุ่มเรียน (เรียนออนไลน์ทั่วไป) --</option>';
    if (!cId) return;

    try {
      const res = await fetch(`enrollments-api.php?action=class_groups&course_id=${cId}`);
      const d = await res.json();
      if (d.success && d.class_groups) {
        d.class_groups.forEach(cg => {
          const capText = `${cg.current_enrolled} / ${cg.capacity} คน`;
          const schedText = cg.schedule_text ? ` (${cg.schedule_text})` : '';
          const opt = document.createElement("option");
          opt.value = cg.id;
          opt.textContent = `${cg.name}${schedText} [${capText}]`;
          cgSelect.appendChild(opt);
        });
      }
    } catch (e) {
      console.error(e);
    }
  });

  // Handle Assign Form Submit
  formAssign?.addEventListener("submit", async e => {
    e.preventDefault();
    errorBox.classList.add("hidden");
    const formData = new FormData(formAssign);
    const body = Object.fromEntries(formData.entries());

    try {
      const res = await fetch("enrollments-api.php?action=assign", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body)
      });
      const d = await res.json();

      if (d.success) {
        alert("กำหนดสิทธิ์คอร์สเรียบร้อยแล้ว");
        window.location.reload();
      } else {
        errorBox.textContent = d.error || "เกิดข้อผิดพลาดในการกำหนดสิทธิ์";
        errorBox.classList.remove("hidden");
        if (d.is_duplicate) {
          if (confirm(`${d.error}\nต้องการขยายเวลาเรียนแทนหรือไม่?`)) {
            // Quick extend call
            await fetch("enrollments-api.php?action=extend", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ enrollment_id: d.existing_enrollment.id, days_or_date: 30 })
            });
            window.location.reload();
          }
        }
      }
    } catch (err) {
      errorBox.textContent = "เชื่อมต่อระบบล้มเหลว: " + err.message;
      errorBox.classList.remove("hidden");
    }
  });

  // Quick Extend Click (+30d, +90d)
  document.querySelectorAll(".btn-quick-extend").forEach(btn => {
    btn.addEventListener("click", async () => {
      const enId = btn.dataset.id;
      const days = btn.dataset.days;
      if (!confirm(`ต้องการขยายเวลาเรียนเพิ่ม ${days} วัน ใช่หรือไม่?`)) return;

      const res = await fetch("enrollments-api.php?action=extend", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ enrollment_id: enId, days_or_date: days })
      });
      const d = await res.json();
      if (d.success) {
        alert(d.message);
        window.location.reload();
      } else {
        alert(d.error || "เกิดข้อผิดพลาด");
      }
    });
  });

  // Update Status Click (pause, revoke, active)
  document.querySelectorAll(".btn-update-status").forEach(btn => {
    btn.addEventListener("click", async () => {
      const enId = btn.dataset.id;
      const status = btn.dataset.status;
      const reason = prompt("ระบุเหตุผลในการเปลี่ยนสถานะ (ไม่บังคับ):", "");

      const res = await fetch("enrollments-api.php?action=update_status", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ enrollment_id: enId, status: status, reason: reason || "" })
      });
      const d = await res.json();
      if (d.success) {
        alert(d.message);
        window.location.reload();
      } else {
        alert(d.error || "เกิดข้อผิดพลาด");
      }
    });
  });

  // Change Class Group Button
  document.querySelectorAll(".btn-change-class").forEach(btn => {
    btn.addEventListener("click", async () => {
      const enId = btn.dataset.enId;
      const courseId = btn.dataset.courseId;
      document.getElementById("cc-enrollment-id").value = enId;

      const ccSelect = document.getElementById("cc-select-cg");
      ccSelect.innerHTML = '<option value="">กำลังโหลดกลุ่มเรียน...</option>';
      changeClassModal.classList.remove("hidden");

      try {
        const res = await fetch(`enrollments-api.php?action=class_groups&course_id=${courseId}`);
        const d = await res.json();
        ccSelect.innerHTML = "";
        if (d.success && d.class_groups) {
          d.class_groups.forEach(cg => {
            const opt = document.createElement("option");
            opt.value = cg.id;
            opt.textContent = `${cg.name} (${cg.schedule_text || ''}) [${cg.current_enrolled}/${cg.capacity}]`;
            ccSelect.appendChild(opt);
          });
        }
      } catch (e) {
        ccSelect.innerHTML = '<option value="">โหลดกลุ่มเรียนไม่สำเร็จ</option>';
      }
    });
  });

  // Submit Change Class
  document.getElementById("form-change-class")?.addEventListener("submit", async e => {
    e.preventDefault();
    const enId = document.getElementById("cc-enrollment-id").value;
    const cgId = document.getElementById("cc-select-cg").value;
    if (!cgId) return;

    const res = await fetch("enrollments-api.php?action=change_class", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ enrollment_id: enId, class_group_id: cgId })
    });
    const d = await res.json();
    if (d.success) {
      alert(d.message);
      window.location.reload();
    } else {
      alert(d.error || "เกิดข้อผิดพลาด");
    }
  });

  // =========================================================================
  // LEARNING GOAL MODAL & ACTIONS (Section 6)
  // =========================================================================
  const goalModal = document.getElementById("modal-learning-goal");
  const formGoal = document.getElementById("form-learning-goal");
  const goalErrorBox = document.getElementById("goal-modal-error");

  window.openGoalModal = () => {
    formGoal.reset();
    document.getElementById("goal-id").value = "";
    document.getElementById("goal-modal-title").textContent = "+ ตั้งเป้าหมายการเรียนใหม่";
    goalErrorBox.classList.add("hidden");
    goalModal.classList.remove("hidden");
  };

  window.closeGoalModal = () => {
    goalModal.classList.add("hidden");
  };

  window.editGoal = (goal) => {
    document.getElementById("goal-id").value = goal.id || "";
    document.getElementById("goal-course-id").value = goal.course_id || "";
    document.getElementById("goal-type").value = goal.goal_type || "a_level";
    document.getElementById("goal-name").value = goal.goal_name || "";
    document.getElementById("goal-target-score").value = goal.target_score ?? "";
    document.getElementById("goal-target-date").value = goal.target_date || "";
    document.getElementById("goal-priority").value = goal.priority || "high";
    document.getElementById("goal-status").value = goal.status || "active";
    document.getElementById("goal-modal-title").textContent = "แก้ไขเป้าหมายการเรียน";
    goalErrorBox.classList.add("hidden");
    goalModal.classList.remove("hidden");
  };

  window.archiveGoal = async (goalId) => {
    if (!confirm("ต้องการจัดเก็บ (Archive) เป้าหมายนี้หรือไม่?")) return;
    try {
      const res = await fetch("learning-journey-api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "archive_goal", goal_id: goalId, student_id: <?= $studentId ?> })
      });
      const d = await res.json();
      if (d.success) {
        window.location.reload();
      } else {
        alert(d.error || "เกิดข้อผิดพลาด");
      }
    } catch (err) {
      alert("เชื่อมต่อระบบล้มเหลว: " + err.message);
    }
  };

  formGoal?.addEventListener("submit", async e => {
    e.preventDefault();
    goalErrorBox.classList.add("hidden");
    const formData = new FormData(formGoal);
    const body = Object.fromEntries(formData.entries());
    body.action = "save_goal";
    body.student_id = <?= $studentId ?>;

    try {
      const res = await fetch("learning-journey-api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body)
      });
      const d = await res.json();
      if (d.success) {
        window.location.reload();
      } else {
        goalErrorBox.textContent = d.error || "เกิดข้อผิดพลาด";
        goalErrorBox.classList.remove("hidden");
      }
    } catch (err) {
      goalErrorBox.textContent = "เชื่อมต่อระบบล้มเหลว: " + err.message;
      goalErrorBox.classList.remove("hidden");
    }
  });

  // Phase 4 — teacher review is mandatory before assignment.
  const remModal = document.getElementById("remediation-modal");
  const remResults = document.getElementById("rem-results");
  const remError = document.getElementById("rem-error");
  let activeGap = null;
  let builderMode = 'remediation';
  let recommendedQuestions = [];
  let lastRemediationSearch = {};

  window.openRemediationBuilder = gap => {
    builderMode = 'remediation';
    activeGap = gap;
    recommendedQuestions = [];
    document.getElementById("rem-title").textContent = gap.topic + (gap.skill ? ` → ${gap.skill}` : "");
    document.getElementById("rem-builder-label").textContent = 'Personalized Remediation Builder';
    document.getElementById("rem-count").value = 10;
    document.getElementById("rem-meta").textContent = `นักเรียน: <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'], ENT_QUOTES) ?> · Mastery ${gap.mastery}% · ครูต้องตรวจสอบก่อนมอบหมาย`;
    remResults.innerHTML = '<div class="py-8 text-center text-sm text-slate-400">กด “เลือกโจทย์ให้อัตโนมัติ” เพื่อค้นจากคลังข้อสอบ</div>';
    document.getElementById("rem-shortfall").classList.add("hidden");
    document.getElementById("rem-assign").disabled = true;
    remError.classList.add("hidden");
    remModal.classList.remove("hidden");
  };
  window.openMasteryCheckBuilder = gap => {
    window.openRemediationBuilder(gap);
    builderMode = 'mastery_check';
    document.getElementById("rem-builder-label").textContent = 'Mastery Check Builder';
    document.getElementById("rem-meta").textContent = `โจทย์ชุดใหม่ที่ไม่ซ้ำแบบฝึกเดิม · ครูตรวจสอบก่อนมอบหมาย`;
    document.getElementById("rem-count").value = 5;
  };
  window.closeRemediationBuilder = () => remModal.classList.add("hidden");

  const selectedQuestionIds = () => [...document.querySelectorAll('.rem-question:checked')].map(el => Number(el.value));
  const renderRecommendations = data => {
    lastRemediationSearch = data;
    recommendedQuestions = data.results || [];
    const capabilities = data.search_capabilities || {};
    document.getElementById("rem-status").textContent = `${data.candidate_count || 0} ข้อเหมาะสม · Semantic: ${capabilities.semantic ? 'พร้อมใช้งาน' : 'ไม่พร้อม (ใช้ Metadata + Keyword)'}`;
    remResults.innerHTML = recommendedQuestions.map((q, i) => `<label class="block p-3 rounded-xl border border-slate-200 hover:border-indigo-300">
      <div class="flex items-start gap-3"><input type="checkbox" class="rem-question mt-1" value="${q.id}" checked><div class="flex-1 min-w-0"><div class="flex gap-2 text-[10px] font-black uppercase text-slate-500"><span>#${i+1}</span><span>${escapeHtml(q.difficulty || '')}</span><span>${q.previously_seen ? 'เคยทำแล้ว' : 'ยังไม่เคยทำ'}</span></div><p class="text-sm font-bold text-navy-950 mt-1">${escapeHtml(q.question_text || '')}</p><p class="text-[11px] text-indigo-600 mt-1">${(q.match_reasons || []).map(escapeHtml).join(' · ')}</p><div class="flex items-center gap-3 mt-2"><details class="text-xs flex-1"><summary class="cursor-pointer font-bold text-slate-500">ดูคำตอบและคำอธิบาย</summary><div class="mt-1 p-2 bg-slate-50 rounded-lg">${escapeHtml(String(q.correct_answer_json || ''))}<br>${escapeHtml(q.explanation || '')}</div></details><button type="button" onclick="event.preventDefault();replaceRemediationQuestion(${q.id})" class="text-[11px] font-bold text-indigo-600">เปลี่ยนข้อ</button></div></div></div></label>`).join('');
    document.getElementById("rem-assign").disabled = recommendedQuestions.length === 0;
    const shortfall = Number(data.shortfall || 0), box = document.getElementById("rem-shortfall");
    if (shortfall > 0) { box.innerHTML = `พบโจทย์ที่เหมาะสม ${recommendedQuestions.length} ข้อจาก ${data.requested_count} ข้อ — สามารถใช้ ${recommendedQuestions.length} ข้อนี้, ปรับตัวกรองด้านบน หรือ <a class="underline" href="ai-worksheet.php">สร้างเพิ่ม ${shortfall} ข้อด้วย AI</a> (ระบบจะไม่สร้างอัตโนมัติ)`; box.classList.remove("hidden"); } else box.classList.add("hidden");
  };
  const escapeHtml = value => String(value).replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));

  document.getElementById("rem-smart-select")?.addEventListener("click", async () => {
    remError.classList.add("hidden"); remResults.innerHTML = '<div class="py-8 text-center text-sm text-slate-500">กำลังค้นหาและจัดชุดที่หลากหลาย…</div>';
    try {
      const difficulties = [...document.querySelectorAll('.rem-difficulty:checked')].map(el => el.value);
      const qs = new URLSearchParams({action:builderMode === 'mastery_check' ? 'recommend_mastery_check' : 'recommend',student_id:'<?= $studentId ?>',course_id:String(activeGap.course_id),gap_id:String(activeGap.id),count:document.getElementById('rem-count').value,seen_policy:document.getElementById('rem-seen-policy').value});
      difficulties.forEach(d => qs.append('difficulty[]', d));
      const res = await fetch(`adaptive-learning-api.php?${qs}`), data = await res.json();
      if (!res.ok || !data.success) throw new Error(data.error || 'ค้นหาไม่สำเร็จ'); renderRecommendations(data);
    } catch (e) { remError.textContent = e.message; remError.classList.remove("hidden"); remResults.innerHTML = ''; }
  });

  window.replaceRemediationQuestion = async rejectedId => {
    const exclude = recommendedQuestions.map(q => q.id);
    try {
      const difficulties=[...document.querySelectorAll('.rem-difficulty:checked')].map(el=>el.value);
      const qs=new URLSearchParams({action:builderMode === 'mastery_check' ? 'recommend_mastery_check' : 'recommend',student_id:'<?= $studentId ?>',course_id:String(activeGap.course_id),gap_id:String(activeGap.id),count:'1',seen_policy:document.getElementById('rem-seen-policy').value});
      [...exclude,rejectedId].forEach(id=>qs.append('exclude_ids[]',String(id)));difficulties.forEach(d=>qs.append('difficulty[]',d));
      const res=await fetch(`adaptive-learning-api.php?${qs}`),data=await res.json();if(!res.ok||!data.success)throw new Error(data.error||'ไม่พบข้อทดแทน');
      if(!data.results?.length)throw new Error('ไม่พบข้อทดแทนที่เหมาะสมจากคลัง');
      recommendedQuestions=recommendedQuestions.map(q=>Number(q.id)===Number(rejectedId)?data.results[0]:q);
      renderRecommendations({...lastRemediationSearch,results:recommendedQuestions,shortfall:0,candidate_count:lastRemediationSearch.candidate_count});
    } catch(e){remError.textContent=e.message;remError.classList.remove('hidden');}
  };

  document.getElementById("rem-assign")?.addEventListener("click", async () => {
    const ids = selectedQuestionIds(); if (!ids.length) return alert('กรุณาเลือกอย่างน้อย 1 ข้อ');
    if (!confirm(`ยืนยันมอบหมาย${builderMode === 'mastery_check' ? 'แบบตรวจความเข้าใจ' : 'แบบฝึก'} ${ids.length} ข้อให้นักเรียน?`)) return;
    try {
      const res = await fetch('adaptive-learning-api.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:builderMode === 'mastery_check' ? 'create_mastery_check' : 'create_remediation',student_id:<?= $studentId ?>,course_id:activeGap.course_id,gap_id:activeGap.id,question_ids:ids,seen_policy:document.getElementById('rem-seen-policy').value,due_at:document.getElementById('rem-due-at').value||null})});
      const data = await res.json(); if (!res.ok || !data.success) throw new Error(data.error || 'สร้างแบบฝึกไม่สำเร็จ');
      alert(data.duplicate ? 'มีแบบฝึกหัวข้อนี้ที่กำลังใช้งานอยู่แล้ว ระบบไม่ได้สร้างซ้ำ' : 'มอบหมายแบบฝึกเฉพาะจุดเรียบร้อยแล้ว'); window.location.reload();
    } catch (e) { remError.textContent=e.message;remError.classList.remove('hidden'); }
  });

  window.showGapEvidence = async gapId => {
    const modal=document.getElementById('gap-evidence-modal'),content=document.getElementById('gap-evidence-content');modal.classList.remove('hidden');content.innerHTML='<p class="text-sm text-slate-500">กำลังโหลด…</p>';
    try { const res=await fetch(`adaptive-learning-api.php?action=gap_evidence&gap_id=${gapId}`),data=await res.json();if(!res.ok)throw new Error(data.error||'โหลดไม่สำเร็จ');
      content.innerHTML=`<div class="p-3 rounded-xl bg-slate-50 text-sm"><b>${escapeHtml(data.gap.topic_name)}</b> · Mastery ${data.gap.mastery_score}% · ${data.gap.evidence_count} หลักฐาน · ${escapeHtml(data.gap.trend)}</div>`+(data.evidence||[]).map(ev=>`<div class="p-3 rounded-xl border flex justify-between gap-3 text-xs"><div><b>${escapeHtml(ev.activity_title||ev.source_type)}</b><div class="text-slate-400">${escapeHtml(ev.occurred_at)}</div></div><b class="${Number(ev.normalized_score)>=60?'text-emerald-600':'text-rose-600'}">${Number(ev.normalized_score).toFixed(1)}%</b></div>`).join('');
    } catch(e){content.innerHTML=`<p class="text-rose-600">${escapeHtml(e.message)}</p>`;}
  };

  window.changeGapState = async (action, gapId) => {
    const reason = prompt(action === 'resolve_gap' ? 'ระบุเหตุผลที่ครูยืนยันว่าพร้อมไปต่อ' : 'ระบุเหตุผลที่ต้องกลับมาติดตามหัวข้อนี้');
    if (!reason?.trim()) return;
    try {
      const res=await fetch('adaptive-learning-api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,gap_id:gapId,reason})});
      const data=await res.json();if(!res.ok||!data.success)throw new Error(data.error||'บันทึกไม่สำเร็จ');window.location.reload();
    } catch(e){alert(e.message);}
  };
  window.createIntervention = async gapId => {
    const notes = prompt('บันทึกสำหรับครูผู้ดูแล (ไม่บังคับ)') ?? '';
    try {
      const res=await fetch('adaptive-learning-api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'create_intervention',gap_id:gapId,teacher_notes:notes,recommended_action:'one_on_one'})});
      const data=await res.json();if(!res.ok||!data.success)throw new Error(data.error||'สร้างรายการช่วยเหลือไม่สำเร็จ');alert('เพิ่มในคิวการช่วยเหลือของครูแล้ว');window.location.reload();
    } catch(e){alert(e.message);}
  };
})();
</script>

<!-- ========================================================================= -->
<!-- MODAL: LEARNING GOAL (Section 6) -->
<!-- ========================================================================= -->
<div id="modal-learning-goal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-navy-950/60 backdrop-blur-xs cursor-pointer" onclick="closeGoalModal()"></div>
  <div class="relative w-full max-w-[500px] bg-white rounded-3xl shadow-2xl border border-[#e2e8f0] p-6 z-10 my-auto animate-in fade-in zoom-in duration-150">
    <div class="flex items-center justify-between gap-3 pb-3 border-b border-[#f1f5f9] mb-4">
      <h3 id="goal-modal-title" class="text-[18px] font-black text-navy-950">เป้าหมายการเรียน</h3>
      <button type="button" onclick="closeGoalModal()" class="w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center text-slate-400">✕</button>
    </div>
    <form id="form-learning-goal" class="space-y-4 text-[13px]">
      <input type="hidden" name="id" id="goal-id">

      <div>
        <label class="block font-bold text-[#475569] mb-1">คอร์สเรียน / รายวิชา *</label>
        <select name="course_id" id="goal-course-id" required class="w-full h-11 px-3 rounded-xl border border-[#dce4ef] font-bold text-navy-950 outline-none">
          <?php foreach ($enrollments as $en): ?>
            <option value="<?= $en['course_id'] ?>"><?= htmlspecialchars($en['course_title']) ?> (<?= htmlspecialchars($en['subject']) ?>)</option>
          <?php endforeach; ?>
          <?php if (empty($enrollments)): ?>
            <?php foreach ($allCourses as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-[#475569] mb-1">ประเภทเป้าหมาย</label>
          <select name="goal_type" id="goal-type" class="w-full h-10 px-3 rounded-xl border border-[#dce4ef] font-bold text-navy-950 outline-none">
            <option value="a_level">A-Level / TPAT</option>
            <option value="entrance_exam">สอบเข้ามหาวิทยาลัย</option>
            <option value="grade_improvement">เพิ่มเกรดในโรงเรียน</option>
            <option value="foundation">ปูพื้นฐานความเข้าใจ</option>
            <option value="faculty_target">เตรียมตัวเข้าคณะเป้าหมาย</option>
            <option value="custom">เป้าหมายกำหนดเอง</option>
          </select>
        </div>
        <div>
          <label class="block font-bold text-[#475569] mb-1">ความสำคัญ (Priority)</label>
          <select name="priority" id="goal-priority" class="w-full h-10 px-3 rounded-xl border border-[#dce4ef] font-bold text-navy-950 outline-none">
            <option value="high">High (ด่วน/สำคัญมาก)</option>
            <option value="medium">Medium (ปานกลาง)</option>
            <option value="low">Low (ทั่วไป)</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block font-bold text-[#475569] mb-1">ชื่อเป้าหมาย *</label>
        <input type="text" name="goal_name" id="goal-name" required placeholder="เช่น A-Level English 75 คะแนน" class="w-full h-10 px-3.5 rounded-xl border border-[#dce4ef] font-bold text-navy-950 outline-none">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-[#475569] mb-1">คะแนนเป้าหมาย (เต็ม 100)</label>
          <input type="number" step="0.1" min="0" max="100" name="target_score" id="goal-target-score" placeholder="เช่น 75" class="w-full h-10 px-3.5 rounded-xl border border-[#dce4ef] font-bold text-navy-950 outline-none">
        </div>
        <div>
          <label class="block font-bold text-[#475569] mb-1">วันที่เป้าหมาย</label>
          <input type="date" name="target_date" id="goal-target-date" class="w-full h-10 px-3 rounded-xl border border-[#dce4ef] font-bold text-navy-950 outline-none">
        </div>
      </div>

      <div>
        <label class="block font-bold text-[#475569] mb-1">สถานะ</label>
        <select name="status" id="goal-status" class="w-full h-10 px-3 rounded-xl border border-[#dce4ef] font-bold text-navy-950 outline-none">
          <option value="active">Active (กำลังดำเนินการ)</option>
          <option value="completed">Completed (สำเร็จแล้ว)</option>
          <option value="archived">Archived (จัดเก็บ)</option>
        </select>
      </div>

      <div id="goal-modal-error" class="hidden p-3 rounded-xl bg-rose-50 text-rose-700 text-[12px] font-bold"></div>

      <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#f1f5f9]">
        <button type="button" onclick="closeGoalModal()" class="h-10 px-4 rounded-xl border font-bold">ยกเลิก</button>
        <button type="submit" class="h-10 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition shadow-xs">บันทึกเป้าหมาย</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
