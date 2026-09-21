<?php
declare(strict_types=1);

/**
 * Test Suite: Individual Student Assignment in /admin/worksheets
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/phase3-mastery-service.php';

echo "=== Running Individual Student Assignment Tests ===\n\n";

$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name} - Details: {$details}\n";
        $failed++;
    }
}

// 1. Test get_class_students via API logic
echo "Test 1: Querying students enrolled in Course 1 (M5 English)...\n";
$st = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.nickname, u.email, u.grade, u.avatar_url,
           CONCAT('NB', LPAD(u.id, 3, '0')) AS student_code,
           GROUP_CONCAT(DISTINCT cg.name SEPARATOR ', ') AS class_group_names
    FROM enrollments e
    JOIN users u ON u.id = e.user_id AND u.role = 'student' AND (u.is_active IS NULL OR u.is_active = 1)
    LEFT JOIN class_groups cg ON cg.id = e.class_group_id
    WHERE e.status IN ('active', 'trial')
      AND (e.end_date IS NULL OR e.end_date >= CURDATE())
      AND e.course_id = 1
    GROUP BY u.id
    ORDER BY u.first_name ASC, u.last_name ASC
");
$st->execute();
$students = $st->fetchAll(PDO::FETCH_ASSOC);

assertTest("Course 1 has enrolled students", count($students) > 0, "Found " . count($students) . " students");
$studentIds = array_column($students, 'id');
assertTest("Student NB035 (ใบเตย) is in Course 1 roster", in_array(35, $studentIds, true));
assertTest("Student codes are properly formatted with NB prefix", !empty($students[0]['student_code']) && str_starts_with($students[0]['student_code'], 'NB'));

// 2. Pick a test worksheet
$wsStmt = $pdo->query("SELECT id, title FROM worksheets WHERE status = 'published' LIMIT 1");
$ws = $wsStmt->fetch(PDO::FETCH_ASSOC);
if (!$ws) {
    // Create a temporary test worksheet
    $pdo->query("INSERT INTO worksheets (title, subject, level, status) VALUES ('Test Automation WS', 'ภาษาอังกฤษ', 'ม.5', 'published')");
    $wsId = (int)$pdo->lastInsertId();
    $wsTitle = 'Test Automation WS';
} else {
    $wsId = (int)$ws['id'];
    $wsTitle = (string)$ws['title'];
}
echo "Using Worksheet ID {$wsId} ({$wsTitle})\n\n";

$p3 = new Phase3MasteryService($pdo);

// 3. Test Whole-Class Assignment
echo "Test 2: Whole-Class Assignment (target_type = 'all')...\n";
$allAssignId = $p3->createAssignment([
    'worksheet_id' => $wsId,
    'course_id' => 1,
    'class_name' => 'Test Class M.5',
    'target_type' => 'all',
    'due_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
    'title' => 'Whole Class Test Assignment',
    'assigned_by' => 2,
    'assigned_by_name' => 'Admin'
]);

assertTest("Whole-class assignment created", $allAssignId > 0);
assertTest("Student 35 (enrolled) can access whole-class assignment", $p3->canStudentAccessAssignment(35, $allAssignId));
assertTest("Student 36 (enrolled) can access whole-class assignment", $p3->canStudentAccessAssignment(36, $allAssignId));

// Clean up whole-class assignment
$pdo->prepare("DELETE FROM worksheet_assignments WHERE id = ?")->execute([$allAssignId]);

// 4. Test Individual Student Assignment
echo "\nTest 3: Individual Assignment (target_type = 'selected')...\n";
// Assign ONLY to student 35 and 36 (NOT 37, NOT 38)
$selectedAssignId = $p3->createAssignment([
    'worksheet_id' => $wsId,
    'course_id' => 1,
    'class_name' => 'Test Individual Class',
    'target_type' => 'selected',
    'student_ids' => [35, 36],
    'due_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
    'title' => 'Individual Targeted Worksheet',
    'assigned_by' => 2,
    'assigned_by_name' => 'Admin'
]);

assertTest("Individual assignment created", $selectedAssignId > 0);
assertTest("Selected student 35 CAN access the worksheet", $p3->canStudentAccessAssignment(35, $selectedAssignId));
assertTest("Selected student 36 CAN access the worksheet", $p3->canStudentAccessAssignment(36, $selectedAssignId));
assertTest("Non-selected student 37 CANNOT access the worksheet (Isolation)", !$p3->canStudentAccessAssignment(37, $selectedAssignId));
assertTest("Non-selected student 38 CANNOT access the worksheet (Isolation)", !$p3->canStudentAccessAssignment(38, $selectedAssignId));

// 5. Verify Student-facing query: getStudentAssignments()
echo "\nTest 4: Student-Facing Query getStudentAssignments()...\n";
$student35Assignments = $p3->getStudentAssignments(35, 1);
$student35AssignIds = array_column($student35Assignments, 'id');
assertTest("Student 35 sees the assignment in their task list", in_array($selectedAssignId, $student35AssignIds, true));

$student37Assignments = $p3->getStudentAssignments(37, 1);
$student37AssignIds = array_column($student37Assignments, 'id');
assertTest("Student 37 DOES NOT see the assignment in their task list", !in_array($selectedAssignId, $student37AssignIds, true));

// 6. Test Duplicate Protection
echo "\nTest 5: Duplicate Protection...\n";
// Simulating the backend duplicate check logic when assigning to [35, 36] again
$existingStmt = $pdo->prepare("
    SELECT target_type, student_ids, course_id
    FROM worksheet_assignments
    WHERE worksheet_id = :wid AND status = 'active'
");
$existingStmt->execute([':wid' => $wsId]);
$existingAssigns = $existingStmt->fetchAll(PDO::FETCH_ASSOC);

$alreadyAssignedIds = [];
foreach ($existingAssigns as $ea) {
    if ($ea['target_type'] === 'selected') {
        $eaSids = json_decode((string)$ea['student_ids'], true) ?: [];
        $alreadyAssignedIds = array_merge($alreadyAssignedIds, array_map('intval', $eaSids));
    }
}
$alreadyAssignedIds = array_unique($alreadyAssignedIds);

$targetStudents = [35, 36];
$newStudents = array_values(array_diff($targetStudents, $alreadyAssignedIds));
assertTest("Duplicate protection detects students [35, 36] are already assigned", empty($newStudents));

// If we add student 37 as well [35, 36, 37]:
$mixedStudents = [35, 36, 37];
$newMixed = array_values(array_diff($mixedStudents, $alreadyAssignedIds));
assertTest("Partial duplicate correctly filters out [35, 36] and keeps only [37]", $newMixed === [37]);

// Cleanup test assignment
$pdo->prepare("DELETE FROM worksheet_assignments WHERE id = ?")->execute([$selectedAssignId]);
echo "\nTest assignment {$selectedAssignId} cleaned up.\n";

echo "\n=== Test Summary: {$passed} passed, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);
