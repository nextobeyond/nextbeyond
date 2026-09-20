<?php
/**
 * student/skill-map.php
 * NEXTBEYOND V2 — Phase 3: Student Skill Map & Mastery View
 * "แผนที่ทักษะและการเรียนรู้" — จุดที่ทำได้ดี / กำลังพัฒนา / ควรทบทวน
 */
declare(strict_types=1);

$pageTitle   = 'แผนที่ทักษะ (Skill Map)';
$currentPage = 'skill-map.php';
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/phase3-mastery-service.php';

$p3 = new Phase3MasteryService($pdo);
$currentStudentId = (int)($currentUser['id'] ?? 0);
$selectedCourseId = isset($_GET['course_id']) && $_GET['course_id'] !== '' ? (int)$_GET['course_id'] : null;

// Fetch enrolled courses for filter
$stmtCourses = $pdo->prepare("
    SELECT c.id, c.title, c.subject, c.level
    FROM enrollments en
    INNER JOIN courses c ON c.id = en.course_id
    WHERE en.user_id = ? AND en.status IN ('active', 'trial')
    ORDER BY c.title ASC
");
$stmtCourses->execute([$currentStudentId]);
$enrolledCourses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

// Fetch Skill Map data
$skillMapData = $p3->getStudentSkillMap($currentStudentId, $selectedCourseId);
$strongTopics = $skillMapData['strong'] ?? [];
$developingTopics = $skillMapData['developing'] ?? [];
$reviewTopics = $skillMapData['needs_review'] ?? [];
$allTopics = $skillMapData['all'] ?? [];

// Fetch Recent Evidence Timeline
$evSql = "
    SELECT le.*, c.title AS course_title
    FROM learning_evidence le
    LEFT JOIN courses c ON c.id = le.course_id
    WHERE le.student_id = :sid
";
$evParams = [':sid' => $currentStudentId];
if ($selectedCourseId !== null && $selectedCourseId > 0) {
    $evSql .= " AND le.course_id = :cid";
    $evParams[':cid'] = $selectedCourseId;
}
$evSql .= " ORDER BY le.occurred_at DESC LIMIT 10";
$stmtEv = $pdo->prepare($evSql);
$stmtEv->execute($evParams);
$recentEvidence = $stmtEv->fetchAll(PDO::FETCH_ASSOC);

// Check Pre/Post Test Comparisons
$prePostList = [];
foreach ($allTopics as $top) {
    $cId = (int)$top['course_id'];
    $tName = (string)$top['topic_name'];
    $comp = $p3->getPrePostComparison($currentStudentId, $cId, $tName);
    if ($comp) {
        $prePostList[] = $comp;
    }
}

// Source labels
$sourceLabels = [
    'diagnostic'     => ['label' => 'แบบวัดพื้นฐาน', 'badge' => 'bg-slate-100 text-slate-700'],
    'get_ready'      => ['label' => 'เตรียมตัวก่อนเรียน', 'badge' => 'bg-blue-50 text-blue-700'],
    'in_class_check' => ['label' => 'เช็คความเข้าใจสด', 'badge' => 'bg-indigo-50 text-indigo-700'],
    'worksheet'      => ['label' => 'ใบงาน (Worksheet)', 'badge' => 'bg-sky-50 text-sky-700'],
    'practice'       => ['label' => 'แบบฝึกหัด (Practice)', 'badge' => 'bg-emerald-50 text-emerald-700'],
    'homework'       => ['label' => 'การบ้าน (Homework)', 'badge' => 'bg-amber-50 text-amber-800'],
    'posttest'       => ['label' => 'วัดผลหลังเรียน (Post-Test)', 'badge' => 'bg-purple-50 text-purple-700'],
    'mock_exam'      => ['label' => 'จำลองข้อสอบจริง', 'badge' => 'bg-rose-50 text-rose-700'],
];
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> - Nextbeyond</title>
  <link rel="stylesheet" href="../assets/css/output.css?v=<?= filemtime(__DIR__ . '/../assets/css/output.css') ?>">
  <link rel="stylesheet" href="../assets/css/student-portal.css?v=<?= filemtime(__DIR__ . '/../assets/css/student-portal.css') ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="../assets/js/student-guard.js"></script>
</head>
<body class="student-portal bg-[#f4f7fb] text-navy-950 font-sans antialiased">

<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>

  <div class="flex-1 flex flex-col ml-[240px] max-[1024px]:ml-0 transition-all duration-300 min-w-0">
    <?php include 'includes/topbar.php'; ?>

    <main class="flex-1 p-6 sm:p-8 max-w-5xl w-full mx-auto space-y-6">

      <!-- Header & Filter -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <p class="student-kicker text-slate-400 mb-1">LEARNING MASTERY ENGINE</p>
          <h1 class="text-2xl sm:text-3xl font-black text-navy-950 flex items-center gap-2.5">
            <span>🗺️ แผนที่ทักษะ (Skill Map)</span>
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-1">
            ระดับความเข้าใจในแต่ละหัวข้อ วิเคราะห์จากกิจกรรมการเรียนรู้ ข้อสอบ และการฝึกฝนจริง
          </p>
        </div>

        <!-- Course Filter -->
        <form method="GET" action="skill-map.php" class="flex items-center gap-2">
          <select name="course_id" onchange="this.form.submit()" class="h-10 px-3.5 rounded-xl border border-slate-200 bg-white text-xs font-bold text-navy-950 focus:border-pink-500 focus:ring-2 focus:ring-pink-500/20 shadow-2xs">
            <option value="">-- ทุกรายวิชา --</option>
            <?php foreach ($enrolledCourses as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $selectedCourseId === (int)$c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['title']) ?> (<?= htmlspecialchars($c['subject']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>

      <!-- Pre vs Post-Test Improvement Highlight (Section 22, Acceptance Test #4) -->
      <?php if (!empty($prePostList)): ?>
        <div class="rounded-3xl bg-gradient-to-r from-indigo-950 via-purple-950 to-navy-950 p-6 text-white border border-indigo-900 shadow-sm relative overflow-hidden">
          <div class="flex items-start justify-between gap-4 flex-wrap relative z-10">
            <div>
              <span class="px-2.5 py-0.5 rounded-full bg-pink-500/20 text-pink-300 border border-pink-500/30 text-[10px] font-black uppercase tracking-wider">
                MEASURED GROWTH
              </span>
              <h3 class="text-lg font-black mt-2">📈 พัฒนาการก่อน-หลังเรียน (Pre vs Post-Test)</h3>
              <p class="text-xs text-slate-300 mt-0.5">การเปรียบเทียบคะแนนก่อนเข้าเรียนและหลังเรียนจบในหัวข้อเดียวกัน</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-4 relative z-10">
            <?php foreach ($prePostList as $item): ?>
              <div class="p-3.5 rounded-2xl bg-white/10 backdrop-blur-md border border-white/15 space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-black text-white truncate"><?= htmlspecialchars($item['topic']) ?></span>
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-black <?= $item['improvement_points'] >= 0 ? 'bg-emerald-400/20 text-emerald-300' : 'bg-rose-400/20 text-rose-300' ?>">
                    <?= $item['improvement_points'] >= 0 ? '+' : '' ?><?= $item['improvement_points'] ?> pts
                  </span>
                </div>
                <div class="flex items-center justify-between text-xs pt-1 border-t border-white/10">
                  <span class="text-slate-300">Pre: <b class="text-white"><?= $item['pre_score'] ?>%</b></span>
                  <span class="text-slate-300">Post: <b class="text-pink-300"><?= $item['post_score'] ?>%</b></span>
                  <span class="text-emerald-300 font-bold"><?= $item['improvement_pct'] ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- High-level 3 Categories (Section 35: จุดที่ทำได้ดี / กำลังพัฒนา / ควรทบทวน) -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

        <!-- 1. จุดที่ทำได้ดี (Strengths >= 80%) -->
        <div class="bg-white rounded-3xl border border-emerald-100 p-5 shadow-sm space-y-3">
          <div class="flex items-center justify-between border-b border-emerald-50 pb-3">
            <div class="flex items-center gap-2">
              <span class="w-7 h-7 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm font-black">🌟</span>
              <h2 class="font-black text-sm text-navy-950">จุดที่ทำได้ดี</h2>
            </div>
            <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-black border border-emerald-200">
              <?= count($strongTopics) ?> หัวข้อ
            </span>
          </div>

          <?php if (empty($strongTopics)): ?>
            <div class="py-8 text-center text-slate-400 text-xs">
              <p>ยังไม่มีหัวข้อที่ถึงเกณฑ์ 80%</p>
              <p class="text-[11px] mt-1">ทำแบบฝึกหัดเพิ่มเพื่อสะสมหลักฐาน</p>
            </div>
          <?php else: ?>
            <div class="space-y-2.5">
              <?php foreach ($strongTopics as $t): ?>
                <?php $mScore = round((float)$t['mastery_score'], 1); ?>
                <div class="p-3 rounded-2xl bg-emerald-50/40 border border-emerald-100 space-y-1.5">
                  <div class="flex items-center justify-between gap-2">
                    <span class="font-bold text-xs text-navy-950 truncate"><?= htmlspecialchars($t['topic_name']) ?></span>
                    <span class="text-xs font-black text-emerald-700"><?= $mScore ?>%</span>
                  </div>
                  <div class="w-full h-1.5 bg-emerald-100 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full" style="width: <?= min(100, $mScore) ?>%"></div>
                  </div>
                  <div class="flex items-center justify-between text-[10px] text-slate-400 pt-0.5">
                    <span>หลักฐาน: <?= (int)$t['actual_evidence_count'] ?> รายการ</span>
                    <span class="px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800 font-semibold uppercase">
                      ความเชื่อมั่น: <?= $t['confidence'] === 'high' ? 'สูง' : ($t['confidence'] === 'medium' ? 'ปานกลาง' : 'เริ่มต้น') ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- 2. กำลังพัฒนา (Developing 65 - 79%) -->
        <div class="bg-white rounded-3xl border border-amber-100 p-5 shadow-sm space-y-3">
          <div class="flex items-center justify-between border-b border-amber-50 pb-3">
            <div class="flex items-center gap-2">
              <span class="w-7 h-7 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-sm font-black">⚡</span>
              <h2 class="font-black text-sm text-navy-950">กำลังพัฒนา</h2>
            </div>
            <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 text-[11px] font-black border border-amber-200">
              <?= count($developingTopics) ?> หัวข้อ
            </span>
          </div>

          <?php if (empty($developingTopics)): ?>
            <div class="py-8 text-center text-slate-400 text-xs">
              <p>ไม่มีหัวข้อในช่วง 65–79%</p>
            </div>
          <?php else: ?>
            <div class="space-y-2.5">
              <?php foreach ($developingTopics as $t): ?>
                <?php $mScore = round((float)$t['mastery_score'], 1); ?>
                <div class="p-3 rounded-2xl bg-amber-50/40 border border-amber-100 space-y-1.5">
                  <div class="flex items-center justify-between gap-2">
                    <span class="font-bold text-xs text-navy-950 truncate"><?= htmlspecialchars($t['topic_name']) ?></span>
                    <span class="text-xs font-black text-amber-700"><?= $mScore ?>%</span>
                  </div>
                  <div class="w-full h-1.5 bg-amber-100 rounded-full overflow-hidden">
                    <div class="h-full bg-amber-500 rounded-full" style="width: <?= min(100, $mScore) ?>%"></div>
                  </div>
                  <div class="flex items-center justify-between text-[10px] text-slate-400 pt-0.5">
                    <span>หลักฐาน: <?= (int)$t['actual_evidence_count'] ?> รายการ</span>
                    <span class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 font-semibold uppercase">
                      ความเชื่อมั่น: <?= $t['confidence'] === 'high' ? 'สูง' : ($t['confidence'] === 'medium' ? 'ปานกลาง' : 'เริ่มต้น') ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- 3. ควรทบทวน (Needs Review < 65%) -->
        <div class="bg-white rounded-3xl border border-rose-100 p-5 shadow-sm space-y-3">
          <div class="flex items-center justify-between border-b border-rose-50 pb-3">
            <div class="flex items-center gap-2">
              <span class="w-7 h-7 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center text-sm font-black">🎯</span>
              <h2 class="font-black text-sm text-navy-950">ควรทบทวน</h2>
            </div>
            <span class="px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 text-[11px] font-black border border-rose-200">
              <?= count($reviewTopics) ?> หัวข้อ
            </span>
          </div>

          <?php if (empty($reviewTopics)): ?>
            <div class="py-8 text-center text-slate-400 text-xs">
              <p>ไม่มีหัวข้อที่ต้องทบทวนพิเศษ 🎉</p>
            </div>
          <?php else: ?>
            <div class="space-y-2.5">
              <?php foreach ($reviewTopics as $t): ?>
                <?php $mScore = round((float)$t['mastery_score'], 1); ?>
                <div class="p-3 rounded-2xl bg-rose-50/40 border border-rose-100 space-y-1.5">
                  <div class="flex items-center justify-between gap-2">
                    <span class="font-bold text-xs text-navy-950 truncate"><?= htmlspecialchars($t['topic_name']) ?></span>
                    <span class="text-xs font-black text-rose-700"><?= $mScore ?>%</span>
                  </div>
                  <div class="w-full h-1.5 bg-rose-100 rounded-full overflow-hidden">
                    <div class="h-full bg-rose-500 rounded-full" style="width: <?= min(100, $mScore) ?>%"></div>
                  </div>
                  <div class="flex items-center justify-between text-[10px] text-slate-400 pt-0.5">
                    <span>หลักฐาน: <?= (int)$t['actual_evidence_count'] ?> รายการ</span>
                    <span class="px-1.5 py-0.2 rounded bg-rose-100 text-rose-800 font-semibold uppercase">
                      ความเชื่อมั่น: <?= $t['confidence'] === 'high' ? 'สูง' : ($t['confidence'] === 'medium' ? 'ปานกลาง' : 'เริ่มต้น') ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- Recent Learning Evidence Timeline (Section 25, 45) -->
      <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="font-bold text-navy-950 text-base">ประวัติหลักฐานการเรียนรู้ (Learning Evidence Log)</h3>
            <p class="text-xs text-slate-400">กิจกรรมล่าสุดที่นำมาคำนวณคะแนนความเชี่ยวชาญ</p>
          </div>
          <span class="text-xs text-slate-500"><?= count($recentEvidence) ?> รายการล่าสุด</span>
        </div>

        <?php if (empty($recentEvidence)): ?>
          <div class="py-8 text-center text-slate-400 text-xs">
            ยังไม่มีหลักฐานการเรียนรู้ที่บันทึก
          </div>
        <?php else: ?>
          <div class="space-y-2">
            <?php foreach ($recentEvidence as $ev): ?>
              <?php
                $sInfo = $sourceLabels[$ev['source_type']] ?? ['label' => $ev['source_type'], 'badge' => 'bg-slate-100 text-slate-700'];
                $pct = round((float)$ev['score'], 1);
                $isPassed = $pct >= 60.0;
              ?>
              <div class="flex items-center justify-between p-3 rounded-2xl border border-slate-100 hover:border-slate-200 transition bg-slate-50/40">
                <div class="flex items-center gap-3 min-w-0">
                  <span class="w-8 h-8 rounded-xl flex items-center justify-center text-xs font-black <?= $isPassed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                    <?= $isPassed ? '✓' : '•' ?>
                  </span>
                  <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="px-2 py-0.5 rounded-md text-[10px] font-bold <?= $sInfo['badge'] ?>">
                        <?= $sInfo['label'] ?>
                      </span>
                      <strong class="text-xs text-navy-950 truncate"><?= htmlspecialchars($ev['topic_name']) ?></strong>
                    </div>
                    <span class="text-[11px] text-slate-400 block truncate mt-0.5">
                      <?= htmlspecialchars($ev['course_title'] ?? '') ?> · น้ำหนัก <?= round((float)$ev['weight'] * 100) ?>%
                    </span>
                  </div>
                </div>

                <div class="text-right shrink-0">
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
        <?php endif; ?>
      </div>

    </main>
  </div>
</div>

</body>
</html>
