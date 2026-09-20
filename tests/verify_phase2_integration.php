<?php
/**
 * Automated Verification Test Suite for NEXTBEYOND V2 — PHASE 2
 * PREPARE & LEARN INTEGRATION (Calendar → Get Ready → Class → In-Class Check)
 * 
 * Verifies 8 Checkpoints:
 * 1. Schema migration (tables + columns)
 * 2. Calendar-Session bi-directional link
 * 3. Student dashboard "Next Class" lookup within 72h
 * 4. Get Ready page data loading + topics
 * 5. Readiness recording & percentage calculation
 * 6. Teacher readiness summary stats
 * 7. In-Class Understanding check recording & stats
 * 8. Post-class sync to Topic Mastery (Phase 1 bridge)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/phase2-session-service.php';
require_once __DIR__ . '/../includes/live-sessions-helper.php';

$p2 = new Phase2SessionService($pdo);

echo "=======================================================\n";
echo "NEXTBEYOND V2 — PHASE 2: PREPARE & LEARN VERIFICATION\n";
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
// CHECKPOINT 1: Schema Migration
// ----------------------------------------------------
echo "--- CHECKPOINT 1: Schema Migration ---\n";
ensureLiveSessionSchema($pdo); // triggers ensurePhase2Schema as well
$p2->ensurePhase2Schema();

// Verify session_topics
$st = $pdo->query("SHOW TABLES LIKE 'session_topics'")->fetch();
assertTest("Table session_topics exists", !empty($st));

// Verify session_readiness
$sr = $pdo->query("SHOW TABLES LIKE 'session_readiness'")->fetch();
assertTest("Table session_readiness exists", !empty($sr));

// Verify session_understanding_checks
$suc = $pdo->query("SHOW TABLES LIKE 'session_understanding_checks'")->fetch();
assertTest("Table session_understanding_checks exists", !empty($suc));

// Verify calendar_events.session_id column
$col1 = $pdo->query("SHOW COLUMNS FROM calendar_events LIKE 'session_id'")->fetch();
assertTest("Column calendar_events.session_id exists", !empty($col1));

// Verify classroom_sessions.calendar_event_id column
$col2 = $pdo->query("SHOW COLUMNS FROM classroom_sessions LIKE 'calendar_event_id'")->fetch();
assertTest("Column classroom_sessions.calendar_event_id exists", !empty($col2));


// ----------------------------------------------------
// CHECKPOINT 2: Calendar-Session Link
// ----------------------------------------------------
echo "\n--- CHECKPOINT 2: Calendar-Session Link ---\n";

// Pre-test cleanup of any previous test artifacts
$pdo->exec("DELETE FROM session_understanding_checks WHERE session_id LIKE 'p2_test_%'");
$pdo->exec("DELETE FROM session_readiness WHERE calendar_event_id IN (SELECT id FROM calendar_events WHERE title = 'Phase 2 Test Class')");
$pdo->exec("DELETE FROM session_topics WHERE calendar_event_id IN (SELECT id FROM calendar_events WHERE title = 'Phase 2 Test Class') OR session_id LIKE 'p2_test_%'");
$pdo->exec("DELETE FROM classroom_sessions WHERE id LIKE 'p2_test_%'");
$pdo->exec("DELETE FROM calendar_events WHERE title = 'Phase 2 Test Class'");
$pdo->exec("DELETE FROM topic_mastery WHERE student_id IN (9991, 9992)");

// Get a valid teacher, course, and exam
$teacher = $pdo->query("SELECT id FROM users WHERE role IN ('teacher','admin') AND is_active = 1 LIMIT 1")->fetch();
$teacherId = (int)($teacher['id'] ?? 1);
$course = $pdo->query("SELECT id FROM courses LIMIT 1")->fetch();
$courseId = (int)($course['id'] ?? 1);
$exam = $pdo->query("SELECT id FROM exams LIMIT 1")->fetch();
$examId = (int)($exam['id'] ?? 1);

// Create a test calendar event for tomorrow
$tomorrow = (new DateTimeImmutable('+1 day'))->format('Y-m-d');
$stmtCe = $pdo->prepare("
    INSERT INTO calendar_events (title, event_type, course_id, teacher_id, event_date, start_time, end_time, created_by, status)
    VALUES ('Phase 2 Test Class', 'lesson', :cid, :tid, :edate, '10:00:00', '12:00:00', :cb, 'scheduled')
");
$stmtCe->execute([':cid' => $courseId, ':tid' => $teacherId, ':edate' => $tomorrow, ':cb' => $teacherId]);
$testEventId = (int)$pdo->lastInsertId();

// Tag 3 topics
$p2->saveTopicsForEvent($testEventId, ['Topic Alpha', 'Topic Beta', 'Topic Gamma']);
$topics = $p2->getEventTopics($testEventId);
assertTest("Event topics tagged correctly", count($topics) === 3 && $topics[0]['topic_name'] === 'Topic Alpha', "Count: " . count($topics));

// Create a live session linked to this event
$testSessionId = 'p2_test_' . bin2hex(random_bytes(4));
$stmtCs = $pdo->prepare("
    INSERT INTO classroom_sessions (id, session_pin, title, teacher_id, exam_id, status, calendar_event_id)
    VALUES (:id, '999888', 'Phase 2 Test Session', :tid, :eid, 'active', :ceid)
");
$stmtCs->execute([':id' => $testSessionId, ':tid' => $teacherId, ':eid' => $examId, ':ceid' => $testEventId]);

// Link bi-directionally
$p2->linkSessionToEvent($testSessionId, $testEventId);

$stmtCheckCe = $pdo->prepare("SELECT session_id FROM calendar_events WHERE id = ?");
$stmtCheckCe->execute([$testEventId]);
$linkedSessionId = $stmtCheckCe->fetchColumn();
assertTest("Calendar event has reverse link to session_id", $linkedSessionId === $testSessionId, "Linked: $linkedSessionId");

// Verify session topics were copied from event
$sessionTopics = $p2->getSessionTopics($testSessionId);
assertTest("Session topics automatically inherited from calendar event", count($sessionTopics) === 3, "Count: " . count($sessionTopics));


// ----------------------------------------------------
// CHECKPOINT 3: Student Dashboard "Next Class" Lookup
// ----------------------------------------------------
echo "\n--- CHECKPOINT 3: Student Dashboard Next Class Lookup ---\n";

// Find or enroll a student in this course
$student = $pdo->query("SELECT id FROM users WHERE role = 'student' AND is_active = 1 LIMIT 1")->fetch();
$studentId = (int)($student['id'] ?? 16);

// Ensure active enrollment
$pdo->prepare("
    INSERT INTO enrollments (user_id, course_id, status, start_date)
    VALUES (:uid, :cid, 'active', CURDATE())
    ON DUPLICATE KEY UPDATE status = 'active'
")->execute([':uid' => $studentId, ':cid' => $courseId]);

$upcoming = $p2->getUpcomingEventsForStudent($studentId, 72);
assertTest("Student receives upcoming classes within 72h", !empty($upcoming), "Upcoming count: " . count($upcoming));
$foundTestEvent = array_filter($upcoming, fn($e) => (int)$e['id'] === $testEventId);
assertTest("Student can see the test event in upcoming list", !empty($foundTestEvent), "Found event ID $testEventId");


// ----------------------------------------------------
// CHECKPOINT 4: Get Ready Page Data Loading
// ----------------------------------------------------
echo "\n--- CHECKPOINT 4: Get Ready Page Data Loading ---\n";

$eventForStudent = $p2->getEventForStudent($testEventId, $studentId);
assertTest("getEventForStudent succeeds for enrolled student", !empty($eventForStudent) && $eventForStudent['title'] === 'Phase 2 Test Class');

$readinessList = $p2->getStudentReadiness($studentId, $testEventId);
assertTest("Initial student readiness shows all topics unreviewed", count($readinessList) === 3 && $readinessList[0]['status'] === 'not_started');


// ----------------------------------------------------
// CHECKPOINT 5: Readiness Recording & Percentage
// ----------------------------------------------------
echo "\n--- CHECKPOINT 5: Readiness Recording & Percentage ---\n";

// Student reviews Topic Alpha and Topic Beta (2 out of 3 = 67%)
$p2->saveReadiness($studentId, $testEventId, 'Topic Alpha', 'reviewed');
$p2->saveReadiness($studentId, $testEventId, 'Topic Beta', 'reviewed');

$updatedReadiness = $p2->getStudentReadiness($studentId, $testEventId);
$reviewedCount = count(array_filter($updatedReadiness, fn($r) => $r['status'] === 'reviewed'));
assertTest("Readiness statuses saved (2/3 reviewed)", $reviewedCount === 2);

// Check readiness % in student upcoming view
$refreshedUpcoming = $p2->getUpcomingEventsForStudent($studentId, 72);
$matching = array_values(array_filter($refreshedUpcoming, fn($e) => (int)$e['id'] === $testEventId));
assertTest("Readiness percentage in student view calculates to ~67%", !empty($matching) && $matching[0]['readiness_pct'] === 67, "Pct: " . ($matching[0]['readiness_pct'] ?? 'none'));


// ----------------------------------------------------
// CHECKPOINT 6: Teacher Readiness Summary Stats
// ----------------------------------------------------
echo "\n--- CHECKPOINT 6: Teacher Readiness Summary Stats ---\n";

$teacherSummary = $p2->getEventReadinessSummaryForTeacher($testEventId);
assertTest("Teacher readiness summary contains total_students and ready_students", isset($teacherSummary['total_students']) && isset($teacherSummary['ready_students']));
assertTest("Teacher readiness summary has topics breakdown", !empty($teacherSummary['topics']) && count($teacherSummary['topics']) === 3);


// ----------------------------------------------------
// CHECKPOINT 7: In-Class Understanding Check & Stats
// ----------------------------------------------------
echo "\n--- CHECKPOINT 7: In-Class Understanding Check & Stats ---\n";

// Simulate 3 students submitting understanding checks
$p2->saveUnderstandingCheck($testSessionId, $studentId, null, 'Topic Alpha', 'got_it');
$p2->saveUnderstandingCheck($testSessionId, 9991, null, 'Topic Alpha', 'somewhat');
$p2->saveUnderstandingCheck($testSessionId, 9992, null, 'Topic Alpha', 'confused');

$uStats = $p2->getUnderstandingStats($testSessionId);
assertTest("Understanding stats aggregates counts correctly", $uStats['totals']['total'] === 3 && $uStats['totals']['got_it'] === 1 && $uStats['totals']['confused'] === 1, "Totals: " . json_encode($uStats['totals']));
assertTest("Confused percentage calculated correctly (33%)", $uStats['confused_pct'] === 33, "Confused %: " . $uStats['confused_pct']);


// ----------------------------------------------------
// CHECKPOINT 8: Post-Class Mastery Sync (Phase 1 Bridge)
// ----------------------------------------------------
echo "\n--- CHECKPOINT 8: Post-Class Mastery Sync ---\n";

// Call syncUnderstandingToMastery
$p2->syncUnderstandingToMastery($testSessionId);

// Check if topic_mastery recorded student 9992's confused status
$stmtMastery = $pdo->prepare("SELECT mastery_score FROM topic_mastery WHERE student_id = ? AND topic_name = ?");
$stmtMastery->execute([9992, 'Topic Alpha']);
$mScore = $stmtMastery->fetchColumn();
assertTest("Confused student mastery synced to low score (20.0)", $mScore !== false && (float)$mScore <= 30.0, "Mastery score: " . var_export($mScore, true));

// Check if student (got_it) was synced to proficient score (75.0)
$stmtMasterySid = $pdo->prepare("SELECT mastery_score FROM topic_mastery WHERE student_id = ? AND topic_name = ?");
$stmtMasterySid->execute([$studentId, 'Topic Alpha']);
$mScoreSid = $stmtMasterySid->fetchColumn();
assertTest("Got_it student mastery synced to proficient score (>= 70.0)", $mScoreSid !== false && (float)$mScoreSid >= 70.0, "Mastery score: " . var_export($mScoreSid, true));


// ----------------------------------------------------
// CLEANUP TEST DATA
// ----------------------------------------------------
$pdo->prepare("DELETE FROM session_understanding_checks WHERE session_id = ?")->execute([$testSessionId]);
$pdo->prepare("DELETE FROM session_readiness WHERE calendar_event_id = ?")->execute([$testEventId]);
$pdo->prepare("DELETE FROM session_topics WHERE calendar_event_id = ? OR session_id = ?")->execute([$testEventId, $testSessionId]);
$pdo->prepare("DELETE FROM classroom_sessions WHERE id = ?")->execute([$testSessionId]);
$pdo->prepare("DELETE FROM calendar_events WHERE id = ?")->execute([$testEventId]);
$pdo->prepare("DELETE FROM topic_mastery WHERE student_id IN (9991, 9992)")->execute();

echo "\n=======================================================\n";
echo "VERIFICATION SUMMARY\n";
echo "=======================================================\n";
echo "Total Passed: $passCount\n";
echo "Total Failed: $failCount\n";

if ($failCount === 0) {
    echo "\n>>> ALL 8 PHASE 2 CHECKPOINTS PASSED SUCCESSFULLY! <<<\n";
    exit(0);
} else {
    echo "\n>>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
