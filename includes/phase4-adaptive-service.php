<?php
/**
 * NEXTBEYOND V2 — Phase 4 adaptive learning services.
 * MySQL remains the academic source of truth. Semantic retrieval is optional and
 * is never imitated when no external vector/embedding provider is configured.
 */
declare(strict_types=1);

namespace NextBeyond\Adaptive;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

interface VectorSearchProvider
{
    public function indexQuestion(array $question): bool;
    public function updateQuestion(array $question): bool;
    public function removeQuestion(int $questionId): bool;
    /** @return array<int,float> question id => semantic score */
    public function search(string $semanticText, array $filters, int $limit): array;
    public function bulkIndex(array $questions): array;
    public function healthCheck(): array;
}

final class NullVectorSearchProvider implements VectorSearchProvider
{
    public function indexQuestion(array $question): bool { return false; }
    public function updateQuestion(array $question): bool { return false; }
    public function removeQuestion(int $questionId): bool { return true; }
    public function search(string $semanticText, array $filters, int $limit): array { return []; }
    public function bulkIndex(array $questions): array
    {
        return ['indexed' => 0, 'failed' => 0, 'not_configured' => count($questions)];
    }
    public function healthCheck(): array
    {
        return ['available' => false, 'provider' => 'none', 'reason' => 'No vector provider configured'];
    }
}

final class QueryIntentParser
{
    public static function parse(string $query): array
    {
        $query = trim($query);
        $intent = [
            'raw_query' => $query, 'subject' => null, 'level' => null, 'topic' => null,
            'subtopic' => null, 'skill' => null, 'difficulty' => [], 'question_type' => null,
            'context' => [], 'count' => 10, 'keywords' => [],
        ];
        if ($query === '') return $intent;

        if (preg_match('/(\d+)\s*(?:ข้อ|questions?|items?)/ui', $query, $m)) {
            $intent['count'] = max(1, min(50, (int)$m[1]));
        }
        $levels = [
            'A-Level' => '/a[\s_-]?level/ui', 'ม.6' => '/(?:ม\.?\s*6|grade\s*12)/ui',
            'ม.5' => '/(?:ม\.?\s*5|grade\s*11)/ui', 'ม.4' => '/(?:ม\.?\s*4|grade\s*10)/ui',
            'ม.3' => '/(?:ม\.?\s*3|grade\s*9)/ui', 'ม.2' => '/(?:ม\.?\s*2|grade\s*8)/ui',
            'ม.1' => '/(?:ม\.?\s*1|grade\s*7)/ui',
        ];
        foreach ($levels as $name => $pattern) if (preg_match($pattern, $query)) { $intent['level'] = $name; break; }

        $subjects = [
            'เคมี' => '/(?:เคมี|chemistry|acid[\s-]?base|กรด[\s-]?เบส)/ui',
            'ฟิสิกส์' => '/(?:ฟิสิกส์|physics|newton|แรง)/ui',
            'ชีววิทยา' => '/(?:ชีววิทยา|ชีวะ|biology|genetics|พันธุศาสตร์)/ui',
            'คณิตศาสตร์' => '/(?:คณิตศาสตร์|คณิต|math|algebra)/ui',
            'ภาษาอังกฤษ' => '/(?:ภาษาอังกฤษ|อังกฤษ|english|grammar|reading|vocab|present\s+)/ui',
            'ภาษาไทย' => '/(?:ภาษาไทย|วรรณคดี|หลักภาษา)/ui',
        ];
        foreach ($subjects as $name => $pattern) if (preg_match($pattern, $query)) { $intent['subject'] = $name; break; }

        $topics = [
            'Present Perfect' => '/present\s+perfect/ui', 'Present Continuous' => '/present\s+continuous/ui',
            'Present Simple' => '/present\s+simple/ui', 'Acid Base' => '/(?:acid[\s-]?base|กรด[\s-]?เบส)/ui',
            "Newton's Law" => '/(?:newton(?:\'s)?\s+law|กฎของนิวตัน)/ui',
            'Genetics' => '/(?:genetics|พันธุศาสตร์)/ui', 'Reading Inference' => '/(?:reading\s+inference|การอนุมาน)/ui',
        ];
        foreach ($topics as $name => $pattern) if (preg_match($pattern, $query)) { $intent['topic'] = $name; break; }

        if (preg_match('/(?:ง่าย|easy)/ui', $query)) $intent['difficulty'][] = 'easy';
        if (preg_match('/(?:ปานกลาง|กลาง|medium|moderate)/ui', $query)) $intent['difficulty'][] = 'medium';
        if (preg_match('/(?:ยากมาก|expert)/ui', $query)) $intent['difficulty'][] = 'expert';
        elseif (preg_match('/(?:ยาก|hard)/ui', $query)) $intent['difficulty'][] = 'hard';
        if (preg_match('/(?:calculation|คำนวณ)/ui', $query)) $intent['context'][] = 'calculation';
        if (preg_match('/(?:daily\s+routine|กิจวัตร)/ui', $query)) $intent['context'][] = 'daily routine';
        if (preg_match('/present\s+perfect\s+(?:vs|versus)\s+past\s+simple/ui', $query)) {
            $intent['skill'] = 'Present Perfect vs Past Simple';
        }

        preg_match_all('/[\p{L}\p{N}\-\']{2,}/u', mb_strtolower($query, 'UTF-8'), $matches);
        $stop = ['ข้อ','เน้น','ระดับ','questions','items','easy','medium','hard'];
        $intent['keywords'] = array_values(array_slice(array_unique(array_filter(
            $matches[0] ?? [], fn(string $word): bool => !in_array($word, $stop, true)
        )), 0, 12));
        return $intent;
    }
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    try {
        $check = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        if ($check && $check->fetch()) return;
        $pdo->exec("ALTER TABLE `{$table}` ADD `{$column}` {$definition}");
    } catch (Throwable $e) {}
}

