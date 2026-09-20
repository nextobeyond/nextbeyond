<?php
/**
 * student/index.php — Student Dashboard
 */
$pageTitle   = 'Dashboard นักเรียน';
$currentPage = 'index.php';
require_once __DIR__ . '/includes/guard.php';

// ── ดึงข้อมูล Stats ──
require_once __DIR__ . '/../admin/enrollments-service.php';
$enrollmentService = new EnrollmentService($pdo);

// ── Learning Journey Core (Phase 1) ──
require_once __DIR__ . '/../includes/learning-journey-service.php';
$journeyService = new LearningJourneyService($pdo);
$primaryNextAction = $journeyService->getStudentNextAction((int)$currentUser['id']);
$studentUpcomingList = $journeyService->getStudentUpcomingList((int)$currentUser['id']);
$activeGoals = $journeyService->getStudentLearningGoals((int)$currentUser['id'], null, 'active');

// ── Phase 2: PREPARE & LEARN — Upcoming Class Events ──
require_once __DIR__ . '/../includes/phase2-session-service.php';
$_p2 = new Phase2SessionService($pdo);
$upcomingClasses = $_p2->getUpcomingEventsForStudent((int)$currentUser['id'], 72);
$nextClass = $upcomingClasses[0] ?? null;

// ── Phase 3: PRACTICE & MEASURE — Student Assignments & Separate Metrics ──
require_once __DIR__ . '/../includes/phase3-mastery-service.php';
$_p3 = new Phase3MasteryService($pdo);
$studentAssignments = $_p3->getStudentAssignments((int)$currentUser['id'], null, 'all');

function resolveStudentActionUrl(?string $url): string {
    if (empty($url)) return 'my-courses.php';
    if (str_starts_with($url, 'student/')) {
        return substr($url, 8);
    }
    if (str_starts_with($url, 'lesson.php') || str_starts_with($url, 'course.php')) {
        return '../' . $url;
    }
    return $url;
}

// จำนวนคอร์สที่ลงทะเบียน (Active / Trial ไม่หมดอายุ)
$stmtCourses = $pdo->prepare('SELECT COUNT(*) FROM enrollments WHERE user_id = :uid AND status IN ("active", "trial") AND (end_date IS NULL OR end_date >= CURDATE())');
$stmtCourses->execute([':uid' => $currentUser['id']]);
$enrolledCount = (int)$stmtCourses->fetchColumn();

// จำนวนข้อสอบที่ทำแล้ว
$stmtAttempts = $pdo->prepare('SELECT COUNT(*) FROM test_attempts WHERE user_id = :uid AND completed_at IS NOT NULL');
$stmtAttempts->execute([':uid' => $currentUser['id']]);
$attemptCount = (int)$stmtAttempts->fetchColumn();

// คะแนนเฉลี่ย
$stmtAvg = $pdo->prepare('SELECT AVG(score) FROM test_attempts WHERE user_id = :uid AND completed_at IS NOT NULL AND score IS NOT NULL');
$stmtAvg->execute([':uid' => $currentUser['id']]);
$avgScore = round((float)($stmtAvg->fetchColumn() ?: 0), 1);

// ข้อสอบที่เปิดอยู่ (is_published=1, status=active)
$stmtExams = $pdo->query(
    "SELECT e.id, e.title, e.subject, e.grade, e.type, e.time_limit_minutes,
            COUNT(q.id) AS question_count
     FROM exams e
     LEFT JOIN exam_questions q ON q.exam_id = e.id
     WHERE e.is_published = 1 AND e.status = 'active'
     GROUP BY e.id
     ORDER BY e.updated_at DESC, e.id DESC
     LIMIT 6"
);
$availableExams = $stmtExams->fetchAll();

// ประวัติข้อสอบล่าสุด 5 รายการ
$stmtHistory = $pdo->prepare(
    'SELECT a.id, a.score, a.correct_count, a.total_questions, a.completed_at,
            e.title, e.subject, e.type
     FROM test_attempts a
     INNER JOIN exams e ON e.id = a.exam_id
     WHERE a.user_id = :uid AND a.completed_at IS NOT NULL
     ORDER BY a.completed_at DESC LIMIT 5'
);
$stmtHistory->execute([':uid' => $currentUser['id']]);
$recentAttempts = $stmtHistory->fetchAll();

// คอร์สกำลังเรียน
$stmtEnrolled = $pdo->prepare(
    'SELECT c.id, c.title, c.subject, c.cover_image, c.duration_hours, en.progress_percent, en.status,
            cg.name AS class_group_name, cg.schedule_day, cg.schedule_time
     FROM enrollments en
     INNER JOIN courses c ON c.id = en.course_id
     LEFT JOIN class_groups cg ON cg.id = en.class_group_id
     WHERE en.user_id = :uid AND en.status IN ("active", "trial") AND (en.end_date IS NULL OR en.end_date >= CURDATE())
     ORDER BY en.enrolled_at DESC LIMIT 6'
);
$stmtEnrolled->execute([':uid' => $currentUser['id']]);
$enrolledCourses = $stmtEnrolled->fetchAll();

// ตารางเรียนรวมจากทุกวิชา (Combined Schedule)
$rawSchedule = $enrollmentService->getStudentCombinedSchedule((int)$currentUser['id']);
$weeklySchedule = [];
foreach ($rawSchedule as $r) {
    $day = $r['schedule_day'] ?: 'อื่นๆ';
    $weeklySchedule[$day][] = $r;
}

$displayName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?: 'นักเรียน';
$firstName = trim((string)($currentUser['first_name'] ?? '')) ?: 'นักเรียน';

