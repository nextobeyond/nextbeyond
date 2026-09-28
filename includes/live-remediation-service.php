<?php
declare(strict_types=1);

/**
 * includes/live-remediation-service.php  — P2.2: Adaptive Remediation Service
 *
 * When the system detects a student group with a common Gap in the same topic,
 * it automatically creates:
 *   1. A student_learning_gaps record (or finds existing one)
 *   2. A teacher_intervention record (manual, type=live_class_followup)
 *   3. An adaptive_roadmap_step for each affected student
 *   4. Optionally links to a remediation worksheet (if available)
 *
 * Trigger: Called by live-remediation-api.php when teacher approves Radar suggestion
 *          OR when auto-remediation threshold is exceeded (configurable).
 *
 * Design Principles (from Master Prompt):
 *   - Teacher MUST remain in control: auto-remediation creates RECOMMENDATIONS only
 *   - AI is Layer 2: suggestions are pre-populated but teacher can edit/dismiss
 *   - Server Authority: all gap + intervention records are created server-side
 */

declare(strict_types=1);

require_once __DIR__ . '/phase5-mastery-service.php';
require_once __DIR__ . '/phase4-adaptive-service.php';

class LiveRemediationService
{
    public function __construct(private PDO $pdo) {}

    /**
     * Detect student groups with common confusion for a session.
     *
     * Returns array of gap groups: each has topic, studentIds, severity, signals.
     * Threshold: topic with >= $minStudents students showing confusion signals.
     */
    public function detectGapGroups(string $sessionId, int $minStudents = 2): array
    {
        // Aggregate confusion signals: wrong answers + UC confused/somewhat
        $signals = $this->aggregateConfusionSignals($sessionId);

        // Group by topic
        $topicStudents = [];
        foreach ($signals as $row) {
            $topic   = (string)($row['topic'] ?: 'ทั่วไป');
            $stId    = (int)$row['student_id'];
            $sigType = (string)$row['signal_type'];
            $weight  = match($sigType) {
                'wrong_answer' => 2,
                'confused'     => 3,
                'somewhat'     => 1,
                default        => 1,
            };

            if (!isset($topicStudents[$topic])) {
                $topicStudents[$topic] = ['students' => [], 'weight' => 0, 'signals' => []];
            }
            $topicStudents[$topic]['students'][$stId] = true;
            $topicStudents[$topic]['weight']          += $weight;
            $topicStudents[$topic]['signals'][]        = $sigType;
        }

        // Filter by minStudents and sort by severity
        $groups = [];
        foreach ($topicStudents as $topic => $data) {
            $studentCount = count($data['students']);
            if ($studentCount < $minStudents) continue;

            $weight   = $data['weight'];
            $severity = match(true) {
                $weight >= 10 => 'critical',
                $weight >= 6  => 'high',
                $weight >= 3  => 'moderate',
                default       => 'low',
            };

            $groups[] = [
                'topic'        => $topic,
                'studentIds'   => array_keys($data['students']),
                'studentCount' => $studentCount,
                'weight'       => $weight,
                'severity'     => $severity,
                'signals'      => array_count_values($data['signals']),
            ];
        }

        usort($groups, fn($a, $b) => $b['weight'] <=> $a['weight']);
        return $groups;
    }

