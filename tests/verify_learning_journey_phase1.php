<?php
/**
 * Automated Verification Test Suite for NEXTBEYOND V2 — PHASE 1
 * LEARNING JOURNEY CORE INTEGRATION
 * 
 * Verifies Acceptance Tests 26, 27, 28, 29 and multi-course isolation.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/learning-journey-service.php';

$service = new LearningJourneyService($pdo);

// Ensure schema and fresh seed data
$service->ensureSchema();
$seedResults = $service->seedPhase1AcceptanceData();

echo "=======================================================\n";
echo "NEXTBEYOND V2 — PHASE 1: CORE INTEGRATION VERIFICATION\n";
echo "=======================================================\n\n";

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

// ----------------------------------------------------
// TEST 1: Acceptance Test 26 - Multi-Course Isolation
// ----------------------------------------------------
echo "--- TEST 1: Acceptance Test 26 (Multi-Course Isolation) ---\n";
$baitoeyId = (int)($seedResults['baitoey_id'] ?? 16);
$candyId = (int)($seedResults['candy_id'] ?? 17);

$stmtBaitoey = $pdo->prepare("SELECT course_id FROM enrollments WHERE user_id = ? AND status IN ('active', 'trial')");
$stmtBaitoey->execute([$baitoeyId]);
$baitoeyCourses = $stmtBaitoey->fetchAll(PDO::FETCH_COLUMN);

assertTest(
    "Baitoey has 3 distinct course enrollments",
    count($baitoeyCourses) === 3 && in_array(1, $baitoeyCourses) && in_array(2, $baitoeyCourses) && in_array(3, $baitoeyCourses),
    "Enrolled course IDs: " . implode(', ', $baitoeyCourses)
);

$stmtCandy = $pdo->prepare("SELECT course_id FROM enrollments WHERE user_id = ? AND status IN ('active', 'trial')");
$stmtCandy->execute([$candyId]);
$candyCourses = $stmtCandy->fetchAll(PDO::FETCH_COLUMN);

assertTest(
    "Candy has 2 distinct course enrollments (English & Math)",
    count($candyCourses) === 2 && in_array(1, $candyCourses) && in_array(4, $candyCourses),
    "Enrolled course IDs: " . implode(', ', $candyCourses)
);

// ----------------------------------------------------
// TEST 2: Acceptance Test 27 - Goal Setting Independence
// ----------------------------------------------------
echo "\n--- TEST 2: Acceptance Test 27 (Independent Goals per Course) ---\n";
$baitoeyEngGoals = $service->getStudentLearningGoals($baitoeyId, 1, 'active');
$baitoeyChemGoals = $service->getStudentLearningGoals($baitoeyId, 2, 'active');
$baitoeyBioGoals = $service->getStudentLearningGoals($baitoeyId, 3, 'active');

assertTest(
    "Baitoey English goal exists with target 75",
    !empty($baitoeyEngGoals) && (float)$baitoeyEngGoals[0]['target_score'] === 75.0,
    "Goal: " . ($baitoeyEngGoals[0]['goal_name'] ?? 'none') . " Target: " . ($baitoeyEngGoals[0]['target_score'] ?? 'none')
);

assertTest(
    "Baitoey Chemistry goal exists with target 70",
    !empty($baitoeyChemGoals) && (float)$baitoeyChemGoals[0]['target_score'] === 70.0,
    "Goal: " . ($baitoeyChemGoals[0]['goal_name'] ?? 'none') . " Target: " . ($baitoeyChemGoals[0]['target_score'] ?? 'none')
);

assertTest(
    "Baitoey Biology goal exists with target 80",
    !empty($baitoeyBioGoals) && (float)$baitoeyBioGoals[0]['target_score'] === 80.0,
    "Goal: " . ($baitoeyBioGoals[0]['goal_name'] ?? 'none')
);

// Verify isolated update
$savedGoalId = $service->saveLearningGoal($baitoeyId, 1, [
    'id' => (int)$baitoeyEngGoals[0]['id'],
    'goal_name' => 'A-Level English 90+',
    'target_score' => 90,
    'target_date' => '2026-12-31',
    'priority' => 'high',
    'status' => 'active'
]);
$updatedEngGoals = $service->getStudentLearningGoals($baitoeyId, 1, 'active');
$chemGoalsAfterUpdate = $service->getStudentLearningGoals($baitoeyId, 2, 'active');

assertTest(
    "Updating English goal does NOT alter Chemistry goal",
    (float)$updatedEngGoals[0]['target_score'] === 90.0 && (float)$chemGoalsAfterUpdate[0]['target_score'] === 70.0,
    "Eng target: {$updatedEngGoals[0]['target_score']}, Chem target: {$chemGoalsAfterUpdate[0]['target_score']}"
);

// Reset back to 75 for consistency
$service->saveLearningGoal($baitoeyId, 1, [
    'id' => (int)$baitoeyEngGoals[0]['id'],
    'goal_name' => 'A-Level English',
    'target_score' => 75,
    'target_date' => '2027-03-31',
    'priority' => 'high',
    'status' => 'active'
]);

// ----------------------------------------------------
// TEST 3: Acceptance Test 28 - Diagnostic & Learning Profile
// ----------------------------------------------------
echo "\n--- TEST 3: Acceptance Test 28 (Diagnostic & Learning Profile) ---\n";
$engProfile = $service->getStudentLearningProfile($baitoeyId, 1);

assertTest(
    "Baitoey English profile exists with baseline score 61%",
    $engProfile !== null && (float)$engProfile['baseline_score'] === 61.0,
    "Baseline score: " . ($engProfile['baseline_score'] ?? 'null') . "%"
);

$strengthTopics = array_column($engProfile['strengths'] ?? [], 'topic');
assertTest(
    "Present Simple (82%) identified in Strengths",
    in_array('Present Simple', $strengthTopics, true),
    "Strengths: " . implode(', ', $strengthTopics)
);

$needsTopics = array_column($engProfile['needs_improvement'] ?? [], 'topic');
assertTest(
    "Present Perfect (39%) identified in Needs Improvement",
    in_array('Present Perfect', $needsTopics, true),
    "Needs improvement: " . implode(', ', $needsTopics)
);

$topicMastery = $service->getStudentTopicMastery($baitoeyId, 1);
$masteryMap = [];
foreach ($topicMastery as $tm) {
    $masteryMap[$tm['topic_name']] = (float)$tm['mastery_score'];
}

assertTest(
    "Topic mastery table stores exact mastery scores",
    isset($masteryMap['Present Simple'], $masteryMap['Present Perfect'])
        && $masteryMap['Present Simple'] === 82.0
        && $masteryMap['Present Perfect'] === 39.0,
    "Present Simple: {$masteryMap['Present Simple']}%, Present Perfect: {$masteryMap['Present Perfect']}%"
);

// ----------------------------------------------------
// TEST 4: Acceptance Test 29 - Next Action Determination
// ----------------------------------------------------
echo "\n--- TEST 4: Acceptance Test 29 (Next Action Determination) ---\n";
$primaryNext = $service->getStudentNextAction($baitoeyId);

assertTest(
    "Next action for Baitoey is 'Present Perfect EP1'",
    $primaryNext !== null && $primaryNext['title'] === 'Present Perfect EP1',
    "Title: " . ($primaryNext['title'] ?? 'none')
);

assertTest(
    "Next action course is English (ID 1)",
    $primaryNext !== null && (int)$primaryNext['course_id'] === 1,
    "Course ID: " . ($primaryNext['course_id'] ?? 'none')
);

assertTest(
    "Next action button label is 'เรียนต่อ'",
    $primaryNext !== null && $primaryNext['action_label'] === 'เรียนต่อ',
    "Action label: " . ($primaryNext['action_label'] ?? 'none')
);

assertTest(
    "Next action URL points to lesson.php?id=3",
    $primaryNext !== null && $primaryNext['action_url'] === 'lesson.php?id=3',
    "Action URL: " . ($primaryNext['action_url'] ?? 'none')
);

// ----------------------------------------------------
// TEST 5: Multi-Course Next Actions
// ----------------------------------------------------
echo "\n--- TEST 5: Upcoming Next Action Across All Enrolled Courses ---\n";
$upcomingList = $service->getStudentUpcomingList($baitoeyId);

assertTest(
    "Upcoming list contains 3 courses for Baitoey",
    count($upcomingList) === 3,
    "Count: " . count($upcomingList)
);

$courseIdsInUpcoming = array_column($upcomingList, 'course_id');
assertTest(
    "Upcoming list includes course 1, 2, and 3",
    in_array(1, $courseIdsInUpcoming) && in_array(2, $courseIdsInUpcoming) && in_array(3, $courseIdsInUpcoming),
    "Course IDs: " . implode(', ', $courseIdsInUpcoming)
);

// ----------------------------------------------------
// TEST 6: Event-Ready Logging
// ----------------------------------------------------
echo "\n--- TEST 6: Learning Events Audit Log ---\n";
$stmtEvents = $pdo->prepare("SELECT COUNT(*) FROM learning_events WHERE student_id = ?");
$stmtEvents->execute([$baitoeyId]);
$eventCount = (int)$stmtEvents->fetchColumn();

assertTest(
    "Events recorded in learning_events table",
    $eventCount > 0,
    "Event count for Baitoey: $eventCount"
);

echo "\n=======================================================\n";
echo "VERIFICATION SUMMARY: $passCount PASSED, $failCount FAILED\n";
echo "=======================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
