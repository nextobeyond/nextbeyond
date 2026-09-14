<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/access.php';

$pageTitle = 'สร้างข้อสอบด้วย AI';
$currentPage = 'ai-exam-app/';
$adminLinkPrefix = '../';
$pageDesc = 'สร้าง ตรวจ และบันทึกข้อสอบด้วย AI';
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - Next Beyond</title>
  <link rel="stylesheet" href="../../assets/css/output.css?v=<?= rawurlencode((string) filemtime(__DIR__ . '/../../assets/css/output.css')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Noto+Sans+Thai:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    body { font-family:Inter,"Noto Sans Thai",sans-serif; }
  </style>
</head>
<body class="min-h-screen bg-[#f4f7fb] text-navy-950 antialiased">
  <div class="min-h-screen flex">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0">
      <?php require __DIR__ . '/../includes/topbar.php'; ?>
  <main class="w-full mx-auto max-w-[1120px] px-5 py-9 max-[640px]:px-4 max-[640px]:py-5">
    <div class="mb-7 text-center">
      <p class="mb-2 text-[11px] font-black tracking-[.18em] text-pink-500">AI EXAM</p>
      <h1 class="text-[clamp(28px,5vw,42px)] font-black tracking-tight">สร้างข้อสอบด้วย AI</h1>
      <p class="mt-2 text-[14px] text-[#65738a]">นำเข้าเอกสาร สร้างข้อสอบ ตรวจคำตอบ และบันทึกลงคลังกลาง</p>
    </div>
    <?php require __DIR__ . '/app.php'; ?>
  </main>
    </div>
  </div>
</body>
</html>
