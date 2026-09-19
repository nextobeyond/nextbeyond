<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../admin/enrollments-service.php';

echo "=== NEXTBEYOND COMPASS: ACCEPTANCE TESTS VERIFICATION ===\n\n";

$service = new EnrollmentService($pdo);

// Run seed data to ensure environment matches exact scenario
$seed = $service->seedAcceptanceScenarioData();
echo "[SETUP] Seeded scenario: " . json_encode($seed, JSON_UNESCAPED_UNICODE) . "\n\n";

$studentIds = $seed['students'];
$courseIds = $seed['courses'];
$classGroupIds = $seed['class_groups'];

$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        echo "  [PASS] {$msg}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$msg}\n";
        $failed++;
    }
}

// ----------------------------------------------------
// TEST 1: Each student's dashboard only shows their purchased courses
// ----------------------------------------------------
echo "--- Acceptance Test #1: Course Access Isolation ---\n";
$btCourses = array_column($service->getStudentCourseAccess($studentIds['ใบเตย']), 'course_title');
$ggCourses = array_column($service->getStudentCourseAccess($studentIds['จีจี้']), 'course_title');
$cdCourses = array_column($service->getStudentCourseAccess($studentIds['แคนดี้']), 'course_title');
$aimCourses = array_column($service->getStudentCourseAccess($studentIds['เอม']), 'course_title');

sort($btCourses);
sort($ggCourses);
sort($cdCourses);
sort($aimCourses);

assertCondition($btCourses === ['M5 Biology', 'M5 Chemistry', 'M5 English'], "ใบเตย has English, Chemistry, Biology (found: " . implode(', ', $btCourses) . ")");
assertCondition($ggCourses === ['M5 Biology', 'M5 Chemistry', 'M5 English'], "จีจี้ has English, Chemistry, Biology (found: " . implode(', ', $ggCourses) . ")");
assertCondition($cdCourses === ['M5 English', 'M5 Mathematics'], "แคนดี้ has English, Mathematics (found: " . implode(', ', $cdCourses) . ")");
assertCondition($aimCourses === ['M5 English', 'M5 Physics'], "เอม has English, Physics (found: " . implode(', ', $aimCourses) . ")");

// ----------------------------------------------------
// TEST 2: M5 English Course & Class Group M5-ENG-A Roster & Calendar
// ----------------------------------------------------
echo "\n--- Acceptance Test #2: M5 English Course & Class Group Roster ---\n";
$engCourseId = $courseIds['M5 English'];
$engClassId = $classGroupIds['M5-ENG-A'];

// Check course roster
$stCourseRoster = $pdo->prepare("SELECT user_id FROM enrollments WHERE course_id = ? AND status IN ('active', 'trial')");
$stCourseRoster->execute([$engCourseId]);
$enrolledStudents = $stCourseRoster->fetchAll(PDO::FETCH_COLUMN);

assertCondition(count($enrolledStudents) === 4, "Course roster has 4 students (Count: " . count($enrolledStudents) . ")");
foreach (['ใบเตย', 'จีจี้', 'แคนดี้', 'เอม'] as $sName) {
    assertCondition(in_array($studentIds[$sName], $enrolledStudents), "{$sName} is in M5 English course roster");
}

// Check class roster
$stClassRoster = $pdo->prepare("SELECT user_id FROM enrollments WHERE class_group_id = ? AND status IN ('active', 'trial')");
$stClassRoster->execute([$engClassId]);
$classStudents = $stClassRoster->fetchAll(PDO::FETCH_COLUMN);

assertCondition(count($classStudents) === 4, "Class roster M5-ENG-A has 4 students (Count: " . count($classStudents) . ")");

// Check student calendar (English appears for all 4)
foreach (['ใบเตย', 'จีจี้', 'แคนดี้', 'เอม'] as $sName) {
    $sched = $service->getStudentCombinedSchedule($studentIds[$sName]);
    $hasEng = false;
    foreach ($sched as $c) {
        if ($c['course_title'] === 'M5 English' && $c['class_group_name'] === 'M5-ENG-A') {
            $hasEng = true;
        }
    }
    assertCondition($hasEng, "Student calendar for {$sName} includes M5-ENG-A");
}

// ----------------------------------------------------
// TEST 3: M5 Chemistry Access Guard (Only ใบเตย and จีจี้)
// ----------------------------------------------------
echo "\n--- Acceptance Test #3: Chemistry Access Guard ---\n";
$chemCourseId = $courseIds['M5 Chemistry'];

$btChem = $service->checkAccess($studentIds['ใบเตย'], $chemCourseId);
$ggChem = $service->checkAccess($studentIds['จีจี้'], $chemCourseId);
$cdChem = $service->checkAccess($studentIds['แคนดี้'], $chemCourseId);
$aimChem = $service->checkAccess($studentIds['เอม'], $chemCourseId);

assertCondition($btChem['has_access'] === true, "ใบเตย has access to Chemistry: ALLOWED");
assertCondition($ggChem['has_access'] === true, "จีจี้ has access to Chemistry: ALLOWED");
assertCondition($cdChem['has_access'] === false, "แคนดี้ DOES NOT have access to Chemistry: BLOCKED");
assertCondition($aimChem['has_access'] === false, "เอม DOES NOT have access to Chemistry: BLOCKED");

// ----------------------------------------------------
// TEST 4: M5 Physics Access Guard (Only เอม)
// ----------------------------------------------------
echo "\n--- Acceptance Test #4: Physics Access Guard ---\n";
$phyCourseId = $courseIds['M5 Physics'];

$btPhy = $service->checkAccess($studentIds['ใบเตย'], $phyCourseId);
$aimPhy = $service->checkAccess($studentIds['เอม'], $phyCourseId);

assertCondition($btPhy['has_access'] === false, "ใบเตย DOES NOT have access to Physics: BLOCKED");
assertCondition($aimPhy['has_access'] === true, "เอม has access to Physics: ALLOWED");

// ----------------------------------------------------
// TEST 5: Bulk Assign Duplicate Protection
// ----------------------------------------------------
echo "\n--- Acceptance Test #5: Bulk Assign Duplicate Protection ---\n";
// Admin selects all 4 students and bulk assigns M5 English
$all4Ids = array_values($studentIds);
$bulkResult = $service->bulkAssignCourse($all4Ids, $engCourseId, [
    'access_type' => 'paid',
    'learning_mode' => 'online'
]);

assertCondition($bulkResult['assigned_count'] === 0, "Assigned count is 0 because all 4 already have access (Assigned: {$bulkResult['assigned_count']})");
assertCondition($bulkResult['skipped_count'] === 4, "Skipped count is 4 (Skipped: {$bulkResult['skipped_count']})");

// Now try assigning a course only some have: M5 Mathematics (Candy has it, others don't)
$mathCourseId = $courseIds['M5 Mathematics'];
$bulkMathResult = $service->bulkAssignCourse([$studentIds['แคนดี้'], $studentIds['เอม']], $mathCourseId, [
    'access_type' => 'paid',
    'learning_mode' => 'online'
]);

assertCondition($bulkMathResult['assigned_count'] === 1, "Assigned 1 new student (เอม) to Mathematics");
assertCondition($bulkMathResult['skipped_count'] === 1, "Skipped 1 student (แคนดี้) due to existing enrollment");

// Summary
echo "\n====================================================\n";
echo "TEST RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "====================================================\n";

if ($failed > 0) {
    exit(1);
}
