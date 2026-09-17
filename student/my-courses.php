<?php
$pageTitle = 'คอร์สของฉัน';
$currentPage = 'my-courses.php';
require_once __DIR__ . '/includes/guard.php';

$status = (string) ($_GET['status'] ?? '');
$allowedStatuses = ['active', 'completed', 'expired'];
$where = in_array($status, $allowedStatuses, true) ? ' AND en.status = :status' : '';
$stmt = $pdo->prepare("SELECT en.progress_percent, en.status, en.enrolled_at, en.expires_at,
                              c.id, c.title, c.subject, c.level, c.cover_image
                       FROM enrollments en
                       INNER JOIN courses c ON c.id = en.course_id
                       WHERE en.user_id = :user_id {$where}
                       ORDER BY en.enrolled_at DESC");
$params = [':user_id' => $currentUser['id']];
if ($where !== '') $params[':status'] = $status;
$stmt->execute($params);
$courses = $stmt->fetchAll();

$courseLessonStats = [];
$nextLessonByCourse = [];
if ($courses) {
    $courseIds = array_map(static fn(array $course): int => (int)$course['id'], $courses);
    $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
    $lessonStatsStmt = $pdo->prepare(
        "SELECT l.course_id, COUNT(l.id) AS total_lessons,
                SUM(CASE WHEN lp.is_completed = 1 THEN 1 ELSE 0 END) AS completed_lessons
         FROM lessons l
         LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ?
         WHERE l.course_id IN ({$placeholders}) GROUP BY l.course_id"
    );
    $lessonStatsStmt->execute(array_merge([(int)$currentUser['id']], $courseIds));
    foreach ($lessonStatsStmt->fetchAll() as $row) $courseLessonStats[(int)$row['course_id']] = $row;

    $nextLessonsStmt = $pdo->prepare(
        "SELECT l.course_id, l.id, l.title, l.sort_order
         FROM lessons l
         LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ?
         WHERE l.course_id IN ({$placeholders}) AND COALESCE(lp.is_completed, 0) = 0
         ORDER BY l.course_id, l.sort_order, l.id"
    );
    $nextLessonsStmt->execute(array_merge([(int)$currentUser['id']], $courseIds));
    foreach ($nextLessonsStmt->fetchAll() as $lesson) {
        $courseId = (int)$lesson['course_id'];
        if (!isset($nextLessonByCourse[$courseId])) $nextLessonByCourse[$courseId] = $lesson;
    }
}

$summaryStmt = $pdo->prepare("SELECT COUNT(*) AS total,
    SUM(status='active') AS active_count,
    SUM(status='completed') AS completed_count,
    COALESCE(AVG(CASE WHEN status='active' THEN progress_percent END),0) AS avg_progress
    FROM enrollments WHERE user_id=:user_id");
$summaryStmt->execute([':user_id' => $currentUser['id']]);
$summary = $summaryStmt->fetch() ?: [];
$filters = ['' => 'ทั้งหมด', 'active' => 'กำลังเรียน', 'completed' => 'เรียนจบ', 'expired' => 'หมดอายุ'];
$statusLabels = ['active' => 'กำลังเรียน', 'completed' => 'เรียนจบแล้ว', 'expired' => 'หมดอายุ'];
$mobileFilters = ['' => 'ทั้งหมด', 'active' => 'กำลังเรียน', 'completed' => 'เรียนจบ'];
$heroCourse = $courses[0] ?? null;
$mobileCourseList = count($courses) > 1 ? array_slice($courses, 1) : [];

function studentCourseCode(string $subject, string $title): string {
    $source = mb_strtolower($subject . ' ' . $title);
    foreach ([
        'คณิต' => 'MA', 'math' => 'MA', 'อังกฤษ' => 'EN', 'english' => 'EN',
        'เคมี' => 'CH', 'chem' => 'CH', 'ฟิสิกส์' => 'PH', 'physics' => 'PH',
        'ชีว' => 'BI', 'biology' => 'BI', 'วิทย' => 'SC', 'science' => 'SC',
    ] as $word => $code) if (str_contains($source, $word)) return $code;
    return mb_strtoupper(mb_substr(trim($subject ?: $title), 0, 2));
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> - Next Beyond</title>
  <link rel="stylesheet" href="../assets/css/output.css?v=<?= filemtime(__DIR__ . '/../assets/css/output.css') ?>">
  <link rel="stylesheet" href="../assets/css/student-portal.css?v=<?= filemtime(__DIR__ . '/../assets/css/student-portal.css') ?>">
</head>
<body class="student-portal bg-[#f4f7fb] text-navy-950">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col ml-[240px] max-[1024px]:ml-0 min-w-0">
    <?php include 'includes/topbar.php'; ?>
    <main class="p-8 max-[640px]:p-4">
      <section class="student-mobile-courses" aria-label="คอร์สของฉันบนมือถือ">
        <header class="mobile-courses-intro">
          <p>MY LEARNING</p>
          <h1>คอร์สของฉัน</h1>
          <span>เรียนต่อจากจุดล่าสุด</span>
        </header>

        <?php if ($heroCourse):
          $heroId = (int)$heroCourse['id'];
          $heroProgress = max(0, min(100, (int)round((float)$heroCourse['progress_percent'])));
          $heroStats = $courseLessonStats[$heroId] ?? ['total_lessons' => 0, 'completed_lessons' => 0];
          $heroNextLesson = $nextLessonByCourse[$heroId] ?? null;
          $heroUrl = $heroNextLesson ? '../lesson.php?id=' . (int)$heroNextLesson['id'] : '../course-details?id=' . $heroId;
        ?>
          <article class="mobile-course-hero">
            <div class="mobile-course-hero-copy">
              <span class="mobile-course-status"><?= htmlspecialchars($statusLabels[$heroCourse['status']] ?? $heroCourse['status']) ?></span>
              <h2><?= htmlspecialchars($heroCourse['title']) ?></h2>
              <p><?= (int)$heroStats['total_lessons'] > 0 ? 'เรียนแล้ว ' . (int)$heroStats['completed_lessons'] . ' จาก ' . (int)$heroStats['total_lessons'] . ' บท' : 'เรียนแล้ว ' . $heroProgress . '%' ?></p>
              <div class="mobile-course-progress"><i style="width:<?= $heroProgress ?>%"></i></div>
              <a href="<?= htmlspecialchars($heroUrl) ?>">▶ เรียนต่อ</a>
            </div>
            <img src="../assets/images/next-owl.png" alt="มาสคอตนกฮูก Next Beyond">
            <strong><?= htmlspecialchars(studentCourseCode((string)$heroCourse['subject'], (string)$heroCourse['title'])) ?></strong>
          </article>
        <?php else: ?>
          <article class="mobile-course-hero empty">
            <div class="mobile-course-hero-copy"><span class="mobile-course-status">เริ่มต้นเรียน</span><h2>ค้นหาคอร์สที่ใช่สำหรับคุณ</h2><p>เลือกเรียนตามเป้าหมายและระดับของคุณ</p><a href="courses.php">ดูคอร์สทั้งหมด</a></div>
            <img src="../assets/images/next-owl.png" alt="มาสคอตนกฮูก Next Beyond">
          </article>
        <?php endif; ?>

        <nav class="mobile-course-filters" aria-label="กรองคอร์ส">
          <?php foreach ($mobileFilters as $key => $label): ?><a href="?status=<?= urlencode($key) ?>" class="<?= $status === $key ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a><?php endforeach; ?>
        </nav>

        <div class="mobile-course-list">
          <?php foreach ($mobileCourseList as $course):
            $courseId = (int)$course['id'];
            $progress = max(0, min(100, (int)round((float)$course['progress_percent'])));
            $nextLesson = $nextLessonByCourse[$courseId] ?? null;
            $courseUrl = $nextLesson ? '../lesson.php?id=' . (int)$nextLesson['id'] : '../course-details?id=' . $courseId;
          ?>
            <a class="mobile-course-row" href="<?= htmlspecialchars($courseUrl) ?>">
              <span class="mobile-course-code"><?= htmlspecialchars(studentCourseCode((string)$course['subject'], (string)$course['title'])) ?></span>
              <span class="mobile-course-info"><b><?= htmlspecialchars($course['title']) ?></b><small><?= htmlspecialchars($course['subject'] ?: ($course['level'] ?: 'คอร์สออนไลน์')) ?></small><i><span style="width:<?= $progress ?>%"></span></i><em>เรียนแล้ว <?= $progress ?>%</em></span>
              <span class="mobile-course-arrow">›</span>
            </a>
          <?php endforeach; ?>
        </div>

        <a class="mobile-find-courses" href="courses.php">⌕&nbsp; ค้นหาคอร์สเพิ่มเติม</a>
      </section>

      <div class="student-courses-desktop">
      <section class="mb-8 border-b border-[#dce3ec] pb-7">
        <p class="student-kicker mb-2">My learning</p>
        <div class="flex items-end justify-between gap-5 flex-wrap">
          <div>
            <h2 class="text-[28px] font-bold text-navy-950">คอร์สของฉัน</h2>
            <p class="mt-2 text-[14px] text-[#65738a]">กลับมาเรียนต่อจากบทล่าสุดและติดตามความก้าวหน้าของคุณ</p>
          </div>
          <a href="courses.php" class="h-10 px-5 inline-flex items-center rounded-lg bg-pink-500 text-white text-[13px] font-bold hover:bg-pink-600">ค้นหาคอร์สเพิ่มเติม</a>
        </div>
      </section>

      <div class="grid grid-cols-3 gap-px bg-[#dfe5ed] border border-[#dfe5ed] rounded-xl overflow-hidden mb-7 max-[680px]:grid-cols-1">
        <div class="bg-white px-5 py-4"><div class="student-kicker">คอร์สทั้งหมด</div><div class="mt-1 text-[25px] font-bold"><?= (int)($summary['total'] ?? 0) ?></div></div>
        <div class="bg-white px-5 py-4"><div class="student-kicker">กำลังเรียน</div><div class="mt-1 text-[25px] font-bold"><?= (int)($summary['active_count'] ?? 0) ?></div></div>
        <div class="bg-white px-5 py-4"><div class="student-kicker">ความคืบหน้าเฉลี่ย</div><div class="mt-1 text-[25px] font-bold"><?= round((float)($summary['avg_progress'] ?? 0)) ?>%</div></div>
      </div>

      <nav class="flex items-center gap-1 mb-6 border-b border-[#dce3ec] overflow-x-auto" aria-label="กรองคอร์ส">
        <?php foreach ($filters as $key => $label): ?>
          <a href="?status=<?= urlencode($key) ?>" class="px-4 py-3 text-[13px] font-bold whitespace-nowrap border-b-2 <?= $status === $key ? 'border-pink-500 text-navy-950' : 'border-transparent text-[#718096] hover:text-navy-950' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </nav>

      <?php if (!$courses): ?>
        <section class="max-w-[680px] mx-auto mt-12 bg-white border border-[#dfe5ed] rounded-xl px-8 py-12 text-center">
          <div class="w-14 h-14 mx-auto mb-5 rounded-xl bg-[#edf2f7] flex items-center justify-center text-navy-950">
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
          </div>
          <h2 class="text-[20px] font-bold text-navy-950">ยังไม่มีคอร์ส<?= $status ? 'ในหมวดนี้' : 'ที่ลงทะเบียน' ?></h2>
          <p class="mt-2 text-[14px] leading-6 text-[#65738a] max-w-[430px] mx-auto">เลือกคอร์สที่เหมาะกับเป้าหมายของคุณ เมื่อสมัครแล้วคอร์สและความคืบหน้าจะแสดงที่หน้านี้</p>
          <a href="courses.php" class="mt-6 h-11 px-6 inline-flex items-center rounded-lg bg-pink-500 text-white text-[14px] font-bold hover:bg-pink-600">ดูคอร์สทั้งหมด</a>
        </section>
      <?php else: ?>
        <div class="grid grid-cols-3 gap-5 max-[1100px]:grid-cols-2 max-[680px]:grid-cols-1">
          <?php foreach ($courses as $course): $progress = min(100, (float)$course['progress_percent']); ?>
            <article class="bg-white border border-[#dfe5ed] rounded-xl overflow-hidden hover:border-[#b8c4d3]">
              <div class="h-28 bg-navy-950 p-5 flex flex-col justify-between text-white">
                <span class="text-[11px] uppercase tracking-[.12em] font-bold text-[#aebdd1]"><?= htmlspecialchars($course['subject'] ?: 'Course') ?></span>
                <div class="text-[13px] text-white/75"><?= htmlspecialchars($course['level'] ?: 'ทุกระดับ') ?></div>
              </div>
              <div class="p-5">
                <div class="flex items-start justify-between gap-3 mb-5">
                  <h3 class="text-[16px] font-bold leading-6"><?= htmlspecialchars($course['title']) ?></h3>
                  <span class="shrink-0 text-[11px] font-bold text-[#65738a]"><?= htmlspecialchars($statusLabels[$course['status']] ?? $course['status']) ?></span>
                </div>
                <div class="flex justify-between text-[12px] mb-2"><span class="text-[#65738a]">ความคืบหน้า</span><strong><?= round($progress) ?>%</strong></div>
                <div class="h-1.5 rounded-full bg-[#e8edf3] overflow-hidden"><div class="h-full bg-pink-500" style="width:<?= $progress ?>%"></div></div>
                <a href="../course-details?id=<?= (int)$course['id'] ?>" class="mt-5 h-10 w-full rounded-lg border border-[#cfd7e2] flex items-center justify-center text-[13px] font-bold hover:bg-[#f5f7fa]">ดูรายละเอียดและเรียนต่อ</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      </div>
    </main>
    <?php include 'includes/bottom-nav.php'; ?>
  </div>
</div>
</body>
</html>
