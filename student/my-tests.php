<?php
$pageTitle = 'ประวัติข้อสอบ';
$currentPage = 'my-tests.php';
require_once __DIR__ . '/includes/guard.php';

$stmtAttempts = $pdo->prepare(
    'SELECT a.id, a.score, a.correct_count, a.total_questions, a.completed_at, a.exam_id,
            e.title, e.subject, e.grade, e.type
     FROM test_attempts a
     INNER JOIN exams e ON e.id = a.exam_id
     WHERE a.user_id = :user_id AND a.completed_at IS NOT NULL
     ORDER BY a.completed_at DESC, a.id DESC'
);
$stmtAttempts->execute([':user_id' => $currentUser['id']]);
$allAttempts = $stmtAttempts->fetchAll();

$typeLabels = [
    'quiz' => 'Quiz',
    'placement' => 'Placement Test',
    'pretest' => 'Pre-test',
    'posttest' => 'Post-test',
];
$selectedType = (string) ($_GET['type'] ?? '');
$selectedResult = (string) ($_GET['result'] ?? '');
$search = trim((string) ($_GET['q'] ?? ''));
$allowedResults = ['passed', 'review'];

if (!array_key_exists($selectedType, $typeLabels)) $selectedType = '';
if (!in_array($selectedResult, $allowedResults, true)) $selectedResult = '';

$attempts = array_values(array_filter($allAttempts, static function (array $attempt) use ($selectedType, $selectedResult, $search): bool {
    $score = (float) ($attempt['score'] ?? 0);
    if ($selectedType !== '' && ($attempt['type'] ?? '') !== $selectedType) return false;
    if ($selectedResult === 'passed' && $score < 60) return false;
    if ($selectedResult === 'review' && $score >= 60) return false;
    if ($search !== '') {
        $haystack = (string) ($attempt['title'] ?? '') . ' ' . (string) ($attempt['subject'] ?? '');
        if (mb_stripos($haystack, $search) === false) return false;
    }
    return true;
}));

$totalAttempts = count($allAttempts);
$scores = array_map(static fn(array $attempt): float => (float) ($attempt['score'] ?? 0), $allAttempts);
$averageScore = $totalAttempts > 0 ? round(array_sum($scores) / $totalAttempts, 1) : 0;
$bestScore = $totalAttempts > 0 ? round(max($scores), 1) : 0;
$passedCount = count(array_filter($scores, static fn(float $score): bool => $score >= 60));
$passRate = $totalAttempts > 0 ? (int) round(($passedCount / $totalAttempts) * 100) : 0;
$latestScore = $totalAttempts > 0 ? round((float) ($allAttempts[0]['score'] ?? 0), 1) : 0;
$hasFilters = $selectedType !== '' || $selectedResult !== '' || $search !== '';

