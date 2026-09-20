<?php
declare(strict_types=1);

require_once __DIR__ . '/settings-service.php';

$schoolName = (string) SettingsService::get('school_name', 'Nextbeyond Compass');
$schoolLogo = (string) SettingsService::get('school_logo', '');
$schoolPhone = (string) SettingsService::get('school_phone', '02-123-4567');
$schoolEmail = (string) SettingsService::get('school_email', 'contact@nextbeyond.com');
$schoolAddress = (string) SettingsService::get('school_address', 'อาคาร Next Beyond ชั้น 3 เขตปทุมวัน กรุงเทพมหานคร 10330');

$pageTitle = $maintenanceTitle ?? "ปิดปรับปรุงระบบชั่วคราว | {$schoolName}";
$pageHeading = $maintenanceTitle ?? "ระบบอยู่ระหว่างการปรับปรุงชั่วคราว";
$pageSub = $maintenanceMessage ?? "เพื่อยกระดับประสิทธิภาพและประสบการณ์การเรียนรู้ที่ดีที่สุด ทีมงานกำลังดำเนินงานบำรุงรักษาระบบและจะกลับมาเปิดให้บริการโดยเร็วที่สุด ขออภัยในความไม่สะดวก";

$baseAssetPrefix = (strpos($_SERVER['REQUEST_URI'] ?? '', '/student/') !== false || strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false) ? '../' : '';
?>
<!DOCTYPE html>
<html lang="th" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= $baseAssetPrefix ?>assets/images/favicon.svg?v=2">
  <link rel="stylesheet" href="<?= $baseAssetPrefix ?>assets/css/output.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Prompt', 'Inter', sans-serif; }
  </style>
