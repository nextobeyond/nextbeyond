<?php
/**
 * Nextbeyond Compass V2 — Phase 3: Practice & Measure Layer Service
 * 
 * Manages:
 * - Learning Activities (Worksheet, Practice, Homework, Post-Test)
 * - Assignment Distribution (Class Group, Course, Individual Student)
 * - Autosave & Secure Submissions
 * - Learning Evidence Recording
 * - Transparent Weighted Mastery Calculation
 * - Pre/Post Test Comparison
 * - Roadmap & Next Action Bridge
 */
declare(strict_types=1);

require_once __DIR__ . '/phase4-adaptive-service.php';
require_once __DIR__ . '/phase5-mastery-service.php';

class Phase3MasteryService
{
    private PDO $pdo;

    // Configurable Mastery Source Weights (Section 30)
    public const WEIGHTS = [
        'diagnostic'     => 0.15,
        'get_ready'      => 0.05,
        'in_class_check' => 0.10,
        'worksheet'      => 0.15,
        'practice'       => 0.15,
        'homework'       => 0.15,
        'posttest'       => 0.25,
        'mock_exam'      => 0.25,
        'remediation'    => 0.15,
        'mastery_check'  => 0.25,
        'teacher_assessment' => 0.25,
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensurePhase3Schema();
    }

    // ──────────────────────────────────────────────────────────────────────
    // 1. NON-DESTRUCTIVE DATABASE MIGRATION
    // ──────────────────────────────────────────────────────────────────────

