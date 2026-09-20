<?php
require_once __DIR__ . '/settings-service.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$isMaintenanceActive = SettingsService::isMaintenanceMode();
$isStaffUser = false;

if (!empty($_SESSION['user_id'])) {
    $userRole = (string) ($_SESSION['user_role'] ?? '');
    if (in_array($userRole, ['admin', 'teacher'], true)) {
        $isStaffUser = true;
    }
}

$currentScript = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
$isAuthPage = ($currentScript === 'auth.php' || ($currentPage ?? '') === 'auth.php');

if ($isMaintenanceActive && !$isStaffUser && !$isAuthPage) {
    http_response_code(503);
    require __DIR__ . '/maintenance-view.php';
    exit;
}

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
  <?php if ($isMaintenanceActive && $isStaffUser): ?>
  <aside aria-label="แจ้งเตือนสถานะระบบ" class="bg-amber-500 text-navy-950 font-semibold text-xs py-2 px-4 text-center shadow-sm flex items-center justify-center gap-2 z-50 relative">
    <span>⚠️ โหมดปิดปรับปรุงระบบกำลังเปิดใช้งาน (บุคคลทั่วไปไม่สามารถเข้าชมได้ คุณเข้าถึงได้ในฐานะเจ้าหน้าที่)</span>
    <a href="admin/settings.php" class="underline font-bold hover:text-white transition-colors">ไปที่การตั้งค่า</a>
  </aside>
  <?php endif; ?>
  <a class="skip-link" href="#main">ข้ามไปยังเนื้อหา</a>