</head>
<body class="h-full bg-[#f8fafc] text-navy-950 flex flex-col justify-between antialiased selection:bg-pink-500 selection:text-white">
  <!-- Top decorative bar -->
  <div class="h-1.5 w-full bg-gradient-to-r from-pink-500 via-[#3b82f6] to-pink-500"></div>

  <main class="flex-1 flex items-center justify-center p-4 sm:p-6 md:p-10">
    <div class="w-full max-w-xl bg-white border border-[#e2e8f0] rounded-3xl shadow-[0_20px_50px_rgba(15,23,42,0.08)] p-8 sm:p-10 text-center relative overflow-hidden">
      <!-- Background subtle gradient circle -->
      <div class="absolute -right-20 -top-20 w-56 h-56 bg-pink-500/5 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -left-20 -bottom-20 w-56 h-56 bg-blue-500/5 rounded-full blur-3xl pointer-events-none"></div>

      <!-- Logo -->
      <div class="inline-flex items-center gap-3 mb-8">
        <?php if (!empty($schoolLogo)): ?>
          <img src="<?= $baseAssetPrefix . htmlspecialchars(ltrim($schoolLogo, '/')) ?>?v=<?= @filemtime(__DIR__ . '/../' . ltrim($schoolLogo, '/')) ?: time() ?>" alt="Logo" class="w-10 h-10 object-contain rounded-lg shrink-0">
        <?php else: ?>
          <svg class="w-10 h-10 shrink-0" viewBox="0 0 40 40" fill="none">
            <line x1="25" y1="32" x2="31" y2="8" stroke="#f54696" stroke-width="8" stroke-linecap="round" />
            <clipPath id="logo-clip-maint">
              <rect x="0" y="9" width="40" height="22" />
            </clipPath>
            <g clip-path="url(#logo-clip-maint)">
              <path d="M 8 36 L 15 4 L 27 36" fill="none" stroke="#0f172a" stroke-width="8.5" stroke-linejoin="miter" stroke-miterlimit="8" />
            </g>
          </svg>
        <?php endif; ?>
        <span class="grid leading-none text-left">
          <strong class="text-navy-950 text-[18px] tracking-[0.04em] font-bold"><?= htmlspecialchars(mb_strtoupper($schoolName)) ?></strong>
          <small class="text-[#65738a] text-[10px] font-bold tracking-[0.2em] uppercase mt-1">COMPASS LEARNING</small>
        </span>
      </div>

      <!-- Maintenance Badge & Icon -->
      <div class="mb-6 flex flex-col items-center">
        <div class="w-20 h-20 rounded-2xl bg-amber-50 border border-amber-200/80 flex items-center justify-center text-amber-500 mb-4 shadow-sm">
          <svg class="w-10 h-10 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.162 1.137-.098 1.637.18 1.48 0.82 2.76-0.46 1.94-1.94a2.986 2.986 0 00-.18-1.637c-.14-.468-.07-1.02.24-1.428l1.45-1.78a2.65 2.65 0 00-3.75-3.75l-1.78 1.45c-.408.31-.96.38-1.428.24a2.986 2.986 0 00-1.637-.18c-1.48-.82-2.76.46-1.94 1.94.278.5.342 1.087.18 1.637-.14.468-.382.891-.766 1.208l-3.03 2.496" />
          </svg>
        </div>
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[12px] font-semibold bg-amber-100 text-amber-800">
          <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
          ระบบปิดปรับปรุงชั่วคราว · Maintenance Mode
        </span>
      </div>

      <!-- Title and Subtitle -->
      <h1 class="text-2xl sm:text-3xl font-bold text-navy-950 tracking-tight mb-3">
        <?= htmlspecialchars($pageHeading) ?>
      </h1>
      <p class="text-[#64748b] text-[15px] leading-relaxed mb-8 max-w-lg mx-auto">
        <?= htmlspecialchars($pageSub) ?>
      </p>

      <!-- Contact Info Box -->
      <div class="bg-[#f8fafc] border border-[#e2e8f0] rounded-2xl p-5 mb-6 text-left space-y-3">
        <h2 class="text-[13px] font-bold text-navy-950 uppercase tracking-wider flex items-center gap-2">
          <svg class="w-4 h-4 text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
          ช่องทางติดต่อสถาบัน
        </h2>
        <?php if ($schoolPhone): ?>
          <div class="flex items-center gap-3 text-[14px] text-[#475569]">
            <svg class="w-4 h-4 text-[#94a3b8] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            <span>โทรศัพท์: <a href="tel:<?= htmlspecialchars($schoolPhone) ?>" class="text-pink-600 font-semibold hover:underline"><?= htmlspecialchars($schoolPhone) ?></a></span>
          </div>
        <?php endif; ?>
        <?php if ($schoolEmail): ?>
          <div class="flex items-center gap-3 text-[14px] text-[#475569]">
            <svg class="w-4 h-4 text-[#94a3b8] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <span>อีเมล: <a href="mailto:<?= htmlspecialchars($schoolEmail) ?>" class="text-pink-600 font-semibold hover:underline"><?= htmlspecialchars($schoolEmail) ?></a></span>
          </div>
        <?php endif; ?>
        <?php if ($schoolAddress): ?>
          <div class="flex items-start gap-3 text-[13px] text-[#64748b]">
            <svg class="w-4 h-4 text-[#94a3b8] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span><?= htmlspecialchars($schoolAddress) ?></span>
          </div>
        <?php endif; ?>
      </div>

      <!-- Action & Admin Link -->
      <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-4 text-[13px]">
        <button onclick="window.location.reload()" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-navy-950 hover:bg-navy-900 text-white font-semibold transition-colors flex items-center justify-center gap-2">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          ลองใหม่อีกครั้ง
        </button>
        <a href="<?= $baseAssetPrefix ?>auth.php" class="text-[#64748b] hover:text-navy-950 underline transition-colors">
          สำหรับผู้ดูแลระบบและอาจารย์ (เข้าสู่ระบบ)
        </a>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="py-4 text-center text-xs text-[#94a3b8]">
    &copy; <?= date('Y') ?> <?= htmlspecialchars($schoolName) ?>. สงวนลิขสิทธิ์ทั้งหมด
  </footer>
</body>
</html>
