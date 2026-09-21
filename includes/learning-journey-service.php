<?php
declare(strict_types=1);

/**
 * Nextbeyond Compass V2 — Phase 1: Learning Journey Core Integration Service
 * 
 * Manages the student learning protocol foundation:
 * Enrollment -> Goal -> Diagnostic -> Learning Profile -> Learning Path -> Study Roadmap -> Next Action
 */

class LearningJourneyService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureSchema();
    }

    /**
     * Non-destructively ensure all required tables and columns exist
     */
    public function ensureSchema(): void
    {
        static $ensured = false;
        if ($ensured) return;

        // 1. Table: learning_goals
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `learning_goals` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `student_id` INT NOT NULL,
                `course_id` INT NOT NULL,
                `goal_type` VARCHAR(50) NOT NULL DEFAULT 'a_level',
                `goal_name` VARCHAR(255) NOT NULL,
                `target_score` DECIMAL(5,2) NULL,
                `target_date` DATE NULL,
                `priority` ENUM('high', 'medium', 'low') NOT NULL DEFAULT 'high',
                `status` ENUM('active', 'completed', 'archived') NOT NULL DEFAULT 'active',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_student_course` (`student_id`, `course_id`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 2. Table: student_learning_profiles
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `student_learning_profiles` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `student_id` INT NOT NULL,
                `course_id` INT NOT NULL,
                `baseline_score` DECIMAL(5,2) NULL,
                `current_mastery` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                `diagnostic_attempt_id` INT NULL,
                `strengths_json` TEXT NULL,
                `needs_improvement_json` TEXT NULL,
                `summary_notes` TEXT NULL,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uniq_student_course` (`student_id`, `course_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 3. Table: topic_mastery
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `topic_mastery` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `student_id` INT NOT NULL,
                `course_id` INT NOT NULL,
                `subject` VARCHAR(100) NOT NULL,
                `topic_id` VARCHAR(100) NULL,
                `topic_name` VARCHAR(255) NOT NULL,
                `mastery_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                `confidence_score` DECIMAL(5,2) NULL,
                `evidence_count` INT NOT NULL DEFAULT 1,
                `source` VARCHAR(50) NOT NULL DEFAULT 'diagnostic',
                `last_assessed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_student_course_topic` (`student_id`, `course_id`, `topic_name`),
                UNIQUE KEY `uniq_student_course_topic` (`student_id`, `course_id`, `topic_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 4. Table: learning_events
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `learning_events` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `student_id` INT NOT NULL,
                `course_id` INT NOT NULL,
                `event_type` VARCHAR(50) NOT NULL,
                `source_type` VARCHAR(50) NOT NULL,
                `source_id` INT NULL,
                `topic_id` VARCHAR(100) NULL,
                `score` DECIMAL(5,2) NULL,
                `max_score` DECIMAL(5,2) NULL,
                `completion_status` VARCHAR(30) NOT NULL DEFAULT 'completed',
                `metadata` LONGTEXT NULL,
                `occurred_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_student_event` (`student_id`, `event_type`),
                INDEX `idx_course_event` (`course_id`, `event_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 5. Extend learning_paths with course_id
        try {
            $cols = [];
            foreach ($this->pdo->query("SHOW COLUMNS FROM `learning_paths`")->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $cols[$c['Field']] = true;
            }
            if (!isset($cols['course_id'])) {
                $this->pdo->exec("ALTER TABLE `learning_paths` ADD COLUMN `course_id` INT NULL AFTER `user_id`, ADD INDEX `idx_lp_course` (`course_id`)");
            }
        } catch (Throwable $e) {
            // Table might not exist yet or column already added
        }

        $ensured = true;
    }

    /**
     * Learning Goals: Get goals for a student (optionally filtered by course)
     */
    public function getStudentLearningGoals(int $studentId, ?int $courseId = null, bool|string $activeOnly = true): array
    {
        $sql = "
            SELECT g.*, c.title AS course_title, c.subject AS course_subject, c.level AS course_level
            FROM learning_goals g
            INNER JOIN courses c ON c.id = g.course_id
            WHERE g.student_id = :student_id
        ";
        $params = [':student_id' => $studentId];

        if ($courseId !== null && $courseId > 0) {
            $sql .= " AND g.course_id = :course_id";
            $params[':course_id'] = $courseId;
        }

        if ($activeOnly === true || $activeOnly === 'active') {
            $sql .= " AND g.status = 'active'";
        } elseif (is_string($activeOnly) && $activeOnly !== '') {
            $sql .= " AND g.status = :goal_status";
            $params[':goal_status'] = $activeOnly;
        }

        $sql .= " ORDER BY g.priority ASC, g.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Learning Goals: Save (Create or Update)
     */
    public function saveLearningGoal(int $studentId, int $courseId, array $data): int
    {
        $goalId = (int)($data['id'] ?? 0);
        $goalType = trim((string)($data['goal_type'] ?? 'a_level'));
        $goalName = trim((string)($data['goal_name'] ?? 'เป้าหมายการเรียน'));
        $targetScore = isset($data['target_score']) && $data['target_score'] !== '' ? (float)$data['target_score'] : null;
        $targetDate = !empty($data['target_date']) ? (string)$data['target_date'] : null;
        $priority = in_array(($data['priority'] ?? ''), ['high', 'medium', 'low'], true) ? $data['priority'] : 'high';
        $status = in_array(($data['status'] ?? ''), ['active', 'completed', 'archived'], true) ? $data['status'] : 'active';

        if ($goalId > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE learning_goals
                SET course_id = :course_id,
                    goal_type = :goal_type,
                    goal_name = :goal_name,
                    target_score = :target_score,
                    target_date = :target_date,
                    priority = :priority,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id AND student_id = :student_id
            ");
            $stmt->execute([
                ':course_id' => $courseId,
                ':goal_type' => $goalType,
                ':goal_name' => $goalName,
                ':target_score' => $targetScore,
                ':target_date' => $targetDate,
                ':priority' => $priority,
                ':status' => $status,
                ':id' => $goalId,
                ':student_id' => $studentId,
            ]);
            $savedId = $goalId;
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO learning_goals (student_id, course_id, goal_type, goal_name, target_score, target_date, priority, status)
                VALUES (:student_id, :course_id, :goal_type, :goal_name, :target_score, :target_date, :priority, :status)
            ");
            $stmt->execute([
                ':student_id' => $studentId,
                ':course_id' => $courseId,
                ':goal_type' => $goalType,
                ':goal_name' => $goalName,
                ':target_score' => $targetScore,
                ':target_date' => $targetDate,
                ':priority' => $priority,
                ':status' => $status,
            ]);
            $savedId = (int)$this->pdo->lastInsertId();
        }

        // Record learning event
        $this->recordLearningEvent($studentId, $courseId, 'goal_set', 'goal', $savedId, $targetScore, [
            'goal_name' => $goalName,
            'target_score' => $targetScore,
            'target_date' => $targetDate
        ]);

        return $savedId;
    }

    /**
     * Learning Goals: Archive
     */
    public function archiveLearningGoal(int $goalId, int $studentId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE learning_goals SET status = 'archived', updated_at = NOW() WHERE id = ? AND student_id = ?");
        return $stmt->execute([$goalId, $studentId]);
    }

    /**
     * Learning Profile: Fetch academic state for student in a course
     */
    public function getStudentLearningProfile(int $studentId, int $courseId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.title AS course_title, c.subject AS course_subject
            FROM student_learning_profiles p
            INNER JOIN courses c ON c.id = p.course_id
            WHERE p.student_id = ? AND p.course_id = ?
            LIMIT 1
        ");
        $stmt->execute([$studentId, $courseId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$profile) {
            return null;
        }

        $profile['strengths'] = json_decode((string)($profile['strengths_json'] ?? '[]'), true) ?: [];
        $profile['needs_improvement'] = json_decode((string)($profile['needs_improvement_json'] ?? '[]'), true) ?: [];
        return $profile;
    }

    /**
     * Topic Mastery: Fetch mastery levels for student topics
     */
    public function getStudentTopicMastery(int $studentId, ?int $courseId = null): array
    {
        $sql = "SELECT * FROM topic_mastery WHERE student_id = :student_id";
        $params = [':student_id' => $studentId];
        if ($courseId !== null && $courseId > 0) {
            $sql .= " AND course_id = :course_id";
            $params[':course_id'] = $courseId;
        }
        $sql .= " ORDER BY mastery_score DESC, topic_name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Diagnostic Connection: Connect diagnostic results to Student, Course, Topics/Skills & Profile
     * 
     * @param int $studentId
     * @param int $courseId
     * @param int $attemptId
     * @param array<string, float> $skillScores associative array: ['Present Simple' => 82.0, 'Present Perfect' => 39.0, 'Reading' => 52.0]
     */
    public function recordDiagnosticResult(int $studentId, int $courseId, int $attemptId, array $skillScores, ?float $overallScore = null): void
    {
        // 1. Fetch course subject
        $cStmt = $this->pdo->prepare("SELECT subject FROM courses WHERE id = ?");
        $cStmt->execute([$courseId]);
        $subject = (string)($cStmt->fetchColumn() ?: 'วิชาทั่วไป');

        // 2. Calculate overall baseline if not provided
        if ($overallScore === null) {
            $overallScore = count($skillScores) > 0 ? array_sum($skillScores) / count($skillScores) : 0.0;
        }
        $overallScore = round((float)$overallScore, 1);

        $strengths = [];
        $needsImprovement = [];

        // 3. Upsert into topic_mastery
        $upsertMastery = $this->pdo->prepare("
            INSERT INTO topic_mastery (student_id, course_id, subject, topic_name, mastery_score, confidence_score, evidence_count, source, last_assessed_at)
            VALUES (:student_id, :course_id, :subject, :topic_name, :mastery_score, 0.85, 1, 'diagnostic', NOW())
            ON DUPLICATE KEY UPDATE
                mastery_score = VALUES(mastery_score),
                evidence_count = evidence_count + 1,
                last_assessed_at = NOW()
        ");

        foreach ($skillScores as $skillName => $scoreVal) {
            $scoreVal = round((float)$scoreVal, 1);
            $upsertMastery->execute([
                ':student_id' => $studentId,
                ':course_id' => $courseId,
                ':subject' => $subject,
                ':topic_name' => (string)$skillName,
                ':mastery_score' => $scoreVal,
            ]);

            if ($scoreVal >= 70.0) {
                $strengths[] = [
                    'topic' => $skillName,
                    'score' => $scoreVal
                ];
            } else {
                $needsImprovement[] = [
                    'topic' => $skillName,
                    'score' => $scoreVal
                ];
            }
        }

        // Sort strengths desc and needsImprovement asc
        usort($strengths, fn($a, $b) => $b['score'] <=> $a['score']);
        usort($needsImprovement, fn($a, $b) => $a['score'] <=> $b['score']);

        // 4. Upsert into student_learning_profiles
        $upsertProfile = $this->pdo->prepare("
            INSERT INTO student_learning_profiles (student_id, course_id, baseline_score, current_mastery, diagnostic_attempt_id, strengths_json, needs_improvement_json, updated_at)
            VALUES (:student_id, :course_id, :baseline_score, :current_mastery, :attempt_id, :strengths, :needs_improvement, NOW())
            ON DUPLICATE KEY UPDATE
                baseline_score = IF(baseline_score IS NULL, VALUES(baseline_score), baseline_score),
                current_mastery = VALUES(current_mastery),
                diagnostic_attempt_id = VALUES(diagnostic_attempt_id),
                strengths_json = VALUES(strengths_json),
                needs_improvement_json = VALUES(needs_improvement_json),
                updated_at = NOW()
        ");

        $upsertProfile->execute([
            ':student_id' => $studentId,
            ':course_id' => $courseId,
            ':baseline_score' => $overallScore,
            ':current_mastery' => $overallScore,
            ':attempt_id' => $attemptId,
            ':strengths' => json_encode($strengths, JSON_UNESCAPED_UNICODE),
            ':needs_improvement' => json_encode($needsImprovement, JSON_UNESCAPED_UNICODE),
        ]);

        // 5. Record Event
        $this->recordLearningEvent($studentId, $courseId, 'diagnostic_completed', 'exam', $attemptId, $overallScore, [
            'skill_scores' => $skillScores,
            'strengths' => array_column($strengths, 'topic'),
            'needs_improvement' => array_column($needsImprovement, 'topic')
        ]);
    }

    /**
     * Event-Ready Architecture: Record any academic event
     */
    public function recordLearningEvent(
        int $studentId,
        int $courseId,
        string $eventType,
        string $sourceType,
        ?int $sourceId = null,
        ?float $score = null,
        array $metadata = []
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO learning_events (student_id, course_id, event_type, source_type, source_id, score, metadata, occurred_at)
            VALUES (:student_id, :course_id, :event_type, :source_type, :source_id, :score, :metadata, NOW())
        ");
        $stmt->execute([
            ':student_id' => $studentId,
            ':course_id' => $courseId,
            ':event_type' => $eventType,
            ':source_type' => $sourceType,
            ':source_id' => $sourceId,
            ':score' => $score,
            ':metadata' => !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Learning Path: Get or initialize a course's learning path for a student
     */
    public function getStudentLearningPath(int $studentId, int $courseId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM learning_paths
            WHERE user_id = :user_id AND (course_id = :course_id OR course_id IS NULL)
            ORDER BY (course_id = :order_course_id) DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $studentId, ':course_id' => $courseId, ':order_course_id' => $courseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $plan = json_decode((string)($row['plan_data'] ?? ''), true) ?: [];
        $row['steps'] = $plan['steps'] ?? (array_is_list($plan) ? $plan : []);
        return $row;
    }

    /**
     * Core Algorithm: "What should this student do next?"
     * 
     * Determines next action per course based on:
     * Enrollment access -> Learning Path topic -> Roadmap task status
     * 
     * @param int $studentId
     * @param int|null $courseId If null, returns the primary next action across all active enrollments
     * @return array|null Structured next action card
     */
    public function getStudentNextAction(int $studentId, ?int $courseId = null): ?array
    {
        // 1. Get student's active enrollments
        $enSql = "
            SELECT en.id AS enrollment_id, en.course_id, en.progress_percent,
                   c.title AS course_title, c.subject AS course_subject, c.level AS course_level
            FROM enrollments en
            INNER JOIN courses c ON c.id = en.course_id
            WHERE en.user_id = :student_id AND en.status IN ('active', 'trial')
              AND (en.end_date IS NULL OR en.end_date >= CURDATE())
        ";
        $enParams = [':student_id' => $studentId];
        if ($courseId !== null && $courseId > 0) {
            $enSql .= " AND en.course_id = :course_id";
            $enParams[':course_id'] = $courseId;
        }
        $enSql .= " ORDER BY en.enrolled_at ASC";

        $stmt = $this->pdo->prepare($enSql);
        $stmt->execute($enParams);
        $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($enrollments)) {
            return null;
        }

        // Phase 3–5: assigned support activities are the clearest next action.
        try {
            require_once __DIR__ . '/phase3-mastery-service.php';
            $p3 = new Phase3MasteryService($this->pdo);
            $pendingActivities = $p3->getStudentAssignments($studentId, $courseId, 'pending');
            $filteredActivities = array_values(array_filter($pendingActivities, function($a) {
                return !empty($a['title']) && (!empty($a['session_id']) || !empty($a['topic_name']));
            }));
            if (!empty($filteredActivities)) {
                $typeOrder = ['remediation' => 0, 'mastery_check' => 1, 'worksheet' => 2, 'practice' => 3, 'homework' => 4, 'posttest' => 5];
                usort($filteredActivities, function($a, $b) use ($typeOrder) {
                    $orderA = $typeOrder[$a['activity_type'] ?? 'worksheet'] ?? 5;
                    $orderB = $typeOrder[$b['activity_type'] ?? 'worksheet'] ?? 5;
                    if ($orderA !== $orderB) return $orderA <=> $orderB;
                    return ($a['due_date'] ?? '9999-12-31') <=> ($b['due_date'] ?? '9999-12-31');
                });
                $topAct = $filteredActivities[0];
                $actType = (string)($topAct['activity_type'] ?? 'worksheet');
                $actLabel = match($actType) {
                    'worksheet' => 'ทำใบงาน',
                    'practice'  => 'ฝึกฝนโจทย์',
                    'homework'  => 'ทำการบ้าน',
                    'posttest'  => 'ทำแบบทดสอบหลังเรียน',
                    'remediation' => 'เริ่มฝึกเพิ่มเติม',
                    'mastery_check' => 'ตรวจความเข้าใจ',
                    default     => 'เริ่มทำกิจกรรม'
                };

                return [
                    'type' => 'learning_activity',
                    'activity_type' => $actType,
                    'course_id' => (int)($topAct['course_id'] ?? 0),
                    'course_title' => (string)($topAct['course_title'] ?? ''),
                    'course_subject' => (string)($topAct['course_subject'] ?? $topAct['subject'] ?? ''),
                    'title' => (string)$topAct['title'],
                    'topic' => (string)($topAct['topic_name'] ?? $topAct['topic'] ?? ''),
                    'assignment_id' => (int)$topAct['id'],
                    'status' => 'available',
                    'action_url' => 'student/activity.php?assignment_id=' . (int)$topAct['id'],
                    'action_label' => $actLabel,
                ];
            }
        } catch (Throwable $e) {
            // Fallback to roadmap / lesson tasks
        }

        // Phase 5 adaptive steps may temporarily lead or block the static roadmap.
        try {
            $adaptiveSql = "
                SELECT s.*, c.title AS course_title, c.subject AS course_subject
                FROM adaptive_roadmap_steps s
                JOIN courses c ON c.id=s.course_id
                WHERE s.student_id=:student_id AND s.status IN ('available','in_progress')
            ";
            $adaptiveParams = [':student_id' => $studentId];
            if ($courseId !== null && $courseId > 0) {
                $adaptiveSql .= ' AND s.course_id=:adaptive_course_id';
                $adaptiveParams[':adaptive_course_id'] = $courseId;
            }
            $adaptiveSql .= ' ORDER BY s.is_blocking DESC,s.sort_order ASC,s.created_at ASC LIMIT 1';
            $adaptiveStmt = $this->pdo->prepare($adaptiveSql);
            $adaptiveStmt->execute($adaptiveParams);
            $adaptive = $adaptiveStmt->fetch(PDO::FETCH_ASSOC);
            if ($adaptive) {
                $waiting = in_array($adaptive['step_type'], ['teacher_review', 'teacher_session'], true) && empty($adaptive['action_url']);
                return [
                    'type' => 'adaptive_support',
                    'activity_type' => (string)$adaptive['step_type'],
                    'course_id' => (int)$adaptive['course_id'],
                    'course_title' => (string)$adaptive['course_title'],
                    'course_subject' => (string)$adaptive['course_subject'],
                    'title' => (string)$adaptive['title'],
                    'topic' => (string)($adaptive['topic_name'] ?? ''),
                    'status' => $waiting ? 'teacher_preparing' : 'available',
                    'action_url' => $adaptive['action_url'] ? 'student/' . ltrim((string)$adaptive['action_url'], '/') : 'student/roadmap.php',
                    'action_label' => $waiting ? 'ครูกำลังเตรียมการช่วยเหลือ' : 'ไปยังขั้นตอนถัดไป',
                    'is_blocking' => (bool)$adaptive['is_blocking'],
                    'completion_type' => 'adaptive_support',
                ];
            }
        } catch (Throwable $e) {
            // Phase 1–4 remain usable before the additive Phase 5 migration is installed.
        }

        // Check each enrolled course for the next task
        foreach ($enrollments as $en) {
            $cId = (int)$en['course_id'];
            $cTitle = (string)$en['course_title'];
            $cSubject = (string)$en['course_subject'];

            // A. Check for next incomplete Roadmap task linked to this course
            $taskStmt = $this->pdo->prepare("
                SELECT t.id, t.title, t.subject, t.completion_type, t.ref_lesson_id, t.ref_exam_id,
                       r.id AS roadmap_id, r.title AS roadmap_title,
                       COALESCE(p.status, 'not_started') AS task_status
                FROM roadmap_tasks t
                INNER JOIN roadmaps r ON r.id = t.roadmap_id
                LEFT JOIN roadmap_task_progress p ON p.task_id = t.id AND p.user_id = :user_id
                WHERE (t.ref_course_id = :course_id OR t.subject = :course_subject)
                  AND t.is_active = 1
                  AND COALESCE(p.status, 'not_started') NOT IN ('completed', 'exempted')
                ORDER BY t.sort_order ASC, t.id ASC
                LIMIT 1
            ");
            $taskStmt->execute([
                ':user_id' => $studentId,
                ':course_id' => $cId,
                ':course_subject' => $cSubject
            ]);
            $task = $taskStmt->fetch(PDO::FETCH_ASSOC);

            if ($task) {
                // Determine action URL
                $actionUrl = 'roadmap.php?id=' . (int)$task['roadmap_id'];
                if (!empty($task['ref_lesson_id'])) {
                    $actionUrl = 'lesson.php?id=' . (int)$task['ref_lesson_id'];
                } elseif (!empty($task['ref_exam_id'])) {
                    $actionUrl = 'take-test.php?id=' . (int)$task['ref_exam_id'];
                }

                return [
                    'type' => 'roadmap_task',
                    'course_id' => $cId,
                    'course_title' => $cTitle,
                    'course_subject' => $cSubject,
                    'title' => (string)$task['title'],
                    'topic' => (string)$task['subject'],
                    'task_id' => (int)$task['id'],
                    'roadmap_id' => (int)$task['roadmap_id'],
                    'completion_type' => (string)$task['completion_type'],
                    'status' => 'available',
                    'action_url' => $actionUrl,
                    'action_label' => 'เรียนต่อ',
                ];
            }

            // B. If no specific roadmap task, check next incomplete Lesson in this course
            $lessonStmt = $this->pdo->prepare("
                SELECT l.id, l.title, l.duration_minutes
                FROM lessons l
                LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = :user_id
                WHERE l.course_id = :course_id AND COALESCE(lp.is_completed, 0) = 0
                ORDER BY l.sort_order ASC, l.id ASC
                LIMIT 1
            ");
            $lessonStmt->execute([
                ':user_id' => $studentId,
                ':course_id' => $cId
            ]);
            $lesson = $lessonStmt->fetch(PDO::FETCH_ASSOC);

            if ($lesson) {
                return [
                    'type' => 'lesson',
                    'course_id' => $cId,
                    'course_title' => $cTitle,
                    'course_subject' => $cSubject,
                    'title' => (string)$lesson['title'],
                    'topic' => $cSubject,
                    'lesson_id' => (int)$lesson['id'],
                    'duration_minutes' => (int)($lesson['duration_minutes'] ?? 0),
                    'status' => 'available',
                    'action_url' => 'lesson.php?id=' . (int)$lesson['id'],
                    'action_label' => 'เรียนต่อ',
                ];
            }
        }

        // Fallback: Student has finished all available lessons/tasks
        $firstEn = $enrollments[0];
        return [
            'type' => 'review',
            'course_id' => (int)$firstEn['course_id'],
            'course_title' => (string)$firstEn['course_title'],
            'course_subject' => (string)$firstEn['course_subject'],
            'title' => 'ทบทวนเนื้อหาและทำแบบฝึกหัดเพิ่มเติม',
            'topic' => (string)$firstEn['course_subject'],
            'status' => 'completed_cycle',
            'action_url' => 'my-courses.php',
            'action_label' => 'ดูคอร์สของฉัน',
        ];
    }

    /**
     * Get list of next upcoming actions across all enrolled courses for a student
     */
    public function getStudentUpcomingList(int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT en.course_id, c.title AS course_title, c.subject AS course_subject,
                   p.baseline_score, p.current_mastery,
                   MAX(g.goal_name) AS goal_name, MAX(g.target_score) AS target_score
            FROM enrollments en
            INNER JOIN courses c ON c.id = en.course_id
            LEFT JOIN student_learning_profiles p ON p.student_id = en.user_id AND p.course_id = en.course_id
            LEFT JOIN learning_goals g ON g.student_id = en.user_id AND g.course_id = en.course_id AND g.status = 'active'
            WHERE en.user_id = :student_id AND en.status IN ('active', 'trial')
              AND (en.end_date IS NULL OR en.end_date >= CURDATE())
            GROUP BY en.course_id, c.title, c.subject, p.baseline_score, p.current_mastery
            ORDER BY MIN(en.enrolled_at) ASC
        ");
        $stmt->execute([':student_id' => $studentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $upcoming = [];
        foreach ($rows as $r) {
            $next = $this->getStudentNextAction($studentId, (int)$r['course_id']);
            $upcoming[] = [
                'course_id' => (int)$r['course_id'],
                'course_title' => (string)$r['course_title'],
                'course_subject' => (string)$r['course_subject'],
                'goal_name' => $r['goal_name'] ?? 'ไม่มีเป้าหมาย',
                'target_score' => $r['target_score'] !== null ? (float)$r['target_score'] : null,
                'current_mastery' => $r['current_mastery'] !== null ? (float)$r['current_mastery'] : null,
                'next_title' => $next['title'] ?? 'ยังไม่มีภารกิจถัดไป',
                'action_url' => $next['action_url'] ?? 'my-courses.php',
            ];
        }
        return $upcoming;
    }

    /**
     * Phase 1 Acceptance Data Seeding
     * 
     * Configures acceptance criteria scenarios:
     * - ใบเตย (M.5): Enrolled in English, Chemistry, Biology
     *   - English Goal: A-Level English, Target: 75
     *   - Chemistry Goal: A-Level Chemistry
     *   - Biology Goal: A-Level Biology
     *   - English Diagnostic: Present Simple (82%), Present Perfect (39%), Reading (52%) -> Baseline 61%
     *   - English Learning Profile: Strengths = Present Simple, Needs = Present Perfect
     *   - English Next Action: "Present Perfect EP1"
     * - แคนดี้ (M.5): Enrolled in English, Mathematics
     *   - English Goal & Math Goal
     */
    public function seedPhase1AcceptanceData(): array
    {
        $this->ensureSchema();

        // 1. Ensure courses exist
        $courses = [
            'M5 English' => ['subject' => 'ภาษาอังกฤษ', 'level' => 'ม.5'],
            'M5 Chemistry' => ['subject' => 'เคมี', 'level' => 'ม.5'],
            'M5 Biology' => ['subject' => 'ชีววิทยา', 'level' => 'ม.5'],
            'M5 Mathematics' => ['subject' => 'คณิตศาสตร์', 'level' => 'ม.5'],
        ];
        $courseIds = [];
        foreach ($courses as $title => $spec) {
            $stmt = $this->pdo->prepare("SELECT id FROM courses WHERE title = ? LIMIT 1");
            $stmt->execute([$title]);
            $cId = $stmt->fetchColumn();
            if (!$cId) {
                $ins = $this->pdo->prepare("
                    INSERT INTO courses (title, subject, level, status, price, duration_hours)
                    VALUES (?, ?, ?, 'active', 1500, 30)
                ");
                $ins->execute([$title, $spec['subject'], $spec['level']]);
                $cId = $this->pdo->lastInsertId();
            }
            $courseIds[$title] = (int)$cId;
        }

        // 2. Ensure students ใบเตย and แคนดี้
        $students = [
            'ใบเตย' => [
                'email' => 'baitoey@student.nextbeyond.com',
                'first_name' => 'ใบเตย',
                'last_name' => 'ปิยธิดา',
                'grade' => 'ม.5',
                'courses' => ['M5 English', 'M5 Chemistry', 'M5 Biology']
            ],
            'แคนดี้' => [
                'email' => 'candy@student.nextbeyond.com',
                'first_name' => 'แคนดี้',
                'last_name' => 'ศิรภัสสร',
                'grade' => 'ม.5',
                'courses' => ['M5 English', 'M5 Mathematics']
            ]
        ];

        $studentIds = [];
        foreach ($students as $nick => $info) {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? OR nickname = ? LIMIT 1");
            $stmt->execute([$info['email'], $nick]);
            $uId = $stmt->fetchColumn();
            if (!$uId) {
                $ins = $this->pdo->prepare("
                    INSERT INTO users (email, password_hash, first_name, last_name, nickname, grade, role, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, 'student', 1)
                ");
                $ins->execute([
                    $info['email'],
                    password_hash('password', PASSWORD_DEFAULT),
                    $info['first_name'],
                    $info['last_name'],
                    $nick,
                    $info['grade']
                ]);
                $uId = $this->pdo->lastInsertId();
            } else {
                $this->pdo->prepare("UPDATE users SET grade = ?, nickname = ? WHERE id = ?")->execute([$info['grade'], $nick, $uId]);
            }
            $studentIds[$nick] = (int)$uId;

            // Ensure enrollments
            foreach ($info['courses'] as $cTitle) {
                $cId = $courseIds[$cTitle];
                $chkEn = $this->pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
                $chkEn->execute([$uId, $cId]);
                if (!$chkEn->fetchColumn()) {
                    $this->pdo->prepare("
                        INSERT INTO enrollments (user_id, course_id, status, access_type, learning_mode, progress_percent, enrolled_at)
                        VALUES (?, ?, 'active', 'paid', 'online', 0.00, NOW())
                    ")->execute([$uId, $cId]);
                }
            }
        }

        $baitoeyId = $studentIds['ใบเตย'];
        $candyId = $studentIds['แคนดี้'];

        $seedGoal = function(int $stuId, int $cId, array $data) {
            $stmt = $this->pdo->prepare("SELECT id FROM learning_goals WHERE student_id = ? AND course_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1");
            $stmt->execute([$stuId, $cId]);
            $existing = $stmt->fetchColumn();
            if ($existing) {
                $data['id'] = (int)$existing;
            }
            $savedId = $this->saveLearningGoal($stuId, $cId, $data);
            $del = $this->pdo->prepare("DELETE FROM learning_goals WHERE student_id = ? AND course_id = ? AND status = 'active' AND id != ?");
            $del->execute([$stuId, $cId, $savedId]);
            return $savedId;
        };

        // 3. Seed Goals for ใบเตย (Section 27 Acceptance Test)
        // English Goal: A-Level English, Target: 75, Target Date: March 2027
        $seedGoal($baitoeyId, $courseIds['M5 English'], [
            'goal_type' => 'a_level',
            'goal_name' => 'A-Level English',
            'target_score' => 75.0,
            'target_date' => '2027-03-31',
            'priority' => 'high'
        ]);

        // Chemistry Goal: A-Level Chemistry
        $seedGoal($baitoeyId, $courseIds['M5 Chemistry'], [
            'goal_type' => 'a_level',
            'goal_name' => 'A-Level Chemistry',
            'target_score' => 70.0,
            'target_date' => '2027-03-31',
            'priority' => 'high'
        ]);

        // Biology Goal: A-Level Biology
        $seedGoal($baitoeyId, $courseIds['M5 Biology'], [
            'goal_type' => 'a_level',
            'goal_name' => 'A-Level Biology',
            'target_score' => 80.0,
            'target_date' => '2027-03-31',
            'priority' => 'high'
        ]);

        // Goals for แคนดี้: English & Math
        $seedGoal($candyId, $courseIds['M5 English'], [
            'goal_type' => 'a_level',
            'goal_name' => 'A-Level English',
            'target_score' => 70.0,
            'target_date' => '2027-03-31',
            'priority' => 'medium'
        ]);
        $seedGoal($candyId, $courseIds['M5 Mathematics'], [
            'goal_type' => 'entrance_exam',
            'goal_name' => 'A-Level คณิตศาสตร์ประยุกต์ 1',
            'target_score' => 85.0,
            'target_date' => '2027-03-31',
            'priority' => 'high'
        ]);

        // 4. Seed Diagnostic Results for ใบเตย (Section 28 Acceptance Test)
        // Present Simple: 82%, Present Perfect: 39%, Reading: 52%
        // Overall: 61%
        $this->recordDiagnosticResult(
            $baitoeyId,
            $courseIds['M5 English'],
            9991, // Simulated diagnostic attempt ID
            [
                'Present Simple' => 82.0,
                'Present Perfect' => 39.0,
                'Reading' => 52.0
            ],
            61.0
        );

        // 5. Seed Chemistry & Biology baseline profiles for ใบเตย
        $this->recordDiagnosticResult(
            $baitoeyId,
            $courseIds['M5 Chemistry'],
            9992,
            [
                'ปริมาณสารสัมพันธ์' => 74.0,
                'พันธะเคมี' => 68.0,
                'สมดุลกรด-เบส' => 45.0
            ],
            62.3
        );
        $this->recordDiagnosticResult(
            $baitoeyId,
            $courseIds['M5 Biology'],
            9993,
            [
                'โครงสร้างเซลล์' => 80.0,
                'การถ่ายทอดพันธุกรรม' => 65.0,
                'ระบบนิเวศ' => 58.0
            ],
            67.7
        );

        // 6. Connect Learning Path & Study Roadmap for Section 29 Acceptance Test
        // Next Topic: Present Perfect -> Next Roadmap Task: Present Perfect EP1
        $englishCourseId = $courseIds['M5 English'];

        // Ensure Roadmap exists
        $roadmapStmt = $this->pdo->query("SELECT id FROM roadmaps WHERE stage = 'm4' LIMIT 1");
        $roadmapId = (int)$roadmapStmt->fetchColumn();
        if (!$roadmapId) {
            $this->pdo->exec("INSERT INTO roadmaps (title, stage, status) VALUES ('Roadmap ม.4 - ม.5', 'm4', 'published')");
            $roadmapId = (int)$this->pdo->lastInsertId();
        }

        // Ensure student is enrolled in this roadmap
        $this->pdo->prepare("
            INSERT INTO roadmap_enrollments (roadmap_id, user_id, status, enrolled_at)
            VALUES (?, ?, 'active', NOW())
            ON DUPLICATE KEY UPDATE status = 'active'
        ")->execute([$roadmapId, $baitoeyId]);

        // Ensure Lesson "Present Perfect EP1" exists for M5 English
        $stmtL = $this->pdo->prepare("SELECT id FROM lessons WHERE course_id = ? AND title LIKE '%Present Perfect EP1%' LIMIT 1");
        $stmtL->execute([$englishCourseId]);
        $lessonId = (int)$stmtL->fetchColumn();
        if (!$lessonId) {
            $this->pdo->prepare("
                INSERT INTO lessons (course_id, title, sort_order, content_type, duration_minutes)
                VALUES (?, 'Present Perfect EP1', 1, 'video', 35)
            ")->execute([$englishCourseId]);
            $lessonId = (int)$this->pdo->lastInsertId();
        }

        // Ensure Task "Present Perfect EP1" exists in the roadmap and is assigned to English course
        $stmtT = $this->pdo->prepare("SELECT id FROM roadmap_tasks WHERE roadmap_id = ? AND title LIKE '%Present Perfect EP1%' LIMIT 1");
        $stmtT->execute([$roadmapId]);
        $taskId = (int)$stmtT->fetchColumn();
        if (!$taskId) {
            $this->pdo->prepare("
                INSERT INTO roadmap_tasks (roadmap_id, stage, title, subject, category, completion_type, ref_course_id, ref_lesson_id, is_required, sort_order, is_active)
                VALUES (?, 'm4', 'Present Perfect EP1', 'ภาษาอังกฤษ', 'Grammar', 'complete_lesson', ?, ?, 1, 1, 1)
            ")->execute([$roadmapId, $englishCourseId, $lessonId]);
            $taskId = (int)$this->pdo->lastInsertId();
        } else {
            $this->pdo->prepare("
                UPDATE roadmap_tasks
                SET ref_course_id = ?, ref_lesson_id = ?, is_active = 1, sort_order = 1
                WHERE id = ?
            ")->execute([$englishCourseId, $lessonId, $taskId]);
        }

        // Reset progress on this task so it is available as NEXT ACTION
        $this->pdo->prepare("
            INSERT INTO roadmap_task_progress (task_id, user_id, status, is_completed, updated_at)
            VALUES (?, ?, 'not_started', 0, NOW())
            ON DUPLICATE KEY UPDATE status = 'not_started', is_completed = 0, updated_at = NOW()
        ")->execute([$taskId, $baitoeyId]);

        // Also ensure Learning Path record exists for English
        $lpStmt = $this->pdo->prepare("SELECT id FROM learning_paths WHERE user_id = ? AND course_id = ?");
        $lpStmt->execute([$baitoeyId, $englishCourseId]);
        if (!$lpStmt->fetchColumn()) {
            $planSteps = [
                ['title' => 'Present Simple (พื้นฐานไวยากรณ์)', 'status' => 'mastered'],
                ['title' => 'Present Perfect (หัวข้อถัดไป)', 'status' => 'next_up'],
                ['title' => 'Reading Inference & Strategies', 'status' => 'upcoming'],
            ];
            $this->pdo->prepare("
                INSERT INTO learning_paths (user_id, course_id, subject, current_level, target_goal, hours_per_week, plan_data, progress_percent)
                VALUES (?, ?, 'ภาษาอังกฤษ', 'ม.5', 'A-Level English 75', 4, ?, 33.3)
            ")->execute([$baitoeyId, $englishCourseId, json_encode(['steps' => $planSteps], JSON_UNESCAPED_UNICODE)]);
        }

        return [
            'success' => true,
            'baitoey_id' => $baitoeyId,
            'candy_id' => $candyId,
            'courses' => $courseIds,
            'english_course_id' => $englishCourseId,
            'next_action_task_id' => $taskId,
            'next_action_lesson_id' => $lessonId,
        ];
    }
}