function ensurePhase4Schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) return;

    try {
        $t1 = $pdo->query("SHOW TABLES LIKE 'question_bank_items'")->fetch();
        $t2 = $pdo->query("SHOW TABLES LIKE 'student_learning_gaps'")->fetch();
        if ($t1 && $t2) {
            $ensured = true;
            return;
        }
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `question_bank_items` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `organization_id` INT NULL,
                `subject` VARCHAR(100) NULL,
                `course_id` INT NULL,
                `grade_level` VARCHAR(50) NULL,
                `topic_id` INT NULL,
                `topic_name` VARCHAR(255) NULL,
                `subtopic_id` INT NULL,
                `subtopic_name` VARCHAR(255) NULL,
                `skill_id` INT NULL,
                `skill_name` VARCHAR(150) NULL,
                `learning_objective` TEXT NULL,
                `question_type` VARCHAR(50) NOT NULL DEFAULT 'multipleChoice',
                `question_text` MEDIUMTEXT NOT NULL,
                `choices_json` MEDIUMTEXT NULL,
                `correct_answer_json` MEDIUMTEXT NULL,
                `explanation` MEDIUMTEXT NULL,
                `hint` TEXT NULL,
                `difficulty` ENUM('easy','medium','hard','expert') NOT NULL DEFAULT 'medium',
                `tags_json` TEXT NULL,
                `language` VARCHAR(12) NOT NULL DEFAULT 'th',
                `source_type` ENUM('exam','worksheet','manual','ai_generated','imported') NOT NULL DEFAULT 'manual',
                `source_record_id` BIGINT UNSIGNED NULL,
                `review_status` ENUM('draft','ai_generated','reviewed','approved','archived','rejected') NOT NULL DEFAULT 'reviewed',
                `quality_status` ENUM('unrated','good','needs_review') NOT NULL DEFAULT 'unrated',
                `quality_score` DECIMAL(5,4) NOT NULL DEFAULT 0.5000,
                `visibility` ENUM('institution','shared','private') NOT NULL DEFAULT 'institution',
                `created_by` INT NULL,
                `status` ENUM('active','archived') NOT NULL DEFAULT 'active',
                `index_status` ENUM('pending','indexed','failed','not_configured') NOT NULL DEFAULT 'pending',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_qbi_source` (`source_type`,`source_record_id`),
                KEY `idx_qbi_search_scope` (`status`,`review_status`,`visibility`,`subject`,`grade_level`),
                KEY `idx_qbi_topic_skill` (`topic_name`,`skill_name`,`difficulty`),
                KEY `idx_qbi_course` (`course_id`),
                KEY `idx_qbi_creator_visibility` (`created_by`,`visibility`),
                FULLTEXT KEY `ft_qbi_academic_text` (`question_text`,`topic_name`,`subtopic_name`,`skill_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `question_embeddings` (
                `question_id` BIGINT UNSIGNED NOT NULL,
                `embedding_provider` VARCHAR(50) NOT NULL,
                `embedding_model` VARCHAR(100) NOT NULL,
                `embedding_version` VARCHAR(50) NOT NULL,
                `content_hash` CHAR(64) NOT NULL,
                `indexed_at` DATETIME NULL,
                `last_error` VARCHAR(500) NULL,
                PRIMARY KEY (`question_id`,`embedding_provider`,`embedding_model`),
                KEY `idx_embedding_status` (`embedding_provider`,`indexed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `question_exposures` (
                `question_id` BIGINT UNSIGNED NOT NULL,
                `student_id` INT NOT NULL,
                `course_id` INT NULL,
                `exposure_count` INT UNSIGNED NOT NULL DEFAULT 1,
                `correct_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `last_seen_at` DATETIME NOT NULL,
                `last_source_type` ENUM('worksheet','exam','practice','remediation','mastery_check') NOT NULL,
                `last_source_id` VARCHAR(64) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`question_id`,`student_id`),
                KEY `idx_exposure_student` (`student_id`,`last_seen_at`),
                KEY `idx_exposure_course` (`course_id`,`student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `student_learning_gaps` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `student_id` INT NOT NULL,
                `course_id` INT NOT NULL,
                `subject` VARCHAR(100) NOT NULL,
                `topic_name` VARCHAR(255) NOT NULL,
                `subtopic_name` VARCHAR(255) NULL,
                `skill_name` VARCHAR(150) NULL,
                `gap_score` DECIMAL(5,2) NOT NULL,
                `severity` ENUM('low','moderate','high','critical') NOT NULL,
                `confidence` ENUM('low','medium','high') NOT NULL,
                `evidence_count` INT UNSIGNED NOT NULL,
                `recent_accuracy` DECIMAL(5,2) NOT NULL,
                `mastery_score` DECIMAL(5,2) NOT NULL,
                `trend` ENUM('improving','stable','declining') NOT NULL DEFAULT 'stable',
                `status` ENUM('open','remediation_assigned','in_remediation','monitoring','improving','resolved','archived') NOT NULL DEFAULT 'open',
                `first_detected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_detected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `resolved_at` DATETIME NULL,
                `resolution_notes` TEXT NULL,
                `blocking_mode` ENUM('blocking','non_blocking') NOT NULL DEFAULT 'non_blocking',
                `resolved_by` INT NULL,
                `resolution_type` VARCHAR(50) NULL,
                `final_mastery` DECIMAL(5,2) NULL,
                `resolution_evidence_count` INT UNSIGNED NULL,
                `intervention_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `cycle_number` INT UNSIGNED NOT NULL DEFAULT 1,
                `last_mastery_status` VARCHAR(50) NULL,
                `blocking_overridden_by` INT NULL,
                `blocking_override_reason` TEXT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_gap_active_topic` (`student_id`,`course_id`,`topic_name`,`status`),
                KEY `idx_gap_priority` (`course_id`,`severity`,`status`,`gap_score`),
                KEY `idx_gap_student_topic` (`student_id`,`topic_name`,`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `personalized_remediations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `gap_id` BIGINT UNSIGNED NOT NULL,
                `student_id` INT NOT NULL,
                `course_id` INT NOT NULL,
                `topic_name` VARCHAR(255) NOT NULL,
                `skill_name` VARCHAR(150) NULL,
                `step_index` INT UNSIGNED NOT NULL DEFAULT 1,
                `total_steps` INT UNSIGNED NOT NULL DEFAULT 1,
                `worksheet_id` INT NULL,
                `assignment_id` INT NULL,
                `question_count` INT UNSIGNED NOT NULL,
                `status` ENUM('draft','assigned','in_progress','completed','evaluated','cancelled') NOT NULL DEFAULT 'draft',
                `approval_mode` ENUM('teacher_manual','teacher_reviewed','auto_prescribed') NOT NULL DEFAULT 'teacher_reviewed',
                `created_by` INT NULL,
                `created_by_name` VARCHAR(150) NULL,
                `notes` TEXT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `assigned_at` DATETIME NULL,
                `completed_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `idx_remediation_gap` (`gap_id`,`status`),
                KEY `idx_remediation_student` (`student_id`,`status`),
                KEY `idx_remediation_assignment` (`assignment_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `remediation_questions` (
                `remediation_id` BIGINT UNSIGNED NOT NULL,
                `question_id` BIGINT UNSIGNED NOT NULL,
                `worksheet_question_id` INT NULL,
                `order_index` INT UNSIGNED NOT NULL,
                `selection_reason` VARCHAR(255) NULL,
                `seen_before` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`remediation_id`,`question_id`),
                KEY `idx_rq_question` (`question_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `question_search_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `actor_id` INT NULL,
                `actor_type` ENUM('teacher','admin','system','ai') NOT NULL DEFAULT 'teacher',
                `raw_query` VARCHAR(500) NULL,
                `parsed_filters_json` TEXT NULL,
                `mode` ENUM('sql','semantic','hybrid') NOT NULL DEFAULT 'sql',
                `vector_provider` VARCHAR(50) NULL,
                `results_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `execution_ms` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_qsl_actor` (`actor_id`,`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}

    ensureColumn($pdo, 'student_learning_profiles', 'prerequisite_gaps_json', 'TEXT NULL');
    ensureColumn($pdo, 'student_learning_profiles', 'open_gap_count', 'INT UNSIGNED NOT NULL DEFAULT 0');
    ensureColumn($pdo, 'student_learning_profiles', 'active_remediation_count', 'INT UNSIGNED NOT NULL DEFAULT 0');
    ensureColumn($pdo, 'student_learning_profiles', 'last_adaptive_cycle_at', 'DATETIME NULL');

    $ensured = true;
}

final class CanonicalQuestionRepository
{
    public function __construct(private PDO $pdo) { ensurePhase4Schema($this->pdo); }

    public function syncExam(int $examId): int
    {
        $rows=$this->pdo->prepare("SELECT q.*,e.subject,e.grade,e.topic,e.created_by,e.is_ai_generated,e.status exam_status
            FROM exam_questions q JOIN exams e ON e.id=q.exam_id WHERE q.exam_id=?");$rows->execute([$examId]);$count=0;
        foreach($rows->fetchAll(PDO::FETCH_ASSOC) as $q){
            $status=$q['review_status']??($q['is_ai_generated']?'ai_generated':'reviewed');
            $this->pdo->prepare("INSERT INTO question_bank_items(subject,grade_level,topic_name,subtopic_name,skill_name,learning_objective,question_type,question_text,choices_json,correct_answer_json,explanation,difficulty,tags_json,source_type,source_record_id,review_status,quality_score,visibility,created_by,status,index_status)
              VALUES(?,?,?,?,?,?,'multipleChoice',?,?,?,?,?,?, 'exam',?,?,?,?,?,?, 'pending')
              ON DUPLICATE KEY UPDATE subject=VALUES(subject),grade_level=VALUES(grade_level),topic_name=VALUES(topic_name),subtopic_name=VALUES(subtopic_name),skill_name=VALUES(skill_name),learning_objective=VALUES(learning_objective),question_text=VALUES(question_text),choices_json=VALUES(choices_json),correct_answer_json=VALUES(correct_answer_json),explanation=VALUES(explanation),difficulty=VALUES(difficulty),tags_json=VALUES(tags_json),review_status=VALUES(review_status),status=VALUES(status),index_status='pending'")
              ->execute([$q['subject'],$q['grade'],$q['topic'],$q['subtopic']??null,$q['skill']??null,$q['learning_objective']??null,$q['question_text'],$q['options'],(string)$q['correct_answer'],$q['explanation']??null,$this->difficulty($q['difficulty']??null),$q['tags']??null,(int)$q['id'],$status,$status==='approved'?0.9:0.6,'institution',$q['created_by']??null,$q['exam_status']==='archived'?'archived':'active']);
            $id=$this->pdo->prepare("SELECT id FROM question_bank_items WHERE source_type='exam' AND source_record_id=?");$id->execute([$q['id']]);
            $this->pdo->prepare('UPDATE exam_questions SET canonical_question_id=? WHERE id=?')->execute([(int)$id->fetchColumn(),$q['id']]);$count++;
        } return $count;
    }

    public function syncWorksheet(int $worksheetId): int
    {
        $rows=$this->pdo->prepare("SELECT q.*,w.subject,w.level,w.topic,w.tags,w.creator_id,w.generation_source,w.status worksheet_status
          FROM worksheet_questions q JOIN worksheets w ON w.id=q.worksheet_id WHERE q.worksheet_id=?");$rows->execute([$worksheetId]);$count=0;
        foreach($rows->fetchAll(PDO::FETCH_ASSOC) as $q){
            if(!empty($q['canonical_question_id']))continue; // local snapshot override retains its canonical source.
            $review=$q['generation_source']==='ai'?'ai_generated':'reviewed';
            $this->pdo->prepare("INSERT INTO question_bank_items(subject,grade_level,topic_name,skill_name,learning_objective,question_type,question_text,choices_json,correct_answer_json,explanation,hint,difficulty,tags_json,source_type,source_record_id,review_status,quality_score,visibility,created_by,status,index_status)
              VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,'worksheet',?,?,?,?,?,?, 'pending')
              ON DUPLICATE KEY UPDATE question_text=VALUES(question_text),choices_json=VALUES(choices_json),correct_answer_json=VALUES(correct_answer_json),explanation=VALUES(explanation),hint=VALUES(hint),skill_name=VALUES(skill_name),difficulty=VALUES(difficulty),index_status='pending'")
              ->execute([$q['subject'],$q['level'],$q['topic'],$q['skill']??null,$q['learning_objective']??null,$q['question_type'],$q['question_text'],$q['options'],json_encode((string)($q['correct_answer']??''),JSON_UNESCAPED_UNICODE),$q['explanation']??null,$q['hint']??null,$this->difficulty($q['difficulty']??null),$q['tags']??null,(int)$q['id'],$review,0.6,$q['creator_id']?'private':'institution',$q['creator_id']??null,$q['worksheet_status']==='archived'?'archived':'active']);
            $id=$this->pdo->prepare("SELECT id FROM question_bank_items WHERE source_type='worksheet' AND source_record_id=?");$id->execute([$q['id']]);
            $this->pdo->prepare('UPDATE worksheet_questions SET canonical_question_id=? WHERE id=?')->execute([(int)$id->fetchColumn(),$q['id']]);$count++;
        } return $count;
    }
    private function difficulty(?string $value): string
    { return in_array($value,['easy','medium','hard','expert'],true)?$value:'medium'; }
}

final class GapAnalysisService
{
    public function __construct(private PDO $pdo) { ensurePhase4Schema($this->pdo); }

    public function recalculateForStudent(int $studentId, ?int $courseId = null, ?string $topicName = null): array
    {
        $where = ['le.student_id = :student_id'];
        $params = [':student_id' => $studentId];
        if ($courseId) { $where[] = 'le.course_id = :course_id'; $params[':course_id'] = $courseId; }
        if ($topicName) { $where[] = 'le.topic_name = :topic_name'; $params[':topic_name'] = $topicName; }

        $sql = "SELECT le.course_id, c.subject, le.topic_name, COALESCE(le.skill,'') skill_name,
                       COUNT(*) evidence_count,
                       ROUND(AVG(le.normalized_score),2) all_accuracy,
                       MAX(tm.mastery_score) mastery_score,
                       MIN(le.occurred_at) first_evidence_at, MAX(le.occurred_at) last_evidence_at
                FROM learning_evidence le
                LEFT JOIN courses c ON c.id=le.course_id
                LEFT JOIN topic_mastery tm ON tm.student_id=le.student_id AND tm.course_id=le.course_id
                  AND tm.topic_name=le.topic_name
                WHERE " . implode(' AND ', $where) . "
                GROUP BY le.course_id,c.subject,le.topic_name,COALESCE(le.skill,'')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) $result[] = $this->evaluateAndStore($studentId, $row);
        return $result;
    }

    private function evaluateAndStore(int $studentId, array $row): array
    {
        $recent = $this->recentScores($studentId, (int)$row['course_id'], (string)$row['topic_name'], (string)$row['skill_name']);
        $count = (int)$row['evidence_count'];
        $mastery = $row['mastery_score'] !== null ? (float)$row['mastery_score'] : (float)$row['all_accuracy'];
        $recentAccuracy = count($recent) ? round(array_sum($recent) / count($recent), 2) : (float)$row['all_accuracy'];
        $trend = $this->trend($recent);
        $confidence = $count >= 3 ? 'high' : ($count >= 2 ? 'medium' : 'low');

        // Evidence count gates severity. A single wrong response can only create a low-confidence potential gap.
        if ($count >= 6 && $mastery < 30 && $recentAccuracy < 35) $severity = 'critical';
        elseif ($count >= 3 && ($mastery < 50 || $recentAccuracy < 50)) $severity = 'high';
        elseif ($count >= 2 && ($mastery < 65 || $recentAccuracy < 60)) $severity = 'moderate';
        else $severity = 'low';
        $gapScore = round(max(0, min(100, (100 - $mastery) * 0.65 + (100 - $recentAccuracy) * 0.35)), 2);
        $status = $trend === 'improving' && $mastery >= 60 ? 'improving' : 'open';

        $upsert = $this->pdo->prepare("INSERT INTO student_learning_gaps
            (student_id,course_id,subject,topic_name,skill_name,gap_score,severity,confidence,
             evidence_count,recent_accuracy,mastery_score,trend,first_detected_at,last_detected_at,status)
            VALUES (:sid,:cid,:subject,:topic,:skill,:gap,:severity,:confidence,:ec,:recent,:mastery,:trend,NOW(),NOW(),:status)
            ON DUPLICATE KEY UPDATE subject=VALUES(subject),gap_score=VALUES(gap_score),severity=VALUES(severity),
              confidence=VALUES(confidence),evidence_count=VALUES(evidence_count),recent_accuracy=VALUES(recent_accuracy),
              mastery_score=VALUES(mastery_score),trend=VALUES(trend),last_detected_at=NOW(),
              status=IF(status='resolved',status,VALUES(status))");
        $upsert->execute([
            ':sid'=>$studentId, ':cid'=>(int)$row['course_id'], ':subject'=>$row['subject'],
            ':topic'=>$row['topic_name'], ':skill'=>$row['skill_name'], ':gap'=>$gapScore,
            ':severity'=>$severity, ':confidence'=>$confidence, ':ec'=>$count, ':recent'=>$recentAccuracy,
            ':mastery'=>$mastery, ':trend'=>$trend, ':status'=>$status,
        ]);
        $idStmt = $this->pdo->prepare("SELECT id FROM student_learning_gaps WHERE student_id=? AND course_id=? AND topic_name=? AND skill_name=?");
        $idStmt->execute([$studentId,(int)$row['course_id'],$row['topic_name'],$row['skill_name']]);
        return ['id'=>(int)$idStmt->fetchColumn(),'topic_name'=>$row['topic_name'],'skill_name'=>$row['skill_name'],
            'gap_score'=>$gapScore,'severity'=>$severity,'confidence'=>$confidence,'evidence_count'=>$count,
            'recent_accuracy'=>$recentAccuracy,'mastery_score'=>$mastery,'trend'=>$trend,'status'=>$status];
    }

    private function recentScores(int $studentId, int $courseId, string $topic, string $skill): array
    {
        $sql = "SELECT normalized_score FROM learning_evidence WHERE student_id=? AND course_id=? AND topic_name=?";
        $params = [$studentId,$courseId,$topic];
        if ($skill !== '') { $sql .= " AND COALESCE(skill,'')=?"; $params[] = $skill; }
        $sql .= ' ORDER BY occurred_at DESC,id DESC LIMIT 6';
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
        return array_map('floatval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function trend(array $newestFirst): string
    {
        if (count($newestFirst) < 4) return 'stable';
        $mid = (int)ceil(count($newestFirst) / 2);
        $new = array_slice($newestFirst, 0, $mid);
        $old = array_slice($newestFirst, $mid);
        $delta = array_sum($new)/count($new) - array_sum($old)/count($old);
        return $delta >= 8 ? 'improving' : ($delta <= -8 ? 'declining' : 'stable');
    }

    public function getStudentGaps(int $studentId, ?int $courseId = null, bool $includeResolved = true): array
    {
        $sql = "SELECT g.*,c.title course_title FROM student_learning_gaps g LEFT JOIN courses c ON c.id=g.course_id WHERE g.student_id=?";
        $params = [$studentId];
        if ($courseId) { $sql .= ' AND g.course_id=?'; $params[] = $courseId; }
        if (!$includeResolved) $sql .= " AND g.status<>'resolved'";
        $sql .= " ORDER BY FIELD(g.status,'open','monitoring','improving','resolved'), FIELD(g.severity,'critical','high','moderate','low'), g.last_detected_at DESC";
        $stmt=$this->pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getGapEvidence(int $gapId, int $limit = 30): array
    {
        $gap=$this->getGap($gapId);
        $stmt=$this->pdo->prepare("SELECT le.*,wa.title activity_title FROM learning_evidence le
            LEFT JOIN activity_submissions s ON CAST(s.id AS CHAR)=le.source_id
            LEFT JOIN worksheet_assignments wa ON wa.id=s.assignment_id
            WHERE le.student_id=? AND le.course_id=? AND le.topic_name=?
              AND (?='' OR COALESCE(le.skill,'')=?) ORDER BY le.occurred_at DESC LIMIT " . max(1,min(100,$limit)));
        $stmt->execute([$gap['student_id'],$gap['course_id'],$gap['topic_name'],$gap['skill_name'],$gap['skill_name']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getGap(int $gapId): array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM student_learning_gaps WHERE id=?'); $stmt->execute([$gapId]);
        $gap=$stmt->fetch(PDO::FETCH_ASSOC); if (!$gap) throw new RuntimeException('Learning gap not found'); return $gap;
    }

    public function getClassGapSummary(?int $courseId = null): array
    {
        $sql="SELECT g.course_id,c.title course_title,g.topic_name,g.skill_name,g.severity,
                    COUNT(DISTINCT g.student_id) affected_students,
                    COUNT(DISTINCT e.user_id) enrolled_students,
                    ROUND(COUNT(DISTINCT g.student_id)*100/NULLIF(COUNT(DISTINCT e.user_id),0),1) affected_percent
              FROM student_learning_gaps g JOIN courses c ON c.id=g.course_id
              LEFT JOIN enrollments e ON e.course_id=g.course_id AND e.status IN ('active','trial')
              WHERE g.status IN ('open','monitoring','improving')";
        $params=[]; if ($courseId) { $sql.=' AND g.course_id=?'; $params[]=$courseId; }
        $sql.=' GROUP BY g.course_id,c.title,g.topic_name,g.skill_name,g.severity ORDER BY affected_students DESC';
        $stmt=$this->pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

final class QuestionSearchService
{
    private array $weights = ['semantic'=>0.35,'metadata'=>0.30,'keyword'=>0.20,'quality'=>0.15];
    public function __construct(private PDO $pdo, private ?VectorSearchProvider $vectorProvider = null)
    {
        ensurePhase4Schema($this->pdo);
        $this->vectorProvider ??= new NullVectorSearchProvider();
    }

    public function searchQuestions(array $request): array
    {
        $parsed=QueryIntentParser::parse((string)($request['query'] ?? ''));
        $subject=trim((string)($request['subject'] ?? $request['subject_id'] ?? $parsed['subject'] ?? ''));
        $grade=trim((string)($request['grade'] ?? $request['level'] ?? $parsed['level'] ?? ''));
        $topic=trim((string)($request['topic'] ?? $parsed['topic'] ?? ''));
        $skill=trim((string)($request['skill'] ?? $parsed['skill'] ?? ''));
        $count=max(1,min(50,(int)($request['count'] ?? $parsed['count'] ?? 10)));
        $candidateLimit=max(50,min(500,$count*20));
        $studentId=(int)($request['student_id'] ?? 0);
        $actorId=(int)($request['actor_id'] ?? 0);
        $policy=strtolower((string)($request['seen_policy'] ?? (!empty($request['exclude_seen'])?'unseen_only':'prefer_unseen')));
        if (!in_array($policy,['unseen_only','prefer_unseen','allow_repeat','retry_incorrect'],true)) $policy='prefer_unseen';
        $difficulties=$request['difficulty'] ?? $parsed['difficulty'];
        if (!is_array($difficulties)) $difficulties=array_filter([trim((string)$difficulties)]);
        $statuses=$request['review_status'] ?? ['approved','reviewed'];
        if (!is_array($statuses)) $statuses=[$statuses];
        $exclude=array_values(array_unique(array_map('intval',(array)($request['exclude_ids'] ?? []))));

        $where=["q.status='active'"]; $params=[];
        $this->inCondition($where,$params,'q.review_status',$statuses,'review');
        if ($subject!=='') { $where[]='q.subject LIKE :subject'; $params[':subject']="%{$subject}%"; }
        if ($grade!=='') { $where[]='q.grade_level=:grade'; $params[':grade']=$grade; }
        if ($topic!=='') {
            $topicPattern='%' . preg_replace('/[\s_-]+/u','%',trim($topic)) . '%';
            $where[]='(q.topic_name LIKE :topic OR q.subtopic_name LIKE :topic2 OR q.tags_json LIKE :topic3)';
            $params[':topic']=$topicPattern; $params[':topic2']=$topicPattern; $params[':topic3']=$topicPattern;
        }
        if ($skill!=='') { $where[]='(q.skill_name LIKE :skill OR q.learning_objective LIKE :skill2)'; $params[':skill']="%{$skill}%"; $params[':skill2']="%{$skill}%"; }
        if ($difficulties) $this->inCondition($where,$params,'q.difficulty',$difficulties,'difficulty');
        if (!empty($request['question_type'])) { $where[]='q.question_type=:question_type'; $params[':question_type']=$request['question_type']; }
        if (!empty($request['course_id'])) { $where[]='(q.course_id IS NULL OR q.course_id=:course_id)'; $params[':course_id']=(int)$request['course_id']; }
        if ($exclude) $this->inCondition($where,$params,'q.id',$exclude,'exclude',true);
        // Institutional/shared assets plus the teacher's private assets only.
        if ($actorId>0) { $where[]="(q.visibility IN ('institution','shared') OR (q.visibility='private' AND q.created_by=:actor))"; $params[':actor']=$actorId; }
        else $where[]="q.visibility IN ('institution','shared')";

        $sql='SELECT q.*,e.attempt_count,e.correct_count,e.incorrect_count,e.last_result,e.last_seen_at
              FROM question_bank_items q LEFT JOIN question_exposures e ON e.question_id=q.id AND e.student_id=:exposure_student
              WHERE '.implode(' AND ',$where).' ORDER BY q.quality_score DESC,q.updated_at DESC LIMIT '.$candidateLimit;
        $params[':exposure_student']=$studentId ?: -1;
        $stmt=$this->pdo->prepare($sql); $stmt->execute($params); $candidates=$stmt->fetchAll(PDO::FETCH_ASSOC);

        $health=$this->vectorProvider->healthCheck();
        $semanticScores=[];
        $semanticText=$this->semanticQueryText($request,$parsed);
        if (!empty($health['available'])) {
            try { $semanticScores=$this->vectorProvider->search($semanticText,['subject'=>$subject,'grade'=>$grade,'topic'=>$topic,'skill'=>$skill],$candidateLimit); }
            catch (Throwable $e) { $health=['available'=>false,'provider'=>$health['provider']??'unknown','reason'=>'Provider request failed']; $this->logProviderFailure($request,$e); }
        }

        $tokens=$this->tokens($semanticText); $scored=[];
        foreach ($candidates as $q) {
            $seen=(int)($q['attempt_count'] ?? 0)>0;
            if ($policy==='unseen_only' && $seen) continue;
            if ($policy==='retry_incorrect' && (!$seen || ($q['last_result']??'')!=='incorrect')) continue;
            $metadata=0.0;
            if ($subject!=='' && $this->contains((string)$q['subject'],$subject)) $metadata+=0.25;
            if ($grade!=='' && strcasecmp((string)$q['grade_level'],$grade)===0) $metadata+=0.20;
            if ($topic!=='' && ($this->contains((string)$q['topic_name'],$topic)||$this->contains((string)$q['subtopic_name'],$topic))) $metadata+=0.30;
            if ($skill!=='' && $this->contains((string)$q['skill_name'],$skill)) $metadata+=0.25;
            $haystack=implode(' ',array_filter([$q['subject'],$q['grade_level'],$q['topic_name'],$q['subtopic_name'],$q['skill_name'],$q['learning_objective'],$q['question_text'],$q['tags_json']]));
            $keyword=$this->keywordScore($tokens,$haystack);
            $semantic=(float)($semanticScores[(int)$q['id']] ?? 0.0);
            $quality=(float)$q['quality_score'];
            $score=$this->weights['metadata']*$metadata+$this->weights['keyword']*$keyword+$this->weights['quality']*$quality;
            if (!empty($health['available'])) $score+=$this->weights['semantic']*$semantic;
            if ($policy==='prefer_unseen' && $seen) $score-=0.22;
            if ($policy==='retry_incorrect' && ($q['last_result']??'')==='incorrect') $score+=0.20;
            $q['previously_seen']=$seen; $q['semantic_score']=!empty($health['available'])?round($semantic,4):null;
            $q['ranking_score']=round(max(0,$score),4); $q['match_reasons']=$this->reasons($q,$topic,$skill,$grade,$seen,$policy);
            $scored[]=$q;
        }
        usort($scored,fn($a,$b)=>$b['ranking_score']<=>$a['ranking_score']);
        $deduped=$this->deduplicate($scored,(float)($request['duplicate_threshold']??0.90));
        $selected=$this->diversify($deduped,$count);
        $this->logSearch($request,array_column($selected,'id'));
        return [
            'results'=>$selected,'recommended'=>$selected,'candidates'=>$deduped,'candidate_count'=>count($deduped),
            'requested_count'=>$count,'shortfall'=>max(0,$count-count($selected)),'parsed_intent'=>$parsed,
            'applied_filters'=>['subject'=>$subject,'grade'=>$grade,'topic'=>$topic,'skill'=>$skill,'difficulty'=>$difficulties,
                'review_status'=>$statuses,'seen_policy'=>$policy],
            'search_capabilities'=>['metadata'=>true,'keyword'=>true,'semantic'=>(bool)($health['available']??false),
                'vector_provider'=>$health['provider']??'none','vector_status'=>$health['reason']??'available','exposure_filter'=>$studentId>0,
                'deduplication'=>true,'diversity_ranking'=>true],
        ];
    }

    public function getRecommendedQuestionsForGap(int $studentId,int $courseId,int $gapId,int $count,array $options=[]): array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM student_learning_gaps WHERE id=? AND student_id=? AND course_id=?');
        $stmt->execute([$gapId,$studentId,$courseId]); $gap=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$gap) throw new RuntimeException('Learning gap not found for this student/course');
        $mastery=(float)$gap['mastery_score'];
        $difficulty=$mastery<45?['easy','medium']:($mastery<75?['medium']:['medium','hard']);
        return $this->searchQuestions(array_merge($options,[
            'student_id'=>$studentId,'course_id'=>$courseId,'gap_id'=>$gapId,'topic'=>$gap['topic_name'],
            'skill'=>$gap['skill_name'],'subject'=>$gap['subject'],'difficulty'=>$options['difficulty']??$difficulty,
            'count'=>$count,'purpose'=>'remediation','seen_policy'=>$options['seen_policy']??'prefer_unseen',
        ]));
    }

    public function markPending(int $questionId): void
    { $this->pdo->prepare("UPDATE question_bank_items SET index_status='pending' WHERE id=?")->execute([$questionId]); }

    public function rebuildIndex(string $mode='all'): array
    {
        $where=$mode==='missing'?"WHERE index_status IN ('pending','failed','not_configured')":'';
        $questions=$this->pdo->query("SELECT * FROM question_bank_items {$where} ORDER BY id LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
        $health=$this->vectorProvider->healthCheck();
        if (empty($health['available'])) {
            if ($questions) {
                $ids=array_column($questions,'id'); $marks=implode(',',array_fill(0,count($ids),'?'));
                $this->pdo->prepare("UPDATE question_bank_items SET index_status='not_configured' WHERE id IN ({$marks})")->execute($ids);
            }
            return ['total'=>count($questions),'indexed'=>0,'failed'=>0,'not_configured'=>count($questions),'provider'=>$health];
        }
        return array_merge(['total'=>count($questions),'provider'=>$health],$this->vectorProvider->bulkIndex($questions));
    }

    private function inCondition(array &$where,array &$params,string $column,array $values,string $prefix,bool $not=false): void
    {
        $values=array_values(array_filter($values,fn($v)=>$v!==''&&$v!==null)); if (!$values) return;
        $marks=[]; foreach($values as $i=>$value){$key=":{$prefix}{$i}";$marks[]=$key;$params[$key]=$value;}
        $where[]=$column.($not?' NOT':'').' IN ('.implode(',',$marks).')';
    }
    private function semanticQueryText(array $request,array $parsed): string
    { return trim(implode(' ',array_filter([(string)($request['query']??''),(string)($request['subject']??$parsed['subject']??''),(string)($request['grade']??$parsed['level']??''),(string)($request['topic']??$parsed['topic']??''),(string)($request['skill']??$parsed['skill']??''),implode(' ',$parsed['context'])]))); }
    private function tokens(string $text): array
    { preg_match_all('/[\p{L}\p{N}]{2,}/u',mb_strtolower($text,'UTF-8'),$m); return array_values(array_unique($m[0]??[])); }
    private function keywordScore(array $tokens,string $text): float
    { if(!$tokens)return 0.0;$text=mb_strtolower($text,'UTF-8');$hits=0;foreach($tokens as $t)if(mb_strpos($text,$t)!==false)$hits++;return min(1,$hits/count($tokens)); }
    private function contains(string $haystack,string $needle): bool
    { return $needle!==''&&mb_stripos($haystack,$needle,0,'UTF-8')!==false; }
    private function normalize(string $text): string
    { return preg_replace('/[^\p{L}\p{N}]+/u','',mb_strtolower($text,'UTF-8'))??''; }
    private function lexicalSimilarity(string $a,string $b): float
    {
        $a=$this->tokens($a);$b=$this->tokens($b);if(!$a||!$b)return 0.0;
        $intersection=count(array_intersect($a,$b));$union=count(array_unique(array_merge($a,$b)));
        return $union?$intersection/$union:0.0;
    }
    private function deduplicate(array $items,float $threshold): array
    {
        $unique=[];$normalized=[];
        foreach($items as $item){$norm=$this->normalize((string)$item['question_text']);$duplicate=false;
            foreach($unique as $i=>$kept){
                if($norm!==''&&$norm===$normalized[$i]){$duplicate=true;break;}
                if($this->lexicalSimilarity((string)$item['question_text'],(string)$kept['question_text'])>=$threshold){$duplicate=true;break;}
            }
            if(!$duplicate){$unique[]=$item;$normalized[]=$norm;}
        } return $unique;
    }
    private function diversify(array $items,int $count): array
    {
        $selected=[];$remaining=$items;
        while(count($selected)<$count&&$remaining){$bestIndex=0;$best=-INF;
            foreach($remaining as $i=>$candidate){$redundancy=0.0;foreach($selected as $picked)$redundancy=max($redundancy,$this->lexicalSimilarity((string)$candidate['question_text'],(string)$picked['question_text']));
                $variety=0.0;foreach($selected as $picked)if(($candidate['skill_name']??'')!==($picked['skill_name']??''))$variety=0.06;
                $score=(float)$candidate['ranking_score']-0.30*$redundancy+$variety;if($score>$best){$best=$score;$bestIndex=$i;}}
            $selected[]=$remaining[$bestIndex];array_splice($remaining,$bestIndex,1);
        } return $selected;
    }
    private function reasons(array $q,string $topic,string $skill,string $grade,bool $seen,string $policy): array
    {
        $r=[];if($topic!==''&&$this->contains((string)$q['topic_name'],$topic))$r[]='ตรงกับหัวข้อ '.$topic;
        if($skill!==''&&$this->contains((string)$q['skill_name'],$skill))$r[]='ตรงกับทักษะ '.$skill;
        if($grade!==''&&(string)$q['grade_level']===$grade)$r[]='เหมาะกับระดับ '.$grade;
        $r[]='ระดับความยาก '.ucfirst((string)$q['difficulty']);
        if(!$seen)$r[]='ยังไม่เคยทำ'; elseif($policy==='retry_incorrect')$r[]='ข้อที่เคยตอบผิด';
        return $r;
    }
    private function logSearch(array $request,array $selected): void
    {
        try{$this->pdo->prepare("INSERT INTO question_search_logs(actor_id,student_id,gap_id,purpose,query_text,filters_json,selected_question_ids_json,event_type) VALUES(?,?,?,?,?,?,?,'search')")
            ->execute([(int)($request['actor_id']??0)?:null,(int)($request['student_id']??0)?:null,(int)($request['gap_id']??0)?:null,$request['purpose']??'question_bank',mb_substr((string)($request['query']??''),0,500),json_encode($request,JSON_UNESCAPED_UNICODE),json_encode($selected)]);}catch(Throwable $e){}
    }
    private function logProviderFailure(array $request,Throwable $error): void
    { try{$this->pdo->prepare("INSERT INTO question_search_logs(actor_id,purpose,query_text,filters_json,event_type) VALUES(?,'admin_debug',?,?,'provider_failure')")->execute([(int)($request['actor_id']??0)?:null,mb_substr((string)($request['query']??''),0,500),json_encode(['error'=>mb_substr($error->getMessage(),0,300)])]);}catch(Throwable $e){} }
}

final class RemediationService
{
    public function __construct(private PDO $pdo) { ensurePhase4Schema($this->pdo); }

    public function createApprovedAssignment(array $params): array
    {
        $studentId=(int)($params['student_id']??0);$gapId=(int)($params['gap_id']??0);
        $courseId=(int)($params['course_id']??0);$creatorId=(int)($params['created_by']??0);
        $questionIds=array_values(array_unique(array_map('intval',(array)($params['question_ids']??[]))));
        if(!$studentId||!$gapId||!$courseId||!$questionIds)throw new InvalidArgumentException('student_id, course_id, gap_id and question_ids are required');
        $gapStmt=$this->pdo->prepare('SELECT * FROM student_learning_gaps WHERE id=? AND student_id=? AND course_id=?');
        $gapStmt->execute([$gapId,$studentId,$courseId]);$gap=$gapStmt->fetch(PDO::FETCH_ASSOC);
        if(!$gap)throw new RuntimeException('Learning gap not found');
        $active=$this->pdo->prepare("SELECT * FROM personalized_remediations WHERE student_id=? AND gap_id=? AND status IN ('assigned','in_progress') ORDER BY id DESC LIMIT 1");
        $active->execute([$studentId,$gapId]);if($existing=$active->fetch(PDO::FETCH_ASSOC))return ['created'=>false,'duplicate'=>true,'remediation'=>$existing];

        $marks=implode(',',array_fill(0,count($questionIds),'?'));
        $qStmt=$this->pdo->prepare("SELECT * FROM question_bank_items WHERE id IN ({$marks}) AND status='active' AND review_status IN ('approved','reviewed')");
        $qStmt->execute($questionIds);$byId=[];foreach($qStmt->fetchAll(PDO::FETCH_ASSOC) as $q)$byId[(int)$q['id']]=$q;
        if(count($byId)!==count($questionIds))throw new RuntimeException('One or more questions are unavailable or not reviewed');
        $title=trim((string)($params['title']??''))?:'แบบฝึกทบทวนเฉพาะจุด: '.$gap['topic_name'];
        $due=!empty($params['due_at'])?date('Y-m-d H:i:s',strtotime((string)$params['due_at'])):null;
        $policy=(string)($params['seen_policy']??'prefer_unseen');

        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare("INSERT INTO worksheets(title,description,subject,level,topic,subtopic,worksheet_type,difficulty,question_count,generation_source,creator_id,creator_name,tags,status)
                SELECT ?,?,COALESCE(c.subject,''),COALESCE(c.level,''),?,?, 'Remediation','medium',?,'manual',?,?,?,'published' FROM courses c WHERE c.id=?")
                ->execute([$title,'แบบฝึกเฉพาะจุดที่ครูตรวจสอบก่อนมอบหมาย',$gap['topic_name'],$gap['subtopic_name'],count($questionIds),$creatorId?:null,$params['created_by_name']??'ครูผู้สอน',json_encode(['remediation',$gap['topic_name']],JSON_UNESCAPED_UNICODE),$courseId]);
            $worksheetId=(int)$this->pdo->lastInsertId();if(!$worksheetId)throw new RuntimeException('Course not found or worksheet could not be created');
            $this->pdo->prepare("INSERT INTO personalized_remediations(student_id,course_id,gap_id,worksheet_id,topic_name,skill_name,question_count,seen_policy,status,created_by,assigned_at,due_at,pre_remediation_mastery)
                VALUES(?,?,?,?,?,?,?,?, 'assigned',?,NOW(),?,?)")
                ->execute([$studentId,$courseId,$gapId,$worksheetId,$gap['topic_name'],$gap['skill_name'],count($questionIds),$policy,$creatorId?:null,$due,$gap['mastery_score']]);
            $remediationId=(int)$this->pdo->lastInsertId();
            $insQ=$this->pdo->prepare("INSERT INTO worksheet_questions(worksheet_id,canonical_question_id,sort_order,question_type,question_text,options,correct_answer,explanation,hint,skill,difficulty,learning_objective,points)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?,1)");
            $insRel=$this->pdo->prepare("INSERT INTO remediation_questions(remediation_id,question_id,worksheet_question_id,order_index,selection_reason,was_seen_before,difficulty_at_assignment) VALUES(?,?,?,?,?,?,?)");
            $seenStmt=$this->pdo->prepare('SELECT attempt_count FROM question_exposures WHERE question_id=? AND student_id=?');
            foreach($questionIds as $index=>$qid){$q=$byId[$qid];$answer=json_decode((string)$q['correct_answer_json'],true);if(is_array($answer))$answer=json_encode($answer,JSON_UNESCAPED_UNICODE);elseif($answer===null)$answer=(string)$q['correct_answer_json'];
                $insQ->execute([$worksheetId,$qid,$index+1,$q['question_type'],$q['question_text'],$q['choices_json'],$answer,$q['explanation'],$q['hint'],$q['skill_name'],$q['difficulty'],$q['learning_objective']]);
                $wqId=(int)$this->pdo->lastInsertId();$seenStmt->execute([$qid,$studentId]);$seen=(int)$seenStmt->fetchColumn()>0;
                $reason=implode(' · ',array_filter(['หัวข้อ '.$gap['topic_name'],$gap['skill_name']?'ทักษะ '.$gap['skill_name']:null,ucfirst($q['difficulty']),$seen?'เคยทำแล้ว':'ยังไม่เคยทำ']));
                $insRel->execute([$remediationId,$qid,$wqId,$index+1,$reason,$seen?1:0,$q['difficulty']]);}
            // Use the existing Phase 3 assignment model; no second runner is created.
            $this->pdo->prepare("INSERT INTO worksheet_assignments(worksheet_id,course_id,target_type,student_ids,due_date,assigned_by,assigned_by_name,activity_type,title,topic_name,max_attempts,status,created_at)
                VALUES(?,?,'selected',?,?,?,?,'remediation',?,?,2,'active',NOW())")
                ->execute([$worksheetId,$courseId,json_encode([$studentId]),$due,$creatorId?:null,$params['created_by_name']??'ครูผู้สอน',$title,$gap['topic_name']]);
            $assignmentId=(int)$this->pdo->lastInsertId();
            $this->pdo->prepare('UPDATE personalized_remediations SET assignment_id=? WHERE id=?')->execute([$assignmentId,$remediationId]);
            if($due&&$creatorId>0){
                $dueAt=new DateTimeImmutable($due);$start=$dueAt->format('H:i:s');if($start==='00:00:00')$start='17:00:00';
                $end=(new DateTimeImmutable($dueAt->format('Y-m-d').' '.$start))->modify('+30 minutes')->format('H:i:s');
                $this->pdo->prepare("INSERT INTO calendar_events(title,event_type,course_id,teacher_id,event_date,start_time,end_time,notes,color,status,created_by)
                  VALUES(?,'other',?,?,?,?,?,?,'#ec4899','scheduled',?)")
                  ->execute(['ครบกำหนด: '.$title,$courseId,$creatorId,$dueAt->format('Y-m-d'),$start,$end,'Personalized remediation assignment #'.$assignmentId,$creatorId]);
            }
            $this->pdo->prepare("INSERT INTO question_search_logs(actor_id,student_id,gap_id,purpose,selected_question_ids_json,event_type) VALUES(?,?,?,'remediation',?,'assignment_created')")
                ->execute([$creatorId?:null,$studentId,$gapId,json_encode($questionIds)]);
            $this->pdo->commit();
            return ['created'=>true,'duplicate'=>false,'remediation_id'=>$remediationId,'assignment_id'=>$assignmentId,'worksheet_id'=>$worksheetId,'question_count'=>count($questionIds)];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function recordCompletion(int $assignmentId,int $submissionId,int $studentId): void
    {
        $stmt=$this->pdo->prepare('SELECT * FROM personalized_remediations WHERE assignment_id=? AND student_id=? LIMIT 1');$stmt->execute([$assignmentId,$studentId]);$rem=$stmt->fetch(PDO::FETCH_ASSOC);if(!$rem)return;
        $answers=$this->pdo->prepare("SELECT rq.question_id,aa.is_correct FROM remediation_questions rq LEFT JOIN activity_answers aa ON aa.question_id=rq.worksheet_question_id AND aa.submission_id=? WHERE rq.remediation_id=?");
        $answers->execute([$submissionId,$rem['id']]);
        $up=$this->pdo->prepare("INSERT INTO question_exposures(question_id,student_id,first_seen_at,last_seen_at,attempt_count,correct_count,incorrect_count,last_result)
            VALUES(?,?,NOW(),NOW(),1,?,?,?) ON DUPLICATE KEY UPDATE last_seen_at=NOW(),attempt_count=attempt_count+1,
              correct_count=correct_count+VALUES(correct_count),incorrect_count=incorrect_count+VALUES(incorrect_count),last_result=VALUES(last_result)");
        foreach($answers->fetchAll(PDO::FETCH_ASSOC) as $a){$correct=(int)($a['is_correct']??0)===1;$up->execute([(int)$a['question_id'],$studentId,$correct?1:0,$correct?0:1,$correct?'correct':'incorrect']);}
        $this->pdo->prepare("UPDATE personalized_remediations SET status='completed',completed_at=NOW() WHERE id=?")->execute([$rem['id']]);
        // Completion is evidence, not mastery proof: Phase 5 will decide resolution.
        $this->pdo->prepare("UPDATE student_learning_gaps SET status='monitoring',last_detected_at=NOW() WHERE id=? AND status<>'resolved'")->execute([$rem['gap_id']]);
    }
}
