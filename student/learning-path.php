<?php
$pageTitle = 'เส้นทางการเรียน';
$currentPage = 'learning-path.php';
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/../includes/roadmap-service.php';
require_once __DIR__ . '/../includes/learning-journey-service.php';
$journeyService = new LearningJourneyService($pdo);

function resolveStudentActionUrl(?string $url): string {
    if (empty($url)) return 'my-courses.php';
    if (str_starts_with($url, 'lesson.php') || str_starts_with($url, 'course.php')) {
        return '../' . $url;
    }
    return $url;
}

$stmt = $pdo->prepare('
    SELECT lp.*, c.title AS course_title, c.subject AS course_subject
    FROM learning_paths lp
    LEFT JOIN courses c ON c.id = lp.course_id
    WHERE lp.user_id = :user_id
    ORDER BY lp.updated_at DESC, lp.id DESC
');
$stmt->execute([':user_id' => $currentUser['id']]);
$paths = $stmt->fetchAll();

$pathCount = count($paths);
$totalHours = 0;
$totalProgress = 0.0;
foreach ($paths as $path) {
    $totalHours += (int) ($path['hours_per_week'] ?? 0);
    $totalProgress += min(100, max(0, (float) ($path['progress_percent'] ?? 0)));
}
$averageProgress = $pathCount > 0 ? (int) round($totalProgress / $pathCount) : 0;

function learningPathSteps(array $path): array
{
    $decoded = json_decode((string) ($path['plan_data'] ?? ''), true);
    if (!is_array($decoded)) return [];
    if (isset($decoded['steps']) && is_array($decoded['steps'])) return array_values($decoded['steps']);
    return array_is_list($decoded) ? $decoded : [];
}

function learningStepTitle($step): string
{
    if (!is_array($step)) return trim((string) $step);
    return trim((string) ($step['title'] ?? $step['name'] ?? $step['topic'] ?? 'ขั้นตอนการเรียน'));
}

// Mobile Learning Journey ใช้ Roadmap ที่นักเรียนเลือกและข้อมูลภารกิจจริง
$studentRoadmaps = getStudentRoadmaps($pdo, (int)$currentUser['id']);
$enrolledRoadmaps = array_values(array_filter(
    $studentRoadmaps,
    static fn(array $roadmap): bool => $roadmap['enroll_status'] !== 'not_enrolled'
));
$requestedRoadmapId = (int)($_GET['roadmap_id'] ?? 0);
$mobileRoadmap = null;
foreach ($enrolledRoadmaps as $roadmap) {
    if (($requestedRoadmapId > 0 && (int)$roadmap['id'] === $requestedRoadmapId)
        || ($requestedRoadmapId === 0 && $roadmap['enroll_status'] === 'active')) {
        $mobileRoadmap = $roadmap;
        break;
    }
}
$mobileRoadmap ??= $enrolledRoadmaps[0] ?? null;
$mobileTasks = $mobileRoadmap ? getRoadmapTasks($pdo, (int)$mobileRoadmap['id'], (int)$currentUser['id']) : [];
$mobileProgress = roadmapProgress($mobileTasks);
$roadmapStageNames = ['m1' => 'ม.1', 'm4' => 'ม.4', 'tcas' => 'TCAS'];
$nextMobileTask = null;
foreach ($mobileTasks as $task) {
    if (!in_array($task['progress_status'], ['completed', 'exempted', 'locked'], true)) {
        $nextMobileTask = $task;
        break;
    }
}

function mobileRoadmapTaskUrl(array $task): string
{
    if (!empty($task['ref_lesson_id'])) return '../lesson.php?id=' . (int)$task['ref_lesson_id'];
    if (!empty($task['ref_exam_id'])) return 'take-test.php?id=' . (int)$task['ref_exam_id'];
    return 'roadmap.php?id=' . (int)$task['roadmap_id'];
}

function mobileRoadmapTaskType(array $task): string
{
    return match ($task['completion_type']) {
        'complete_lesson', 'complete_course' => '▤ บทเรียน  ▷ วิดีโอ',
        'submit_test', 'pass_test' => '▣ แบบทดสอบ',
        'attend_schedule' => '◷ ตารางเรียน',
        default => $task['subject'] ?: 'ภารกิจใน Roadmap',
    };
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
      <section class="student-mobile-journey" aria-label="เส้นทางการเรียนบนมือถือ">
        <header class="mobile-journey-intro">
          <div><p>LEARNING JOURNEY</p><h1>เส้นทางของฉัน</h1></div>
          <?php if (count($enrolledRoadmaps) > 1): ?>
            <select aria-label="เลือก Roadmap" onchange="location.href='learning-path.php?roadmap_id='+this.value">
              <?php foreach ($enrolledRoadmaps as $roadmap): ?><option value="<?= (int)$roadmap['id'] ?>" <?= (int)$roadmap['id'] === (int)$mobileRoadmap['id'] ? 'selected' : '' ?>><?= htmlspecialchars($roadmapStageNames[$roadmap['stage']] ?? strtoupper($roadmap['stage'])) ?></option><?php endforeach; ?>
            </select>
          <?php else: ?>
            <span class="mobile-journey-stage"><?= htmlspecialchars($roadmapStageNames[$mobileRoadmap['stage'] ?? 'm4'] ?? 'ม.4') ?></span>
          <?php endif; ?>
        </header>

        <nav class="mobile-journey-tabs" aria-label="เมนูเส้นทางการเรียน">
          <a class="active" href="learning-path.php">แผนของฉัน</a>
          <a href="roadmap.php">เลือก Roadmap</a>
        </nav>

        <?php if ($mobileRoadmap): ?>
          <article class="mobile-goal-card">
            <div><small>เป้าหมายของฉัน</small><h2><?= htmlspecialchars($mobileRoadmap['title']) ?></h2><p><?= htmlspecialchars($mobileRoadmap['description'] ?: 'ทุกบทเรียน พาคุณไปไกลกว่าเดิม') ?></p></div>
            <div class="mobile-progress-ring" style="--journey-progress:<?= (int)$mobileProgress['percent'] ?>%"><strong><?= (int)$mobileProgress['percent'] ?>%</strong></div>
          </article>

          <div class="mobile-journey-timeline">
            <div class="mobile-route-line" aria-hidden="true"></div>
            <?php foreach (array_slice($mobileTasks, 0, 6) as $index => $task):
              $isDone = in_array($task['progress_status'], ['completed', 'exempted'], true);
              $isCurrent = $nextMobileTask && (int)$task['id'] === (int)$nextMobileTask['id'];
              $taskClass = $isDone ? 'done' : ($isCurrent ? 'current' : 'pending');
            ?>
              <a class="mobile-milestone <?= $taskClass ?>" href="<?= htmlspecialchars(mobileRoadmapTaskUrl($task)) ?>">
                <span class="mobile-milestone-number"><?= $isDone ? '✓' : $index + 1 ?></span>
                <span class="mobile-milestone-copy">
                  <small><?= $isCurrent ? 'บทเรียนถัดไป' : 'บทที่ ' . ($index + 1) ?><?= $isCurrent && $task['subject'] ? ' • ' . htmlspecialchars($task['subject']) : '' ?></small>
                  <b><?= htmlspecialchars($task['title']) ?></b>
                  <em><?= $isDone ? 'เรียนจบแล้ว' : ($isCurrent ? htmlspecialchars(mobileRoadmapTaskType($task)) : 'ยังไม่ได้เรียน') ?></em>
                </span>
                <?php if ($isCurrent): ?><strong class="mobile-milestone-arrow">›</strong><?php endif; ?>
              </a>
            <?php endforeach; ?>
          </div>

          <?php if ($nextMobileTask): ?><a class="mobile-next-lesson" href="<?= htmlspecialchars(mobileRoadmapTaskUrl($nextMobileTask)) ?>"><span>เริ่มบทเรียนถัดไป</span><b>›</b></a><?php endif; ?>
        <?php else: ?>
          <article class="mobile-goal-card empty">
            <div><small>เป้าหมายของฉัน</small><h2>เลือก Roadmap ก่อนเริ่มเรียน</h2><p>เลือกแผนที่ตรงกับระดับและเป้าหมายของคุณ</p></div>
            <div class="mobile-progress-ring" style="--journey-progress:0%"><strong>0%</strong></div>
          </article>
          <div class="mobile-journey-empty"><span>✦</span><h2>ยังไม่มีแผนของฉัน</h2><p>เมื่อเลือก Roadmap แล้ว ภารกิจทั้งหมดจะแสดงเป็นเส้นทางในหน้านี้</p></div>
          <a class="mobile-next-lesson" href="roadmap.php"><span>เลือก Roadmap</span><b>›</b></a>
        <?php endif; ?>
      </section>

      <div class="student-learning-desktop">
      <section class="mb-8 border-b border-[#dce3ec] pb-7">
        <p class="student-kicker mb-2">Learning roadmap</p>
        <div class="flex items-end justify-between gap-5 flex-wrap">
          <div>
            <h2 class="text-[28px] font-bold text-navy-950">แผนการเรียนของฉัน</h2>
            <p class="mt-2 text-[14px] text-[#65738a]">ดูเป้าหมาย ลำดับการเรียน และความก้าวหน้าทั้งหมดในที่เดียว</p>
          </div>
          <a href="../courses" class="h-10 px-5 inline-flex items-center rounded-lg bg-pink-500 text-white text-[13px] font-bold hover:bg-pink-600">เลือกคอร์สเรียน</a>
        </div>
      </section>

      <div class="grid grid-cols-3 gap-px bg-[#dfe5ed] border border-[#dfe5ed] rounded-xl overflow-hidden mb-7 max-[680px]:grid-cols-1">
        <div class="bg-white px-5 py-4"><div class="student-kicker">แผนทั้งหมด</div><div class="mt-1 text-[25px] font-bold"><?= $pathCount ?></div></div>
        <div class="bg-white px-5 py-4"><div class="student-kicker">ความก้าวหน้าเฉลี่ย</div><div class="mt-1 text-[25px] font-bold"><?= $averageProgress ?>%</div></div>
        <div class="bg-white px-5 py-4"><div class="student-kicker">เวลาเรียนต่อสัปดาห์</div><div class="mt-1 text-[25px] font-bold"><?= $totalHours ?> <span class="text-[13px] font-semibold text-[#718096]">ชั่วโมง</span></div></div>
      </div>

      <?php if (!$paths): ?>
        <div class="learning-empty-grid">
          <section class="bg-white border border-[#dfe5ed] rounded-xl overflow-hidden">
            <div class="h-2 bg-pink-500"></div>
            <div class="px-10 py-12 max-[640px]:px-6 max-[640px]:py-9">
              <div class="w-14 h-14 rounded-xl bg-[#eef2f7] flex items-center justify-center text-navy-950 mb-6">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z"/></svg>
              </div>
              <p class="student-kicker mb-2">เริ่มต้นวางเส้นทาง</p>
              <h2 class="text-[24px] font-bold text-navy-950">ยังไม่มีแผนการเรียน</h2>
              <p class="mt-3 max-w-[560px] text-[14px] leading-7 text-[#65738a]">เริ่มจากเลือกคอร์สที่สนใจหรือทำแบบทดสอบวัดระดับ เมื่อมีแผนแล้ว คุณจะเห็นหัวข้อที่ควรเรียน ลำดับขั้น และความก้าวหน้าที่หน้านี้</p>
              <div class="mt-7 flex items-center gap-3 flex-wrap">
                <a href="../courses" class="h-11 px-6 inline-flex items-center rounded-lg bg-pink-500 text-white text-[14px] font-bold hover:bg-pink-600">ดูคอร์สทั้งหมด</a>
                <a href="tests" class="h-11 px-6 inline-flex items-center rounded-lg border border-[#cfd7e2] bg-white text-navy-950 text-[14px] font-bold hover:bg-[#f5f7fa]">ทำแบบทดสอบ</a>
              </div>
            </div>
          </section>

          <aside class="bg-navy-950 text-white rounded-xl px-6 py-7">
            <p class="text-[11px] font-bold uppercase tracking-[.14em] text-[#90a3bd]">How it works</p>
            <h2 class="mt-2 text-[18px] font-bold">เริ่มเรียนอย่างเป็นขั้นตอน</h2>
            <ol class="mt-7 space-y-6">
              <li class="flex gap-4"><span class="w-7 h-7 shrink-0 rounded-full border border-[#4a607d] flex items-center justify-center text-[12px] font-bold">1</span><div><strong class="block text-[14px]">ตั้งเป้าหมาย</strong><span class="block mt-1 text-[12px] leading-5 text-[#aebbd0]">เลือกวิชาและระดับที่ต้องการ</span></div></li>
              <li class="flex gap-4"><span class="w-7 h-7 shrink-0 rounded-full border border-[#4a607d] flex items-center justify-center text-[12px] font-bold">2</span><div><strong class="block text-[14px]">เรียนตามลำดับ</strong><span class="block mt-1 text-[12px] leading-5 text-[#aebbd0]">ทำแต่ละหัวข้อให้ครบตามแผน</span></div></li>
              <li class="flex gap-4"><span class="w-7 h-7 shrink-0 rounded-full border border-[#4a607d] flex items-center justify-center text-[12px] font-bold">3</span><div><strong class="block text-[14px]">วัดผลและพัฒนา</strong><span class="block mt-1 text-[12px] leading-5 text-[#aebbd0]">ตรวจผลแล้วปรับแผนให้เหมาะกับคุณ</span></div></li>
            </ol>
          </aside>
        </div>
      <?php else: ?>
        <div class="space-y-5">
          <?php foreach ($paths as $path):
              $progress = min(100, max(0, (float) ($path['progress_percent'] ?? 0)));
              $steps = learningPathSteps($path);
              $cId = !empty($path['course_id']) ? (int)$path['course_id'] : null;
              $courseGoals = $cId ? $journeyService->getStudentLearningGoals((int)$currentUser['id'], $cId, 'active') : [];
              $activeGoal = $courseGoals[0] ?? null;
              $courseNextAction = $cId ? $journeyService->getStudentNextAction((int)$currentUser['id'], $cId) : null;
          ?>
            <article class="bg-white border border-[#dfe5ed] rounded-xl overflow-hidden hover:border-[#b8c4d3] shadow-sm">
              <div class="grid grid-cols-[280px_minmax(0,1fr)] max-[760px]:grid-cols-1">
                <div class="bg-navy-950 text-white p-6 flex flex-col justify-between min-h-[250px]">
                  <div>
                    <div class="flex items-center gap-2 flex-wrap mb-2">
                      <span class="text-[11px] uppercase tracking-[.14em] font-bold text-pink-400">
                        <?= htmlspecialchars($path['course_title'] ?: $path['subject'] ?: 'Learning path') ?>
                      </span>
                    </div>
                    <h2 class="text-[20px] leading-7 font-bold">
                      <?= htmlspecialchars($path['target_goal'] ?: ($activeGoal['goal_name'] ?? 'เป้าหมายการเรียนของคุณ')) ?>
                    </h2>
                    <?php if ($activeGoal): ?>
                      <div class="mt-2.5 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/10 text-white text-xs border border-white/15">
                        <span>🎯</span>
                        <span class="font-semibold"><?= htmlspecialchars($activeGoal['goal_name']) ?></span>
                        <?php if ($activeGoal['target_score'] !== null): ?>
                          <span class="text-pink-400 font-bold">(<?= (float)$activeGoal['target_score'] ?>)</span>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="pt-6 border-t border-white/10 text-[12px] text-[#aebbd0]">
                    <div>ระดับ <?= htmlspecialchars($path['current_level'] ?: 'ทั่วไป') ?></div>
                    <div class="mt-1"><?= (int) ($path['hours_per_week'] ?? 0) ?> ชั่วโมงต่อสัปดาห์</div>
                  </div>
                </div>
                <div class="p-6 max-[640px]:p-5">
                  <div class="flex items-center justify-between gap-4 mb-2">
                    <span class="text-[13px] font-bold text-slate-600">ความก้าวหน้าของแผน</span>
                    <strong class="text-[18px] text-navy-950"><?= (int) round($progress) ?>%</strong>
                  </div>
                  <div class="h-2 rounded-full bg-[#e8edf3] overflow-hidden mb-6">
                    <div class="h-full bg-pink-500" style="width:<?= $progress ?>%"></div>
                  </div>

                  <?php if ($courseNextAction): ?>
                    <div class="mb-6 p-4 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-4 flex-wrap">
                      <div class="flex items-center gap-3">
                        <span class="w-3 h-3 rounded-full bg-pink-500 shrink-0 animate-pulse"></span>
                        <div>
                          <div class="text-[10px] font-black uppercase tracking-wider text-pink-600">ภารกิจถัดไป (Next Action)</div>
                          <div class="text-sm font-bold text-navy-950"><?= htmlspecialchars($courseNextAction['title']) ?></div>
                        </div>
                      </div>
                      <a href="<?= htmlspecialchars(resolveStudentActionUrl($courseNextAction['action_url'])) ?>"
                         class="h-9 px-4 rounded-lg bg-pink-500 hover:bg-pink-600 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm transition-all">
                        <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        <span>เรียนต่อ</span>
                      </a>
                    </div>
                  <?php endif; ?>

                  <div>
                    <h3 class="student-section-title text-[15px] font-bold text-navy-950">ลำดับการเรียน (Learning Steps)</h3>
                    <?php if ($steps): ?>
                      <ol class="mt-4 grid grid-cols-2 gap-3 max-[640px]:grid-cols-1">
                        <?php foreach (array_slice($steps, 0, 6) as $index => $step):
                          $title = learningStepTitle($step);
                          $isStepCurrent = $courseNextAction && (stripos($courseNextAction['title'], $title) !== false || stripos($title, $courseNextAction['title']) !== false);
                        ?>
                          <li class="flex items-center gap-3 border <?= $isStepCurrent ? 'border-pink-300 bg-pink-50/50' : 'border-[#e3e8ef]' ?> rounded-lg p-3">
                            <span class="w-7 h-7 shrink-0 rounded-md <?= $isStepCurrent ? 'bg-pink-500 text-white' : 'bg-[#eef2f7] text-navy-950' ?> flex items-center justify-center text-[11px] font-bold">
                              <?= $isStepCurrent ? '▶' : ($index + 1) ?>
                            </span>
                            <span class="text-[13px] font-semibold leading-5 <?= $isStepCurrent ? 'text-pink-600' : 'text-slate-800' ?>">
                              <?= htmlspecialchars($title) ?>
                            </span>
                          </li>
                        <?php endforeach; ?>
                      </ol>
                    <?php else: ?>
                      <p class="mt-4 text-[13px] text-[#718096]">รายละเอียดขั้นตอนกำลังจัดเตรียม</p>
                    <?php endif; ?>
                  </div>
                </div>
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
