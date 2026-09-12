<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/includes/access.php';

$pageTitle = 'AI Exam Generator';
$pageDesc = 'สร้าง ตรวจ และบันทึกข้อสอบด้วย AI';
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - Next Beyond</title>
  <link rel="stylesheet" href="../assets/css/output.css?v=<?= rawurlencode((string) filemtime(__DIR__ . '/../assets/css/output.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Noto+Sans+Thai:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    body { font-family:Inter,"Noto Sans Thai",sans-serif; }
  </style>
</head>
<body class="min-h-screen bg-[#f4f7fb] text-navy-950 antialiased">
  <header class="sticky top-0 z-40 border-b border-[#e8ecf2] bg-white/95 backdrop-blur">
    <div class="mx-auto flex h-[68px] max-w-[1120px] items-center gap-4 px-5">
      <a href="index.php" class="flex items-center gap-3 text-navy-950" aria-label="AI Exam Generator">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-navy-950 text-lg text-white">✦</span>
        <span class="grid leading-tight">
          <strong class="text-[15px] font-black tracking-wide">AI EXAM</strong>
          <small class="text-[10px] font-bold tracking-[.16em] text-pink-500">NEXT BEYOND</small>
        </span>
      </a>
      <div class="ml-auto flex items-center gap-2">
        <a href="../admin/tests.php" class="rounded-xl border border-[#dce4ef] px-4 py-2 text-[13px] font-bold text-[#4b5e7a] hover:bg-[#f8fafc]">คลังข้อสอบ</a>
        <a href="../admin/ai-exam.php" class="rounded-xl bg-pink-500 px-4 py-2 text-[13px] font-bold text-white">กลับ Admin</a>
      </div>
    </div>
  </header>

  <main class="mx-auto max-w-[1120px] px-5 py-9 max-[640px]:px-4 max-[640px]:py-5">
    <div class="mb-7 text-center">
      <p class="mb-2 text-[11px] font-black tracking-[.18em] text-pink-500">STANDALONE TOOL</p>
      <h1 class="text-[clamp(28px,5vw,42px)] font-black tracking-tight">สร้างข้อสอบด้วย AI</h1>
      <p class="mt-2 text-[14px] text-[#65738a]">นำเข้าเอกสาร สร้างข้อสอบ ตรวจคำตอบ และบันทึกลงคลังกลาง</p>
    </div>
    <?php require __DIR__ . '/app.php'; ?>
  </main>
</body>
</html>