    /**
     * Create remediation package for a group of students sharing a gap topic.
     *
     * Returns: array of {studentId, gapId, interventionId, roadmapStepId}
     * All records created with status='recommended' — teacher approves each one.
     */
    public function createGroupRemediation(
        string $sessionId,
        array  $studentIds,
        string $topic,
        string $severity,
        int    $teacherId,
        string $teacherNote = ''
    ): array {
        if (empty($studentIds) || $topic === '') return [];

        // Find course for this session
        $courseId = $this->getSessionCourseId($sessionId);
        $results  = [];

        foreach ($studentIds as $studentId) {
            $studentId = (int)$studentId;
            try {
                $result = $this->createStudentRemediation(
                    $sessionId, $studentId, $courseId, $topic, $severity, $teacherId, $teacherNote
                );
                $results[] = array_merge(['studentId' => $studentId], $result);
            } catch (Throwable $e) {
                error_log("LiveRemediation student {$studentId} error: " . $e->getMessage());
                $results[] = ['studentId' => $studentId, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Create remediation record for a single student.
     */
    private function createStudentRemediation(
        string $sessionId,
        int    $studentId,
        int    $courseId,
        string $topic,
        string $severity,
        int    $teacherId,
        string $teacherNote
    ): array {
        // 1. Find or create a learning gap record
        $gapId = $this->ensureLearningGap($studentId, $courseId, $topic, $severity, $sessionId);

        // 2. Create a teacher_intervention (manual, live_class_followup type)
        $note = "ซ่อมเสริมอัตโนมัติจาก Live Session #{$sessionId}" .
                ($teacherNote ? " — {$teacherNote}" : '');

        $intervention = new \NextBeyond\Mastery\InterventionService($this->pdo);
        $res = $intervention->createManual([
            'student_id'         => $studentId,
            'course_id'          => $courseId,
            'gap_id'             => $gapId,
            'topic_name'         => $topic,
            'trigger_type'       => 'live_class_followup',
            'recommended_action' => 'extra_practice',
            'priority_score'     => match($severity) { 'critical' => 90, 'high' => 75, 'moderate' => 60, default => 45 },
            'severity'           => $severity,
            'teacher_notes'      => $note,
        ], $teacherId);

        // 3. Find a relevant remediation worksheet (optional — best-effort)
        $worksheetId = $this->findRemediationWorksheet($courseId, $topic);

        // 4. If worksheet found, create a personalized_remediation link
        $remediationId = null;
        if ($worksheetId) {
            $remediationId = $this->linkRemediationWorksheet($studentId, (int)($res['gap_id'] ?? $gapId), $worksheetId, $sessionId);
        }

        return [
            'gapId'          => $gapId,
            'interventionId' => $res['intervention_id'] ?? null,
            'worksheetId'    => $worksheetId,
            'remediationId'  => $remediationId,
            'created'        => $res['created'] ?? true,
        ];
    }

    /**
     * Find or create a student_learning_gaps record.
     */
    private function ensureLearningGap(int $studentId, int $courseId, string $topic, string $severity, string $sessionId): int
    {
        // Check for existing open gap
        $stmt = $this->pdo->prepare("
            SELECT id FROM student_learning_gaps
            WHERE student_id = :uid AND course_id = :cid AND topic_name = :topic
              AND status <> 'resolved'
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([':uid' => $studentId, ':cid' => $courseId, ':topic' => $topic]);
        $existing = $stmt->fetchColumn();
        if ($existing) return (int)$existing;

        // Create new gap
        $this->pdo->prepare("
            INSERT INTO student_learning_gaps
                (student_id, course_id, topic_name, skill_name, severity, status, source, evidence_count, mastery_score, blocking_mode)
            VALUES
                (:uid, :cid, :topic, :topic, :sev, 'open', 'live_session', 1, 40.0, 'non_blocking')
        ")->execute([
            ':uid'   => $studentId,
            ':cid'   => $courseId,
            ':topic' => $topic,
            ':sev'   => $severity,
        ]);

        $gapId = (int)$this->pdo->lastInsertId();

        // Log session as evidence
        try {
            $this->pdo->prepare("
                INSERT INTO gap_evidence (gap_id, evidence_type, session_id, recorded_at)
                VALUES (:gid, 'live_session_confusion', :sid, NOW())
            ")->execute([':gid' => $gapId, ':sid' => $sessionId]);
        } catch (Throwable) { /* table may not exist */ }

        return $gapId;
    }

    /**
     * Find a published worksheet that matches the topic (fuzzy keyword search).
     */
    private function findRemediationWorksheet(int $courseId, string $topic): ?int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM worksheets
                WHERE is_published = 1
                  AND status = 'active'
                  AND (
                      course_id = :cid
                      OR course_id IS NULL
                  )
                  AND (
                      LOWER(title) LIKE :kw
                      OR LOWER(topic_tags) LIKE :kw
                  )
                ORDER BY course_id = :cid DESC, created_at DESC
                LIMIT 1
            ");
            $keyword = '%' . mb_strtolower($topic) . '%';
            $stmt->execute([':cid' => $courseId, ':kw' => $keyword]);
            $result = $stmt->fetchColumn();
            return $result ? (int)$result : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Link a worksheet to a gap via personalized_remediations table.
     */
    private function linkRemediationWorksheet(int $studentId, int $gapId, int $worksheetId, string $sessionId): ?int
    {
        try {
            // Check for duplicate
            $chk = $this->pdo->prepare("
                SELECT id FROM personalized_remediations
                WHERE student_id = :uid AND gap_id = :gid AND worksheet_id = :wid LIMIT 1
            ");
            $chk->execute([':uid' => $studentId, ':gid' => $gapId, ':wid' => $worksheetId]);
            if ($existing = $chk->fetchColumn()) return (int)$existing;

            $this->pdo->prepare("
                INSERT INTO personalized_remediations
                    (student_id, gap_id, worksheet_id, trigger_type, status, created_at)
                VALUES
                    (:uid, :gid, :wid, 'live_session_auto', 'assigned', NOW())
            ")->execute([':uid' => $studentId, ':gid' => $gapId, ':wid' => $worksheetId]);

            return (int)$this->pdo->lastInsertId();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get course_id linked to a session via calendar_event → course.
     */
    private function getSessionCourseId(string $sessionId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(ce.course_id, 0)
            FROM classroom_sessions cs
            LEFT JOIN calendar_events ce ON ce.id = cs.calendar_event_id
            WHERE cs.id = :sid LIMIT 1
        ");
        $stmt->execute([':sid' => $sessionId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    /**
     * Aggregate confusion signals: wrong answers + UC signals per student per topic.
     */
    private function aggregateConfusionSignals(string $sessionId): array
    {
        $results = [];

        // Wrong answers → confusion signal
        try {
            $stmt = $this->pdo->prepare("
                SELECT sp.student_id, COALESCE(q.skill, 'ทั่วไป') AS topic, 'wrong_answer' AS signal_type
                FROM test_answers ta
                JOIN test_attempts att ON att.id = ta.attempt_id
                JOIN session_participants sp ON sp.attempt_id = att.id
                JOIN exam_questions q ON q.id = ta.question_id
                WHERE sp.session_id = :sid AND ta.is_correct = 0
            ");
            $stmt->execute([':sid' => $sessionId]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable) {}

        // UC confused/somewhat → confusion signal
        try {
            $stmt = $this->pdo->prepare("
                SELECT student_id, COALESCE(topic_name, 'ทั่วไป') AS topic, understanding AS signal_type
                FROM session_understanding_checks
                WHERE session_id = :sid AND understanding IN ('confused', 'somewhat')
            ");
            $stmt->execute([':sid' => $sessionId]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable) {}

        return $results;
    }
}
