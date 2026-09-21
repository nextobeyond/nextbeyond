<?php
/**
 * Automated Verification Test Suite for NEXTBEYOND V2
 * LEARNING NAVIGATION REFACTOR ACCEPTANCE TESTS (AT #1 to #8)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../student/includes/guard.php';
require_once __DIR__ . '/../includes/learning-journey-service.php';
require_once __DIR__ . '/../includes/phase3-mastery-service.php';

$passCount = 0;
$failCount = 0;

function assertTest(string $testName, bool $condition, string $details = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] $testName" . ($details ? " ($details)" : "") . "\n";
    } else {
        $failCount++;
        echo " [FAIL] $testName" . ($details ? " ($details)" : "") . "\n";
    }
}

echo "=======================================================\n";
echo "NEXTBEYOND V2 — LEARNING NAVIGATION REFACTOR VERIFICATION\n";
echo "=======================================================\n\n";

// ----------------------------------------------------
// ACCEPTANCE TEST #1: Sidebar Menu Structure
// ----------------------------------------------------
echo "--- ACCEPTANCE TEST #1: Student Sidebar Menu ---\n";
ob_start();
$_SESSION['user_id'] = 35;
$currentUser = ['id' => 35, 'role' => 'student', 'first_name' => 'ใบเตย'];
include __DIR__ . '/../student/includes/sidebar.php';
$sidebarHtml = ob_get_clean();

assertTest("Sidebar contains 'คอร์สของฉัน'", str_contains($sidebarHtml, 'คอร์สของฉัน'));
assertTest("Sidebar contains 'ค้นหาคอร์ส'", str_contains($sidebarHtml, 'ค้นหาคอร์ส'));
assertTest("Sidebar contains 'เส้นทางการเรียน'", str_contains($sidebarHtml, 'เส้นทางการเรียน'));
assertTest("Sidebar contains 'แผนที่ทักษะ'", str_contains($sidebarHtml, 'แผนที่ทักษะ'));
assertTest("Sidebar does NOT contain 'Study Roadmap'", !str_contains($sidebarHtml, 'Study Roadmap'));

// ----------------------------------------------------
// ACCEPTANCE TEST #2: 3 Tabs on Learning Path Page
// ----------------------------------------------------
echo "\n--- ACCEPTANCE TEST #2: 3 Main Tabs in Learning Path ---\n";
ob_start();
$_GET = ['course_id' => 1, 'tab' => 'path'];
include __DIR__ . '/../student/learning-path.php';
$pathHtml = ob_get_clean();

assertTest("Tab 1 'เส้นทางของฉัน' exists", str_contains($pathHtml, 'เส้นทางของฉัน'));
assertTest("Tab 2 'แผนสัปดาห์นี้' exists", str_contains($pathHtml, 'แผนสัปดาห์นี้'));
assertTest("Tab 3 'ปฏิทิน' exists", str_contains($pathHtml, 'ปฏิทิน'));

// ----------------------------------------------------
// ACCEPTANCE TEST #3: Existing Study Roadmap Data in Weekly Plan
// ----------------------------------------------------
echo "\n--- ACCEPTANCE TEST #3: Study Roadmap in Weekly Plan ---\n";
ob_start();
$_GET = ['course_id' => 1, 'tab' => 'weekly'];
include __DIR__ . '/../student/learning-path.php';
$weeklyHtml = ob_get_clean();

assertTest("Weekly Plan tab renders task list", str_contains($weeklyHtml, 'แผนสัปดาห์นี้ (Weekly Study Plan)'));
assertTest("Weekly Plan preserves tasks", str_contains($weeklyHtml, 'Present Perfect EP1') || str_contains($weeklyHtml, 'ภารกิจ'));
assertTest("No Study Roadmap tasks lost in DB", (int)$pdo->query("SELECT COUNT(*) FROM roadmap_tasks")->fetchColumn() >= 12);

// ----------------------------------------------------
// ACCEPTANCE TEST #4: Old /student/roadmap 302 Redirect
// ----------------------------------------------------
echo "\n--- ACCEPTANCE TEST #4: /student/roadmap Backward Compatibility ---\n";
$roadmapContent = file_get_contents(__DIR__ . '/../student/roadmap.php');
assertTest("roadmap.php performs redirect", str_contains($roadmapContent, 'learning-path.php'));
assertTest("roadmap.php sets tab=weekly", str_contains($roadmapContent, "'weekly'") || str_contains($roadmapContent, '"weekly"'));
assertTest("roadmap.php preserves query parameters", str_contains($roadmapContent, 'http_build_query'));

// ----------------------------------------------------
// ACCEPTANCE TEST #5: Multi-Course Switching (English -> Chemistry)
// ----------------------------------------------------
echo "\n--- ACCEPTANCE TEST #5: Course Switching Updates Everything ---\n";
// Test English
ob_start();
$_GET = ['course_id' => 1, 'tab' => 'path'];
include __DIR__ . '/../student/learning-path.php';
$engHtml = ob_get_clean();

// Test Chemistry
ob_start();
$_GET = ['course_id' => 2, 'tab' => 'path'];
include __DIR__ . '/../student/learning-path.php';
$chemHtml = ob_get_clean();

assertTest("English view shows English Goal (A-Level English)", str_contains($engHtml, 'A-Level English'));
assertTest("Chemistry view shows Chemistry Goal (A-Level Chemistry)", str_contains($chemHtml, 'A-Level Chemistry'));
assertTest("English view shows English Topics", str_contains($engHtml, 'Present Simple') || str_contains($engHtml, 'Present Perfect'));
assertTest("Chemistry view shows Chemistry Topics", str_contains($chemHtml, 'สารสัมพันธ์') || str_contains($chemHtml, 'กรด-เบส'));

// ----------------------------------------------------
// ACCEPTANCE TEST #6: Adaptive Step Injection on Learning Gap
// ----------------------------------------------------
echo "\n--- ACCEPTANCE TEST #6: Adaptive Step Injection ---\n";
// In English, student 35 has a gap in Present Continuous / Present Perfect
assertTest("Learning Path shows 'แบบฝึกเฉพาะจุด' for gap topic", str_contains($engHtml, 'แบบฝึกเฉพาะจุด'));
assertTest("Learning Path shows 'Mastery Check' after gap topic", str_contains($engHtml, 'Mastery Check'));

// ----------------------------------------------------
// ACCEPTANCE TEST #7: Skill Map Isolation & Backlinks
// ----------------------------------------------------
echo "\n--- ACCEPTANCE TEST #7: Skill Map Standalone & Backlinks ---\n";
$skillMapContent = file_get_contents(__DIR__ . '/../student/skill-map.php');
assertTest("Learning Path has link to full Skill Map", str_contains($engHtml, 'skill-map.php'));
assertTest("Skill Map page remains full standalone page", str_contains($skillMapContent, 'LEARNING MASTERY ENGINE'));
assertTest("Skill Map topic cards have 'ดูในเส้นทางการเรียน' backlink", str_contains($skillMapContent, 'ดูในเส้นทางการเรียน'));

// ----------------------------------------------------
// ACCEPTANCE TEST #8: Empty State for Student with No Learning Path
// ----------------------------------------------------
echo "\n--- ACCEPTANCE TEST #8: Clean Empty State (No Path) ---\n";
ob_start();
// Student 99999 has no enrollments
$currentUser = ['id' => 99999, 'role' => 'student', 'first_name' => 'New Student'];
$_GET = [];
include __DIR__ . '/../student/learning-path.php';
$emptyHtml = ob_get_clean();

assertTest("Empty state headline 'ยังไม่มีเส้นทางการเรียน'", str_contains($emptyHtml, 'ยังไม่มีเส้นทางการเรียน'));
assertTest("Empty state has 'ทำแบบประเมิน' button", str_contains($emptyHtml, 'ทำแบบประเมิน'));
assertTest("Empty state has 'ดูคอร์ส' button", str_contains($emptyHtml, 'ดูคอร์ส'));
assertTest("Empty state does NOT show confusing roadmap templates", !str_contains($emptyHtml, 'อยากได้ Roadmap แบบไหน'));

echo "\n=======================================================\n";
echo "SUMMARY: $passCount PASSED, $failCount FAILED\n";
echo "=======================================================\n";

if ($failCount > 0) {
    exit(1);
}
