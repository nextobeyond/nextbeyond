<?php
$pageTitle = 'คลังข้อสอบ';
$currentPage = 'tests.php';
require_once __DIR__ . '/includes/guard.php';

$stmt = $pdo->query(
    "SELECT e.id, e.title, e.subject, e.grade, e.type, e.time_limit_minutes,
            e.is_ai_generated, e.updated_at, COUNT(q.id) AS question_count
     FROM exams e
     LEFT JOIN exam_questions q ON q.exam_id = e.id
     WHERE e.is_published = 1 AND e.status = 'active'
     GROUP BY e.id
     ORDER BY e.updated_at DESC, e.id DESC"
);
$exams = $stmt->fetchAll();

$stmtDone = $pdo->prepare(
    'SELECT exam_id, MAX(score) AS best_score, COUNT(*) AS times
     FROM test_attempts WHERE user_id = :uid AND completed_at IS NOT NULL GROUP BY exam_id'
);
$stmtDone->execute([':uid' => $currentUser['id']]);
$doneMap = [];
foreach ($stmtDone->fetchAll() as $row) $doneMap[(int)$row['exam_id']] = $row;

$recentStmt = $pdo->prepare(
    'SELECT a.id, a.score, a.correct_count, a.total_questions, e.title, e.subject
     FROM test_attempts a INNER JOIN exams e ON e.id = a.exam_id
     WHERE a.user_id = :uid AND a.completed_at IS NOT NULL
     ORDER BY a.completed_at DESC, a.id DESC LIMIT 1'
);
$recentStmt->execute([':uid' => $currentUser['id']]);
$recentAttempt = $recentStmt->fetch() ?: null;

$subjects = [];
foreach ($exams as $exam) {
    $subject = trim((string)($exam['subject'] ?? '')) ?: 'ทั่วไป';
    $subjects[$subject] = true;
}
ksort($subjects, SORT_NATURAL);

function studentExamCode(string $subject, string $title): string
{
    $source = mb_strtolower($subject . ' ' . $title);
    foreach ([
        'คณิต' => 'MA', 'math' => 'MA', 'อังกฤษ' => 'EN', 'english' => 'EN',
        'เคมี' => 'CH', 'chem' => 'CH', 'ฟิสิกส์' => 'PH', 'physics' => 'PH',
        'ชีว' => 'BI', 'biology' => 'BI', 'วิทย' => 'SC', 'science' => 'SC',
    ] as $word => $code) if (str_contains($source, $word)) return $code;
    return mb_strtoupper(mb_substr(trim($subject ?: $title ?: 'EX'), 0, 2));
}

function studentExamMobileGroup(string $type): string
{
    return match ($type) {
        'pretest' => 'pre', 'posttest' => 'post', default => 'mock',
    };
}

