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
            <?php if ($featuredCourse): ?>
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
      <div class="student-home-hero mb-8 rounded-xl bg-navy-950 p-7 text-white border border-[#17304f]">
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