    public function ensurePhase3Schema(): void
    {
        static $ensured = false;
        if ($ensured) return;

        // 1. Extend worksheet_assignments with activity fields
        $this->ensureColumn('worksheet_assignments', 'activity_type', "ENUM('worksheet', 'practice', 'homework', 'posttest', 'remediation', 'mastery_check') NOT NULL DEFAULT 'worksheet'");
        $this->ensureColumn('worksheet_assignments', 'title', "VARCHAR(255) NULL");
        $this->ensureColumn('worksheet_assignments', 'exam_id', "INT NULL");
        $this->ensureColumn('worksheet_assignments', 'topic_name', "VARCHAR(255) NULL");
        $this->ensureColumn('worksheet_assignments', 'session_id', "VARCHAR(64) NULL");
        $this->ensureColumn('worksheet_assignments', 'class_group_id', "INT NULL");
        $this->ensureColumn('worksheet_assignments', 'max_attempts', "INT NOT NULL DEFAULT 1");
        $this->ensureColumn('worksheet_assignments', 'pass_score', "DECIMAL(5,2) NULL");
        $this->ensureColumn('worksheet_assignments', 'status', "ENUM('active', 'closed', 'archived') NOT NULL DEFAULT 'active'");
        try {
            $this->pdo->exec("ALTER TABLE worksheet_assignments MODIFY COLUMN worksheet_id INT NULL");
        } catch (Throwable $e) {}

        // 2. Table: activity_submissions
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `activity_submissions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `assignment_id` INT NOT NULL,
                `student_id` INT NOT NULL,
                `activity_type` VARCHAR(50) NOT NULL DEFAULT 'worksheet',
                `attempt_number` INT NOT NULL DEFAULT 1,
                `score` DECIMAL(5,2) NULL,
                `max_score` DECIMAL(5,2) NULL,
                `score_percent` DECIMAL(5,2) NULL,
                `status` ENUM('not_started', 'in_progress', 'submitted', 'completed', 'late', 'overdue', 'review_required', 'returned') NOT NULL DEFAULT 'not_started',
                `started_at` DATETIME NULL,
                `submitted_at` DATETIME NULL,
                `graded_at` DATETIME NULL,
                `teacher_feedback` TEXT NULL,
                `answers_json` MEDIUMTEXT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_assign_student_attempt` (`assignment_id`, `student_id`, `attempt_number`),
                INDEX `idx_student_status` (`student_id`, `status`),
                INDEX `idx_assign_student` (`assignment_id`, `student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        // 3. Table: activity_answers
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `activity_answers` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `submission_id` INT NOT NULL,
                `question_id` INT NOT NULL,
                `question_type` VARCHAR(50) NOT NULL DEFAULT 'multipleChoice',
                `student_answer` MEDIUMTEXT NULL,
                `correct_answer` MEDIUMTEXT NULL,
                `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
                `score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                `hint_used` TINYINT(1) NOT NULL DEFAULT 0,
                `time_spent_seconds` INT NOT NULL DEFAULT 0,
                `topic_name` VARCHAR(255) NULL,
                `skill` VARCHAR(100) NULL,
                `saved_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_sub_q` (`submission_id`, `question_id`),
                INDEX `idx_sub` (`submission_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 4. Table: learning_evidence
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `learning_evidence` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `student_id` INT NOT NULL,
                `course_id` INT NOT NULL,
                `topic_name` VARCHAR(255) NOT NULL,
                `skill` VARCHAR(100) NULL,
                `source_type` ENUM('diagnostic', 'get_ready', 'in_class_check', 'worksheet', 'practice', 'homework', 'posttest', 'mock_exam', 'remediation', 'mastery_check', 'teacher_assessment') NOT NULL,
                `source_id` VARCHAR(64) NOT NULL,
                `question_id` INT NULL,
                `canonical_question_id` BIGINT UNSIGNED NULL,
                `difficulty` VARCHAR(50) NULL,
                `is_correct` TINYINT(1) NULL,
                `score` DECIMAL(5,2) NOT NULL,
                `max_score` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
                `normalized_score` DECIMAL(5,2) NOT NULL,
                `weight` DECIMAL(4,2) NOT NULL DEFAULT 1.00,
                `confidence` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
                `occurred_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_student_topic` (`student_id`, `course_id`, `topic_name`),
                INDEX `idx_source` (`source_type`, `source_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $this->ensureColumn('learning_evidence', 'canonical_question_id', 'BIGINT UNSIGNED NULL AFTER question_id');
        $this->ensureColumn('learning_evidence', 'difficulty', 'VARCHAR(50) NULL AFTER canonical_question_id');
        $this->ensureColumn('learning_evidence', 'is_correct', 'TINYINT(1) NULL AFTER difficulty');

        // Keep upgraded installations aligned with Phase 4/5 evidence and assignment types.
        try {
            $this->pdo->exec("ALTER TABLE worksheet_assignments MODIFY activity_type ENUM('worksheet','practice','homework','posttest','remediation','mastery_check') NOT NULL DEFAULT 'worksheet'");
            $this->pdo->exec("ALTER TABLE learning_evidence MODIFY source_type ENUM('diagnostic','get_ready','in_class_check','worksheet','practice','homework','posttest','mock_exam','remediation','mastery_check','teacher_assessment') NOT NULL");
        } catch (Throwable $e) {}

        $ensured = true;
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        try {
            $check = $this->pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
            if ($check && $check->fetch()) return;
            $this->pdo->exec("ALTER TABLE `{$table}` ADD `{$column}` {$definition}");
        } catch (Throwable $e) {
            // Already exists or non-fatal
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // 2. ASSIGNMENT MANAGEMENT & TARGETING
    // ──────────────────────────────────────────────────────────────────────

    public function createAssignment(array $params): int
    {
        $worksheetId   = !empty($params['worksheet_id']) ? (int)$params['worksheet_id'] : null;
        $examId        = !empty($params['exam_id']) ? (int)$params['exam_id'] : null;
        $courseId      = !empty($params['course_id']) ? (int)$params['course_id'] : null;
        $epId          = !empty($params['ep_id']) ? (int)$params['ep_id'] : null;
        $classGroupId  = !empty($params['class_group_id']) ? (int)$params['class_group_id'] : null;
        $className     = trim((string)($params['class_name'] ?? ''));
        $targetType    = ($params['target_type'] ?? '') === 'selected' ? 'selected' : 'all';
        $studentIds    = isset($params['student_ids']) && is_array($params['student_ids']) ? json_encode(array_map('intval', $params['student_ids'])) : null;
        $dueDate       = !empty($params['due_date']) ? date('Y-m-d H:i:s', strtotime($params['due_date'])) : null;
        $activityType  = in_array($params['activity_type'] ?? '', ['worksheet', 'practice', 'homework', 'posttest', 'remediation', 'mastery_check'], true) ? $params['activity_type'] : 'worksheet';
        $title         = trim((string)($params['title'] ?? ''));
        $topicName     = trim((string)($params['topic_name'] ?? ''));
        $sessionId     = trim((string)($params['session_id'] ?? '')) ?: null;
        $maxAttempts   = !empty($params['max_attempts']) ? (int)$params['max_attempts'] : 1;
        $passScore     = isset($params['pass_score']) && $params['pass_score'] !== '' ? (float)$params['pass_score'] : null;
        $assignedBy    = (int)($params['assigned_by'] ?? 1);
        $assignedByName= trim((string)($params['assigned_by_name'] ?? 'ครูผู้สอน'));

        // If title is empty, infer from worksheet or exam
        if ($title === '') {
            if ($worksheetId) {
                $st = $this->pdo->prepare("SELECT title, topic, subject FROM worksheets WHERE id = ?");
                $st->execute([$worksheetId]);
                $ws = $st->fetch();
                if ($ws) {
                    $title = $ws['title'];
                    if ($topicName === '') $topicName = $ws['topic'] ?: $ws['subject'];
                }
            } elseif ($examId) {
                $st = $this->pdo->prepare("SELECT title, topic, subject FROM exams WHERE id = ?");
                $st->execute([$examId]);
                $ex = $st->fetch();
                if ($ex) {
                    $title = $ex['title'];
                    if ($topicName === '') $topicName = $ex['topic'] ?: $ex['subject'];
                }
            }
        }

        // If class_group_id provided but class_name empty, infer from class_groups
        if ($classGroupId && $className === '') {
            $cg = $this->pdo->prepare("SELECT name, course_id FROM class_groups WHERE id = ?");
            $cg->execute([$classGroupId]);
            $rowCg = $cg->fetch();
            if ($rowCg) {
                $className = $rowCg['name'];
                if (!$courseId) $courseId = (int)$rowCg['course_id'];
            }
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO worksheet_assignments (
                worksheet_id, exam_id, course_id, ep_id, class_name, class_group_id,
                target_type, student_ids, due_date, activity_type, title, topic_name,
                session_id, max_attempts, pass_score, status, assigned_by, assigned_by_name, created_at
            ) VALUES (
                :ws_id, :ex_id, :cid, :ep_id, :cname, :cg_id,
                :ttype, :sids, :due, :atype, :title, :tname,
                :sid, :max_att, :pass_score, 'active', :by_id, :by_name, NOW()
            )
        ");
        $stmt->execute([
            ':ws_id'      => $worksheetId,
            ':ex_id'      => $examId,
            ':cid'        => $courseId,
            ':ep_id'      => $epId,
            ':cname'      => $className ?: null,
            ':cg_id'      => $classGroupId,
            ':ttype'      => $targetType,
            ':sids'       => $studentIds,
            ':due'        => $dueDate,
            ':atype'      => $activityType,
            ':title'      => $title ?: 'แบบฝึกหัดการเรียนรู้',
            ':tname'      => $topicName ?: null,
            ':sid'        => $sessionId,
            ':max_att'    => $maxAttempts,
            ':pass_score' => $passScore,
            ':by_id'      => $assignedBy,
            ':by_name'    => $assignedByName,
        ]);

        $assignmentId = (int)$this->pdo->lastInsertId();

        if ($worksheetId) {
            $this->pdo->prepare("UPDATE worksheets SET usage_count = usage_count + 1 WHERE id = ?")->execute([$worksheetId]);
        }

        return $assignmentId;
    }

    /**
     * Server-side access control: can student access this assignment?
     */
    public function canStudentAccessAssignment(int $studentId, int $assignmentId): bool
    {
        $stmt = $this->pdo->prepare("SELECT * FROM worksheet_assignments WHERE id = ?");
        $stmt->execute([$assignmentId]);
        $a = $stmt->fetch();
        if (!$a || $a['status'] === 'archived') return false;

        // 1. If assigned explicitly to selected students
        if ($a['target_type'] === 'selected') {
            $sids = json_decode((string)$a['student_ids'], true) ?: [];
            return in_array($studentId, $sids, true);
        }

        // 2. If assigned to a specific class group
        if (!empty($a['class_group_id'])) {
            $cgStmt = $this->pdo->prepare("
                SELECT 1 FROM enrollments
                WHERE user_id = :sid AND class_group_id = :cgid
                  AND status IN ('active', 'trial')
                  AND (end_date IS NULL OR end_date >= CURDATE())
                LIMIT 1
            ");
            $cgStmt->execute([':sid' => $studentId, ':cgid' => $a['class_group_id']]);
            if ($cgStmt->fetchColumn()) return true;
        }

        // 3. If assigned by course_id
        if (!empty($a['course_id'])) {
            $enStmt = $this->pdo->prepare("
                SELECT 1 FROM enrollments
                WHERE user_id = :sid AND course_id = :cid
                  AND status IN ('active', 'trial')
                  AND (end_date IS NULL OR end_date >= CURDATE())
                LIMIT 1
            ");
            $enStmt->execute([':sid' => $studentId, ':cid' => $a['course_id']]);
            if ($enStmt->fetchColumn()) return true;
        }

        // 4. If neither course nor group set and target_type is all (global activity)
        if (empty($a['course_id']) && empty($a['class_group_id']) && $a['target_type'] === 'all') {
            return true;
        }

        return false;
    }

    /**
     * Get all assignments visible to a student, with submission status
     */
    public function getStudentAssignments(int $studentId, ?int $courseId = null, string $statusFilter = 'active'): array
    {
        // Find enrolled course IDs and class group IDs for student
        $enStmt = $this->pdo->prepare("
            SELECT course_id, class_group_id FROM enrollments
            WHERE user_id = :sid AND status IN ('active', 'trial')
              AND (end_date IS NULL OR end_date >= CURDATE())
        ");
        $enStmt->execute([':sid' => $studentId]);
        $enrollments = $enStmt->fetchAll();
        $courseIds = array_unique(array_filter(array_column($enrollments, 'course_id')));
        $classGroupIds = array_unique(array_filter(array_column($enrollments, 'class_group_id')));

        if (empty($courseIds) && empty($classGroupIds)) {
            return [];
        }

        $sql = "
            SELECT a.*, c.title AS course_title, c.subject AS course_subject,
                   w.question_count,
                   sub.id AS submission_id, sub.status AS submission_status,
                   sub.score_percent, sub.attempt_number, sub.submitted_at, sub.updated_at AS sub_updated_at
            FROM worksheet_assignments a
            LEFT JOIN courses c ON c.id = a.course_id
            LEFT JOIN worksheets w ON w.id = a.worksheet_id
            LEFT JOIN activity_submissions sub ON sub.assignment_id = a.id
                 AND sub.student_id = :sid
                 AND sub.attempt_number = (
                     SELECT MAX(attempt_number) FROM activity_submissions
                     WHERE assignment_id = a.id AND student_id = :sid2
                 )
            WHERE a.status = 'active'
        ";

        $params = [':sid' => $studentId, ':sid2' => $studentId];

        if ($courseId !== null && $courseId > 0) {
            $sql .= " AND a.course_id = :filter_course_id";
            $params[':filter_course_id'] = $courseId;
        }

        $sql .= " ORDER BY (a.due_date IS NOT NULL AND a.due_date >= NOW()) DESC, a.due_date ASC, a.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $visible = [];
        $now = new DateTimeImmutable();

        foreach ($rows as $r) {
            // Check access
            $targetType = $r['target_type'];
            $hasAccess = false;

            if ($targetType === 'selected') {
                $sids = json_decode((string)$r['student_ids'], true) ?: [];
                if (in_array($studentId, $sids, true)) $hasAccess = true;
            } else {
                if (!empty($r['class_group_id']) && in_array((int)$r['class_group_id'], $classGroupIds, true)) {
                    $hasAccess = true;
                } elseif (!empty($r['course_id']) && in_array((int)$r['course_id'], $courseIds, true)) {
                    $hasAccess = true;
                } elseif (empty($r['course_id']) && empty($r['class_group_id'])) {
                    $hasAccess = true;
                }
            }

            if (!$hasAccess) continue;

            // Determine effective status
            $subStatus = $r['submission_status'] ?? 'not_started';
            $isOverdue = false;
            if ($r['due_date']) {
                $dueDt = new DateTimeImmutable($r['due_date']);
                if ($dueDt < $now && in_array($subStatus, ['not_started', 'in_progress'], true)) {
                    $subStatus = 'overdue';
                    $isOverdue = true;
                }
            }

            if ($statusFilter === 'pending' && in_array($subStatus, ['submitted', 'completed'], true)) {
                continue;
            } elseif ($statusFilter === 'completed' && !in_array($subStatus, ['submitted', 'completed'], true)) {
                continue;
            }

            $r['effective_status'] = $subStatus;
            $r['is_overdue'] = $isOverdue;
            $visible[] = $r;
        }

        return $visible;
    }

    /**
     * Get assignment detail with scorable questions
     */
    public function getAssignmentDetails(int $assignmentId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, c.title AS course_title, c.subject AS course_subject,
                   w.description AS worksheet_desc, w.difficulty AS worksheet_difficulty,
                   e.time_limit_minutes AS exam_time_limit
            FROM worksheet_assignments a
            LEFT JOIN courses c ON c.id = a.course_id
            LEFT JOIN worksheets w ON w.id = a.worksheet_id
            LEFT JOIN exams e ON e.id = a.exam_id
            WHERE a.id = ?
        ");
        $stmt->execute([$assignmentId]);
        $a = $stmt->fetch();
        if (!$a) return null;

        $questions = [];
        if (!empty($a['worksheet_id'])) {
            $qStmt = $this->pdo->prepare("
                SELECT id, canonical_question_id, sort_order, question_type, question_text, options,
                       correct_answer, explanation, hint, skill, difficulty, learning_objective, image_url, points
                FROM worksheet_questions
                WHERE worksheet_id = ?
                ORDER BY sort_order ASC, id ASC
            ");
            $qStmt->execute([$a['worksheet_id']]);
            $questions = $qStmt->fetchAll();
        } elseif (!empty($a['exam_id'])) {
            $qStmt = $this->pdo->prepare("
                SELECT id, canonical_question_id, sort_order, 'multipleChoice' AS question_type, question_text, options,
                       correct_answer, explanation, '' AS hint, skill, difficulty, learning_objective, image_url, 1 AS points
                FROM exam_questions
                WHERE exam_id = ?
                ORDER BY sort_order ASC, id ASC
            ");
            $qStmt->execute([$a['exam_id']]);
            $questions = $qStmt->fetchAll();
        }

        foreach ($questions as &$q) {
            if ($q['options']) {
                $opts = json_decode((string)$q['options'], true);
                $q['options'] = is_array($opts) ? $opts : explode("\n", (string)$q['options']);
            } else {
                $q['options'] = [];
            }
        }
        unset($q);

        $a['questions'] = $questions;
        return $a;
    }

    // ──────────────────────────────────────────────────────────────────────
    // 3. STUDENT RUNNER, AUTOSAVE & SUBMISSIONS
    // ──────────────────────────────────────────────────────────────────────

    public function getOrCreateSubmission(int $assignmentId, int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM activity_submissions
            WHERE assignment_id = :aid AND student_id = :sid
            ORDER BY attempt_number DESC LIMIT 1
        ");
        $stmt->execute([':aid' => $assignmentId, ':sid' => $studentId]);
        $sub = $stmt->fetch();

        if ($sub && in_array($sub['status'], ['in_progress', 'not_started'], true)) {
            $sub['answers'] = json_decode((string)($sub['answers_json'] ?? '{}'), true) ?: [];
            return $sub;
        }

        // If completed or no submission, check max_attempts
        $attemptNum = $sub ? (int)$sub['attempt_number'] + 1 : 1;

        $assignStmt = $this->pdo->prepare("SELECT activity_type, max_attempts FROM worksheet_assignments WHERE id = ?");
        $assignStmt->execute([$assignmentId]);
        $assign = $assignStmt->fetch();
        $activityType = $assign ? $assign['activity_type'] : 'worksheet';
        $maxAttempts = $assign ? (int)$assign['max_attempts'] : 1;

        if ($sub && $maxAttempts > 0 && $attemptNum > $maxAttempts) {
            // Cannot start new attempt; return latest completed
            $sub['answers'] = json_decode((string)($sub['answers_json'] ?? '{}'), true) ?: [];
            $sub['can_retry'] = false;
            return $sub;
        }

        $ins = $this->pdo->prepare("
            INSERT INTO activity_submissions (
                assignment_id, student_id, activity_type, attempt_number,
                status, started_at, answers_json
            ) VALUES (
                :aid, :sid, :atype, :att, 'in_progress', NOW(), '{}'
            )
        ");
        $ins->execute([
            ':aid'   => $assignmentId,
            ':sid'   => $studentId,
            ':atype' => $activityType,
            ':att'   => $attemptNum,
        ]);

        $subId = (int)$this->pdo->lastInsertId();
        return [
            'id'             => $subId,
            'assignment_id'  => $assignmentId,
            'student_id'     => $studentId,
            'activity_type'  => $activityType,
            'attempt_number' => $attemptNum,
            'status'         => 'in_progress',
            'started_at'     => date('Y-m-d H:i:s'),
            'answers'        => [],
            'can_retry'      => true,
        ];
    }

    /**
     * Autosave individual question response
     */
    public function autosaveAnswer(
        int $submissionId, int $studentId, int $questionId,
        $studentAnswer, bool $hintUsed = false, int $timeSpent = 0,
        ?string $clientTimestamp = null
    ): array {
        // Validate submission ownership & status
        $stmt = $this->pdo->prepare("
            SELECT s.*, a.activity_type, a.worksheet_id, a.exam_id
            FROM activity_submissions s
            JOIN worksheet_assignments a ON a.id = s.assignment_id
            WHERE s.id = :sub_id AND s.student_id = :sid
        ");
        $stmt->execute([':sub_id' => $submissionId, ':sid' => $studentId]);
        $sub = $stmt->fetch();
        if (!$sub) {
            return ['success' => false, 'error' => 'Submission not found or unauthorized'];
        }
        if (in_array($sub['status'], ['completed', 'submitted'], true)) {
            return ['success' => false, 'error' => 'Activity already submitted'];
        }

        // Recency Protection (Section 15, 73): Protect against old client state overwriting newer server state
        if (!empty($clientTimestamp)) {
            $existing = $this->pdo->prepare("SELECT saved_at FROM activity_answers WHERE submission_id = ? AND question_id = ?");
            $existing->execute([$submissionId, $questionId]);
            $existingSavedAt = $existing->fetchColumn();
            if ($existingSavedAt && strtotime((string)$existingSavedAt) > strtotime($clientTimestamp)) {
                return [
                    'success'     => true,
                    'skipped'     => true,
                    'reason'      => 'Server has newer state',
                    'question_id' => $questionId,
                    'saved_at'    => $existingSavedAt,
                ];
            }
        }

        // Fetch question info
        $qInfo = null;
        if (!empty($sub['worksheet_id'])) {
            $stQ = $this->pdo->prepare("SELECT * FROM worksheet_questions WHERE id = ?");
            $stQ->execute([$questionId]);
            $qInfo = $stQ->fetch();
        } elseif (!empty($sub['exam_id'])) {
            $stQ = $this->pdo->prepare("SELECT *, 'multipleChoice' AS question_type FROM exam_questions WHERE id = ?");
            $stQ->execute([$questionId]);
            $qInfo = $stQ->fetch();
        }

        $correctAnswer = $qInfo ? (string)$qInfo['correct_answer'] : null;
        $qType = $qInfo ? (string)$qInfo['question_type'] : 'multipleChoice';
        $skill = $qInfo ? ($qInfo['skill'] ?? null) : null;
        $topic = $qInfo ? ($qInfo['subtopic'] ?? null) : null;

        // Auto-check correctness for practice or immediate feedback
        $isCorrect = false;
        $points = $qInfo ? (int)($qInfo['points'] ?? 1) : 1;
        $scoreEarned = 0.0;

        if ($correctAnswer !== null) {
            $trimmedAns = trim((string)$studentAnswer);
            $trimmedCor = trim($correctAnswer);
            if (strcasecmp($trimmedAns, $trimmedCor) === 0) {
                $isCorrect = true;
                $scoreEarned = (float)$points;
            }
        }

        $saveTime = !empty($clientTimestamp) ? date('Y-m-d H:i:s', strtotime($clientTimestamp)) : date('Y-m-d H:i:s');

        // Save to activity_answers table
        $ansStmt = $this->pdo->prepare("
            INSERT INTO activity_answers (
                submission_id, question_id, question_type, student_answer,
                correct_answer, is_correct, score, hint_used, time_spent_seconds,
                topic_name, skill, saved_at
            ) VALUES (
                :sub_id, :qid, :qtype, :ans,
                :corr, :is_corr, :score, :hint, :time_spent,
                :topic, :skill, :saved_at
            )
            ON DUPLICATE KEY UPDATE
                student_answer     = VALUES(student_answer),
                is_correct         = VALUES(is_correct),
                score              = VALUES(score),
                hint_used          = GREATEST(hint_used, VALUES(hint_used)),
                time_spent_seconds = time_spent_seconds + VALUES(time_spent_seconds),
                saved_at           = VALUES(saved_at)
        ");
        $ansStmt->execute([
            ':sub_id'     => $submissionId,
            ':qid'        => $questionId,
            ':qtype'      => $qType,
            ':ans'        => is_array($studentAnswer) ? json_encode($studentAnswer) : (string)$studentAnswer,
            ':corr'       => $correctAnswer,
            ':is_corr'    => $isCorrect ? 1 : 0,
            ':score'      => $scoreEarned,
            ':hint'       => $hintUsed ? 1 : 0,
            ':time_spent' => $timeSpent,
            ':topic'      => $topic,
            ':skill'      => $skill,
            ':saved_at'   => $saveTime,
        ]);

        // Update answers_json in activity_submissions
        $currAnswers = json_decode((string)($sub['answers_json'] ?? '{}'), true) ?: [];
        $currAnswers[(string)$questionId] = $studentAnswer;

        $this->pdo->prepare("
            UPDATE activity_submissions
            SET answers_json = :ajson, status = 'in_progress', updated_at = NOW()
            WHERE id = :id
        ")->execute([
            ':ajson' => json_encode($currAnswers, JSON_UNESCAPED_UNICODE),
            ':id'    => $submissionId,
        ]);

        return [
            'success'     => true,
            'question_id' => $questionId,
            'is_correct'  => $isCorrect,
            'explanation' => in_array($sub['activity_type'], ['practice', 'remediation'], true) ? ($qInfo['explanation'] ?? '') : null,
            'saved_at'    => $saveTime,
        ];
    }

    /**
     * Batch sync pending answers (handles offline reconnection)
     */
    public function syncPendingAnswers(int $submissionId, int $studentId, array $pendingAnswers): array
    {
        $saved = [];
        foreach ($pendingAnswers as $k => $val) {
            if (is_array($val) && isset($val['question_id'])) {
                $qIdInt = (int)$val['question_id'];
                $ans = $val['student_answer'] ?? $val['answer'] ?? '';
                $hint = !empty($val['hint_used']);
                $time = (int)($val['time_spent'] ?? $val['time_spent_seconds'] ?? 0);
                $ts = isset($val['client_timestamp']) ? (string)$val['client_timestamp'] : null;
            } else {
                $qIdInt = (int)$k;
                $ans = $val;
                $hint = false;
                $time = 0;
                $ts = null;
            }

            if ($qIdInt > 0) {
                $saved[$qIdInt] = $this->autosaveAnswer($submissionId, $studentId, $qIdInt, $ans, $hint, $time, $ts);
            }
        }
        return ['success' => true, 'synced_count' => count($saved), 'results' => $saved];
    }

    /**
     * Submit activity: final grade calculation, evidence recording, roadmap update, mastery sync
     */
    public function submitActivity(int $submissionId, int $studentId, ?array $finalAnswers = null): array
    {
        // 1. Validate submission
        $stmt = $this->pdo->prepare("
            SELECT s.*, a.activity_type, a.course_id, a.worksheet_id, a.exam_id, a.topic_name,
                   a.pass_score, a.due_date, a.class_group_id
            FROM activity_submissions s
            JOIN worksheet_assignments a ON a.id = s.assignment_id
            WHERE s.id = :sub_id AND s.student_id = :sid
        ");
        $stmt->execute([':sub_id' => $submissionId, ':sid' => $studentId]);
        $sub = $stmt->fetch();
        if (!$sub) {
            return ['success' => false, 'error' => 'Submission not found'];
        }
        if ($sub['status'] === 'completed') {
            return [
                'success'       => true,
                'already_done'  => true,
                'score_percent' => (float)$sub['score_percent'],
                'score'         => (float)$sub['score'],
                'max_score'     => (float)$sub['max_score'],
            ];
        }

        // If final answers provided, sync them first
        if ($finalAnswers && is_array($finalAnswers)) {
            $this->syncPendingAnswers($submissionId, $studentId, $finalAnswers);
        }

        // 2. Fetch all questions for this activity
        $details = $this->getAssignmentDetails((int)$sub['assignment_id']);
        $questions = $details ? $details['questions'] : [];
        $totalQuestions = count($questions);

        // Fetch saved answers
        $ansStmt = $this->pdo->prepare("SELECT * FROM activity_answers WHERE submission_id = ?");
        $ansStmt->execute([$submissionId]);
        $savedAnswers = [];
        foreach ($ansStmt->fetchAll() as $row) {
            $savedAnswers[(int)$row['question_id']] = $row;
        }

        // Calculate score
        $totalPointsEarned = 0.0;
        $maxPointsPossible = 0.0;
        $correctCount = 0;
        $topicPerformance = [];

        foreach ($questions as $q) {
            $qid = (int)$q['id'];
            $qPoints = (float)($q['points'] ?? 1.0);
            $maxPointsPossible += $qPoints;

            $topic = trim((string)($q['subtopic'] ?? ($details['topic_name'] ?? 'General')));
            if (!isset($topicPerformance[$topic])) {
                $topicPerformance[$topic] = ['earned' => 0.0, 'max' => 0.0, 'correct' => 0, 'total' => 0];
            }
            $topicPerformance[$topic]['max'] += $qPoints;
            $topicPerformance[$topic]['total']++;

            $ans = $savedAnswers[$qid] ?? null;
            if ($ans && (int)$ans['is_correct'] === 1) {
                $totalPointsEarned += $qPoints;
                $correctCount++;
                $topicPerformance[$topic]['earned'] += $qPoints;
                $topicPerformance[$topic]['correct']++;
            }

            if (in_array((string)$sub['activity_type'], ['remediation', 'mastery_check'], true)) {
                $correct = $ans && (int)$ans['is_correct'] === 1;
                $evidenceType = (string)$sub['activity_type'];
                $this->recordLearningEvidence([
                    'student_id' => $studentId,
                    'course_id' => (int)($sub['course_id'] ?? 0),
                    'topic_name' => $topic,
                    'skill' => $q['skill'] ?? null,
                    'source_type' => $evidenceType,
                    'source_id' => $submissionId . ':' . $qid,
                    'question_id' => $qid,
                    'canonical_question_id' => $q['canonical_question_id'] ?? null,
                    'difficulty' => $q['difficulty'] ?? null,
                    'is_correct' => $correct,
                    'score' => $correct ? 1.0 : 0.0,
                    'max_score' => 1.0,
                    'weight' => $evidenceType === 'mastery_check' ? 0.25 : 0.15,
                ]);
            }
        }

        $scorePercent = $maxPointsPossible > 0 ? round(($totalPointsEarned / $maxPointsPossible) * 100, 2) : 0.0;

        // Determine status (Completed or Late)
        $now = new DateTimeImmutable();
        $isLate = false;
        if (!empty($sub['due_date'])) {
            $dueDt = new DateTimeImmutable($sub['due_date']);
            if ($now > $dueDt) {
                $isLate = true;
            }
        }
        $finalStatus = $isLate ? 'late' : 'completed';

        // Update activity_submissions
        $upd = $this->pdo->prepare("
            UPDATE activity_submissions
            SET score = :score, max_score = :max_score, score_percent = :score_pct,
                status = :status, submitted_at = NOW(), graded_at = NOW(), updated_at = NOW()
            WHERE id = :id
        ");
        $upd->execute([
            ':score'     => $totalPointsEarned,
            ':max_score' => $maxPointsPossible,
            ':score_pct' => $scorePercent,
            ':status'    => $finalStatus,
            ':id'        => $submissionId,
        ]);

        $courseId = (int)($sub['course_id'] ?? 0);
        $activityType = (string)$sub['activity_type'];
        $activityTopic = (string)($sub['topic_name'] ?? 'General');

        // 3. Record Learning Evidence per topic and overall
        foreach ($topicPerformance as $tName => $perf) {
            $topicScorePct = $perf['max'] > 0 ? round(($perf['earned'] / $perf['max']) * 100, 2) : 0.0;
            $this->recordLearningEvidence(
                $studentId,
                $courseId,
                $tName,
                $activityType,
                (string)$submissionId,
                (float)$topicScorePct,
                100.0,
                null
            );

            // Trigger mastery update for this topic
            $this->updateStudentMastery($studentId, $courseId, $tName);
        }

        // Also record overall course activity evidence if activity topic is distinct
        if (!empty($activityTopic) && !isset($topicPerformance[$activityTopic])) {
            $this->recordLearningEvidence(
                $studentId,
                $courseId,
                $activityTopic,
                $activityType,
                (string)$submissionId,
                (float)$scorePercent,
                100.0,
                null
            );
            $this->updateStudentMastery($studentId, $courseId, $activityTopic);
        }

        // 4. Update Roadmap task if any match
        $this->evaluateRoadmapAfterActivity($studentId, $courseId, $sub);

        if ($activityType === 'remediation') {
            try {
                (new \NextBeyond\Adaptive\RemediationService($this->pdo))
                    ->recordCompletion((int)$sub['assignment_id'], $submissionId, $studentId);
                $check = (new \NextBeyond\Mastery\MasteryCheckService($this->pdo))
                    ->evaluate($studentId, $courseId, $activityTopic, '', ['check_type' => 'evidence_evaluation']);
                if (!empty($check['gap_id'])) {
                    (new \NextBeyond\Mastery\LearningDecisionService($this->pdo))->applySystemDecision($check);
                }
            } catch (Throwable $e) {
                // Submission/evidence must remain durable; the completion hook is safely retryable.
            }
        }

        $learningDecision = null;
        if ($activityType === 'mastery_check') {
            try {
                $learningDecision = (new \NextBeyond\Mastery\MasteryCheckService($this->pdo))
                    ->handleAssignmentCompletion((int)$sub['assignment_id'], $submissionId, $studentId);
            } catch (Throwable $e) {
                // The graded submission remains durable and the idempotent hook can be retried.
            }
        }

        return [
            'success'          => true,
            'submission_id'    => $submissionId,
            'score'            => $totalPointsEarned,
            'max_score'        => $maxPointsPossible,
            'score_percent'    => $scorePercent,
            'percentage'       => $scorePercent,
            'correct_count'    => $correctCount,
            'total_questions'  => $totalQuestions,
            'status'           => $finalStatus,
            'is_late'          => $isLate,
            'topic_breakdown'  => $topicPerformance,
            'learning_decision'=> $learningDecision,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // 4. EVIDENCE RECORDING & WEIGHTED MASTERY ENGINE
    // ──────────────────────────────────────────────────────────────────────

    public function recordLearningEvidence(
        int|array $studentId, ?int $courseId = null, ?string $topicName = null,
        ?string $sourceType = null, ?string $sourceId = null, ?float $score = null,
        float $maxScore = 100.0, ?string $skill = null
    ): int {
        $occurredAt = null;
        if (is_array($studentId)) {
            $p = $studentId;
            $sid = (int)($p['student_id'] ?? 0);
            $cid = (int)($p['course_id'] ?? 0);
            $topic = (string)($p['topic_name'] ?? '');
            $stype = (string)($p['source_type'] ?? 'worksheet');
            $srcId = (string)($p['source_id'] ?? 'manual_' . time());
            $sc = (float)($p['score'] ?? $p['normalized_score'] ?? 0.0);
            $maxSc = (float)($p['max_score'] ?? 100.0);
            $skl = isset($p['skill']) ? (string)$p['skill'] : null;
            $questionId = isset($p['question_id']) ? (int)$p['question_id'] : null;
            $canonicalQuestionId = isset($p['canonical_question_id']) ? (int)$p['canonical_question_id'] : null;
            $evidenceDifficulty = isset($p['difficulty']) ? (string)$p['difficulty'] : null;
            $evidenceCorrect = array_key_exists('is_correct', $p) ? (int)(bool)$p['is_correct'] : null;
            $occurredAt = isset($p['occurred_at']) ? (string)$p['occurred_at'] : null;
            $customWeight = isset($p['weight']) ? (float)$p['weight'] : null;
        } else {
            $sid = $studentId;
            $cid = (int)($courseId ?? 0);
            $topic = (string)($topicName ?? '');
            $stype = (string)($sourceType ?? 'worksheet');
            $srcId = (string)($sourceId ?? 'manual_' . time());
            $sc = (float)($score ?? 0.0);
            $maxSc = $maxScore;
            $skl = $skill;
            $questionId = null;
            $canonicalQuestionId = null;
            $evidenceDifficulty = null;
            $evidenceCorrect = null;
            $customWeight = null;
        }

        $normalized = $maxSc > 0 ? round(($sc / $maxSc) * 100, 2) : 0.0;
        $weight = $customWeight !== null ? $customWeight : (self::WEIGHTS[$stype] ?? 0.15);

        // Evidence confidence:
        $stmtCount = $this->pdo->prepare("
            SELECT COUNT(*) FROM learning_evidence
            WHERE student_id = :sid AND course_id = :cid AND topic_name = :topic
        ");
        $stmtCount->execute([':sid' => $sid, ':cid' => $cid, ':topic' => $topic]);
        $priorCount = (int)$stmtCount->fetchColumn();

        $confidence = match (true) {
            $priorCount >= 5 => 'high',
            $priorCount >= 2 => 'medium',
            default          => 'low',
        };

        $sql = "
            INSERT INTO learning_evidence (
                student_id, course_id, topic_name, skill, source_type,
                source_id, question_id, canonical_question_id, difficulty, is_correct,
                score, max_score, normalized_score, weight,
                confidence, occurred_at
            ) VALUES (
                :sid, :cid, :topic, :skill, :stype,
                :src_id, :question_id, :canonical_question_id, :difficulty, :is_correct,
                :score, :max_score, :norm, :weight,
                :conf, " . ($occurredAt ? ":occ" : "NOW()") . "
            )
        ";
        $stmt = $this->pdo->prepare($sql);
        $execParams = [
            ':sid'       => $sid,
            ':cid'       => $cid,
            ':topic'     => $topic,
            ':skill'     => $skl,
            ':stype'     => $stype,
            ':src_id'    => $srcId,
            ':question_id' => $questionId,
            ':canonical_question_id' => $canonicalQuestionId,
            ':difficulty' => $evidenceDifficulty,
            ':is_correct' => $evidenceCorrect,
            ':score'     => $sc,
            ':max_score' => $maxSc,
            ':norm'      => $normalized,
            ':weight'    => $weight,
            ':conf'      => $confidence,
        ];
        if ($occurredAt) {
            $execParams[':occ'] = $occurredAt;
        }
        $stmt->execute($execParams);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Compute transparent weighted mastery for student in topic
     */
    public function updateStudentMastery(int $studentId, int $courseId, string $topicName): array
    {
        if ($studentId < 1 || $courseId < 1 || $topicName === '') {
            return ['mastery_score' => 0.0, 'evidence_count' => 0];
        }

        // Fetch all evidence for this student + course + topic
        $stmt = $this->pdo->prepare("
            SELECT source_type, normalized_score, weight, occurred_at
            FROM learning_evidence
            WHERE student_id = :sid AND course_id = :cid AND topic_name = :topic
            ORDER BY occurred_at ASC
        ");
        $stmt->execute([':sid' => $studentId, ':cid' => $courseId, ':topic' => $topicName]);
        $evidences = $stmt->fetchAll();

        $evidenceCount = count($evidences);
        if ($evidenceCount === 0) {
            return ['mastery_score' => 0.0, 'evidence_count' => 0, 'confidence' => 'low'];
        }

        // Calculate weighted score sum & weight sum
        $weightedSum = 0.0;
        $weightSum   = 0.0;

        foreach ($evidences as $ev) {
            $w = (float)($ev['weight'] ?? 0.15);
            $s = (float)$ev['normalized_score'];
            $weightedSum += ($s * $w);
            $weightSum   += $w;
        }

        $masteryScore = $weightSum > 0 ? round($weightedSum / $weightSum, 2) : 0.0;
        $confidence = match (true) {
            $evidenceCount >= 6 => 'high',
            $evidenceCount >= 3 => 'medium',
            default             => 'low',
        };

        // Determine course subject
        $cStmt = $this->pdo->prepare("SELECT subject FROM courses WHERE id = ?");
        $cStmt->execute([$courseId]);
        $subject = (string)($cStmt->fetchColumn() ?: 'General');

        // Upsert topic_mastery
        $upsert = $this->pdo->prepare("
            INSERT INTO topic_mastery (
                student_id, course_id, subject, topic_name, mastery_score,
                confidence_score, evidence_count, source, last_assessed_at
            ) VALUES (
                :sid, :cid, :subject, :topic, :score,
                :conf_score, :ev_count, 'learning_evidence', NOW()
            )
            ON DUPLICATE KEY UPDATE
                mastery_score    = VALUES(mastery_score),
                confidence_score = VALUES(confidence_score),
                evidence_count   = VALUES(evidence_count),
                source           = 'learning_evidence',
                last_assessed_at = NOW()
        ");
        $confNumeric = match($confidence) { 'high' => 90.0, 'medium' => 70.0, default => 50.0 };
        $upsert->execute([
            ':sid'        => $studentId,
            ':cid'        => $courseId,
            ':subject'    => $subject,
            ':topic'      => $topicName,
            ':score'      => $masteryScore,
            ':conf_score' => $confNumeric,
            ':ev_count'   => $evidenceCount,
        ]);

        // Recalculate student_learning_profiles for this course
        $this->refreshStudentLearningProfile($studentId, $courseId);

        // Phase 4: every mastery refresh also reinterprets the relevant gap automatically.
        try {
            $gapService = new \NextBeyond\Adaptive\GapAnalysisService($this->pdo);
            $gapService->recalculateForStudent($studentId, $courseId, $topicName);
        } catch (Throwable $e) {
            // Phase 3 remains operational before the additive Phase 4 migration is installed.
        }

        // Phase 5: reinterpret fresh evidence, detect regression and choose the next loop step.
        try {
            $check = (new \NextBeyond\Mastery\MasteryCheckService($this->pdo))
                ->evaluate($studentId, $courseId, $topicName, '', ['check_type' => 'evidence_evaluation']);
            if (!empty($check['gap_id']) && (
                ($check['mastery_status'] ?? '') === 'regression_detected'
                || ($check['recommendation'] ?? '') === 'teacher_intervention'
            )) {
                (new \NextBeyond\Mastery\LearningDecisionService($this->pdo))->applySystemDecision($check);
            }
        } catch (Throwable $e) {
            // Phase 3 remains operational before the additive Phase 5 migration is installed.
        }

        return [
            'mastery_score'    => $masteryScore,
            'evidence_count'   => $evidenceCount,
            'confidence'       => $confidence,
            'confidence_level' => $confidence,
            'topic_name'       => $topicName,
        ];
    }

    /**
     * Refresh student_learning_profiles (strengths, needs_improvement, overall current_mastery)
     */
    public function refreshStudentLearningProfile(int $studentId, int $courseId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT topic_name, mastery_score FROM topic_mastery
            WHERE student_id = :sid AND course_id = :cid
        ");
        $stmt->execute([':sid' => $studentId, ':cid' => $courseId]);
        $topics = $stmt->fetchAll();

        if (empty($topics)) return;

        $scores = array_map(fn($t) => (float)$t['mastery_score'], $topics);
        $avgMastery = round(array_sum($scores) / count($scores), 2);

        $strengths = [];
        $needsImprovement = [];

        foreach ($topics as $t) {
            $s = (float)$t['mastery_score'];
            if ($s >= 75.0) {
                $strengths[] = $t['topic_name'];
            } elseif ($s < 65.0) {
                $needsImprovement[] = $t['topic_name'];
            }
        }

        $updProfile = $this->pdo->prepare("
            INSERT INTO student_learning_profiles (
                student_id, course_id, current_mastery, strengths_json, needs_improvement_json, updated_at
            ) VALUES (
                :sid, :cid, :mastery, :strengths, :needs, NOW()
            )
            ON DUPLICATE KEY UPDATE
                current_mastery        = VALUES(current_mastery),
                strengths_json         = VALUES(strengths_json),
                needs_improvement_json = VALUES(needs_improvement_json),
                updated_at             = NOW()
        ");
        $updProfile->execute([
            ':sid'       => $studentId,
            ':cid'       => $courseId,
            ':mastery'   => $avgMastery,
            ':strengths' => json_encode($strengths, JSON_UNESCAPED_UNICODE),
            ':needs'     => json_encode($needsImprovement, JSON_UNESCAPED_UNICODE),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 5. SKILL MAP & PRE / POST COMPARISON
    // ──────────────────────────────────────────────────────────────────────

    public function getStudentSkillMap(int $studentId, ?int $courseId = null): array
    {
        $sql = "
            SELECT tm.*, c.title AS course_title, c.subject AS course_subject,
                   (SELECT COUNT(*) FROM learning_evidence WHERE student_id = tm.student_id AND course_id = tm.course_id AND topic_name = tm.topic_name) AS actual_evidence_count,
                   (SELECT source_type FROM learning_evidence WHERE student_id = tm.student_id AND course_id = tm.course_id AND topic_name = tm.topic_name ORDER BY occurred_at DESC LIMIT 1) AS last_source,
                   (SELECT occurred_at FROM learning_evidence WHERE student_id = tm.student_id AND course_id = tm.course_id AND topic_name = tm.topic_name ORDER BY occurred_at DESC LIMIT 1) AS last_activity_at
            FROM topic_mastery tm
            LEFT JOIN courses c ON c.id = tm.course_id
            WHERE tm.student_id = :sid
        ";
        $params = [':sid' => $studentId];
        if ($courseId !== null && $courseId > 0) {
            $sql .= " AND tm.course_id = :cid";
            $params[':cid'] = $courseId;
        }
        $sql .= " ORDER BY tm.mastery_score DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $strong = [];
        $developing = [];
        $needsReview = [];

        foreach ($rows as $r) {
            $score = (float)$r['mastery_score'];
            $evCount = (int)$r['actual_evidence_count'];
            $confidence = match (true) {
                $evCount >= 6 => 'high',
                $evCount >= 3 => 'medium',
                default       => 'low',
            };
            $r['confidence'] = $confidence;

            if ($score >= 80.0) {
                $strong[] = $r;
            } elseif ($score >= 65.0) {
                $developing[] = $r;
            } else {
                $needsReview[] = $r;
            }
        }

        return [
            'all'          => $rows,
            'strong'       => $strong,        // จุดที่ทำได้ดี (>= 80%)
            'developing'   => $developing,    // กำลังพัฒนา (65 - 79%)
            'needs_review' => $needsReview,   // ควรทบทวน (< 65%)
        ];
    }

    /**
     * Compare Pre-Test vs Post-Test for student in course/topic (Section 22)
     */
    public function getPrePostComparison(int $studentId, int $courseId, string $topicName): ?array
    {
        // Fetch pretest evidence
        $preStmt = $this->pdo->prepare("
            SELECT score, normalized_score, occurred_at FROM learning_evidence
            WHERE student_id = :sid AND course_id = :cid AND topic_name = :topic
              AND source_type IN ('diagnostic', 'mock_exam')
            ORDER BY occurred_at ASC LIMIT 1
        ");
        $preStmt->execute([':sid' => $studentId, ':cid' => $courseId, ':topic' => $topicName]);
        $pre = $preStmt->fetch();

        // Fetch posttest evidence
        $postStmt = $this->pdo->prepare("
            SELECT score, normalized_score, occurred_at FROM learning_evidence
            WHERE student_id = :sid AND course_id = :cid AND topic_name = :topic
              AND source_type = 'posttest'
            ORDER BY occurred_at DESC LIMIT 1
        ");
        $postStmt->execute([':sid' => $studentId, ':cid' => $courseId, ':topic' => $topicName]);
        $post = $postStmt->fetch();

        if (!$pre || !$post) {
            return null;
        }

        $preScore  = (float)$pre['normalized_score'];
        $postScore = (float)$post['normalized_score'];
        $delta     = round($postScore - $preScore, 1);

        return [
            'topic'              => $topicName,
            'topic_name'         => $topicName,
            'pre_score'          => $preScore,
            'post_score'         => $postScore,
            'delta'              => $delta,
            'improvement_points' => $delta,
            'improvement_pct'    => ($delta >= 0 ? '+' : '') . $delta . '%',
            'improved'           => $delta > 0,
            'pre_date'           => $pre['occurred_at'],
            'post_date'          => $post['occurred_at'],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // 6. ROADMAP & SESSION INTEGRATION
    // ──────────────────────────────────────────────────────────────────────

    private function evaluateRoadmapAfterActivity(int $studentId, int $courseId, array $submission): void
    {
        try {
            require_once __DIR__ . '/roadmap-service.php';
            // Find active roadmap task for this course and activity
            $tStmt = $this->pdo->prepare("
                SELECT t.id, t.roadmap_id
                FROM roadmap_tasks t
                JOIN roadmaps r ON r.id = t.roadmap_id
                WHERE (t.ref_course_id = :cid OR t.ref_exam_id = :eid)
                  AND t.is_active = 1 AND r.status = 'published'
                LIMIT 1
            ");
            $tStmt->execute([
                ':cid' => $courseId,
                ':eid' => $submission['exam_id'] ?? 0
            ]);
            $task = $tStmt->fetch();
            if ($task) {
                markTaskCompleted(
                    $this->pdo,
                    $studentId,
                    (int)$task['id'],
                    (int)$task['roadmap_id'],
                    'system',
                    null,
                    null,
                    10
                );
            }
        } catch (Throwable $e) {
            // Non-fatal
        }
    }

    /**
     * Quick assign from a completed live session (Section 42-43)
     */
    public function quickAssignFromSession(
        string $sessionId, int $worksheetId, string $dueDate,
        string $activityType = 'homework', ?int $teacherId = null
    ): int {
        // Fetch session info
        $st = $this->pdo->prepare("
            SELECT cs.*, ce.course_id, ce.title AS event_title
            FROM classroom_sessions cs
            LEFT JOIN calendar_events ce ON ce.id = cs.calendar_event_id
            WHERE cs.id = ?
        ");
        $st->execute([$sessionId]);
        $session = $st->fetch();
        if (!$session) {
            throw new InvalidArgumentException("Session not found");
        }

        $courseId = (int)($session['course_id'] ?? 0);
        $topics = (new Phase2SessionService($this->pdo))->getSessionTopics($sessionId);
        $topicName = !empty($topics) ? $topics[0]['topic_name'] : $session['title'];

        return $this->createAssignment([
            'worksheet_id'   => $worksheetId,
            'course_id'      => $courseId ?: null,
            'session_id'     => $sessionId,
            'title'          => $session['title'] . ' - แบบฝึกหัดหลังเรียน',
            'topic_name'     => $topicName,
            'due_date'       => $dueDate,
            'activity_type'  => $activityType,
            'target_type'    => 'all',
            'assigned_by'    => $teacherId ?: (int)$session['teacher_id'],
            'assigned_by_name'=> 'ครูผู้สอน',
        ]);
    }
}
