<?php
$pageTitle = 'ค้นหาคอร์สเรียน';
$currentPage = 'courses.php';
require_once __DIR__ . '/includes/guard.php';
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
    <main class="p-8 max-[640px]:p-4 flex-1">
      <section class="mb-8 border-b border-[#dce3ec] pb-7">
        <p class="student-kicker mb-2">COURSE CATALOG</p>
        <div class="flex items-end justify-between gap-5 flex-wrap">
          <div>
            <h2 class="text-[28px] font-bold text-navy-950">ค้นหาคอร์สเรียน</h2>
            <p class="mt-2 text-[14px] text-[#65738a]">ค้นหาและลงทะเบียนคอร์สเรียนใหม่ที่เหมาะกับคุณ</p>
          </div>
        </div>
      </section>

      <section class="min-h-[300px] flex flex-col items-center justify-center text-center bg-white border border-[#e8ecf2] rounded-xl shadow-sm p-8 mt-8">
        <span class="w-16 h-16 mb-5 rounded-2xl bg-[#f1f5ff] text-[#2369dd] flex items-center justify-center" aria-hidden="true">
          <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
        </span>
        <h2 class="text-[22px] font-bold text-navy-950 mb-2">กำลังเตรียมคอร์สเรียน</h2>
        <p class="max-w-[480px] text-[14px] leading-7 text-[#65738a] m-0">ระบบค้นหาและสมัครคอร์สจะเปิดให้บริการในเร็วๆ นี้</p>
      </section>

    </main>
    <?php include 'includes/bottom-nav.php'; ?>
  </div>
</div>
</body>
</html>