// ข้อมูลหน้าแรก Mobile: บทเรียนถัดไป ภารกิจจาก Roadmap และกิจกรรมรายสัปดาห์
$featuredCourse = $enrolledCourses[0] ?? null;
$featuredProgress = $featuredCourse ? max(0, min(100, (int)round((float)$featuredCourse['progress_percent']))) : 0;
$featuredLesson = null;
$featuredLessonTotal = 0;
$featuredLessonDone = 0;
if ($featuredCourse) {
    $lessonSummary = $pdo->prepare(
        'SELECT COUNT(l.id) AS total_lessons,
                SUM(CASE WHEN lp.is_completed = 1 THEN 1 ELSE 0 END) AS completed_lessons
         FROM lessons l
         LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ?
         WHERE l.course_id = ?'
    );
    $lessonSummary->execute([(int)$currentUser['id'], (int)$featuredCourse['id']]);
    $lessonCounts = $lessonSummary->fetch() ?: [];
    $featuredLessonTotal = (int)($lessonCounts['total_lessons'] ?? 0);
    $featuredLessonDone = (int)($lessonCounts['completed_lessons'] ?? 0);

    $nextLesson = $pdo->prepare(
        'SELECT l.id, l.title, l.duration_minutes
         FROM lessons l
         LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ?
         WHERE l.course_id = ? AND COALESCE(lp.is_completed, 0) = 0
         ORDER BY l.sort_order, l.id LIMIT 1'
    );
    $nextLesson->execute([(int)$currentUser['id'], (int)$featuredCourse['id']]);
    $featuredLesson = $nextLesson->fetch() ?: null;
}

// Phase 3: Three Distinct Learning Metrics (Section 53-54)
$featuredCourseId = (int)($featuredCourse['id'] ?? 0);
$metricCompletionPct = $featuredProgress;
$metricPerformancePct = 0;
$metricMasteryPct = 0;

