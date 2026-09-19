<?php
$pageTitle = 'คอร์สเรียน';
$currentPage = 'my-courses.php';
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/../admin/enrollments-service.php';

$enrollmentService = new EnrollmentService($pdo);

$courseId = (int)($_GET['id'] ?? 0);
$slug = trim((string)($_GET['slug'] ?? ''));

$course = null;
if ($courseId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
    $stmt->execute([$courseId]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($slug !== '') {
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE slug = ? OR title LIKE ? LIMIT 1');
    $stmt->execute([$slug, "%{$slug}%"]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($course) $courseId = (int)$course['id'];
}

if (!$course) {
    header('Location: my-courses.php');
    exit;
}

$pageTitle = $course['title'];

// Verify Access
$hasAccess = false;
$accessDetails = null;

if (($currentUser['role'] ?? '') === 'admin' || ($currentUser['role'] ?? '') === 'teacher') {
    $hasAccess = true;
} else {
    $accessDetails = $enrollmentService->checkAccess((int)$currentUser['id'], $courseId);
    $hasAccess = !empty($accessDetails['has_access']);
}

// Fetch Lessons if access granted
$lessons = [];
$classGroupInfo = null;
if ($hasAccess) {
    $stmtLessons = $pdo->prepare('
        SELECT l.*, COALESCE(lp.is_completed, 0) AS is_completed, lp.last_watched_at
        FROM lessons l
        LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ?
        WHERE l.course_id = ?
        ORDER BY l.sort_order ASC, l.id ASC
    ');
    $stmtLessons->execute([(int)$currentUser['id'], $courseId]);
    $lessons = $stmtLessons->fetchAll(PDO::FETCH_ASSOC);

    // Fetch student's assigned class group for this course
    if (!empty($accessDetails['enrollment']['class_group_id'])) {
        $cgId = (int)$accessDetails['enrollment']['class_group_id'];
        $stmtCg = $pdo->prepare("
            SELECT cg.*, CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
            FROM class_groups cg
            LEFT JOIN users u ON u.id = cg.teacher_id
            WHERE cg.id = ?
        ");
        $stmtCg->execute([$cgId]);
        $classGroupInfo = $stmtCg->fetch(PDO::FETCH_ASSOC);
    }
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
<body class="student-portal bg-[#f4f7fb] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col ml-[240px] max-[1024px]:ml-0 min-w-0">
    <?php include 'includes/topbar.php'; ?>

    <main class="p-8 max-[640px]:p-4 flex-1 max-w-[1400px] w-full mx-auto">

      <?php if (!$hasAccess): ?>
        <!-- Access Blocked UI -->
        <div class="min-h-[60vh] flex items-center justify-center p-4">
          <div class="max-w-[540px] w-full bg-white rounded-[28px] border border-slate-200/90 shadow-xl p-8 text-center animate-fade-in">
            <div class="w-20 h-20 rounded-3xl bg-rose-50 text-rose-500 mx-auto flex items-center justify-center mb-6 shadow-sm">
              <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15v2m0 0v2m0-2h2m-2 0H10m7-7V7a5 5 0 00-10 0v3M5 10h14a2 2 0 012 2v7a2 2 0 01-2 2H5a2 2 0 01-2-2v-7a2 2 0 012-2z"/></svg>
            </div>
            <span class="inline-block px-3 py-1 rounded-full bg-rose-100 text-rose-700 text-xs font-bold uppercase tracking-wider mb-3">
              จำกัดสิทธิ์การเข้าถึงรายบุคคล
            </span>
            <h1 class="text-2xl md:text-3xl font-black text-navy-950 mb-3 leading-tight">
              คุณยังไม่มีสิทธิ์เข้าเรียนคอร์สนี้
            </h1>
            <p class="text-slate-500 text-sm leading-relaxed mb-8">
              ขออภัย บัญชีของคุณยังไม่ได้รับสิทธิ์หรือหมดอายุการเข้าเรียนในคอร์ส <b><?= htmlspecialchars($course['title']) ?></b> หากคุณได้ลงทะเบียนหรือต้องการสอบถามรายละเอียด กรุณาติดต่อสถาบัน
            </p>
            <div class="flex flex-wrap items-center justify-center gap-3">
              <a href="my-courses.php" class="h-11 px-6 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-sm shadow-md shadow-pink-500/20 transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m7 7l-7-7 7-7"/></svg>
                <span>ดูคอร์สอื่นที่ลงทะเบียน</span>
              </a>
              <a href="../contact-us.php" class="h-11 px-6 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-sm transition-colors flex items-center gap-2">
                <span>ติดต่อสถาบัน</span>
              </a>
            </div>
          </div>
        </div>

      <?php else: ?>
        <!-- Full Course Content Hub -->
        <div class="space-y-6">
          <!-- Course Header Card -->
          <div class="bg-navy-950 text-white rounded-[24px] p-8 max-[640px]:p-5 relative overflow-hidden shadow-lg">
            <div class="relative z-10 grid grid-cols-[1fr_auto] gap-6 items-center max-[768px]:grid-cols-1">
              <div>
                <div class="flex flex-wrap items-center gap-2.5 mb-3">
                  <span class="px-3 py-1 rounded-full bg-pink-500/20 text-pink-400 border border-pink-500/30 text-xs font-black uppercase tracking-wider">
                    <?= htmlspecialchars($course['subject'] ?? 'วิชาเรียน') ?> · <?= htmlspecialchars($course['level'] ?? 'ม.ปลาย') ?>
                  </span>
                  <span class="px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs font-bold uppercase">
                    <?= !empty($accessDetails['enrollment']['status']) && $accessDetails['enrollment']['status'] === 'trial' ? 'สิทธิ์ทดลองเรียน (Trial)' : 'สิทธิ์การเรียนปกติ (Active)' ?>
                  </span>
                  <?php if ($classGroupInfo): ?>
                    <span class="px-3 py-1 rounded-full bg-blue-500/20 text-blue-300 border border-blue-500/30 text-xs font-bold">
                      กลุ่มเรียน: <?= htmlspecialchars($classGroupInfo['name']) ?>
                    </span>
                  <?php endif; ?>
                </div>

                <h1 class="text-3xl max-[640px]:text-2xl font-black mb-3 leading-tight"><?= htmlspecialchars($course['title']) ?></h1>
                <p class="text-slate-300 text-sm max-w-[680px] leading-relaxed mb-5">
                  <?= htmlspecialchars($course['description'] ?? 'เนื้อหาและแบบฝึกหัดพัฒนาความเข้าใจตามมาตรฐานหลักสูตร') ?>
                </p>

                <!-- Class Group & Schedule Details -->
                <?php if ($classGroupInfo): ?>
                  <div class="flex flex-wrap items-center gap-4 text-xs text-slate-300 bg-white/5 border border-white/10 p-3.5 rounded-xl max-w-[680px]">
                    <div class="flex items-center gap-2">
                      <svg class="w-4 h-4 text-pink-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                      <span>เวลาเรียน: <b><?= htmlspecialchars($classGroupInfo['schedule_day'] ?? '') ?> <?= htmlspecialchars($classGroupInfo['schedule_time'] ?? '') ?></b></span>
                    </div>
                    <?php if (!empty($classGroupInfo['teacher_name'])): ?>
                      <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span>ครูผู้สอน: <b><?= htmlspecialchars($classGroupInfo['teacher_name']) ?></b></span>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($classGroupInfo['room'])): ?>
                      <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <span>ห้อง: <b><?= htmlspecialchars($classGroupInfo['room']) ?></b></span>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Quick Action Box -->
              <div class="bg-white/10 backdrop-blur-sm border border-white/10 p-5 rounded-2xl flex flex-col gap-3 min-w-[220px]">
                <div class="text-xs text-slate-300">ความพร้อมการเรียน</div>
                <div class="text-lg font-black text-white"><?= count($lessons) ?> บทเรียน</div>
                <?php 
                  $firstLessonId = !empty($lessons[0]['id']) ? (int)$lessons[0]['id'] : 0;
                  $firstLessonUrl = $firstLessonId > 0 ? "../lesson.php?id={$firstLessonId}&course_id={$courseId}" : "#";
                ?>
                <a href="<?= htmlspecialchars($firstLessonUrl) ?>" class="h-10 px-5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-xs flex items-center justify-center gap-2 transition-all shadow-md">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                  <span>เข้าสู่ห้องเรียน</span>
                </a>
              </div>
            </div>
          </div>

          <!-- Lessons List -->
          <div class="bg-white rounded-[24px] border border-slate-200/80 p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
              <h2 class="text-lg font-bold text-navy-950 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-pink-500"></span>
                บทเรียนและคลิปการสอน (Lessons & EPs)
              </h2>
              <span class="text-xs text-slate-500">ทั้งหมด <?= count($lessons) ?> บท</span>
            </div>

            <?php if (empty($lessons)): ?>
              <div class="p-12 text-center text-slate-400">
                <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <div>ยังไม่มีบทเรียนในคอร์สนี้</div>
              </div>
            <?php else: ?>
              <div class="space-y-3">
                <?php foreach ($lessons as $idx => $ls): 
                  $isDone = !empty($ls['is_completed']);
                  $lsUrl = "../lesson.php?id=" . (int)$ls['id'] . "&course_id=" . $courseId;
                ?>
                  <div class="flex items-center justify-between gap-4 p-4 rounded-2xl border border-slate-200/80 hover:border-pink-300 hover:bg-slate-50/70 transition-all">
                    <div class="flex items-center gap-3.5">
                      <div class="w-9 h-9 rounded-xl <?= $isDone ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-600' ?> flex items-center justify-center font-black text-xs shrink-0">
                        <?= $isDone ? '✓' : ($idx + 1) ?>
                      </div>
                      <div>
                        <a href="<?= htmlspecialchars($lsUrl) ?>" class="font-bold text-navy-950 hover:text-pink-600 text-sm transition-colors">
                          <?= htmlspecialchars($ls['title']) ?>
                        </a>
                        <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-2">
                          <span><?= !empty($ls['duration_minutes']) ? (int)$ls['duration_minutes'] . ' นาที' : 'เนื้อหาออนไลน์' ?></span>
                          <?php if ($isDone): ?>
                            <span class="text-emerald-600 font-semibold">· เรียนจบแล้ว</span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <a href="<?= htmlspecialchars($lsUrl) ?>" class="h-9 px-4 rounded-xl bg-pink-50 hover:bg-pink-100 text-pink-600 font-bold text-xs flex items-center gap-1.5 transition-colors shrink-0">
                      <span><?= $isDone ? 'ทบทวน' : 'เข้าเรียน' ?></span>
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                    </a>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

    </main>
    <?php include 'includes/bottom-nav.php'; ?>
  </div>
</div>
</body>
</html>