function testScoreMeta(float $score): array
{
    if ($score >= 80) return ['label' => 'ดีมาก', 'class' => 'score-good'];
    if ($score >= 60) return ['label' => 'ผ่าน', 'class' => 'score-pass'];
    return ['label' => 'ควรทบทวน', 'class' => 'score-review'];
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> - Nextbeyond Compass</title>
  <link rel="stylesheet" href="../assets/css/output.css?v=<?= filemtime(__DIR__ . '/../assets/css/output.css') ?>">
  <link rel="stylesheet" href="../assets/css/student-portal.css?v=<?= filemtime(__DIR__ . '/../assets/css/student-portal.css') ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="student-portal bg-[#f4f7fb] font-sans antialiased">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col ml-[240px] max-[1024px]:ml-0 min-w-0">
    <?php include 'includes/topbar.php'; ?>
    <main class="flex-1 p-8 max-[640px]:p-4">
      <section class="mb-8 border-b border-[#dce3ec] pb-7">
        <p class="student-kicker mb-2">Test performance</p>
        <div class="flex items-end justify-between gap-5 flex-wrap">
          <div>
            <h2 class="text-[28px] font-bold text-navy-950">ผลการทดสอบของฉัน</h2>
            <p class="mt-2 text-[14px] text-[#65738a]">ตรวจคะแนน ดูเฉลย และเลือกหัวข้อที่ควรกลับไปทบทวน</p>
          </div>
          <a href="tests" class="h-10 px-5 inline-flex items-center rounded-lg bg-pink-500 text-white text-[13px] font-bold hover:bg-pink-600">เลือกแบบทดสอบ</a>
        </div>
      </section>

      <div class="grid grid-cols-4 gap-px bg-[#dfe5ed] border border-[#dfe5ed] rounded-xl overflow-hidden mb-7 max-[900px]:grid-cols-2 max-[520px]:grid-cols-1">
        <div class="bg-white px-5 py-4"><div class="student-kicker">ทำข้อสอบแล้ว</div><div class="mt-1 text-[25px] font-bold"><?= $totalAttempts ?> <span class="text-[13px] font-semibold text-[#718096]">ครั้ง</span></div></div>
        <div class="bg-white px-5 py-4"><div class="student-kicker">คะแนนล่าสุด</div><div class="mt-1 text-[25px] font-bold"><?= $latestScore ?>%</div></div>
        <div class="bg-white px-5 py-4"><div class="student-kicker">คะแนนเฉลี่ย</div><div class="mt-1 text-[25px] font-bold"><?= $averageScore ?>%</div></div>
        <div class="bg-white px-5 py-4"><div class="student-kicker">อัตราผ่าน</div><div class="mt-1 text-[25px] font-bold"><?= $passRate ?>%</div></div>
      </div>

      <?php if ($totalAttempts > 0): ?>
        <form method="get" class="bg-white border border-[#dfe5ed] rounded-xl p-4 mb-5 flex items-center gap-3 flex-wrap">
          <label class="relative flex-1 min-w-[230px]">
            <svg class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-[#8190a5]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.35-5.65A7 7 0 1 1 4 11a7 7 0 0 1 14 0Z"/></svg>
            <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาชื่อข้อสอบหรือวิชา" class="w-full h-10 rounded-lg border border-[#d7dee8] bg-white pl-11 pr-4 text-[13px] outline-none focus:border-[#8190a5]">
          </label>
          <select name="type" class="h-10 min-w-[155px] rounded-lg border border-[#d7dee8] bg-white px-3 text-[13px] font-semibold text-navy-950 outline-none">
            <option value="">ทุกประเภท</option>
            <?php foreach ($typeLabels as $value => $label): ?><option value="<?= $value ?>" <?= $selectedType === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
          </select>
          <select name="result" class="h-10 min-w-[145px] rounded-lg border border-[#d7dee8] bg-white px-3 text-[13px] font-semibold text-navy-950 outline-none">
            <option value="">ทุกผลลัพธ์</option>
            <option value="passed" <?= $selectedResult === 'passed' ? 'selected' : '' ?>>ผ่าน</option>
            <option value="review" <?= $selectedResult === 'review' ? 'selected' : '' ?>>ควรทบทวน</option>
          </select>
          <button class="h-10 px-5 rounded-lg bg-pink-500 text-white text-[13px] font-bold hover:bg-pink-600">ค้นหา</button>
          <?php if ($hasFilters): ?><a href="my-tests" class="h-10 px-3 inline-flex items-center text-[13px] font-bold text-[#65738a] hover:text-navy-950">ล้างตัวกรอง</a><?php endif; ?>
        </form>
      <?php endif; ?>

      <?php if (!$attempts): ?>
        <section class="max-w-[760px] mx-auto mt-10 bg-white border border-[#dfe5ed] rounded-xl px-8 py-12 text-center">
          <div class="w-14 h-14 mx-auto mb-5 rounded-xl bg-[#eef2f7] flex items-center justify-center text-navy-950">
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v-6.5m3 6.5v-10m3 10v-3.5M4.5 19.5h15A1.5 1.5 0 0 0 21 18V6a1.5 1.5 0 0 0-1.5-1.5h-15A1.5 1.5 0 0 0 3 6v12a1.5 1.5 0 0 0 1.5 1.5Z"/></svg>
          </div>
          <p class="student-kicker mb-2"><?= $hasFilters ? 'No matching results' : 'Your first test' ?></p>
          <h2 class="text-[21px] font-bold text-navy-950"><?= $hasFilters ? 'ไม่พบผลสอบที่ค้นหา' : 'ยังไม่มีประวัติการทำข้อสอบ' ?></h2>
          <p class="mt-2 text-[14px] leading-6 text-[#65738a] max-w-[480px] mx-auto"><?= $hasFilters ? 'ลองเปลี่ยนคำค้นหา ประเภท หรือผลลัพธ์ แล้วค้นหาอีกครั้ง' : 'เลือกแบบทดสอบที่เหมาะกับระดับของคุณ หลังส่งคำตอบ คะแนนและเฉลยจะถูกเก็บไว้ที่หน้านี้' ?></p>
          <?php if ($hasFilters): ?>
            <a href="my-tests" class="mt-6 h-11 px-6 inline-flex items-center rounded-lg border border-[#cfd7e2] font-bold text-[14px] hover:bg-[#f5f7fa]">แสดงประวัติทั้งหมด</a>
          <?php else: ?>
            <a href="tests" class="mt-6 h-11 px-6 inline-flex items-center rounded-lg bg-pink-500 text-white font-bold text-[14px] hover:bg-pink-600">เริ่มทำแบบทดสอบ</a>
          <?php endif; ?>
        </section>
      <?php else: ?>
        <section class="bg-white border border-[#dfe5ed] rounded-xl overflow-hidden">
          <div class="px-5 py-4 border-b border-[#e3e8ef] flex items-center justify-between gap-4">
            <h2 class="student-section-title text-[15px] font-bold">ประวัติการทำข้อสอบ</h2>
            <span class="text-[12px] text-[#718096]"><?= count($attempts) ?> รายการ</span>
          </div>

          <div class="my-tests-desktop overflow-x-auto">
            <table class="w-full text-left min-w-[780px]">
              <thead>
                <tr class="bg-[#f7f8fa] border-b border-[#e3e8ef]">
                  <th class="px-5 py-3 text-[11px] font-bold tracking-[.08em] text-[#718096] uppercase">แบบทดสอบ</th>
                  <th class="px-5 py-3 text-[11px] font-bold tracking-[.08em] text-[#718096] uppercase">วันที่ทำ</th>
                  <th class="px-5 py-3 text-[11px] font-bold tracking-[.08em] text-[#718096] uppercase text-center">ตอบถูก</th>
                  <th class="px-5 py-3 text-[11px] font-bold tracking-[.08em] text-[#718096] uppercase">คะแนน</th>
                  <th class="px-5 py-3"><span class="sr-only">จัดการ</span></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#edf0f4]">
                <?php foreach ($attempts as $attempt):
                    $score = round((float) $attempt['score'], 1);
                    $meta = testScoreMeta($score);
                ?>
                  <tr class="hover:bg-[#fafbfc]">
                    <td class="px-5 py-4">
                      <div class="font-bold text-[14px] text-navy-950"><?= htmlspecialchars($attempt['title']) ?></div>
                      <div class="mt-1 text-[12px] text-[#718096]"><?= htmlspecialchars($attempt['subject'] ?: 'ไม่ระบุวิชา') ?><?php if (!empty($attempt['grade'])): ?> · <?= htmlspecialchars($attempt['grade']) ?><?php endif; ?> · <?= htmlspecialchars($typeLabels[$attempt['type']] ?? ucfirst((string) $attempt['type'])) ?></div>
                    </td>
                    <td class="px-5 py-4 text-[12px] text-[#65738a] whitespace-nowrap">
                      <strong class="block text-[13px] font-semibold text-navy-950"><?= date('d/m/Y', strtotime($attempt['completed_at'])) ?></strong>
                      <?= date('H:i', strtotime($attempt['completed_at'])) ?> น.
                    </td>
                    <td class="px-5 py-4 text-center text-[13px] font-semibold"><?= (int) $attempt['correct_count'] ?><span class="text-[#8190a5]">/<?= (int) $attempt['total_questions'] ?></span></td>
                    <td class="px-5 py-4">
                      <div class="flex items-center gap-3">
                        <strong class="text-[18px] min-w-[62px]"><?= $score ?>%</strong>
                        <span class="test-score-badge <?= $meta['class'] ?>"><?= $meta['label'] ?></span>
                      </div>
                    </td>
                    <td class="px-5 py-4 text-right whitespace-nowrap">
                      <a href="test-result?id=<?= (int) $attempt['id'] ?>" class="h-9 px-4 inline-flex items-center rounded-lg border border-[#cfd7e2] text-[12px] font-bold hover:bg-[#f3f5f8]">ดูผลและเฉลย</a>
                      <a href="take-test?id=<?= (int) $attempt['exam_id'] ?>" class="ml-2 text-[12px] font-bold text-pink-600 hover:text-pink-500">ทำอีกครั้ง</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="my-tests-mobile hidden divide-y divide-[#edf0f4]">
            <?php foreach ($attempts as $attempt):
                $score = round((float) $attempt['score'], 1);
                $meta = testScoreMeta($score);
            ?>
              <article class="p-5">
                <div class="flex items-start justify-between gap-4">
                  <div><h3 class="text-[15px] leading-6 font-bold"><?= htmlspecialchars($attempt['title']) ?></h3><p class="mt-1 text-[12px] text-[#718096]"><?= htmlspecialchars($attempt['subject'] ?: 'ไม่ระบุวิชา') ?> · <?= htmlspecialchars($typeLabels[$attempt['type']] ?? ucfirst((string) $attempt['type'])) ?></p></div>
                  <strong class="text-[20px]"><?= $score ?>%</strong>
                </div>
                <div class="mt-4 pt-4 border-t border-[#edf0f4] flex items-center justify-between gap-3 text-[12px]">
                  <div class="text-[#718096]"><?= date('d/m/Y', strtotime($attempt['completed_at'])) ?> · ถูก <?= (int) $attempt['correct_count'] ?>/<?= (int) $attempt['total_questions'] ?></div>
                  <span class="test-score-badge <?= $meta['class'] ?>"><?= $meta['label'] ?></span>
                </div>
                <div class="mt-4 flex gap-2">
                  <a href="test-result?id=<?= (int) $attempt['id'] ?>" class="h-9 flex-1 inline-flex items-center justify-center rounded-lg bg-pink-500 text-white text-[12px] font-bold">ดูผลและเฉลย</a>
                  <a href="take-test?id=<?= (int) $attempt['exam_id'] ?>" class="h-9 px-4 inline-flex items-center justify-center rounded-lg border border-[#cfd7e2] text-[12px] font-bold">ทำอีกครั้ง</a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>
    </main>
    <?php include 'includes/bottom-nav.php'; ?>
  </div>
</div>
</body>
</html>
