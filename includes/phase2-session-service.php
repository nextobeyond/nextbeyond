<?php
declare(strict_types=1);

/**
 * includes/phase2-session-service.php
 * NEXTBEYOND V2 — Phase 2: PREPARE & LEARN Integration Service
 *
 * Connects: Calendar Event → Get Ready → Live Session → Understanding Check → Mastery
 */

class Phase2SessionService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensurePhase2Schema();
    }

    // ──────────────────────────────────────────────────────────────────────
    // 1. SCHEMA MANAGEMENT
    // ──────────────────────────────────────────────────────────────────────

    public function ensurePhase2Schema(): void
    {
        static $done = false;
        if ($done) return;

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `session_topics` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `session_id` VARCHAR(64) NULL,
                `calendar_event_id` INT NULL,
                `topic_name` VARCHAR(255) NOT NULL,
                `lesson_id` INT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_st_session` (`session_id`),
                INDEX `idx_st_event` (`calendar_event_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `session_readiness` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `student_id` INT NOT NULL,
                `calendar_event_id` INT NOT NULL,
                `topic_name` VARCHAR(255) NOT NULL DEFAULT '_general',
                `status` ENUM('not_started','reviewed') NOT NULL DEFAULT 'not_started',
                `checked_at` DATETIME NULL,
                UNIQUE KEY `uq_readiness` (`student_id`, `calendar_event_id`, `topic_name`),
                INDEX `idx_sr_event` (`calendar_event_id`),
                INDEX `idx_sr_student` (`student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `session_understanding_checks` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `session_id` VARCHAR(64) NOT NULL,
                `student_id` INT NOT NULL,
                `topic_name` VARCHAR(255) NULL,
                `question_index` INT NULL,
                `understanding` ENUM('got_it','confused','somewhat') NOT NULL,
                `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_suc_session` (`session_id`),
                INDEX `idx_suc_student` (`student_id`, `session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        try {
            $cols = [];
            foreach ($this->pdo->query("SHOW COLUMNS FROM classroom_sessions")->fetchAll() as $c) {
                $cols[$c['Field']] = true;
            }
            if (!isset($cols['calendar_event_id'])) {
                $this->pdo->exec("ALTER TABLE `classroom_sessions` ADD COLUMN `calendar_event_id` INT NULL AFTER `teacher_id`");
                $this->pdo->exec("ALTER TABLE `classroom_sessions` ADD INDEX `idx_cs_calendar_event` (`calendar_event_id`)");
            }
        } catch (Throwable $e) { /* silently continue */ }

        try {
            $cols = [];
            foreach ($this->pdo->query("SHOW COLUMNS FROM calendar_events")->fetchAll() as $c) {
                $cols[$c['Field']] = true;
            }
            if (!isset($cols['session_id'])) {
                $this->pdo->exec("ALTER TABLE `calendar_events` ADD COLUMN `session_id` VARCHAR(64) NULL AFTER `status`");
                $this->pdo->exec("ALTER TABLE `calendar_events` ADD INDEX `idx_ce_session` (`session_id`)");
            }
        } catch (Throwable $e) { /* silently continue */ }

        $done = true;
    }

    // ──────────────────────────────────────────────────────────────────────
    // 2. UPCOMING EVENTS FOR STUDENT
    // ──────────────────────────────────────────────────────────────────────

    public function getUpcomingEventsForStudent(int $studentId, int $hoursAhead = 72): array
    {
        $now   = new DateTimeImmutable();
        $until = $now->modify("+{$hoursAhead} hours");

        $stmt = $this->pdo->prepare("
            SELECT ce.id, ce.title, ce.event_type, ce.event_date, ce.start_time, ce.end_time,
                   ce.location, ce.notes, ce.color, ce.session_id, ce.course_id,
                   c.title AS course_title, c.subject AS course_subject,
                   CASE
                       WHEN u.nickname IS NOT NULL AND TRIM(u.nickname) <> ''
                           THEN CONCAT(u.first_name, ' ', u.last_name, ' (', u.nickname, ')')
                       ELSE CONCAT_WS(' ', u.first_name, u.last_name)
                   END AS teacher_name,
                   cs.id AS live_session_id,
                   cs.session_pin,
                   cs.status AS session_status
            FROM calendar_events ce
            INNER JOIN enrollments en ON en.course_id = ce.course_id
                AND en.user_id = :sid
                AND en.status IN ('active','trial')
                AND (en.end_date IS NULL OR en.end_date >= CURDATE())
            LEFT JOIN courses c ON c.id = ce.course_id
            LEFT JOIN users u ON u.id = ce.teacher_id
            LEFT JOIN classroom_sessions cs ON cs.id = ce.session_id
            WHERE ce.status = 'scheduled'
              AND ce.event_type IN ('lesson','exam')
              AND CONCAT(ce.event_date,' ',ce.start_time) >= :from_dt
              AND CONCAT(ce.event_date,' ',ce.start_time) <= :until_dt
            ORDER BY ce.event_date ASC, ce.start_time ASC
            LIMIT 5
        ");
        $stmt->execute([
            ':sid'      => $studentId,
            ':from_dt'  => $now->format('Y-m-d H:i:s'),
            ':until_dt' => $until->format('Y-m-d H:i:s'),
        ]);
        $events = $stmt->fetchAll();

        $result = [];
        foreach ($events as $ev) {
            $eventId   = (int) $ev['id'];
            $topics    = $this->getEventTopics($eventId);
            $readiness = $this->getReadinessSummaryInternal($studentId, $eventId, $topics);
            $result[]  = [
                'id'              => $eventId,
                'title'           => (string) $ev['title'],
                'event_type'      => (string) $ev['event_type'],
                'event_date'      => (string) $ev['event_date'],
                'start_time'      => substr((string) $ev['start_time'], 0, 5),
                'end_time'        => substr((string) $ev['end_time'], 0, 5),
                'location'        => $ev['location'] ?? null,
                'color'           => (string) ($ev['color'] ?? '#6366f1'),
                'course_id'       => $ev['course_id'] ? (int) $ev['course_id'] : null,
                'course_title'    => (string) ($ev['course_title'] ?? ''),
                'course_subject'  => (string) ($ev['course_subject'] ?? ''),
                'teacher_name'    => (string) ($ev['teacher_name'] ?? 'ครูผู้สอน'),
                'topics'          => $topics,
                'readiness_pct'   => $readiness['pct'],
                'readiness_done'  => $readiness['done'],
                'readiness_total' => $readiness['total'],
                'session_id'      => $ev['live_session_id'] ?? null,
                'session_pin'     => $ev['session_pin'] ?? null,
                'session_status'  => $ev['session_status'] ?? null,
            ];
        }
        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────
    // 3. TOPIC MANAGEMENT
    // ──────────────────────────────────────────────────────────────────────

    public function getEventTopics(int $eventId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT MIN(id) AS id, topic_name, MAX(lesson_id) AS lesson_id, MIN(sort_order) AS sort_order
            FROM session_topics
            WHERE calendar_event_id = :eid
            GROUP BY topic_name
            ORDER BY MIN(sort_order), MIN(id)
        ");
        $stmt->execute([':eid' => $eventId]);
        return $stmt->fetchAll();
    }

    public function getSessionTopics(string $sessionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT MIN(id) AS id, topic_name, MAX(lesson_id) AS lesson_id, MIN(sort_order) AS sort_order
            FROM session_topics
            WHERE session_id = :sid
            GROUP BY topic_name
            ORDER BY MIN(sort_order), MIN(id)
        ");
        $stmt->execute([':sid' => $sessionId]);
        $rows = $stmt->fetchAll();
        if (!empty($rows)) {
            return $rows;
        }

        // If no direct topics, check linked calendar event
        $stmt2 = $this->pdo->prepare("
            SELECT calendar_event_id FROM classroom_sessions WHERE id = :sid LIMIT 1
        ");
        $stmt2->execute([':sid' => $sessionId]);
        $ceid = (int)($stmt2->fetchColumn() ?: 0);
        if ($ceid > 0) {
            return $this->getEventTopics($ceid);
        }
        return [];
    }

    public function saveTopicsForEvent(int $eventId, array $topics): void
    {
        $this->pdo->prepare("DELETE FROM session_topics WHERE calendar_event_id = :eid")
                  ->execute([':eid' => $eventId]);

        $stmt = $this->pdo->prepare(
            "INSERT INTO session_topics (calendar_event_id, topic_name, lesson_id, sort_order)
             VALUES (:eid, :topic, :lid, :sort)"
        );
        foreach ($topics as $i => $t) {
            $name = is_array($t) ? trim((string)($t['topic_name'] ?? '')) : trim((string)$t);
            if ($name === '') continue;
            $stmt->execute([
                ':eid'   => $eventId,
                ':topic' => $name,
                ':lid'   => is_array($t) && !empty($t['lesson_id']) ? (int)$t['lesson_id'] : null,
                ':sort'  => $i,
            ]);
        }
    }

    public function linkSessionToEvent(string $sessionId, int $eventId): void
    {
        $this->pdo->prepare("UPDATE classroom_sessions SET calendar_event_id = :eid WHERE id = :sid")
                  ->execute([':eid' => $eventId, ':sid' => $sessionId]);
        $this->pdo->prepare("UPDATE calendar_events SET session_id = :sid WHERE id = :eid")
                  ->execute([':sid' => $sessionId, ':eid' => $eventId]);

        // Attach session_id to existing event topics so they are shared
        $this->pdo->prepare("UPDATE session_topics SET session_id = :sid WHERE calendar_event_id = :eid AND (session_id IS NULL OR session_id = '')")
                  ->execute([':sid' => $sessionId, ':eid' => $eventId]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 4. STUDENT READINESS
    // ──────────────────────────────────────────────────────────────────────

    public function getStudentReadiness(int $studentId, int $eventId): array
    {
        $topics = $this->getEventTopics($eventId);
        $stmt   = $this->pdo->prepare(
            "SELECT topic_name, status FROM session_readiness
             WHERE student_id = :sid AND calendar_event_id = :eid"
        );
        $stmt->execute([':sid' => $studentId, ':eid' => $eventId]);
        $saved = [];
        foreach ($stmt->fetchAll() as $row) {
            $saved[$row['topic_name']] = $row['status'];
        }

        if (empty($topics)) {
            return [['topic_name' => '_general', 'status' => $saved['_general'] ?? 'not_started', 'lesson_id' => null]];
        }
        return array_map(fn($t) => [
            'topic_name' => $t['topic_name'],
            'status'     => $saved[$t['topic_name']] ?? 'not_started',
            'lesson_id'  => $t['lesson_id'],
        ], $topics);
    }

    public function saveReadiness(int $studentId, int $eventId, string $topicName, string $status): bool
    {
        if (!in_array($status, ['not_started','reviewed'], true)) return false;
        $this->pdo->prepare("
            INSERT INTO session_readiness (student_id, calendar_event_id, topic_name, status, checked_at)
            VALUES (:sid, :eid, :topic, :status, NOW())
            ON DUPLICATE KEY UPDATE status = VALUES(status), checked_at = NOW()
        ")->execute([':sid' => $studentId, ':eid' => $eventId, ':topic' => $topicName, ':status' => $status]);
        return true;
    }

    public function getEventReadinessSummaryForTeacher(int $eventId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT en.user_id) AS enrolled_count
            FROM calendar_events ce
            JOIN enrollments en ON en.course_id = ce.course_id
                AND en.status IN ('active','trial')
                AND (en.end_date IS NULL OR en.end_date >= CURDATE())
            WHERE ce.id = :eid
        ");
        $stmt->execute([':eid' => $eventId]);
        $enrolled = (int)($stmt->fetchColumn() ?: 0);

        $stmt2 = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT student_id) FROM session_readiness
             WHERE calendar_event_id = :eid AND status = 'reviewed'"
        );
        $stmt2->execute([':eid' => $eventId]);
        $ready = (int)$stmt2->fetchColumn();

        $topics = $this->getEventTopics($eventId);
        $topicStats = [];
        foreach ($topics as $t) {
            $tName = $t['topic_name'];
            $stCheck = $this->pdo->prepare("
                SELECT COUNT(*) FROM session_readiness
                WHERE calendar_event_id = :eid AND topic_name = :topic AND status = 'reviewed'
            ");
            $stCheck->execute([':eid' => $eventId, ':topic' => $tName]);
            $reviewedStudents = (int)$stCheck->fetchColumn();
            $topicStats[] = [
                'topic_name'        => $tName,
                'reviewed_students' => $reviewedStudents,
                'reviewed_pct'      => $enrolled > 0 ? (int)round($reviewedStudents * 100 / $enrolled) : 0,
            ];
        }

        return [
            'enrolled'       => $enrolled,
            'total_students' => $enrolled,
            'ready'          => $ready,
            'ready_students' => $ready,
            'ready_pct'      => $enrolled > 0 ? (int)round($ready * 100 / $enrolled) : 0,
            'topics'         => $topicStats,
        ];
    }

    private function getReadinessSummaryInternal(int $studentId, int $eventId, array $topics): array
    {
        if (empty($topics)) return ['pct' => 0, 'done' => 0, 'total' => 0];
        $total = count($topics);
        $stmt  = $this->pdo->prepare(
            "SELECT COUNT(*) FROM session_readiness
             WHERE student_id = :sid AND calendar_event_id = :eid AND status = 'reviewed'"
        );
        $stmt->execute([':sid' => $studentId, ':eid' => $eventId]);
        $done = (int)$stmt->fetchColumn();
        return ['pct' => (int)round($done * 100 / $total), 'done' => $done, 'total' => $total];
    }

    // ──────────────────────────────────────────────────────────────────────
    // 5. IN-CLASS UNDERSTANDING CHECKS
    // ──────────────────────────────────────────────────────────────────────

    public function saveUnderstandingCheck(
        string $sessionId, int $studentId, ?int $questionIndex,
        string $topicName, string $understanding
    ): bool {
        if (!in_array($understanding, ['got_it','confused','somewhat'], true)) return false;
        $this->pdo->prepare("
            INSERT INTO session_understanding_checks
                (session_id, student_id, topic_name, question_index, understanding, recorded_at)
            VALUES (:sid, :uid, :topic, :qidx, :understanding, NOW())
        ")->execute([
            ':sid'           => $sessionId,
            ':uid'           => $studentId,
            ':topic'         => $topicName ?: null,
            ':qidx'          => $questionIndex,
            ':understanding' => $understanding,
        ]);
        return true;
    }

    public function getUnderstandingStats(string $sessionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(topic_name,'_general') AS topic, understanding, COUNT(*) AS cnt
            FROM session_understanding_checks
            WHERE session_id = :sid
            GROUP BY topic, understanding
        ");
        $stmt->execute([':sid' => $sessionId]);

        $byTopic = [];
        $totals  = ['got_it' => 0, 'confused' => 0, 'somewhat' => 0, 'total' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $t = $row['topic']; $u = $row['understanding']; $n = (int)$row['cnt'];
            if (!isset($byTopic[$t])) $byTopic[$t] = ['topic' => $t, 'got_it' => 0, 'confused' => 0, 'somewhat' => 0, 'total' => 0];
            $byTopic[$t][$u] += $n; $byTopic[$t]['total'] += $n;
            $totals[$u] += $n; $totals['total'] += $n;
        }
        foreach ($byTopic as &$t) {
            $t['confused_pct'] = $t['total'] > 0 ? (int)round($t['confused'] * 100 / $t['total']) : 0;
            $t['got_it_pct']   = $t['total'] > 0 ? (int)round($t['got_it'] * 100 / $t['total']) : 0;
        }
        return [
            'topics'       => array_values($byTopic),
            'totals'       => $totals,
            'confused_pct' => $totals['total'] > 0 ? (int)round($totals['confused'] * 100 / $totals['total']) : 0,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // 6. POST-CLASS: SYNC TO TOPIC MASTERY (Phase 1 Integration)
    // ──────────────────────────────────────────────────────────────────────

    public function syncUnderstandingToMastery(string $sessionId): void
    {
        try { $this->pdo->query("SELECT 1 FROM topic_mastery LIMIT 1"); }
        catch (Throwable $e) { return; }

        $stmt = $this->pdo->prepare("
            SELECT cs.calendar_event_id, ce.course_id
            FROM classroom_sessions cs
            LEFT JOIN calendar_events ce ON ce.id = cs.calendar_event_id
            WHERE cs.id = :sid LIMIT 1
        ");
        $stmt->execute([':sid' => $sessionId]);
        $row      = $stmt->fetch();
        $courseId = $row ? (int)($row['course_id'] ?? 0) : 0;
        if ($courseId < 1) return;

        $subjectRow = $this->pdo->prepare("SELECT subject FROM courses WHERE id = :cid LIMIT 1");
        $subjectRow->execute([':cid' => $courseId]);
        $subject = (string)($subjectRow->fetchColumn() ?: 'General');

        $stmt2 = $this->pdo->prepare("
            SELECT student_id, topic_name, understanding
            FROM session_understanding_checks WHERE session_id = :sid
        ");
        $stmt2->execute([':sid' => $sessionId]);

        $updates = [];
        foreach ($stmt2->fetchAll() as $check) {
            $uid   = (int)$check['student_id'];
            $topic = $check['topic_name'] ?: '_general';
            $score = match($check['understanding']) {
                'got_it'   => 75.0,
                'somewhat' => 50.0,
                'confused' => 20.0,
                default    => 50.0,
            };
            $updates[$uid][$topic][] = $score;
        }

        $upsert = $this->pdo->prepare("
            INSERT INTO topic_mastery
                (student_id, course_id, subject, topic_name, mastery_score, evidence_count, source, last_assessed_at)
            VALUES (:sid, :cid, :subject, :topic, :score, 1, 'live_class', NOW())
            ON DUPLICATE KEY UPDATE
                mastery_score    = ROUND((mastery_score * evidence_count + :score2) / (evidence_count + 1), 2),
                evidence_count   = evidence_count + 1,
                source           = 'live_class',
                last_assessed_at = NOW()
        ");
        foreach ($updates as $sid => $topics) {
            foreach ($topics as $topicName => $scores) {
                $avg = array_sum($scores) / count($scores);
                $upsert->execute([
                    ':sid' => $sid, ':cid' => $courseId, ':subject' => $subject,
                    ':topic' => $topicName, ':score' => round($avg,2), ':score2' => round($avg,2),
                ]);
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // 7. UTILITY
    // ──────────────────────────────────────────────────────────────────────

    public function getEventForStudent(int $eventId, int $studentId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT ce.*, c.title AS course_title, c.subject AS course_subject,
                   CASE
                       WHEN u.nickname IS NOT NULL AND TRIM(u.nickname) <> ''
                           THEN CONCAT(u.first_name,' ',u.last_name,' (',u.nickname,')')
                       ELSE CONCAT_WS(' ', u.first_name, u.last_name)
                   END AS teacher_name,
                   cs.session_pin, cs.status AS session_status, cs.id AS live_session_id
            FROM calendar_events ce
            LEFT JOIN courses c ON c.id = ce.course_id
            LEFT JOIN users u ON u.id = ce.teacher_id
            LEFT JOIN classroom_sessions cs ON cs.id = ce.session_id
            WHERE ce.id = :eid LIMIT 1
        ");
        $stmt->execute([':eid' => $eventId]);
        $ev = $stmt->fetch();
        if (!$ev) return null;

        if ($ev['course_id']) {
            $check = $this->pdo->prepare("
                SELECT 1 FROM enrollments
                WHERE user_id = :uid AND course_id = :cid
                  AND status IN ('active','trial')
                  AND (end_date IS NULL OR end_date >= CURDATE()) LIMIT 1
            ");
            $check->execute([':uid' => $studentId, ':cid' => $ev['course_id']]);
            if (!$check->fetchColumn()) return null;
        }
        return $ev;
    }
}
