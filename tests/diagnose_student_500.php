<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';

echo "=== DIAGNOSE STUDENT DASHBOARD 500 ERROR ===\n";

// Find all student users to test with
$users = $pdo->query("SELECT id, email, first_name, last_name, role FROM users LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($users) . " users in database:\n";
foreach ($users as $u) {
    echo " - User #{$u['id']} ({$u['role']}): {$u['email']} - {$u['first_name']} {$u['last_name']}\n";
}

$testUserId = (int)($users[0]['id'] ?? 1);
// Prefer a student
foreach ($users as $u) {
    if ($u['role'] === 'student') {
        $testUserId = (int)$u['id'];
        break;
    }
}
echo "\nTesting with Student User ID: {$testUserId}\n\n";

$steps = [
    '1. EnrollmentService init' => function() use ($pdo) {
        require_once __DIR__ . '/../admin/enrollments-service.php';
        new EnrollmentService($pdo);
    },
    '2. LearningJourneyService init' => function() use ($pdo) {
        require_once __DIR__ . '/../includes/learning-journey-service.php';
        new LearningJourneyService($pdo);
    },
    '3. Phase2SessionService init' => function() use ($pdo) {
        require_once __DIR__ . '/../includes/phase2-session-service.php';
        new Phase2SessionService($pdo);
    },
    '4. Phase3MasteryService init' => function() use ($pdo) {
        require_once __DIR__ . '/../includes/phase3-mastery-service.php';
        new Phase3MasteryService($pdo);
    },
    '5. getStudentNextAction' => function() use ($pdo, $testUserId) {
        $js = new LearningJourneyService($pdo);
        $res = $js->getStudentNextAction($testUserId);
        return 'Found: ' . ($res ? $res['title'] : 'null');
    },
    '6. getStudentUpcomingList' => function() use ($pdo, $testUserId) {
        $js = new LearningJourneyService($pdo);
        $res = $js->getStudentUpcomingList($testUserId);
        return 'Found ' . count($res) . ' upcoming';
    },
    '7. getStudentLearningGoals' => function() use ($pdo, $testUserId) {
        $js = new LearningJourneyService($pdo);
        $res = $js->getStudentLearningGoals($testUserId, null, 'active');
        return 'Found ' . count($res) . ' goals';
    },
    '8. getUpcomingEventsForStudent' => function() use ($pdo, $testUserId) {
        $p2 = new Phase2SessionService($pdo);
        $res = $p2->getUpcomingEventsForStudent($testUserId, 72);
        return 'Found ' . count($res) . ' events';
    },
    '9. getStudentAssignments' => function() use ($pdo, $testUserId) {
        $p3 = new Phase3MasteryService($pdo);
        $res = $p3->getStudentAssignments($testUserId, null, 'all');
        return 'Found ' . count($res) . ' assignments';
    },
    '10. getStudentCombinedSchedule' => function() use ($pdo, $testUserId) {
        $es = new EnrollmentService($pdo);
        $res = $es->getStudentCombinedSchedule($testUserId);
        return 'Found ' . count($res) . ' schedule items';
    },
    '11. Available exams query' => function() use ($pdo) {
        $stmtExams = $pdo->query(
            "SELECT e.id, e.title, e.subject, e.grade, e.type, e.time_limit_minutes,
                    COUNT(q.id) AS question_count
             FROM exams e
             LEFT JOIN exam_questions q ON q.exam_id = e.id
             WHERE e.is_published = 1 AND e.status = 'active'
             GROUP BY e.id
             ORDER BY e.updated_at DESC, e.id DESC
             LIMIT 6"
        );
        return 'Found ' . count($stmtExams->fetchAll()) . ' exams';
    },
    '12. Enrolled courses query' => function() use ($pdo, $testUserId) {
        $stmt = $pdo->prepare(
            'SELECT c.id, c.title, c.subject, c.cover_image, c.duration_hours, en.progress_percent, en.status,
                    cg.name AS class_group_name, cg.schedule_day, cg.schedule_time
             FROM enrollments en
             INNER JOIN courses c ON c.id = en.course_id
             LEFT JOIN class_groups cg ON cg.id = en.class_group_id
             WHERE en.user_id = :uid AND en.status IN ("active", "trial") AND (en.end_date IS NULL OR en.end_date >= CURDATE())
             ORDER BY en.enrolled_at DESC LIMIT 6'
        );
        $stmt->execute([':uid' => $testUserId]);
        return 'Found ' . count($stmt->fetchAll()) . ' courses';
    },
    '13. Roadmap missions query' => function() use ($pdo, $testUserId) {
        $missionStmt = $pdo->prepare(
            "SELECT t.id, t.title, t.subject, t.completion_type, t.ref_lesson_id, t.ref_exam_id,
                    t.points_reward, COALESCE(p.status, 'not_started') AS progress_status
             FROM roadmap_enrollments re
             INNER JOIN roadmaps r ON r.id = re.roadmap_id AND r.status = 'published'
             INNER JOIN roadmap_tasks t ON t.roadmap_id = re.roadmap_id AND t.is_active = 1
             LEFT JOIN roadmap_task_progress p ON p.task_id = t.id AND p.user_id = re.user_id
             WHERE re.user_id = ? AND re.status = 'active'
             ORDER BY FIELD(COALESCE(p.status, 'not_started'), 'in_progress', 'not_started', 'locked', 'completed', 'exempted'),
                      COALESCE(t.due_date, '9999-12-31'), t.sort_order, t.id
             LIMIT 2"
        );
        $missionStmt->execute([$testUserId]);
        return 'Found ' . count($missionStmt->fetchAll()) . ' missions';
    },
    '14. Weekly activity query' => function() use ($pdo, $testUserId) {
        $weekStart = new DateTimeImmutable('monday this week');
        $weekEnd = $weekStart->modify('+7 days');
        $activityStmt = $pdo->prepare(
            'SELECT activity_date FROM (
               SELECT DATE(COALESCE(last_watched_at, completed_at, started_at)) AS activity_date
               FROM lesson_progress WHERE user_id = ?
               UNION
               SELECT DATE(completed_at) AS activity_date
               FROM test_attempts WHERE user_id = ? AND completed_at IS NOT NULL
             ) activity
             WHERE activity_date >= ? AND activity_date < ?'
        );
        $activityStmt->execute([$testUserId, $testUserId, $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);
        return 'Found ' . count($activityStmt->fetchAll()) . ' active days';
    },
    '15. Include student/index.php full render' => function() use ($pdo, $testUserId) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['user_id'] = $testUserId;
        global $pdo;
        ob_start();
        include __DIR__ . '/../student/index.php';
        $out = ob_get_clean();
        return 'Rendered successfully (' . strlen($out) . ' bytes)';
    },
    '16. Include student/index.php without session (Dev bypass)' => function() use ($pdo) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['user_id']);
        }
        global $pdo;
        ob_start();
        include __DIR__ . '/../student/index.php';
        $out = ob_get_clean();
        return 'Rendered dev bypass successfully (' . strlen($out) . ' bytes)';
    }
];

foreach ($steps as $name => $fn) {
    echo "Running [{$name}] ... ";
    try {
        $res = $fn();
        echo "OK" . ($res ? " ({$res})" : "") . "\n";
    } catch (Throwable $e) {
        echo "FAILED!\n";
        echo "  EXCEPTION: " . get_class($e) . ": " . $e->getMessage() . "\n";
        echo "  FILE: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "  TRACE:\n" . $e->getTraceAsString() . "\n\n";
    }
}

echo "\n=== DIAGNOSIS COMPLETE ===\n";