if ($featuredCourseId > 0) {
    // Performance: Average scores across activity submissions & exams
    $stmtActScores = $pdo->prepare("
        SELECT percentage FROM activity_submissions sub
        INNER JOIN worksheet_assignments wa ON wa.id = sub.assignment_id
        WHERE sub.student_id = ? AND wa.course_id = ? AND sub.status IN ('completed', 'submitted')
    ");
    $stmtActScores->execute([(int)$currentUser['id'], $featuredCourseId]);
    $actScores = $stmtActScores->fetchAll(PDO::FETCH_COLUMN);

    $stmtExamScores = $pdo->prepare("
        SELECT ta.score FROM test_attempts ta
        INNER JOIN exams e ON e.id = ta.exam_id
        WHERE ta.user_id = ? AND e.course_id = ? AND ta.completed_at IS NOT NULL
    ");
    $stmtExamScores->execute([(int)$currentUser['id'], $featuredCourseId]);
    $examScores = $stmtExamScores->fetchAll(PDO::FETCH_COLUMN);

    $allScores = array_merge($actScores, $examScores);
    if (!empty($allScores)) {
        $metricPerformancePct = (int)round(array_sum($allScores) / count($allScores));
    } else {
        $metricPerformancePct = (int)round((float)($averageScore ?? 0));
    }

    // Mastery: Average topic mastery for this course
    $stmtM = $pdo->prepare("SELECT AVG(mastery_score) FROM topic_mastery WHERE student_id = ? AND course_id = ?");
    $stmtM->execute([(int)$currentUser['id'], $featuredCourseId]);
    $avgM = $stmtM->fetchColumn();
    if ($avgM !== false && $avgM !== null) {
        $metricMasteryPct = (int)round((float)$avgM);
    } else {
        $stmtProf = $pdo->prepare("SELECT overall_mastery FROM student_learning_profiles WHERE student_id = ? AND course_id = ?");
        $stmtProf->execute([(int)$currentUser['id'], $featuredCourseId]);
        $profM = $stmtProf->fetchColumn();
        $metricMasteryPct = $profM !== false && $profM !== null ? (int)round((float)$profM) : 0;
    }
}

$missionStmt = $pdo->prepare(
    "SELECT t.id, t.title, t.subject, t.completion_type, t.ref_lesson_id, t.ref_exam_id,
            t.points_reward, COALESCE(p.status, 'not_started') AS progress_status
     FROM roadmap_enrollments re
     INNER JOIN roadmaps r ON r.id = re.roadmap_id AND r.status = 'published'
     INNER JOIN roadmap_tasks t ON t.roadmap_id = re.roadmap_id AND t.is_active = 1
     LEFT JOIN roadmap_task_progress p ON p.task_id = t.id AND p.user_id = re.user_id
     WHERE re.user_id = ? AND re.status = 'active'
     ORDER BY FIELD(COALESCE(p.status, 'not_started'), 'in_progress', 'not_started', 'locked', 'completed', 'exempted'),
              COALESCE(t.due_date, '9999-12-31'), t.sort_order, t.id
     LIMIT 2"
);
$missionStmt->execute([(int)$currentUser['id']]);
$mobileMissions = $missionStmt->fetchAll();

if (!$mobileMissions && $featuredCourse) {
    $mobileMissions[] = [
        'title' => $featuredLesson['title'] ?? $featuredCourse['title'],
        'subject' => $featuredCourse['subject'] ?: 'คอร์สของฉัน',
        'completion_type' => 'course', 'progress_status' => 'in_progress',
        'ref_lesson_id' => $featuredLesson['id'] ?? null, 'ref_exam_id' => null,
        'points_reward' => 0,
    ];
}
if (count($mobileMissions) < 2 && !empty($availableExams)) {
    $mobileMissions[] = [
        'title' => $availableExams[0]['title'], 'subject' => $availableExams[0]['subject'] ?: 'แบบทดสอบ',
        'completion_type' => 'submit_test', 'progress_status' => 'not_started',
        'ref_lesson_id' => null, 'ref_exam_id' => $availableExams[0]['id'], 'points_reward' => 0,
    ];
}

$weekStart = new DateTimeImmutable('monday this week');
$weekEnd = $weekStart->modify('+7 days');
$activityStmt = $pdo->prepare(
    'SELECT activity_date FROM (
       SELECT DATE(COALESCE(last_watched_at, completed_at, started_at)) AS activity_date
       FROM lesson_progress WHERE user_id = ?
       UNION
       SELECT DATE(completed_at) AS activity_date
       FROM test_attempts WHERE user_id = ? AND completed_at IS NOT NULL
     ) activity
     WHERE activity_date >= ? AND activity_date < ?'
);
$activityStmt->execute([(int)$currentUser['id'], (int)$currentUser['id'], $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);
$activeWeekDays = array_fill(0, 7, false);
foreach ($activityStmt->fetchAll(PDO::FETCH_COLUMN) as $activityDate) {
    $dayIndex = (int)(new DateTimeImmutable((string)$activityDate))->format('N') - 1;
    if ($dayIndex >= 0 && $dayIndex < 7) $activeWeekDays[$dayIndex] = true;
}
$weekDayLabels = ['จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'];
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> - Nextbeyond Compass</title>
  <link rel="stylesheet" href="../assets/css/output.css?v=<?= filemtime(__DIR__ . '/../assets/css/output.css') ?>">
  <link rel="stylesheet" href="../assets/css/student-portal.css?v=<?= filemtime(__DIR__ . '/../assets/css/student-portal.css') ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="student-portal bg-[#f4f7fb] text-navy-950 font-sans antialiased">

<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>

  <div class="flex-1 flex flex-col ml-[240px] max-[1024px]:ml-0 transition-all duration-300 min-w-0">
    <?php include 'includes/topbar.php'; ?>

    <main class="flex-1 p-8 max-[640px]:p-4">

      <section class="student-mobile-home" aria-label="หน้าแรกนักเรียนบนมือถือ">
        <div class="mobile-home-intro">
          <p>สวัสดี <?= htmlspecialchars($firstName) ?> 👋</p>
          <h1>ก้าวต่อไปของคุณ</h1>
          <span>เรียนทีละก้าว ไปให้ไกลกว่าเดิม</span>
        </div>

        <article class="mobile-learning-hero">
          <div class="mobile-hero-orbit" aria-hidden="true"></div>
          <div class="mobile-hero-copy">
            <?php if ($primaryNextAction): ?>
              <span class="mobile-hero-label">เรียนต่อจากเดิม</span>
              <h2><?= htmlspecialchars($primaryNextAction['course_title']) ?></h2>
              <p><?= htmlspecialchars($primaryNextAction['title']) ?></p>
              <div class="mobile-progress"><i style="width:<?= $featuredProgress ?>%"></i></div>
              <small><?= htmlspecialchars($primaryNextAction['topic'] ?: ($featuredLessonTotal > 0 ? 'เรียนแล้ว ' . $featuredLessonDone . ' จาก ' . $featuredLessonTotal . ' บท' : 'ภารกิจถัดไป')) ?></small>
              <a href="<?= htmlspecialchars(resolveStudentActionUrl($primaryNextAction['action_url'])) ?>" class="mobile-primary">▶ เรียนต่อ</a>
            <?php elseif ($featuredCourse): ?>
              <span class="mobile-hero-label">เรียนต่อจากเดิม</span>
              <h2><?= htmlspecialchars($featuredCourse['title']) ?></h2>
              <p><?= htmlspecialchars($featuredLesson['title'] ?? ($featuredCourse['subject'] ?: 'บทเรียนของคุณ')) ?></p>
              <div class="mobile-progress"><i style="width:<?= $featuredProgress ?>%"></i></div>
              <small><?= $featuredLessonTotal > 0 ? 'เรียนแล้ว ' . $featuredLessonDone . ' จาก ' . $featuredLessonTotal . ' บท' : 'ความคืบหน้า ' . $featuredProgress . '%' ?></small>
              <a href="<?= $featuredLesson ? '../lesson.php?id=' . (int)$featuredLesson['id'] : 'my-courses.php' ?>" class="mobile-primary">▶ เรียนต่อ</a>
            <?php elseif (!empty($availableExams)): ?>
              <span class="mobile-hero-label">แนะนำสำหรับคุณ</span>
              <h2><?= htmlspecialchars($availableExams[0]['subject'] ?: 'ฝึกทำข้อสอบ') ?></h2>
              <p><?= htmlspecialchars($availableExams[0]['title']) ?></p>
              <small><?= (int)$availableExams[0]['question_count'] ?> ข้อ<?= $availableExams[0]['time_limit_minutes'] ? ' • ' . (int)$availableExams[0]['time_limit_minutes'] . ' นาที' : '' ?></small>
              <a href="take-test.php?id=<?= (int)$availableExams[0]['id'] ?>" class="mobile-primary">▶ เริ่มฝึก</a>
            <?php else: ?>
              <span class="mobile-hero-label">เริ่มต้นวันนี้</span>
              <h2>เลือกเส้นทางที่ใช่</h2>
              <p>สร้างเป้าหมายการเรียนของคุณ</p>
              <small>มี Roadmap ให้เลือกตามระดับ</small>
              <a href="roadmap.php" class="mobile-primary">เลือก Roadmap</a>
            <?php endif; ?>
          </div>
          <img class="mobile-owl" src="../assets/images/next-owl.png" alt="มาสคอตนกฮูก Next Beyond">
        </article>

        <nav class="mobile-quick-grid" aria-label="เมนูลัด">
          <a href="tests.php"><span>▣</span><b>ทำข้อสอบ</b></a>
          <a href="my-tests.php"><span>▥</span><b>ผลการเรียน</b></a>
          <a href="roadmap.php"><span>◎</span><b>เป้าหมาย</b></a>
          <a href="score-calculator.php"><span>▦</span><b>TCAS</b></a>
        </nav>

        <div class="mobile-section-heading"><h2>ภารกิจวันนี้</h2><a href="roadmap.php">ดูทั้งหมด</a></div>
        <div class="mobile-task-list">
          <?php if (!$mobileMissions): ?>
            <a class="mobile-task" href="roadmap.php"><span class="mobile-task-check"></span><span><b>เลือก Study Roadmap</b><small>วางแผนการเรียนให้ตรงกับเป้าหมาย</small></span><em>เริ่มเลย</em></a>
          <?php else: foreach (array_slice($mobileMissions, 0, 2) as $mission):
            $isMissionDone = in_array($mission['progress_status'], ['completed', 'exempted'], true);
            $missionUrl = 'roadmap.php';
            if (!empty($mission['ref_lesson_id'])) $missionUrl = '../lesson.php?id=' . (int)$mission['ref_lesson_id'];
            elseif (!empty($mission['ref_exam_id'])) $missionUrl = 'take-test.php?id=' . (int)$mission['ref_exam_id'];
          ?>
            <a class="mobile-task <?= $isMissionDone ? 'done' : '' ?>" href="<?= htmlspecialchars($missionUrl) ?>">
              <span class="mobile-task-check"><?= $isMissionDone ? '✓' : '' ?></span>
              <span><b><?= htmlspecialchars($mission['title']) ?></b><small><?= htmlspecialchars($mission['subject'] ?: 'ภารกิจใน Roadmap') ?></small></span>
              <em><?= (int)$mission['points_reward'] > 0 ? '+' . (int)$mission['points_reward'] . ' NC' : 'ไปต่อ' ?></em>
            </a>
          <?php endforeach; endif; ?>
        </div>

        <div class="mobile-week-card">
          <div><span>สถิติการเรียนรายสัปดาห์</span><strong><?= count(array_filter($activeWeekDays)) ?> วัน</strong></div>
          <div class="mobile-week-days">
            <?php foreach ($weekDayLabels as $index => $label): ?><span class="<?= $activeWeekDays[$index] ? 'active' : '' ?>"><?= $label ?></span><?php endforeach; ?>
          </div>
        </div>
      </section>

      <div class="student-home-desktop">

      <!-- Welcome Banner -->
      <div class="student-home-hero mb-6 rounded-xl bg-navy-950 p-7 text-white border border-[#17304f]">
        <div class="flex items-end justify-between gap-6 flex-wrap">
          <div>
          <p class="student-kicker text-[#b9c5d7] mb-2">Student overview</p>
          <h2 class="text-[26px] font-bold mb-1"><?= htmlspecialchars($displayName) ?></h2>
          <p class="text-[13px] text-[#b9c5d7]">ติดตามบทเรียน ข้อสอบ และความก้าวหน้าของคุณ</p>
          </div>
          <div class="flex flex-wrap gap-3">
            <a href="tests.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-pink-500 rounded-xl font-bold text-[14px] hover:bg-pink-600 transition-colors shadow-[0_4px_14px_rgba(231,45,130,0.4)]">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
              ทำข้อสอบ
            </a>
            <a href="my-courses.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-transparent border border-[#53647d] rounded-lg font-bold text-[14px] hover:bg-white/10 transition-colors">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
              คอร์สของฉัน
            </a>
          </div>
        </div>
      </div>

      <!-- PHASE 3: THREE DISTINCT LEARNING METRICS (Section 53-54) -->
      <?php if ($featuredCourse): ?>
        <div class="mb-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
          <!-- Metric 1: Completion -->
          <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-2">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
              <span>ความคืบหน้าคอร์ส (Completion)</span>
              <span class="text-blue-600 font-extrabold"><?= $metricCompletionPct ?>%</span>
            </div>
            <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-blue-500 rounded-full" style="width: <?= min(100, $metricCompletionPct) ?>%"></div>
            </div>
            <p class="text-[11px] text-slate-400">สัดส่วนบทเรียนและภารกิจที่เรียนจบแล้ว</p>
          </div>

          <!-- Metric 2: Performance -->
          <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-2">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
              <span>คะแนนเฉลี่ย (Performance)</span>
              <span class="text-pink-600 font-extrabold"><?= $metricPerformancePct ?>%</span>
            </div>
            <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-pink-500 rounded-full" style="width: <?= min(100, $metricPerformancePct) ?>%"></div>
            </div>
            <p class="text-[11px] text-slate-400">ผลคะแนนจากการสอบและการทำแบบฝึกหัด</p>
          </div>

          <!-- Metric 3: Mastery -->
          <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-2">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
              <span>ระดับความเชี่ยวชาญ (Mastery)</span>
              <span class="text-emerald-600 font-extrabold"><?= $metricMasteryPct ?>%</span>
            </div>
            <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-emerald-500 rounded-full" style="width: <?= min(100, $metricMasteryPct) ?>%"></div>
            </div>
            <p class="text-[11px] text-slate-400">ความเข้าใจในแต่ละ Topic จากหลักฐานจริง</p>
          </div>
        </div>
      <?php endif; ?>

      <!-- PHASE 2: UPCOMING CLASS CARD ─────────────────────────────────── -->
      <?php if ($nextClass): ?>
        <?php
          $evDt    = new DateTimeImmutable($nextClass['event_date'] . ' ' . $nextClass['start_time']);
          $now     = new DateTimeImmutable();
          $diff    = $now->diff($evDt);
          $totalH  = $diff->days * 24 + $diff->h;
          $isSoon  = $totalH <= 2;
          $isActive = ($nextClass['session_status'] === 'active');
          $thDays  = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัส','ศุกร์','เสาร์'];
          $thMons  = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
          $dayLabel = $thDays[(int)(new DateTimeImmutable($nextClass['event_date']))->format('w')];
          $dateLabel = $dayLabel . 'ที่ ' . (new DateTimeImmutable($nextClass['event_date']))->format('j') . ' ' . $thMons[(int)(new DateTimeImmutable($nextClass['event_date']))->format('n')];
          $countdownLabel = $isSoon ? '⏰ กำลังจะเริ่ม!' : 'อีก ' . ($diff->days > 0 ? $diff->days . ' วัน ' : '') . $diff->h . ' ชม.';
          $accentColor = $isActive ? '#10b981' : ($isSoon ? '#f59e0b' : '#6366f1');
          $topicCount  = count($nextClass['topics']);
        ?>
        <div class="mb-6 rounded-2xl overflow-hidden shadow-lg border" style="border-color: <?= htmlspecialchars($accentColor) ?>33; background: linear-gradient(135deg, #fff 0%, #f8f4ff 100%);">
          <?php if ($isActive): ?>
            <div class="px-5 py-2 text-xs font-800 flex items-center gap-2" style="background:#10b981; color:#fff;">
              <span class="inline-block w-2 h-2 bg-white rounded-full animate-pulse"></span>
              🔴 ห้องเรียนสดเปิดอยู่แล้ว — PIN: <?= htmlspecialchars($nextClass['session_pin'] ?? '') ?>
            </div>
          <?php elseif ($isSoon): ?>
            <div class="px-5 py-2 text-xs font-800" style="background:#f59e0b; color:#fff;">⏰ คลาสจะเริ่มเร็วๆ นี้!</div>
          <?php endif; ?>
          <div class="p-5 flex items-start justify-between gap-4 flex-wrap">
            <div class="flex-1 min-w-0">
              <p class="text-xs font-700 mb-1" style="color:<?= htmlspecialchars($accentColor) ?>">
                📅 คลาสถัดไป · <?= htmlspecialchars($countdownLabel) ?>
              </p>
              <h3 class="font-900 text-navy-950 text-lg leading-tight mb-1 truncate">
                <?= htmlspecialchars($nextClass['title']) ?>
              </h3>
              <p class="text-sm text-slate-500 font-600">
                <?= htmlspecialchars($dateLabel) ?> · <?= htmlspecialchars($nextClass['start_time']) ?>–<?= htmlspecialchars($nextClass['end_time']) ?> น.
                <?php if ($nextClass['course_title']): ?> · 📚 <?= htmlspecialchars($nextClass['course_title']) ?><?php endif; ?>
              </p>
              <?php if ($nextClass['teacher_name']): ?>
                <p class="text-xs text-slate-400 mt-1">👩‍🏫 <?= htmlspecialchars($nextClass['teacher_name']) ?></p>
              <?php endif; ?>
              <?php if ($topicCount > 0): ?>
                <div class="flex flex-wrap gap-1.5 mt-2">
                  <?php foreach (array_slice($nextClass['topics'], 0, 3) as $t): ?>
                    <span class="px-2 py-0.5 rounded-full text-xs font-700" style="background:<?= htmlspecialchars($accentColor) ?>18; color:<?= htmlspecialchars($accentColor) ?>;"><?= htmlspecialchars($t['topic_name']) ?></span>
                  <?php endforeach; ?>
                  <?php if ($topicCount > 3): ?>
                    <span class="px-2 py-0.5 rounded-full text-xs font-700 bg-slate-100 text-slate-500">+<?= $topicCount - 3 ?> หัวข้อ</span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if ($nextClass['readiness_pct'] > 0): ?>
                <div class="flex items-center gap-2 mt-2">
                  <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden" style="max-width:100px">
                    <div class="h-full rounded-full" style="width:<?= $nextClass['readiness_pct'] ?>%; background:<?= htmlspecialchars($accentColor) ?>;"></div>
                  </div>
                  <span class="text-xs text-slate-400 font-600">ทบทวนแล้ว <?= $nextClass['readiness_pct'] ?>%</span>
                </div>
              <?php endif; ?>
            </div>
            <div class="flex flex-col gap-2 flex-shrink-0">
              <?php if ($isActive && !empty($nextClass['session_pin'])): ?>
                <a href="live-session.php?pin=<?= urlencode($nextClass['session_pin']) ?>"
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-800 text-sm text-white"
                   style="background:linear-gradient(135deg,#10b981,#059669); box-shadow:0 6px 18px rgba(16,185,129,.3);">
                  🎓 เข้าห้องเรียน
                </a>
              <?php endif; ?>
              <a href="get-ready.php?event_id=<?= $nextClass['id'] ?>"
                 class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-800 text-sm border"
                 style="border-color:<?= htmlspecialchars($accentColor) ?>; color:<?= htmlspecialchars($accentColor) ?>; background:<?= htmlspecialchars($accentColor) ?>0d;">
                📋 เตรียมตัว
              </a>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- NEXT ACTION (เรียนอะไรต่อ? — Phase 1 Core Integration) -->
      <?php if ($primaryNextAction): ?>

        <div class="mb-8 rounded-2xl bg-gradient-to-r from-navy-950 via-[#0d223d] to-navy-950 p-6 text-white border border-[#1e3a5f] shadow-lg relative overflow-hidden">
          <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-pink-500/10 rounded-full blur-3xl pointer-events-none"></div>

          <div class="flex items-center justify-between gap-4 flex-wrap mb-4 pb-4 border-b border-white/10">
            <div class="flex items-center gap-3">
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black tracking-wide bg-pink-500/20 text-pink-400 border border-pink-500/30">
                <span class="w-2 h-2 rounded-full bg-pink-500 animate-pulse"></span>
                เรียนอะไรต่อ? (NEXT ACTION)
              </span>
              <span class="text-xs text-slate-300">ระบบประมวลผลจากเป้าหมายและเส้นทางการเรียนของคุณ</span>
            </div>
            <a href="learning-path.php" class="text-xs font-bold text-pink-400 hover:text-pink-300 flex items-center gap-1">
              <span>ดูแผนการเรียนทั้งหมด</span>
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
          </div>

          <div class="flex items-center justify-between gap-6 flex-wrap">
            <div class="space-y-1.5 flex-1 min-w-[280px]">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-blue-500/20 text-blue-300 border border-blue-500/30">
                  <?= htmlspecialchars($primaryNextAction['course_title'] ?? $primaryNextAction['course_subject'] ?? 'คอร์สเรียน') ?>
                </span>
                <?php if (!empty($primaryNextAction['topic'])): ?>
                  <span class="text-xs text-slate-400">· หัวข้อ: <?= htmlspecialchars($primaryNextAction['topic']) ?></span>
                <?php endif; ?>
              </div>
              <h3 class="text-xl md:text-2xl font-black text-white tracking-tight">
                <?= htmlspecialchars($primaryNextAction['title']) ?>
              </h3>
              <p class="text-xs text-slate-300">
                <?= $primaryNextAction['type'] === 'roadmap_task' ? 'ภารกิจสำคัญตาม Study Roadmap ที่ต้องทำให้สำเร็จ' : 'บทเรียนต่อเนื่องในคอร์สของคุณ' ?>
              </p>
            </div>

            <div class="flex items-center gap-3 shrink-0">
              <a href="<?= htmlspecialchars(resolveStudentActionUrl($primaryNextAction['action_url'])) ?>"
                 class="h-12 px-7 rounded-xl bg-gradient-to-r from-pink-500 to-rose-500 text-white font-black text-sm flex items-center gap-2.5 shadow-[0_4px_20px_rgba(231,45,130,0.4)] hover:brightness-110 active:scale-95 transition-all">
                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                <span>เรียนต่อ</span>
              </a>
            </div>
          </div>

          <!-- Multi-Course Next Actions (If enrolled in more than 1 course) -->
          <?php if (count($studentUpcomingList) > 1): ?>
            <div class="mt-5 pt-4 border-t border-white/10">
              <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2.5">
                ภารกิจถัดไปแยกตามคอร์สที่คุณลงทะเบียน (<?= count($studentUpcomingList) ?> วิชา)
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <?php foreach ($studentUpcomingList as $upcoming): ?>
                  <?php $isPrimary = ((int)$upcoming['course_id'] === (int)$primaryNextAction['course_id']); ?>
                  <div class="p-3 rounded-xl <?= $isPrimary ? 'bg-white/10 border-pink-500/40' : 'bg-white/5 border-white/10' ?> border flex flex-col justify-between gap-2">
                    <div>
                      <div class="flex items-center justify-between text-[11px] mb-1">
                        <span class="font-bold text-white"><?= htmlspecialchars($upcoming['course_title']) ?></span>
                        <?php if ($upcoming['current_mastery'] !== null): ?>
                          <span class="text-pink-400 font-bold"><?= round((float)$upcoming['current_mastery']) ?>% Mastery</span>
                        <?php endif; ?>
                      </div>
                      <div class="text-xs text-slate-300 font-medium truncate">
                        <?= htmlspecialchars($upcoming['next_title']) ?>
                      </div>
                      <?php if (!empty($upcoming['goal_name']) && $upcoming['goal_name'] !== 'ไม่มีเป้าหมาย'): ?>
                        <div class="text-[10px] text-slate-400 mt-1 flex items-center gap-1">
                          <span>🎯</span>
                          <span class="truncate"><?= htmlspecialchars($upcoming['goal_name']) ?></span>
                          <?php if ($upcoming['target_score'] !== null): ?>
                            <span class="text-amber-300 font-bold">(<?= (float)$upcoming['target_score'] ?>)</span>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </div>
                    <div class="pt-1 flex items-center justify-between text-[11px]">
                      <span class="text-slate-400 text-[10px]">
                        <?= $isPrimary ? '⭐ ภารกิจหลักตอนนี้' : 'คอร์สคู่ขนาน' ?>
                      </span>
                      <a href="<?= htmlspecialchars(resolveStudentActionUrl($upcoming['action_url'])) ?>" class="font-bold text-pink-400 hover:text-pink-300">
                        <?= $isPrimary ? 'ทำเลย →' : 'เข้าเรียน →' ?>
                      </a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- PHASE 3: งานที่ต้องทำ (STUDENT ASSIGNMENTS — WORKSHEET, HOMEWORK, PRACTICE, POST-TEST) -->
      <?php if (!empty($studentAssignments)): ?>
        <div class="mb-8 rounded-3xl bg-white border border-slate-200 p-6 shadow-xs space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3 flex-wrap gap-2">
            <div>
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-pink-500"></span>
                <h3 class="text-lg font-black text-navy-950">งานที่ต้องทำ (Assignments & Practice)</h3>
              </div>
              <p class="text-xs text-slate-500 mt-0.5">ใบงาน แบบฝึกหัด การบ้าน และแบบทดสอบหลังเรียนที่ได้รับมอบหมาย</p>
            </div>
            <span class="px-2.5 py-1 rounded-full bg-pink-50 text-pink-700 text-xs font-bold">
              <?= count($studentAssignments) ?> รายการ
            </span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($studentAssignments as $assign): ?>
              <?php
                $aId = (int)$assign['id'];
                $actType = (string)($assign['activity_type'] ?? 'worksheet');
                $subStatus = (string)($assign['submission_status'] ?? 'not_started');

                $badgeInfo = match($actType) {
                    'worksheet' => ['label' => 'ใบงาน (Worksheet)', 'bg' => 'bg-sky-50 text-sky-700 border-sky-200'],
                    'practice'  => ['label' => 'ฝึกฝน (Practice)',   'bg' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
                    'homework'  => ['label' => 'การบ้าน (Homework)',  'bg' => 'bg-amber-50 text-amber-800 border-amber-200'],
                    'posttest'  => ['label' => 'แบบทดสอบหลังเรียน (Post-Test)', 'bg' => 'bg-purple-50 text-purple-700 border-purple-200'],
                    'remediation' => ['label' => 'แนะนำให้ทบทวน', 'bg' => 'bg-pink-50 text-pink-700 border-pink-200'],
                    'mastery_check' => ['label' => 'ตรวจความเข้าใจ', 'bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                    default     => ['label' => 'กิจกรรม', 'bg' => 'bg-slate-50 text-slate-700 border-slate-200'],
                };

                $statusBadge = match($subStatus) {
                    'in_progress' => ['label' => 'กำลังทำ', 'class' => 'bg-amber-100 text-amber-800'],
                    'submitted', 'completed' => ['label' => 'ส่งแล้ว', 'class' => 'bg-emerald-100 text-emerald-800'],
                    'late'        => ['label' => 'ส่งช้า', 'class' => 'bg-orange-100 text-orange-800'],
                    'overdue'     => ['label' => 'เลยกำหนด', 'class' => 'bg-rose-100 text-rose-800'],
                    default       => ['label' => 'ยังไม่เริ่ม', 'class' => 'bg-slate-100 text-slate-700'],
                };

                $btnInfo = match(true) {
                    in_array($subStatus, ['submitted', 'completed'], true) => [
                        'text' => 'ดูผลลัพธ์',
                        'class' => 'bg-slate-100 hover:bg-slate-200 text-slate-800'
                    ],
                    $subStatus === 'in_progress' => [
                        'text' => 'ทำต่อ',
                        'class' => 'bg-amber-500 hover:bg-amber-600 text-white shadow-xs'
                    ],
                    $actType === 'posttest' => [
                        'text' => 'เริ่มทดสอบ',
                        'class' => 'bg-purple-600 hover:bg-purple-700 text-white shadow-xs'
                    ],
                    $actType === 'mastery_check' => [
                        'text' => 'เริ่มตรวจความเข้าใจ',
                        'class' => 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs'
                    ],
                    default => [
                        'text' => 'เริ่มทำ',
                        'class' => 'bg-pink-500 hover:bg-pink-600 text-white shadow-xs'
                    ],
                };

                $dueStr = 'ไม่มีกำหนดส่ง';
                if (!empty($assign['due_date'])) {
                    $dueTime = strtotime($assign['due_date']);
                    $dueStr = 'Due: ' . date('d M', $dueTime);
                    if (date('Y-m-d', $dueTime) === date('Y-m-d')) {
                        $dueStr = 'Due: Tonight';
                    }
                }
              ?>
              <div class="p-4 rounded-2xl border border-slate-200/90 bg-white hover:border-pink-300 transition-all flex flex-col justify-between space-y-3 shadow-2xs">
                <div class="space-y-2">
                  <div class="flex items-center justify-between gap-2">
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold border <?= $badgeInfo['bg'] ?>">
                      <?= $badgeInfo['label'] ?>
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold <?= $statusBadge['class'] ?>">
                      <?= $statusBadge['label'] ?>
                    </span>
                  </div>

                  <div>
                    <h4 class="font-bold text-sm text-navy-950 line-clamp-2">
                      <?= htmlspecialchars($assign['title']) ?>
                    </h4>
                    <p class="text-[11px] text-slate-400 mt-0.5 truncate">
                      <?= htmlspecialchars($assign['course_title'] ?? $assign['subject'] ?? '') ?>
                      <?php if (!empty($assign['topic_name'] ?? $assign['topic'])): ?>
                        · <?= htmlspecialchars($assign['topic_name'] ?? $assign['topic']) ?>
                      <?php endif; ?>
                    </p>
                    <?php if ($actType === 'remediation'): ?>
                      <p class="text-[11px] text-pink-600 mt-1 font-semibold">หัวข้อนี้ยังต้องฝึกเพิ่มเติม · <?= (int)($assign['question_count'] ?? 0) ?> ข้อ · ประมาณ <?= max(1, (int)ceil(((int)($assign['question_count'] ?? 0)) * 1.2)) ?> นาที</p>
                    <?php endif; ?>
                    <?php if ($actType === 'mastery_check'): ?>
                      <p class="text-[11px] text-emerald-700 mt-1 font-semibold">ลองใช้ความเข้าใจกับโจทย์ชุดใหม่ · ผลนี้ช่วยวางก้าวถัดไปของคุณ</p>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
                  <span class="text-[11px] font-semibold text-slate-500">
                    <?= htmlspecialchars($dueStr) ?>
                  </span>
                  <a href="activity.php?assignment_id=<?= $aId ?>"
                     class="px-4 py-1.5 rounded-xl text-xs font-extrabold transition-all <?= $btnInfo['class'] ?>">
                    [ <?= $btnInfo['text'] ?> ]
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Stats Cards -->
      <div class="student-home-stats grid grid-cols-3 gap-4 mb-8 max-[700px]:grid-cols-1">
        <div class="bg-white rounded-[18px] border border-[#e8ecf2] p-5 shadow-[0_2px_12px_rgba(15,42,83,0.04)]">
          <div class="flex items-center justify-between mb-3">
            <span class="text-[12px] font-bold text-[#65738a] uppercase tracking-wide">คะแนนเฉลี่ย</span>
            <div class="w-9 h-9 rounded-xl bg-pink-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
          </div>
          <div class="text-[32px] font-black text-navy-950"><?= $avgScore > 0 ? $avgScore . '%' : '—' ?></div>
          <div class="text-[12px] text-[#65738a] mt-1">จาก <?= $attemptCount ?> ครั้ง</div>
        </div>
        <div class="bg-white rounded-[18px] border border-[#e8ecf2] p-5 shadow-[0_2px_12px_rgba(15,42,83,0.04)]">
          <div class="flex items-center justify-between mb-3">
            <span class="text-[12px] font-bold text-[#65738a] uppercase tracking-wide">ข้อสอบที่ทำ</span>
            <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            </div>
          </div>
          <div class="text-[32px] font-black text-navy-950"><?= $attemptCount ?></div>
          <div class="text-[12px] text-[#65738a] mt-1">ชุดข้อสอบ</div>
        </div>
        <div class="bg-white rounded-[18px] border border-[#e8ecf2] p-5 shadow-[0_2px_12px_rgba(15,42,83,0.04)]">
          <div class="flex items-center justify-between mb-3">
            <span class="text-[12px] font-bold text-[#65738a] uppercase tracking-wide">คอร์สที่เรียน</span>
            <div class="w-9 h-9 rounded-xl bg-green-50 flex items-center justify-center">
              <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
          </div>
          <div class="text-[32px] font-black text-navy-950"><?= $enrolledCount ?></div>
          <div class="text-[12px] text-[#65738a] mt-1">คอร์สที่ลงทะเบียน</div>
        </div>
      </div>

      <div class="grid grid-cols-5 gap-6 max-[1100px]:grid-cols-1">

        <!-- Available Exams (left, wider) -->
        <div class="col-span-3">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-[16px] font-bold text-navy-950">ข้อสอบที่พร้อมทำ</h3>
            <a href="tests.php" class="text-[13px] font-bold text-pink-500 hover:text-pink-600">ดูทั้งหมด →</a>
          </div>
          <?php if (empty($availableExams)): ?>
            <div class="bg-white rounded-[18px] border border-[#e8ecf2] p-10 text-center text-[#65738a]">
              <svg class="w-12 h-12 mx-auto mb-3 text-[#dce4ef]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
              <p class="font-medium">ยังไม่มีข้อสอบที่เปิดอยู่ในขณะนี้</p>
            </div>
          <?php else: ?>
            <div class="space-y-3">
              <?php foreach ($availableExams as $exam): ?>
                <?php
                  $typeLabel = ['quiz' => 'Quiz', 'placement' => 'Placement', 'pretest' => 'Pre-test', 'posttest' => 'Post-test'][$exam['type']] ?? $exam['type'];
                  $typeBg    = ['quiz' => 'bg-blue-50 text-blue-600', 'placement' => 'bg-purple-50 text-purple-600', 'pretest' => 'bg-green-50 text-green-600', 'posttest' => 'bg-orange-50 text-orange-600'][$exam['type']] ?? 'bg-[#f4f7fb] text-[#65738a]';
                ?>
                <div class="bg-white rounded-[16px] border border-[#e8ecf2] p-4 flex items-center gap-4 hover:border-pink-200 hover:shadow-[0_4px_16px_rgba(231,45,130,0.08)] transition-all group">
                  <div class="w-11 h-11 rounded-[12px] bg-pink-50 border border-pink-100 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="text-[14px] font-bold text-navy-950 truncate group-hover:text-pink-500 transition-colors"><?= htmlspecialchars($exam['title']) ?></div>
                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                      <span class="text-[11px] px-2 py-0.5 rounded-md font-bold <?= $typeBg ?>"><?= $typeLabel ?></span>
                      <span class="text-[11px] text-[#65738a]"><?= (int)$exam['question_count'] ?> ข้อ</span>
                      <?php if ($exam['time_limit_minutes']): ?>
                        <span class="text-[11px] text-[#65738a]">⏱ <?= (int)$exam['time_limit_minutes'] ?> นาที</span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <a href="take-test.php?id=<?= $exam['id'] ?>" class="shrink-0 h-9 px-4 rounded-[10px] bg-pink-500 text-white text-[13px] font-bold flex items-center hover:bg-pink-600 transition-colors shadow-[0_4px_10px_rgba(231,45,130,0.2)]">
                    เริ่มทำ
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Recent Attempts (right) -->
        <div class="col-span-2">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-[16px] font-bold text-navy-950">ผลล่าสุด</h3>
            <a href="my-tests.php" class="text-[13px] font-bold text-pink-500 hover:text-pink-600">ดูทั้งหมด →</a>
          </div>
          <div class="bg-white rounded-[18px] border border-[#e8ecf2] overflow-hidden">
            <?php if (empty($recentAttempts)): ?>
              <div class="p-8 text-center text-[#65738a]">
                <p class="text-[13px]">ยังไม่มีประวัติการทำข้อสอบ</p>
                <a href="tests.php" class="mt-3 inline-block text-[13px] font-bold text-pink-500">เริ่มทำข้อสอบแรก →</a>
              </div>
            <?php else: ?>
              <div class="divide-y divide-[#f1f5f9]">
                <?php foreach ($recentAttempts as $att): ?>
                  <?php
                    $pct = $att['total_questions'] > 0
                      ? round(($att['correct_count'] / $att['total_questions']) * 100)
                      : ($att['score'] ?? 0);
                    $scoreColor = $pct >= 80 ? 'text-green-600' : ($pct >= 60 ? 'text-blue-600' : 'text-red-500');
                    $date = $att['completed_at'] ? date('d/m/Y', strtotime($att['completed_at'])) : '—';
                  ?>
                  <a href="test-result.php?id=<?= $att['id'] ?>" class="flex items-center gap-3 px-4 py-3.5 hover:bg-[#f8fafc] transition-colors">
                    <div class="flex-1 min-w-0">
                      <div class="text-[13px] font-bold text-navy-950 truncate"><?= htmlspecialchars($att['title']) ?></div>
                      <div class="text-[11px] text-[#65738a] mt-0.5"><?= htmlspecialchars($att['subject'] ?? '—') ?> · <?= $date ?></div>
                    </div>
                    <div class="text-right shrink-0">
                      <div class="text-[16px] font-black <?= $scoreColor ?>"><?= $pct ?>%</div>
                      <div class="text-[11px] text-[#65738a]"><?= $att['correct_count'] ?>/<?= $att['total_questions'] ?> ข้อ</div>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Weekly Schedule Widget (Combined across all active enrollments) -->
          <?php if (!empty($weeklySchedule)): ?>
            <div class="mt-6 bg-white rounded-[16px] border border-[#e8ecf2] p-4 shadow-sm">
              <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100">
                <h3 class="text-[15px] font-bold text-navy-950 flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                  <span>ตารางเรียนประจำสัปดาห์</span>
                </h3>
                <span class="text-[11px] text-slate-400">จากทุกวิชาที่ลงทะเบียน</span>
              </div>
              <div class="space-y-2.5">
                <?php foreach ($weeklySchedule as $dayName => $dayClasses): ?>
                  <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100/80">
                    <div class="text-[11px] font-bold text-pink-600 uppercase tracking-wider mb-1.5"><?= htmlspecialchars($dayName) ?></div>
                    <div class="space-y-1.5">
                      <?php foreach ($dayClasses as $cls): ?>
                        <div class="flex items-start justify-between gap-2 text-xs">
                          <div>
                            <div class="font-bold text-navy-950"><?= htmlspecialchars($cls['course_title']) ?></div>
                            <div class="text-[11px] text-slate-500 flex items-center gap-1.5">
                              <span class="font-semibold text-blue-600"><?= htmlspecialchars($cls['class_group_name']) ?></span>
                              <?php if (!empty($cls['teacher_name'])): ?>
                                <span>· <?= htmlspecialchars($cls['teacher_name']) ?></span>
                              <?php endif; ?>
                            </div>
                          </div>
                          <span class="shrink-0 px-2 py-0.5 rounded-md bg-white border border-slate-200 font-bold text-[11px] text-navy-950">
                            <?= htmlspecialchars($cls['schedule_time'] ?: 'ตามตาราง') ?>
                          </span>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Enrolled Courses (below) -->
          <?php if (!empty($enrolledCourses)): ?>
            <div class="mt-6">
              <div class="flex items-center justify-between mb-3">
                <h3 class="text-[15px] font-bold text-navy-950">คอร์สของฉัน</h3>
                <a href="my-courses.php" class="text-[13px] font-bold text-pink-500">ดูทั้งหมด →</a>
              </div>
              <div class="space-y-3">
                <?php foreach ($enrolledCourses as $course): ?>
                  <a href="course.php?id=<?= (int)$course['id'] ?>" class="block bg-white rounded-[14px] border border-[#e8ecf2] p-4 hover:border-pink-300 transition-colors">
                    <div class="flex items-start justify-between gap-2 mb-2">
                      <div class="text-[13px] font-bold text-navy-950 truncate"><?= htmlspecialchars($course['title']) ?></div>
                      <?php if (!empty($course['class_group_name'])): ?>
                        <span class="shrink-0 px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200/60">
                          <?= htmlspecialchars($course['class_group_name']) ?>
                        </span>
                      <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2">
                      <div class="flex-1 h-2 bg-[#f1f5f9] rounded-full overflow-hidden">
                        <div class="h-full bg-pink-500 rounded-full transition-all" style="width:<?= min(100, (float)$course['progress_percent']) ?>%"></div>
                      </div>
                      <span class="text-[12px] font-bold text-[#65738a] shrink-0"><?= round((float)$course['progress_percent']) ?>%</span>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
      </div>
    </main>
    <?php include 'includes/bottom-nav.php'; ?>
  </div>
</div>
</body>
</html>
