<?php
/**
 * tests/verify_phase3_integration.php
 * NEXTBEYOND V2 — PHASE 3: PRACTICE & MEASURE LAYER
 * End-to-End Verification of Acceptance Tests #1 through #8
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/phase3-mastery-service.php';
require_once __DIR__ . '/../includes/learning-journey-service.php';

$p3 = new Phase3MasteryService($pdo);
$journey = new LearningJourneyService($pdo);

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertCheck(string $testName, bool $condition, string $detail = ''): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo " [PASS] {$testName}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
    } else {
        $failedTests++;
        echo " [FAIL] {$testName}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
    }
}

echo "=======================================================\n";
echo "NEXTBEYOND V2 — PHASE 3: PRACTICE & MEASURE VERIFICATION\n";
echo "=======================================================\n\n";

// Fetch known users for testing
$baitoey = $pdo->query("SELECT id FROM users WHERE email = 'baitoey@student.nextbeyond.com' OR id = 16")->fetch(PDO::FETCH_ASSOC);
$candy   = $pdo->query("SELECT id FROM users WHERE email = 'candy@student.nextbeyond.com' OR id = 17")->fetch(PDO::FETCH_ASSOC);
$gigi    = $pdo->query("SELECT id FROM users WHERE email = 'gigijutamat@gmail.com' OR id = 6")->fetch(PDO::FETCH_ASSOC);

$baitoeyId = (int)($baitoey['id'] ?? 16);
$candyId   = (int)($candy['id'] ?? 17);
$gigiId    = (int)($gigi['id'] ?? 6);

// Clean any previous test artifacts before starting
$pdo->exec("
    DELETE FROM activity_answers WHERE submission_id IN (
        SELECT id FROM activity_submissions WHERE assignment_id IN (
            SELECT id FROM worksheet_assignments WHERE title LIKE 'Present Continuous Worksheet%'
                OR title LIKE 'Chemistry Chemical Bonding%'
                OR title LIKE 'Present Perfect Practice #2%'
                OR title LIKE 'Offline Sync Resilience%'
        )
    )
");
$pdo->exec("
    DELETE FROM activity_submissions WHERE assignment_id IN (
        SELECT id FROM worksheet_assignments WHERE title LIKE 'Present Continuous Worksheet%'
            OR title LIKE 'Chemistry Chemical Bonding%'
            OR title LIKE 'Present Perfect Practice #2%'
            OR title LIKE 'Offline Sync Resilience%'
    )
");
$pdo->exec("
    DELETE FROM worksheet_assignments WHERE title LIKE 'Present Continuous Worksheet%'
        OR title LIKE 'Chemistry Chemical Bonding%'
        OR title LIKE 'Present Perfect Practice #2%'
        OR title LIKE 'Offline Sync Resilience%'
");

// Ensure test course (Course 1: English) and class group (1: M5-ENG-A)
$course1Id = 1;
$classGroupId = 1; // M5-ENG-A

// Ensure a test worksheet with 10 questions exists for Present Continuous
$stmtWs = $pdo->prepare("SELECT id FROM worksheets WHERE title = 'Present Continuous Worksheet #1' LIMIT 1");
$stmtWs->execute();
$wsId = (int)$stmtWs->fetchColumn();

if (!$wsId) {
    $stmtInsWs = $pdo->prepare("
        INSERT INTO worksheets (title, subject, level, topic, subtopic, worksheet_type, difficulty, question_count, status)
        VALUES ('Present Continuous Worksheet #1', 'ภาษาอังกฤษ', 'ม.5', 'Present Continuous', 'Basic affirmative & continuous', 'Worksheet', 'medium', 10, 'published')
    ");
    $stmtInsWs->execute();
    $wsId = (int)$pdo->lastInsertId();

    for ($i = 1; $i <= 10; $i++) {
        $stmtQ = $pdo->prepare("
            INSERT INTO worksheet_questions (worksheet_id, sort_order, question_type, question_text, options, correct_answer, points, skill, difficulty)
            VALUES (?, ?, 'multipleChoice', ?, ?, ?, 1, 'Present Continuous', 'medium')
        ");
        $options = json_encode([
            ['key' => 'A', 'text' => 'is eating'],
            ['key' => 'B', 'text' => 'eats'],
            ['key' => 'C', 'text' => 'ate'],
            ['key' => 'D', 'text' => 'have eaten']
        ], JSON_UNESCAPED_UNICODE);
        $stmtQ->execute([$wsId, $i, "Question {$i}: She ___ dinner right now.", $options, 'A']);
    }
}

// Ensure questions exist
$qCount = (int)$pdo->query("SELECT COUNT(*) FROM worksheet_questions WHERE worksheet_id = {$wsId}")->fetchColumn();
if ($qCount < 10) {
    for ($i = $qCount + 1; $i <= 10; $i++) {
        $stmtQ = $pdo->prepare("
            INSERT INTO worksheet_questions (worksheet_id, sort_order, question_type, question_text, options, correct_answer, points, skill, difficulty)
            VALUES (?, ?, 'multipleChoice', ?, ?, ?, 1, 'Present Continuous', 'medium')
        ");
        $options = json_encode([
            ['key' => 'A', 'text' => 'is eating'],
            ['key' => 'B', 'text' => 'eats'],
            ['key' => 'C', 'text' => 'ate'],
            ['key' => 'D', 'text' => 'have eaten']
        ], JSON_UNESCAPED_UNICODE);
        $stmtQ->execute([$wsId, $i, "Question {$i}: She ___ dinner right now.", $options, 'A']);
    }
}

// ----------------------------------------------------
// ACCEPTANCE TEST #1: Quick Assign to Class Group (M5-ENG-A)
// ----------------------------------------------------
echo "--- ACCEPTANCE TEST #1: Class Group Assignment ---\n";
// Teacher finishes M5 English Present Continuous class -> assigns Present Continuous Worksheet #1 to M5-ENG-A due Friday
$dueDateFriday = date('Y-m-d 23:59:59', strtotime('next friday'));
$testSessionId = 'sess_p3_test_' . substr(md5((string)time()), 0, 8);

// Ensure classroom_sessions record exists for session link
$pdo->prepare("
    INSERT INTO classroom_sessions (id, title, teacher_id, status, session_pin, classroom_id)
    VALUES (?, 'M5 English Present Continuous', 1, 'closed', '1234', 1)
    ON DUPLICATE KEY UPDATE status = 'closed'
")->execute([$testSessionId]);

$assign1Id = $p3->createAssignment([
    'worksheet_id'   => $wsId,
    'course_id'      => $course1Id,
    'class_group_id' => $classGroupId,
    'session_id'     => $testSessionId,
    'title'          => 'Present Continuous Worksheet #1',
    'topic_name'     => 'Present Continuous',
    'activity_type'  => 'worksheet',
    'due_date'       => $dueDateFriday,
    'target_type'    => 'all',
    'max_attempts'   => 2,
    'pass_score'     => 70.0,
    'assigned_by'    => 1,
    'assigned_by_name'=> 'Kru Base',
]);

// Verify Baitoey (enrolled in M5-ENG-A / Course 1) has access
$canBaitoeyAccess = $p3->canStudentAccessAssignment($baitoeyId, $assign1Id);
assertCheck("Assignment created and Baitoey has access", $canBaitoeyAccess, "Assignment ID: {$assign1Id}");

// Verify assignment shows up in Baitoey's pending assignments
$baitoeyAssignments = $p3->getStudentAssignments($baitoeyId, $course1Id, 'all');
$foundAss = false;
foreach ($baitoeyAssignments as $a) {
    if ((int)$a['id'] === $assign1Id) {
        $foundAss = true;
        break;
    }
}
assertCheck("Assignment appears in student assignment list", $foundAss, "Title: Present Continuous Worksheet #1");

// Section 39 Check: Next Action should now be the pending assigned worksheet
$nextActAfterAssign = $journey->getStudentNextAction($baitoeyId, $course1Id);
assertCheck("Next action updates to assigned worksheet after class", $nextActAfterAssign !== null && $nextActAfterAssign['title'] === 'Present Continuous Worksheet #1', "Next action: " . ($nextActAfterAssign['title'] ?? 'none'));
assertCheck("Next action action_label is 'ทำใบงาน'", $nextActAfterAssign !== null && $nextActAfterAssign['action_label'] === 'ทำใบงาน', "Label: " . ($nextActAfterAssign['action_label'] ?? 'none'));


// ----------------------------------------------------
// ACCEPTANCE TEST #2: Autosave & Refresh Resilience
// ----------------------------------------------------
echo "\n--- ACCEPTANCE TEST #2: Autosave & State Recovery ---\n";
// Baitoey starts worksheet -> answers 5 / 10 questions -> simulates refresh
$submission1 = $p3->getOrCreateSubmission($assign1Id, $baitoeyId);
$sub1Id = (int)$submission1['id'];

// Get 10 questions for this worksheet
$wsQuestions = $pdo->query("SELECT id FROM worksheet_questions WHERE worksheet_id = {$wsId} ORDER BY sort_order ASC LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);

// Save 5 answers
for ($i = 0; $i < 5; $i++) {
    $qId = (int)$wsQuestions[$i];
    $res = $p3->autosaveAnswer($sub1Id, $baitoeyId, $qId, 'A', false, 15);
}

// Simulate browser refresh: fetch saved answers from server
$stmtAnswers = $pdo->prepare("SELECT question_id, student_answer FROM activity_answers WHERE submission_id = ?");
$stmtAnswers->execute([$sub1Id]);
$savedAnswers = $stmtAnswers->fetchAll(PDO::FETCH_KEY_PAIR);

assertCheck("5 answers saved on server after refresh", count($savedAnswers) === 5, "Saved count: " . count($savedAnswers));
assertCheck("Question 1 answer is retained", ($savedAnswers[$wsQuestions[0]] ?? '') === 'A', "Answer: " . ($savedAnswers[$wsQuestions[0]] ?? ''));
assertCheck("Question 5 answer is retained", ($savedAnswers[$wsQuestions[4]] ?? '') === 'A', "Answer: " . ($savedAnswers[$wsQuestions[4]] ?? ''));


// ----------------------------------------------------
// ACCEPTANCE TEST #3: Submit, Score 70%, Evidence & Skill Map
// ----------------------------------------------------
echo "\n--- ACCEPTANCE TEST #3: Submission, Evidence & Skill Map ---\n";
// Answer remaining 5 questions: 2 correct ('A'), 3 incorrect ('B') -> Total 7/10 = 70%
for ($i = 5; $i < 7; $i++) {
    $qId = (int)$wsQuestions[$i];
    $p3->autosaveAnswer($sub1Id, $baitoeyId, $qId, 'A'); // correct
}
for ($i = 7; $i < 10; $i++) {
    $qId = (int)$wsQuestions[$i];
    $p3->autosaveAnswer($sub1Id, $baitoeyId, $qId, 'B'); // incorrect
}

// Submit activity
$submitResult = $p3->submitActivity($sub1Id, $baitoeyId);
assertCheck("Submission processed successfully", $submitResult['success'] === true, "Message: " . ($submitResult['message'] ?? ''));
assertCheck("Score calculated correctly to 70%", (float)$submitResult['percentage'] === 70.0, "Score: {$submitResult['score']}/{$submitResult['max_score']} (70%)");

// Verify activity_submissions status is 'completed'
$stmtSubCheck = $pdo->prepare("SELECT status FROM activity_submissions WHERE id = ?");
$stmtSubCheck->execute([$sub1Id]);
$subStatus = $stmtSubCheck->fetchColumn();
assertCheck("Submission status is 'completed'", $subStatus === 'completed', "Status: {$subStatus}");

// Verify learning_evidence record created
$stmtEv = $pdo->prepare("SELECT * FROM learning_evidence WHERE source_type = 'worksheet' AND source_id = ? AND student_id = ?");
$stmtEv->execute([$sub1Id, $baitoeyId]);
$evRecord = $stmtEv->fetch(PDO::FETCH_ASSOC);
assertCheck("Learning evidence record created", !empty($evRecord), "Evidence ID: " . ($evRecord['id'] ?? 'none'));
assertCheck("Evidence normalized score is 70.0", (float)($evRecord['normalized_score'] ?? 0) === 70.0, "Score: " . ($evRecord['normalized_score'] ?? ''));

// Section 39 Check: After worksheet is completed, Next Action moves forward to next lesson/task
$nextActAfterComplete = $journey->getStudentNextAction($baitoeyId, $course1Id);
assertCheck("After worksheet submitted, Next Action moves to next lesson/task", $nextActAfterComplete !== null && $nextActAfterComplete['title'] === 'Present Perfect EP1', "Next action: " . ($nextActAfterComplete['title'] ?? 'none'));


// ----------------------------------------------------
// ACCEPTANCE TEST #4: Pre vs Post Comparison
// ----------------------------------------------------
echo "\n--- ACCEPTANCE TEST #4: Pre vs Post Comparison ---\n";
// Record a Pre-Test evidence (40%) and Post-Test evidence (80%) for Present Continuous
$p3->recordLearningEvidence([
    'student_id'       => $baitoeyId,
    'course_id'        => $course1Id,
    'topic_name'       => 'Present Continuous',
    'source_type'      => 'diagnostic',
    'score'            => 40.0,
    'max_score'        => 100.0,
    'normalized_score' => 40.0,
    'weight'           => 0.15,
    'occurred_at'      => date('Y-m-d H:i:s', strtotime('-5 days')),
]);

$p3->recordLearningEvidence([
    'student_id'       => $baitoeyId,
    'course_id'        => $course1Id,
    'topic_name'       => 'Present Continuous',
    'source_type'      => 'posttest',
    'score'            => 80.0,
    'max_score'        => 100.0,
    'normalized_score' => 80.0,
    'weight'           => 0.25,
    'occurred_at'      => date('Y-m-d H:i:s'),
]);

$compare = $p3->getPrePostComparison($baitoeyId, $course1Id, 'Present Continuous');
assertCheck("Pre vs Post comparison found", $compare !== null, "Topic: Present Continuous");
assertCheck("Pre score is 40%", (float)$compare['pre_score'] === 40.0, "Pre: {$compare['pre_score']}%");
assertCheck("Post score is 80%", (float)$compare['post_score'] === 80.0, "Post: {$compare['post_score']}%");
assertCheck("Improvement shows +40 points", (float)$compare['improvement_points'] === 40.0, "Improvement: +{$compare['improvement_points']} points");


// ----------------------------------------------------
// ACCEPTANCE TEST #5: Weighted Multi-Source Mastery Engine
// ----------------------------------------------------
echo "\n--- ACCEPTANCE TEST #5: Weighted Multi-Source Mastery Engine ---\n";
// Test student Candy on a specific topic 'Acid-Base Equilibrium' with the exact Section 70 weights:
// Get Ready: 60% (wt 0.05)
// In-Class Check 1: 40% (wt 0.10)
// In-Class Check 2: 80% (wt 0.10)
// Worksheet: 75% (wt 0.15)
// Homework: 80% (wt 0.15)
// Post-Test: 85% (wt 0.25)
$chemTopic = 'Acid-Base Equilibrium';
$chemCourseId = 2;

// Clean any previous test evidence for this test topic
$pdo->prepare("DELETE FROM learning_evidence WHERE student_id = ? AND topic_name = ?")->execute([$candyId, $chemTopic]);

$p3->recordLearningEvidence(['student_id' => $candyId, 'course_id' => $chemCourseId, 'topic_name' => $chemTopic, 'source_type' => 'get_ready', 'normalized_score' => 60.0, 'weight' => 0.05]);
$p3->recordLearningEvidence(['student_id' => $candyId, 'course_id' => $chemCourseId, 'topic_name' => $chemTopic, 'source_type' => 'in_class_check', 'normalized_score' => 40.0, 'weight' => 0.10]);
$p3->recordLearningEvidence(['student_id' => $candyId, 'course_id' => $chemCourseId, 'topic_name' => $chemTopic, 'source_type' => 'in_class_check', 'normalized_score' => 80.0, 'weight' => 0.10]);
$p3->recordLearningEvidence(['student_id' => $candyId, 'course_id' => $chemCourseId, 'topic_name' => $chemTopic, 'source_type' => 'worksheet', 'normalized_score' => 75.0, 'weight' => 0.15]);
$p3->recordLearningEvidence(['student_id' => $candyId, 'course_id' => $chemCourseId, 'topic_name' => $chemTopic, 'source_type' => 'homework', 'normalized_score' => 80.0, 'weight' => 0.15]);
$p3->recordLearningEvidence(['student_id' => $candyId, 'course_id' => $chemCourseId, 'topic_name' => $chemTopic, 'source_type' => 'posttest', 'normalized_score' => 85.0, 'weight' => 0.25]);

// Calculate weighted mastery
$masteryRes = $p3->updateStudentMastery($candyId, $chemCourseId, $chemTopic);

// Manual expected calculation:
// Total weights: 0.05 + 0.10 + 0.10 + 0.15 + 0.15 + 0.25 = 0.80
// Weighted sum: (60*0.05) + (40*0.10) + (80*0.10) + (75*0.15) + (80*0.15) + (85*0.25)
// = 3.0 + 4.0 + 8.0 + 11.25 + 12.0 + 21.25 = 59.5
// Normalized = 59.5 / 0.80 = 74.375% (74.38%)
$expectedMastery = 74.38;

assertCheck("Skill Map mastery calculated with configured source weights", abs((float)$masteryRes['mastery_score'] - $expectedMastery) < 0.1, "Calculated: {$masteryRes['mastery_score']}%, Expected: ~{$expectedMastery}%");
assertCheck("Evidence count tracked correctly (6 items)", (int)$masteryRes['evidence_count'] === 6, "Evidence count: {$masteryRes['evidence_count']}");
assertCheck("Confidence level is 'high' for 6 evidence items", $masteryRes['confidence_level'] === 'high', "Confidence: {$masteryRes['confidence_level']}");


// ----------------------------------------------------
// ACCEPTANCE TEST #6: Server-side Access Control (Unenrolled Course)
// ----------------------------------------------------
echo "\n--- ACCEPTANCE TEST #6: Server-side Access Control (Unenrolled) ---\n";
// Candy does NOT have Chemistry enrollment.
// Create a Chemistry Homework assignment
$chemAssignId = $p3->createAssignment([
    'title'          => 'Chemistry Chemical Bonding Homework',
    'course_id'      => 2, // Chemistry
    'topic_name'     => 'Chemical Bonding',
    'activity_type'  => 'homework',
    'target_type'    => 'all',
    'assigned_by'    => 1,
]);

$canCandyAccessChem = $p3->canStudentAccessAssignment($candyId, $chemAssignId);
assertCheck("Candy is blocked from accessing Chemistry assignment server-side", $canCandyAccessChem === false, "Access allowed: " . ($canCandyAccessChem ? 'YES' : 'NO'));

// Baitoey (who has Chemistry enrollment) should be allowed
$canBaitoeyAccessChem = $p3->canStudentAccessAssignment($baitoeyId, $chemAssignId);
assertCheck("Baitoey (enrolled in Chemistry) can access Chemistry assignment", $canBaitoeyAccessChem === true, "Access allowed: " . ($canBaitoeyAccessChem ? 'YES' : 'NO'));


// ----------------------------------------------------
// ACCEPTANCE TEST #7: Targeted Assignment Isolation
// ----------------------------------------------------
echo "\n--- ACCEPTANCE TEST #7: Individual Targeted Assignment Isolation ---\n";
// Assignment is given ONLY to Baitoey (student_id: 16)
$personalAssignId = $p3->createAssignment([
    'title'          => 'Present Perfect Practice #2 (Personalized)',
    'course_id'      => $course1Id,
    'topic_name'     => 'Present Perfect',
    'activity_type'  => 'practice',
    'target_type'    => 'selected',
    'student_ids'    => [$baitoeyId], // Only Baitoey
    'assigned_by'    => 1,
]);

$canBaitoeyAccessPersonal = $p3->canStudentAccessAssignment($baitoeyId, $personalAssignId);
$canGigiAccessPersonal    = $p3->canStudentAccessAssignment($gigiId, $personalAssignId);

assertCheck("Baitoey has access to her targeted assignment", $canBaitoeyAccessPersonal === true, "Allowed: YES");
assertCheck("Gigi does NOT have access even though in same English course", $canGigiAccessPersonal === false, "Gigi access: " . ($canGigiAccessPersonal ? 'YES' : 'BLOCKED'));


// ----------------------------------------------------
// ACCEPTANCE TEST #8: Offline Sync & Overwrite Protection
// ----------------------------------------------------
echo "\n--- ACCEPTANCE TEST #8: Offline Sync & Recency Protection ---\n";
// Create an assignment and submission for testing offline recovery
$syncAssignId = $p3->createAssignment([
    'title'          => 'Offline Sync Resilience Worksheet',
    'course_id'      => $course1Id,
    'activity_type'  => 'worksheet',
    'target_type'    => 'selected',
    'student_ids'    => [$baitoeyId],
    'assigned_by'    => 1,
]);

$subSync = $p3->getOrCreateSubmission($syncAssignId, $baitoeyId);
$syncSubId = (int)$subSync['id'];
$testQId = 99901;

// 1. Student saves answer on server at 10:00:00
$serverTime1 = '2026-09-20 10:00:00';
$p3->autosaveAnswer($syncSubId, $baitoeyId, $testQId, 'ServerNewerAnswer', false, 20, $serverTime1);

// 2. An older offline client backup timestamped 09:55:00 tries to sync
$olderClientTime = '2026-09-20 09:55:00';
$p3->syncPendingAnswers($syncSubId, $baitoeyId, [
    ['question_id' => $testQId, 'student_answer' => 'StaleOfflineAnswer', 'client_timestamp' => $olderClientTime]
]);

// Verify older client data did NOT overwrite newer server answer
$stmtCheckAns1 = $pdo->prepare("SELECT student_answer FROM activity_answers WHERE submission_id = ? AND question_id = ?");
$stmtCheckAns1->execute([$syncSubId, $testQId]);
$finalAns1 = $stmtCheckAns1->fetchColumn();
assertCheck("Older client state did NOT overwrite newer server answer", $finalAns1 === 'ServerNewerAnswer', "Current DB Answer: {$finalAns1}");

// 3. A newer offline client backup timestamped 10:05:00 syncs when internet restores
$newerClientTime = '2026-09-20 10:05:00';
$p3->syncPendingAnswers($syncSubId, $baitoeyId, [
    ['question_id' => $testQId, 'student_answer' => 'RestoredOnlineAnswer', 'client_timestamp' => $newerClientTime]
]);

$stmtCheckAns2 = $pdo->prepare("SELECT student_answer FROM activity_answers WHERE submission_id = ? AND question_id = ?");
$stmtCheckAns2->execute([$syncSubId, $testQId]);
$finalAns2 = $stmtCheckAns2->fetchColumn();
assertCheck("Newer restored client answer successfully updated server", $finalAns2 === 'RestoredOnlineAnswer', "Current DB Answer: {$finalAns2}");

// Teardown: Clean up test artifacts so Phase 1 and 2 tests remain pure
$pdo->prepare("DELETE FROM activity_answers WHERE submission_id IN (SELECT id FROM activity_submissions WHERE assignment_id IN (?, ?, ?, ?))")->execute([$assign1Id, $chemAssignId, $personalAssignId, $syncAssignId]);
$pdo->prepare("DELETE FROM activity_submissions WHERE assignment_id IN (?, ?, ?, ?)")->execute([$assign1Id, $chemAssignId, $personalAssignId, $syncAssignId]);
$pdo->prepare("DELETE FROM worksheet_assignments WHERE id IN (?, ?, ?, ?)")->execute([$assign1Id, $chemAssignId, $personalAssignId, $syncAssignId]);


// ----------------------------------------------------
// SUMMARY
// ----------------------------------------------------
echo "\n=======================================================\n";
echo "PHASE 3 VERIFICATION SUMMARY\n";
echo "=======================================================\n";
echo "Total Tests:  {$totalTests}\n";
echo "Passed:       {$passedTests}\n";
echo "Failed:       {$failedTests}\n";

if ($failedTests === 0) {
    echo "\n>>> ALL 8 ACCEPTANCE TESTS PASSED PERFECTLY! <<<\n";
    exit(0);
} else {
    echo "\n>>> SOME TESTS FAILED. PLEASE REVIEW OUTPUT ABOVE. <<<\n";
    exit(1);
}
