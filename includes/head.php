<?php
$pageTitle = $pageTitle ?? "Nextbeyond Compass";
$pageDesc = $pageDesc ?? "Nextbeyond Compass — เรียนอย่างเป็นระบบ ไปได้ไกลกว่าเดิม";
?>
<!doctype html>
<html lang="th" class="scroll-smooth">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg?v=2">
  <?php if (!empty($preloadImage)): ?>
  <link rel="preload" as="image" href="<?= htmlspecialchars($preloadImage) ?>" fetchpriority="high">
  <?php endif; ?>
  <link rel="stylesheet" href="assets/css/output.css">
  <script defer src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
  <?= $extraHead ?? "" ?>
</head>
<body class="text-ink bg-surface font-sans leading-relaxed">
  <a class="skip-link" href="#main">ข้ามไปยังเนื้อหา</a>
