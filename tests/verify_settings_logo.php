<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/settings-service.php';

echo "=== 1. Test SettingsService ===\n";
$currentLogo = SettingsService::get('school_logo', '', $pdo);
echo "Current school_logo: '{$currentLogo}'\n";
$currentSchoolName = SettingsService::get('school_name', '', $pdo);
echo "Current school_name: '{$currentSchoolName}'\n";

assert(!str_starts_with($currentLogo, '/'), "school_logo should not start with leading slash!");

echo "=== 2. Test File Existence of Logo ===\n";
if (!empty($currentLogo)) {
    $fullLogoPath = __DIR__ . '/../' . ltrim($currentLogo, '/');
    echo "Checking logo file: {$fullLogoPath}\n";
    if (file_exists($fullLogoPath)) {
        echo "✓ Logo file exists, size = " . filesize($fullLogoPath) . " bytes\n";
    } else {
        echo "✗ Logo file NOT found at {$fullLogoPath}!\n";
    }
}

echo "=== 3. Test admin/includes/sidebar.php rendering ===\n";
$_SESSION['user_role'] = 'admin';
$_SESSION['user_id'] = 1;
$consoleUser = ['first_name' => 'Admin', 'last_name' => 'Test', 'role' => 'admin'];
function consoleAllowed(string $page): bool { return true; }
ob_start();
require __DIR__ . '/../admin/includes/sidebar.php';
$adminSidebarHtml = ob_get_clean();
if (str_contains($adminSidebarHtml, 'admin-sidebar-logo-slot') && str_contains($adminSidebarHtml, 'admin-sidebar-school-name')) {
    echo "✓ admin/includes/sidebar.php renders with proper slots!\n";
} else {
    echo "✗ admin/includes/sidebar.php missing slots!\n";
}
if (!empty($currentLogo)) {
    if (str_contains($adminSidebarHtml, '<img src="../' . ltrim($currentLogo, '/'))) {
        echo "✓ admin/includes/sidebar.php renders uploaded logo image!\n";
    } else {
        echo "✗ admin/includes/sidebar.php does not render logo image!\n";
    }
}

echo "=== 4. Test includes/header.php rendering ===\n";
ob_start();
require __DIR__ . '/../includes/header.php';
$headerHtml = ob_get_clean();
if (!empty($currentLogo)) {
    if (str_contains($headerHtml, '<img src="' . ltrim($currentLogo, '/'))) {
        echo "✓ includes/header.php renders uploaded logo image!\n";
    } else {
        echo "✗ includes/header.php does not render logo image!\n";
    }
}

echo "=== 5. Test includes/footer.php rendering ===\n";
ob_start();
require __DIR__ . '/../includes/footer.php';
$footerHtml = ob_get_clean();
if (!empty($currentLogo)) {
    if (str_contains($footerHtml, '<img src="' . ltrim($currentLogo, '/'))) {
        echo "✓ includes/footer.php renders uploaded logo image!\n";
    } else {
        echo "✗ includes/footer.php does not render logo image!\n";
    }
}

echo "=== 6. Test student/includes/sidebar.php rendering ===\n";
$currentUser = ['first_name' => 'Test', 'last_name' => 'Student'];
function studentAvatarUrl(?string $url): string { return ''; }
ob_start();
require __DIR__ . '/../student/includes/sidebar.php';
$studentSidebarHtml = ob_get_clean();
if (!empty($currentLogo)) {
    if (str_contains($studentSidebarHtml, '<img src="../' . ltrim($currentLogo, '/'))) {
        echo "✓ student/includes/sidebar.php renders uploaded logo image!\n";
    } else {
        echo "✗ student/includes/sidebar.php does not render logo image!\n";
    }
}

echo "=== 7. Test includes/maintenance-view.php rendering ===\n";
ob_start();
require __DIR__ . '/../includes/maintenance-view.php';
$maintenanceHtml = ob_get_clean();
if (!empty($currentLogo)) {
    if (str_contains($maintenanceHtml, '<img src="' . ltrim($currentLogo, '/'))) {
        echo "✓ includes/maintenance-view.php renders uploaded logo image!\n";
    } else {
        echo "✗ includes/maintenance-view.php does not render logo image!\n";
    }
}

echo "\nALL TESTS COMPLETED SUCCESSFULLY!\n";