$featuredExam = $exams[0] ?? null;
$cssVersion = (string)filemtime(__DIR__ . '/../assets/css/student-exam.css');
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> - Nextbeyond Compass</title>
  <link rel="stylesheet" href="../assets/css/output.css?v=<?= filemtime(__DIR__ . '/../assets/css/output.css') ?>">
  <link rel="stylesheet" href="../assets/css/student-portal.css?v=<?= filemtime(__DIR__ . '/../assets/css/student-portal.css') ?>">
  <link rel="stylesheet" href="../assets/css/student-exam.css?v=<?= $cssVersion ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="student-portal bg-[#f4f7fb] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col ml-[240px] max-[1024px]:ml-0 min-w-0">
    <?php include 'includes/topbar.php'; ?>
<main class="exam-page">
  <section class="student-mobile-exams" aria-label="คลังข้อสอบบนมือถือ">
    <header class="mobile-exams-intro"><p>PRACTICE HUB</p><h1>พร้อมท้าทายตัวเอง?</h1><span>ฝึกทีละนิด เข้าใกล้เป้าหมาย</span></header>

    <article class="mobile-exam-hero">
      <div class="mobile-exam-hero-copy">
        <?php if ($featuredExam): ?>
          <small>ฝึกวันนี้</small>
          <h2><?= htmlspecialchars($featuredExam['subject'] ?: 'แบบทดสอบแนะนำ') ?></h2>
          <p><?= (int)$featuredExam['question_count'] ?> ข้อ<?= $featuredExam['time_limit_minutes'] ? ' • ' . (int)$featuredExam['time_limit_minutes'] . ' นาที' : '' ?></p>
          <a href="take-test.php?id=<?= (int)$featuredExam['id'] ?>">เริ่มทำข้อสอบ ›</a>
        <?php else: ?>
          <small>Practice Hub</small><h2>ข้อสอบกำลังจัดเตรียม</h2><p>กลับมาตรวจสอบอีกครั้งเร็วๆ นี้</p><a href="my-tests.php">ดูผลการเรียน ›</a>
        <?php endif; ?>
      </div>
      <img src="../assets/images/next-compass.png" alt="เข็มทิศ Next Beyond">
    </article>

    <div class="mobile-exam-search"><label><span>⌕</span><input id="mobile-exam-search" type="search" placeholder="ค้นหาข้อสอบ เช่น ภาษาอังกฤษ"></label><button type="button" aria-label="แสดงตัวกรอง" onclick="document.querySelector('.mobile-exam-chips')?.scrollIntoView({behavior:'smooth',block:'nearest'})">☷</button></div>
    <nav class="mobile-exam-chips" aria-label="ประเภทข้อสอบ">
      <button class="active" type="button" data-mobile-exam-filter="all">ทั้งหมด</button>
      <button type="button" data-mobile-exam-filter="pre">Pre-test</button>
      <button type="button" data-mobile-exam-filter="post">Post-test</button>
      <button type="button" data-mobile-exam-filter="mock">Mock</button>
    </nav>

    <div class="mobile-exam-list" id="mobile-exam-list">
      <?php foreach ($exams as $exam):
        $subject = trim((string)($exam['subject'] ?? '')) ?: 'ทั่วไป';
        $typeLabel = ['quiz'=>'Quiz','placement'=>'Mock','pretest'=>'Pre-test','posttest'=>'Post-test'][$exam['type']] ?? ucfirst((string)$exam['type']);
      ?>
        <a class="mobile-exam-row" href="take-test.php?id=<?= (int)$exam['id'] ?>" data-mobile-search="<?= htmlspecialchars(mb_strtolower($exam['title'].' '.$subject)) ?>" data-mobile-type="<?= studentExamMobileGroup((string)$exam['type']) ?>">
          <span class="mobile-exam-code"><?= htmlspecialchars(studentExamCode($subject, (string)$exam['title'])) ?></span>
          <span><b><?= htmlspecialchars($exam['title']) ?></b><small><?= htmlspecialchars($typeLabel) ?> • <?= (int)$exam['question_count'] ?> ข้อ<?= $exam['time_limit_minutes'] ? ' • ' . (int)$exam['time_limit_minutes'] . ' นาที' : '' ?></small></span>
          <strong>›</strong>
        </a>
      <?php endforeach; ?>
      <div class="mobile-exam-empty" <?= $exams ? 'hidden' : '' ?>>ยังไม่มีข้อสอบที่เปิดให้นักเรียนทำ</div>
    </div>

    <?php if ($recentAttempt):
      $recentPercent = (int)$recentAttempt['total_questions'] > 0 ? (int)round((int)$recentAttempt['correct_count'] * 100 / (int)$recentAttempt['total_questions']) : (int)round((float)$recentAttempt['score']);
    ?>
      <a class="mobile-latest-result" href="test-result.php?id=<?= (int)$recentAttempt['id'] ?>"><span>▥</span><span><small>ผลครั้งล่าสุด</small><b><?= htmlspecialchars($recentAttempt['subject'] ?: $recentAttempt['title']) ?> • <?= $recentPercent ?>%</b></span><strong>ดูเลย ›</strong></a>
    <?php else: ?>
      <a class="mobile-latest-result" href="my-tests.php"><span>▥</span><span><small>ผลครั้งล่าสุด</small><b>ยังไม่มีประวัติการสอบ</b></span><strong>ดูประวัติ ›</strong></a>
    <?php endif; ?>
  </section>

  <div class="student-exams-desktop">
  <section class="exam-banner">
    <div>
      <span class="exam-kicker">✦ คลังข้อสอบมาตรฐาน &amp; ข้อสอบจากผู้สอน</span>
      <h1>ทดสอบระดับและจำลองสอบเสมือนจริง</h1>
      <p>เลือกทำข้อสอบที่ผู้ดูแลเผยแพร่ไว้ให้คุณ<br>ฝึกทำโจทย์จับเวลาจริง พร้อมตรวจคำตอบและคำอธิบายอย่างละเอียด</p>
    </div>
    <a class="exam-action" href="my-tests.php">▣ ดูประวัติการสอบ</a>
  </section>

  <div class="exam-filters" aria-label="ตัวกรองข้อสอบ">
    <input id="exam-search" class="exam-filter exam-search" type="search" placeholder="ค้นหาชื่อข้อสอบหรือวิชา...">
    <select id="subject-filter" class="exam-filter">
      <option value="">ทุกวิชา</option>
      <?php foreach (array_keys($subjects) as $subject): ?>
        <option value="<?= htmlspecialchars(mb_strtolower($subject)) ?>"><?= htmlspecialchars($subject) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="type-filter" class="exam-filter">
      <option value="">ทุกประเภท</option>
      <option value="placement">Placement</option><option value="pretest">Pre-test</option>
      <option value="quiz">Quiz</option><option value="posttest">Post-test</option>
    </select>
    <span id="exam-count" class="exam-count"><?= count($exams) ?> ชุดข้อสอบ</span>
  </div>

  <section id="exam-grid" class="student-exam-grid">
    <?php if (!$exams): ?><div class="exam-empty">ยังไม่มีข้อสอบที่เปิดให้นักเรียนทำ</div><?php endif; ?>
    <?php foreach ($exams as $exam):
      $subject = trim((string)($exam['subject'] ?? '')) ?: 'ทั่วไป';
      $grade = trim((string)($exam['grade'] ?? '')) ?: 'ทุกระดับ';
      $done = $doneMap[(int)$exam['id']] ?? null;
      $typeLabel = ['quiz'=>'Quiz','placement'=>'Placement','pretest'=>'Pre-test','posttest'=>'Post-test'][$exam['type']] ?? ucfirst((string)$exam['type']);
    ?>
      <article class="student-exam-card"
        data-search="<?= htmlspecialchars(mb_strtolower($exam['title'].' '.$subject.' '.$grade)) ?>"
        data-subject="<?= htmlspecialchars(mb_strtolower($subject)) ?>" data-type="<?= htmlspecialchars((string)$exam['type']) ?>">
        <div class="exam-card-head"><span class="exam-subject"><?= htmlspecialchars($subject) ?></span><span class="exam-time">◷ <?= $exam['time_limit_minutes'] ? (int)$exam['time_limit_minutes'] . ' นาที' : 'ไม่จำกัดเวลา' ?></span></div>
        <h3><?= htmlspecialchars($exam['title']) ?></h3>
        <p><?= htmlspecialchars($grade) ?> · <?= htmlspecialchars($typeLabel) ?><?= $exam['is_ai_generated'] ? ' · สร้างด้วย AI' : '' ?></p>
        <div class="exam-card-meta">
          <span>ⓘ <?= (int)$exam['question_count'] ?> ข้อ</span>
          <span><?= $done ? 'คะแนนสูงสุด '.round((float)$done['best_score']).'%' : 'รหัส #'.(int)$exam['id'] ?></span>
        </div>
        <a class="exam-action" href="take-test.php?id=<?= (int)$exam['id'] ?>">▷ <?= $done ? 'ทำข้อสอบอีกครั้ง' : 'เริ่มทำข้อสอบ' ?></a>
      </article>
    <?php endforeach; ?>
  </section>
  <div id="no-results" class="exam-empty" hidden>ไม่พบข้อสอบที่ตรงกับการค้นหา</div>
  </div>
</main>
<?php include 'includes/bottom-nav.php'; ?>
</div>
</div>
<script>
(() => {
  const search = document.getElementById('exam-search'), subject = document.getElementById('subject-filter');
  const type = document.getElementById('type-filter'), count = document.getElementById('exam-count');
  const cards = [...document.querySelectorAll('.student-exam-card')], empty = document.getElementById('no-results');
  function filter() {
    const query = search.value.trim().toLocaleLowerCase('th'); let visible = 0;
    cards.forEach(card => {
      const show = (!query || card.dataset.search.includes(query)) && (!subject.value || card.dataset.subject === subject.value) && (!type.value || card.dataset.type === type.value);
      card.hidden = !show; if (show) visible++;
    });
    count.textContent = visible + ' ชุดข้อสอบ'; empty.hidden = visible > 0 || cards.length === 0;
  }
  search.addEventListener('input', filter); subject.addEventListener('change', filter); type.addEventListener('change', filter);

  const mobileSearch = document.getElementById('mobile-exam-search');
  const mobileRows = [...document.querySelectorAll('.mobile-exam-row')];
  const mobileButtons = [...document.querySelectorAll('[data-mobile-exam-filter]')];
  let mobileType = 'all';
  function filterMobile() {
    const query = (mobileSearch?.value || '').trim().toLocaleLowerCase('th');
    let visible = 0;
    mobileRows.forEach(row => {
      const show = (!query || row.dataset.mobileSearch.includes(query)) && (mobileType === 'all' || row.dataset.mobileType === mobileType);
      row.hidden = !show;
      if (show) visible++;
    });
    const emptyMobile = document.querySelector('.mobile-exam-empty');
    if (emptyMobile) emptyMobile.hidden = visible > 0;
  }
  mobileSearch?.addEventListener('input', filterMobile);
  mobileButtons.forEach(button => button.addEventListener('click', () => {
    mobileType = button.dataset.mobileExamFilter;
    mobileButtons.forEach(item => item.classList.toggle('active', item === button));
    filterMobile();
  }));
})();
</script>
</body>
</html>
