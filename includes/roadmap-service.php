<?php
declare(strict_types=1);

function ensureRoadmapSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;

    // Check if the new 'roadmaps' table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'roadmaps'");
    if ($stmt->rowCount() === 0) {
        // Run migration script
        $sql = file_get_contents(__DIR__ . '/../database/roadmap_v2_migration.sql');
        if ($sql !== false) {
            $pdo->exec($sql);
        }
    } else {
        // Fallback simple checks for tables if needed, but migration covers it.
    }
    
    seedRoadmapTemplates($pdo);
    $ready = true;
}

function seedRoadmapTemplates(PDO $pdo): void
{
    $seedKey = 'roadmap_templates_seeded_v2';
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$seedKey]);
        if ($stmt->fetchColumn() !== false) return;
    } catch (PDOException $error) {
        if ($error->getCode() !== '42S02') throw $error;
    }

    // Default roadmaps should have been created by migration if there were tasks.
    // Let's ensure at least one roadmap exists.
    if ((int)$pdo->query('SELECT COUNT(*) FROM roadmaps')->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO roadmaps (title, description, stage, status, assignment_mode) VALUES 
            ('Roadmap ม.6 / TCAS', 'เตรียมสอบเข้ามหาวิทยาลัย', 'tcas', 'published', 'self_select'),
            ('Roadmap ม.4 - ม.5', 'เนื้อหามัธยมปลาย', 'm4', 'published', 'self_select'),
            ('Roadmap ม.1 - ม.3', 'พื้นฐานมัธยมต้น', 'm1', 'published', 'self_select')
        ");
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, '1') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$seedKey]);
    } catch (PDOException $error) {
        if ($error->getCode() !== '42S02') throw $error;
    }
}

function roadmapStageLabels(): array
{
    return [
        'tcas' => ['title' => 'ม.6 / TCAS มหาวิทยาลัย', 'badge' => 'TCAS & A-Level', 'description' => 'ทบทวนวิชาสามัญ TGAT, TPAT และเตรียม Portfolio สำหรับเข้ามหาวิทยาลัย'],
        'm4' => ['title' => 'ม.4 - ม.5 มัธยมปลาย', 'badge' => 'มัธยมปลาย', 'description' => 'ปรับพื้นฐานวิทย์ คณิต และภาษา พร้อมเก็บคะแนนในโรงเรียน'],
        'm1' => ['title' => 'ม.1 - ม.3 มัธยมต้น', 'badge' => 'มัธยมต้น', 'description' => 'ปูพื้นฐานวิชาหลักและเตรียมสอบเข้าในระดับถัดไป'],
    ];
}

function validRoadmapStage(string $stage): string
{
    return array_key_exists($stage, roadmapStageLabels()) ? $stage : 'tcas';
}

function getStudentRoadmaps(PDO $pdo, int $userId): array
{
    ensureRoadmapSchema($pdo);
    // Get all published roadmaps the user is enrolled in, or all self_select published roadmaps
    $stmt = $pdo->prepare("
        SELECT r.*, 
               COALESCE(e.status, 'not_enrolled') AS enroll_status,
               e.progress_percent,
               e.is_mandatory
        FROM roadmaps r
        LEFT JOIN roadmap_enrollments e ON e.roadmap_id = r.id AND e.user_id = :user_id
        WHERE r.status = 'published'
        ORDER BY r.stage DESC, r.id ASC
    ");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}

function getStudentRoadmap(PDO $pdo, int $userId, int $roadmapId): ?array
{
    ensureRoadmapSchema($pdo);
    $stmt = $pdo->prepare("
        SELECT r.*, 
               COALESCE(e.status, 'not_enrolled') AS enroll_status,
               e.progress_percent,
               e.is_mandatory,
               e.enrolled_at
        FROM roadmaps r
        LEFT JOIN roadmap_enrollments e ON e.roadmap_id = r.id AND e.user_id = :user_id
        WHERE r.id = :roadmap_id AND r.status = 'published'
    ");
    $stmt->execute([':user_id' => $userId, ':roadmap_id' => $roadmapId]);
    return $stmt->fetch() ?: null;
}

function getRoadmapTasks(PDO $pdo, int $roadmapId, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT t.*, 
               COALESCE(p.status, 'not_started') AS progress_status, 
               p.completed_at,
               p.completion_source
        FROM roadmap_tasks t
        LEFT JOIN roadmap_task_progress p ON p.task_id = t.id AND p.user_id = :user_id
        WHERE t.roadmap_id = :roadmap_id AND t.is_active = 1
        ORDER BY t.sort_order, t.due_date IS NULL, t.due_date, t.id
    ");
    $stmt->execute([':user_id' => $userId, ':roadmap_id' => $roadmapId]);
    $tasks = $stmt->fetchAll();
    
    // Evaluate prerequisite locks
    $completedTaskIds = [];
    foreach ($tasks as $task) {
        if ($task['progress_status'] === 'completed' || $task['progress_status'] === 'exempted') {
            $completedTaskIds[] = (int)$task['id'];
        }
    }
    
    foreach ($tasks as &$task) {
        if ($task['progress_status'] !== 'completed' && $task['progress_status'] !== 'exempted') {
            if (!empty($task['prerequisite_task_id']) && !in_array((int)$task['prerequisite_task_id'], $completedTaskIds)) {
                $task['progress_status'] = 'locked';
            }
        }
    }

    // Phase 5 blocking gaps pause only the affected subject/course branch.
    try {
        $blockingStmt = $pdo->prepare("
            SELECT DISTINCT s.course_id,c.subject
            FROM adaptive_roadmap_steps s
            JOIN courses c ON c.id=s.course_id
            WHERE s.student_id=? AND s.is_blocking=1
              AND s.status IN ('available','in_progress','locked')
        ");
        $blockingStmt->execute([$userId]);
        $blocking = $blockingStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tasks as &$task) {
            if (in_array($task['progress_status'], ['completed', 'exempted'], true)) continue;
            foreach ($blocking as $branch) {
                $sameCourse = !empty($task['ref_course_id']) && (int)$task['ref_course_id'] === (int)$branch['course_id'];
                $sameSubject = !empty($task['subject']) && mb_strtolower(trim((string)$task['subject'])) === mb_strtolower(trim((string)$branch['subject']));
                if ($sameCourse || $sameSubject) {
                    $task['progress_status'] = 'locked';
                    $task['adaptive_lock'] = true;
                    $task['adaptive_lock_message'] = 'ทำขั้นตอนทบทวนที่แนะนำให้เสร็จก่อน แล้วเส้นทางนี้จะเปิดต่ออัตโนมัติ';
                    break;
                }
            }
        }
        unset($task);
    } catch (Throwable $e) {
        // Static prerequisite behavior remains available before Phase 5 is installed.
    }
    
    return $tasks;
}

function roadmapProgress(array $tasks): array
{
    $requiredTasks = array_filter($tasks, static fn(array $t): bool => (bool)$t['is_required']);
    $total = count($requiredTasks);
    $completed = count(array_filter($requiredTasks, static fn(array $task): bool => $task['progress_status'] === 'completed' || $task['progress_status'] === 'exempted'));
    return ['total' => $total, 'completed' => $completed, 'percent' => $total ? (int)round($completed * 100 / $total) : 0];
}

function canCompleteManually(array $task): bool
{
    return $task['completion_type'] === 'manual';
}
