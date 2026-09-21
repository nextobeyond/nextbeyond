<?php
/** NEXTBEYOND V2 — Phase 5 mastery decisions and human intervention loop. */
declare(strict_types=1);

namespace NextBeyond\Mastery;

require_once __DIR__ . '/phase4-adaptive-service.php';

use DateTimeImmutable;
use InvalidArgumentException;
use NextBeyond\Adaptive\QuestionSearchService;
use PDO;
use RuntimeException;
use Throwable;

function ensurePhase5Schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) return;

    \NextBeyond\Adaptive\ensurePhase4Schema($pdo);

    try {
        $t1 = $pdo->query("SHOW TABLES LIKE 'adaptive_learning_config'")->fetch();
        $t2 = $pdo->query("SHOW TABLES LIKE 'teacher_interventions'")->fetch();
        if ($t1 && $t2) {
            $hasRow = $pdo->query("SELECT id FROM adaptive_learning_config WHERE id=1")->fetch();
            if (!$hasRow) {
                $pdo->exec("INSERT IGNORE INTO adaptive_learning_config (`id`) VALUES (1)");
            }
            $ensured = true;
            return;
        }
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `adaptive_learning_config` (
              `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
              `mastery_threshold` DECIMAL(5,2) NOT NULL DEFAULT 80.00,
              `near_mastery_threshold` DECIMAL(5,2) NOT NULL DEFAULT 65.00,
              `developing_threshold` DECIMAL(5,2) NOT NULL DEFAULT 50.00,
              `minimum_evidence` INT UNSIGNED NOT NULL DEFAULT 8,
              `minimum_source_diversity` INT UNSIGNED NOT NULL DEFAULT 2,
              `recent_evidence_window` INT UNSIGNED NOT NULL DEFAULT 8,
              `remediation_retry_limit` INT UNSIGNED NOT NULL DEFAULT 2,
              `teacher_intervention_threshold` DECIMAL(5,2) NOT NULL DEFAULT 60.00,
              `regression_drop_threshold` DECIMAL(5,2) NOT NULL DEFAULT 20.00,
              `mastery_check_question_count` INT UNSIGNED NOT NULL DEFAULT 5,
              `blocking_gap_severity` ENUM('low','moderate','high','critical') NOT NULL DEFAULT 'critical',
              `automation_policy` ENUM('manual','teacher_approved','auto_assign') NOT NULL DEFAULT 'teacher_approved',
              `retention_recheck_days` INT UNSIGNED NOT NULL DEFAULT 7,
              `updated_by` INT NULL,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $pdo->exec("INSERT IGNORE INTO adaptive_learning_config (`id`) VALUES (1)");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `mastery_checks` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `idempotency_key` VARCHAR(191) NOT NULL,
              `student_id` INT NOT NULL,
              `course_id` INT NOT NULL,
              `gap_id` BIGINT UNSIGNED NULL,
              `topic_name` VARCHAR(255) NOT NULL,
              `skill_name` VARCHAR(150) NULL,
              `check_type` ENUM('evidence_evaluation','explicit_activity','teacher_assessment','retention_recheck') NOT NULL,
              `assignment_id` INT NULL,
              `submission_id` INT NULL,
              `mastery_score` DECIMAL(5,2) NOT NULL,
              `recent_accuracy` DECIMAL(5,2) NOT NULL,
              `evidence_count` INT UNSIGNED NOT NULL,
              `source_diversity` INT UNSIGNED NOT NULL DEFAULT 0,
              `difficulty_diversity` INT UNSIGNED NOT NULL DEFAULT 0,
              `consistency_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
              `confidence` ENUM('low','medium','high') NOT NULL,
              `trend` ENUM('improving','stable','declining') NOT NULL DEFAULT 'stable',
              `mastery_status` ENUM('not_enough_evidence','developing','near_mastery','mastered','regression_detected') NOT NULL,
              `recommendation` ENUM('advance','continue_practice','retry_remediation','teacher_intervention','monitor') NOT NULL,
              `config_snapshot_json` TEXT NULL,
              `rationale_json` TEXT NULL,
              `evaluated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_mastery_check_idempotency` (`idempotency_key`),
              KEY `idx_mastery_student_topic` (`student_id`,`course_id`,`topic_name`,`evaluated_at`),
              KEY `idx_mastery_gap` (`gap_id`,`evaluated_at`),
              KEY `idx_mastery_assignment` (`assignment_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `mastery_check_assignments` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `student_id` INT NOT NULL,
              `course_id` INT NOT NULL,
              `gap_id` BIGINT UNSIGNED NOT NULL,
              `worksheet_id` INT NOT NULL,
              `assignment_id` INT NOT NULL,
              `status` ENUM('assigned','in_progress','completed','cancelled') NOT NULL DEFAULT 'assigned',
              `created_by` INT NULL,
              `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `completed_at` DATETIME NULL,
              `recheck_after` DATETIME NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_mastery_check_assignment` (`assignment_id`),
              KEY `idx_mastery_check_gap_status` (`gap_id`,`status`),
              KEY `idx_mastery_check_student` (`student_id`,`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `mastery_check_questions` (
              `mastery_check_assignment_id` BIGINT UNSIGNED NOT NULL,
              `question_id` BIGINT UNSIGNED NOT NULL,
              `worksheet_question_id` INT NOT NULL,
              `order_index` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`mastery_check_assignment_id`,`question_id`),
              KEY `idx_mcq_worksheet_question` (`worksheet_question_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `learning_decisions` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `idempotency_key` VARCHAR(191) NOT NULL,
              `student_id` INT NOT NULL,
              `course_id` INT NOT NULL,
              `gap_id` BIGINT UNSIGNED NULL,
              `mastery_check_id` BIGINT UNSIGNED NULL,
              `system_recommendation` ENUM('advance','continue_practice','retry_remediation','teacher_intervention','monitor') NOT NULL,
              `teacher_decision` ENUM('advance','continue_practice','retry_remediation','teacher_intervention','monitor') NULL,
              `effective_decision` ENUM('advance','continue_practice','retry_remediation','teacher_intervention','monitor') NOT NULL,
              `override_reason` TEXT NULL,
              `decided_by` INT NULL,
              `rationale_json` TEXT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `applied_at` DATETIME NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_learning_decision_idempotency` (`idempotency_key`),
              KEY `idx_decision_student` (`student_id`,`created_at`),
              KEY `idx_decision_gap` (`gap_id`,`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `teacher_interventions` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `student_id` INT NOT NULL,
              `course_id` INT NOT NULL,
              `gap_id` BIGINT UNSIGNED NULL,
              `topic_name` VARCHAR(255) NOT NULL,
              `skill_name` VARCHAR(150) NULL,
              `trigger_type` ENUM('system_recommendation','teacher_manual','live_class_followup','regression','prerequisite_gap') NOT NULL,
              `severity` ENUM('low','moderate','high','critical') NOT NULL DEFAULT 'moderate',
              `priority_score` DECIMAL(5,2) NOT NULL DEFAULT 50.00,
              `recommended_action` ENUM('reteach_in_class','small_group','one_on_one','extra_practice','prerequisite_review','teacher_feedback','makeup_class','onsite_support','online_support','custom') NOT NULL,
              `assigned_teacher_id` INT NULL,
              `status` ENUM('recommended','planned','in_progress','completed','cancelled','monitoring') NOT NULL DEFAULT 'recommended',
              `calendar_event_id` INT NULL,
              `scheduled_session_id` VARCHAR(64) NULL,
              `teacher_notes` TEXT NULL,
              `outcome` ENUM('improved','needs_more_practice','needs_another_session','prerequisite_problem_found','resolved','other') NULL,
              `resolution_reason` TEXT NULL,
              `mastery_before` DECIMAL(5,2) NULL,
              `mastery_after` DECIMAL(5,2) NULL,
              `duration_minutes` INT UNSIGNED NULL,
              `override_decision_id` BIGINT UNSIGNED NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `started_at` DATETIME NULL,
              `completed_at` DATETIME NULL,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_intervention_queue` (`assigned_teacher_id`,`status`,`priority_score`),
              KEY `idx_intervention_student` (`student_id`,`status`),
              KEY `idx_intervention_gap` (`gap_id`,`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `adaptive_learning_cycles` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `gap_id` BIGINT UNSIGNED NOT NULL,
              `student_id` INT NOT NULL,
              `cycle_number` INT UNSIGNED NOT NULL,
              `remediation_id` BIGINT UNSIGNED NULL,
              `mastery_check_id` BIGINT UNSIGNED NULL,
              `intervention_id` BIGINT UNSIGNED NULL,
              `status` ENUM('practice','checking','support','resolved','closed') NOT NULL DEFAULT 'practice',
              `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `completed_at` DATETIME NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_gap_cycle` (`gap_id`,`cycle_number`),
              KEY `idx_cycle_student` (`student_id`,`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `adaptive_roadmap_steps` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `idempotency_key` VARCHAR(191) NOT NULL,
              `student_id` INT NOT NULL,
              `course_id` INT NOT NULL,
              `gap_id` BIGINT UNSIGNED NULL,
              `intervention_id` BIGINT UNSIGNED NULL,
              `step_type` ENUM('prerequisite_review','remediation','mastery_check','teacher_review','teacher_session','additional_practice','retention_recheck') NOT NULL,
              `title` VARCHAR(255) NOT NULL,
              `topic_name` VARCHAR(255) NULL,
              `status` ENUM('available','in_progress','completed','locked','cancelled') NOT NULL DEFAULT 'available',
              `is_blocking` TINYINT(1) NOT NULL DEFAULT 0,
              `action_url` VARCHAR(500) NULL,
              `due_at` DATETIME NULL,
              `sort_order` INT NOT NULL DEFAULT 0,
              `completed_at` DATETIME NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_adaptive_step_idempotency` (`idempotency_key`),
              KEY `idx_adaptive_next` (`student_id`,`course_id`,`status`,`is_blocking`,`sort_order`),
              KEY `idx_adaptive_gap` (`gap_id`,`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `gap_resolution_history` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `idempotency_key` VARCHAR(191) NOT NULL,
              `gap_id` BIGINT UNSIGNED NOT NULL,
              `student_id` INT NOT NULL,
              `event_type` ENUM('resolved','reopened') NOT NULL,
              `resolution_type` ENUM('mastery_check','teacher_override','regression','manual_reopen') NOT NULL,
              `mastery_score` DECIMAL(5,2) NULL,
              `evidence_count` INT UNSIGNED NULL,
              `intervention_count` INT UNSIGNED NOT NULL DEFAULT 0,
              `performed_by` INT NULL,
              `reason` TEXT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_gap_resolution_idempotency` (`idempotency_key`),
              KEY `idx_resolution_gap` (`gap_id`,`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `learning_audit_log` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `idempotency_key` VARCHAR(191) NULL,
              `student_id` INT NULL,
              `course_id` INT NULL,
              `gap_id` BIGINT UNSIGNED NULL,
              `actor_id` INT NULL,
              `actor_type` ENUM('system','teacher','admin','student') NOT NULL DEFAULT 'system',
              `event_type` VARCHAR(80) NOT NULL,
              `entity_type` VARCHAR(80) NULL,
              `entity_id` VARCHAR(64) NULL,
              `summary` VARCHAR(500) NULL,
              `details_json` TEXT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_learning_audit_idempotency` (`idempotency_key`),
              KEY `idx_audit_student` (`student_id`,`created_at`),
              KEY `idx_audit_gap` (`gap_id`,`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable $e) {}

    \NextBeyond\Adaptive\ensureColumn($pdo, 'student_learning_gaps', 'blocking_mode', "ENUM('blocking','non_blocking') NOT NULL DEFAULT 'non_blocking'");
    \NextBeyond\Adaptive\ensureColumn($pdo, 'student_learning_gaps', 'resolved_by', "INT NULL");
    \NextBeyond\Adaptive\ensureColumn($pdo, 'student_learning_gaps', 'resolution_type', "VARCHAR(50) NULL");
    \NextBeyond\Adaptive\ensureColumn($pdo, 'student_learning_gaps', 'final_mastery', "DECIMAL(5,2) NULL");
    \NextBeyond\Adaptive\ensureColumn($pdo, 'student_learning_gaps', 'resolution_evidence_count', "INT UNSIGNED NULL");
    \NextBeyond\Adaptive\ensureColumn($pdo, 'student_learning_gaps', 'intervention_count', "INT UNSIGNED NOT NULL DEFAULT 0");
    \NextBeyond\Adaptive\ensureColumn($pdo, 'student_learning_gaps', 'cycle_number', "INT UNSIGNED NOT NULL DEFAULT 1");
    \NextBeyond\Adaptive\ensureColumn($pdo, 'student_learning_gaps', 'last_mastery_status', "VARCHAR(50) NULL");
    \NextBeyond\Adaptive\ensureColumn($pdo, 'student_learning_gaps', 'blocking_overridden_by', "INT NULL");
    \NextBeyond\Adaptive\ensureColumn($pdo, 'student_learning_gaps', 'blocking_override_reason', "TEXT NULL");

    \NextBeyond\Adaptive\ensureColumn($pdo, 'student_learning_profiles', 'developing_skills_json', "TEXT NULL");
    \NextBeyond\Adaptive\ensureColumn($pdo, 'student_learning_profiles', 'mastered_topics_json', "TEXT NULL");
    \NextBeyond\Adaptive\ensureColumn($pdo, 'student_learning_profiles', 'intervention_history_json', "TEXT NULL");
    \NextBeyond\Adaptive\ensureColumn($pdo, 'student_learning_profiles', 'goal_progress_status', "VARCHAR(50) NULL");

    \NextBeyond\Adaptive\ensureColumn($pdo, 'classroom_sessions', 'intervention_id', "BIGINT UNSIGNED NULL");
    \NextBeyond\Adaptive\ensureColumn($pdo, 'classroom_sessions', 'gap_id', "BIGINT UNSIGNED NULL");

    $ensured = true;
}

final class AdaptiveConfigService
{
    private static array $defaults = [
        'id' => 1,
        'mastery_threshold' => 80.0,
        'near_mastery_threshold' => 65.0,
        'developing_threshold' => 50.0,
        'minimum_evidence' => 8,
        'minimum_source_diversity' => 2,
        'recent_evidence_window' => 8,
        'remediation_retry_limit' => 2,
        'teacher_intervention_threshold' => 60.0,
        'regression_drop_threshold' => 20.0,
        'mastery_check_question_count' => 5,
        'blocking_gap_severity' => 'critical',
        'automation_policy' => 'teacher_approved',
        'retention_recheck_days' => 7,
        'updated_by' => null,
        'updated_at' => null,
    ];

    public function __construct(private PDO $pdo) { ensurePhase5Schema($this->pdo); }

    public function get(): array
    {
        $defaults = self::$defaults;
        try {
            $row = $this->pdo->query('SELECT * FROM adaptive_learning_config WHERE id=1')->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $this->pdo->exec('INSERT IGNORE INTO adaptive_learning_config (`id`) VALUES (1)');
                $row = $this->pdo->query('SELECT * FROM adaptive_learning_config WHERE id=1')->fetch(PDO::FETCH_ASSOC);
            }
            if ($row) {
                foreach (['mastery_threshold','near_mastery_threshold','developing_threshold','teacher_intervention_threshold','regression_drop_threshold'] as $key) {
                    $row[$key] = isset($row[$key]) ? (float)$row[$key] : $defaults[$key];
                }
                foreach (['minimum_evidence','minimum_source_diversity','recent_evidence_window','remediation_retry_limit','mastery_check_question_count','retention_recheck_days'] as $key) {
                    $row[$key] = isset($row[$key]) ? (int)$row[$key] : $defaults[$key];
                }
                return array_merge($defaults, $row);
            }
        } catch (Throwable $e) {
            error_log('AdaptiveConfigService::get error: ' . $e->getMessage());
        }
        return $defaults;
    }

    public function update(array $values,int $actorId): array
    {
        $allowed=['mastery_threshold','near_mastery_threshold','developing_threshold','minimum_evidence','minimum_source_diversity','recent_evidence_window','remediation_retry_limit','teacher_intervention_threshold','regression_drop_threshold','mastery_check_question_count','blocking_gap_severity','automation_policy','retention_recheck_days'];
        $current=$this->get();$candidate=array_merge($current,array_intersect_key($values,array_flip($allowed)));
        if((float)$candidate['developing_threshold']>=(float)$candidate['near_mastery_threshold']||(float)$candidate['near_mastery_threshold']>=(float)$candidate['mastery_threshold'])throw new InvalidArgumentException('Thresholds must increase from developing to near mastery to mastery');
        foreach(['mastery_threshold','near_mastery_threshold','developing_threshold','teacher_intervention_threshold','regression_drop_threshold']as$key)if((float)$candidate[$key]<0||(float)$candidate[$key]>100)throw new InvalidArgumentException($key.' must be between 0 and 100');
        foreach(['minimum_evidence','minimum_source_diversity','recent_evidence_window','mastery_check_question_count']as$key)if((int)$candidate[$key]<1)throw new InvalidArgumentException($key.' must be at least 1');
        if(!in_array((string)$candidate['automation_policy'],['manual','teacher_approved','auto_assign'],true))throw new InvalidArgumentException('Invalid automation policy');
        if(!in_array((string)$candidate['blocking_gap_severity'],['low','moderate','high','critical'],true))throw new InvalidArgumentException('Invalid blocking severity');
        $sets=[];$params=[':actor'=>$actorId];
        foreach($allowed as $key)if(array_key_exists($key,$values)){$sets[]="`{$key}`=:{$key}";$params[":{$key}"]=$values[$key];}
        if($sets){
            try { $this->pdo->exec('INSERT IGNORE INTO adaptive_learning_config (`id`) VALUES (1)'); } catch (Throwable $e) {}
            $this->pdo->prepare('UPDATE adaptive_learning_config SET '.implode(',',$sets).',updated_by=:actor WHERE id=1')->execute($params);
        }
        return $this->get();
    }
}

final class LearningAuditService
{
    public function __construct(private PDO $pdo) { ensurePhase5Schema($this->pdo); }
    public function record(string $event,array $context,string $summary,array $details=[],?string $idempotencyKey=null): void
    {
        $this->pdo->prepare("INSERT INTO learning_audit_log(idempotency_key,student_id,course_id,gap_id,actor_id,actor_type,event_type,entity_type,entity_id,summary,details_json)
          VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id")
          ->execute([$idempotencyKey,$context['student_id']??null,$context['course_id']??null,$context['gap_id']??null,$context['actor_id']??null,$context['actor_type']??'system',$event,$context['entity_type']??null,isset($context['entity_id'])?(string)$context['entity_id']:null,mb_substr($summary,0,500),json_encode($details,JSON_UNESCAPED_UNICODE)]);
    }
}

final class MasteryCheckService
{
    private AdaptiveConfigService $config;
    private LearningAuditService $audit;
    public function __construct(private PDO $pdo,private ?QuestionSearchService $search=null)
    {
        ensurePhase5Schema($this->pdo);
        $this->config=new AdaptiveConfigService($pdo);
        $this->audit=new LearningAuditService($pdo);
        $this->search??=new QuestionSearchService($pdo);
    }

    public function evaluate(int $studentId,int $courseId,string $topic,string $skill='',array $options=[]): array
    {
        $cfg=$this->config->get();$window=max(1,$cfg['recent_evidence_window']);
        $sql="SELECT * FROM learning_evidence WHERE student_id=? AND course_id=? AND topic_name=?";$params=[$studentId,$courseId,$topic];
        if($skill!==''){$sql.=" AND (COALESCE(skill,'')=? OR skill IS NULL)";$params[]=$skill;}$sql.=' ORDER BY occurred_at ASC,id ASC';
        $stmt=$this->pdo->prepare($sql);$stmt->execute($params);$evidence=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $scores=[];$weights=[];$sources=[];$difficulties=[];
        $sourceWeights=['diagnostic'=>0.8,'get_ready'=>0.6,'in_class_check'=>0.8,'worksheet'=>0.9,'practice'=>0.9,'homework'=>1.0,'posttest'=>1.2,'mock_exam'=>1.25,'remediation'=>1.0,'mastery_check'=>1.35,'teacher_assessment'=>1.25];
        $difficultyWeights=['easy'=>0.80,'medium'=>1.0,'hard'=>1.15,'expert'=>1.20];$total=count($evidence);
        foreach($evidence as $i=>$ev){$score=(float)$ev['normalized_score'];$recency=0.75+0.25*(($i+1)/max(1,$total));$weight=($sourceWeights[$ev['source_type']]??1.0)*($difficultyWeights[$ev['difficulty']]??1.0)*$recency;
            $scores[]=$score;$weights[]=$weight;$sources[$ev['source_type']]=true;if(!empty($ev['difficulty']))$difficulties[$ev['difficulty']]=true;}
        $evidenceCount=count($scores);$weighted=$this->weightedAverage($scores,$weights);$recent=array_slice($scores,-$window);$recentAccuracy=$recent?round(array_sum($recent)/count($recent),2):0.0;
        $consistency=$this->consistency($recent);$trend=$this->trend($scores,$window);$sourceDiversity=count($sources);$difficultyDiversity=count($difficulties);
        $submissionId=(int)($options['submission_id']??0);$verificationScore=null;
        if($submissionId){$s=$this->pdo->prepare('SELECT score_percent FROM activity_submissions WHERE id=? AND student_id=?');$s->execute([$submissionId,$studentId]);$v=$s->fetchColumn();if($v!==false)$verificationScore=(float)$v;}
        $mastery=round($verificationScore!==null?($verificationScore*0.70+$weighted*0.30):($recentAccuracy*0.60+$weighted*0.40),2);
        $hasChallenge=isset($difficulties['medium'])||isset($difficulties['hard'])||isset($difficulties['expert']);
        if($evidenceCount>=$cfg['minimum_evidence']*1.5&&$sourceDiversity>=$cfg['minimum_source_diversity']&&$hasChallenge&&$consistency>=65)$confidence='high';
        elseif($evidenceCount>=$cfg['minimum_evidence']&&$sourceDiversity>=min(2,$cfg['minimum_source_diversity']))$confidence='medium';else $confidence='low';
        $gap=$this->findGap($studentId,$courseId,$topic,$skill);if($gap&&empty($gap['blocking_overridden_by'])){$rank=['low'=>1,'moderate'=>2,'high'=>3,'critical'=>4];$autoBlocking=($rank[$gap['severity']]??1)>=($rank[$cfg['blocking_gap_severity']]??4)?'blocking':'non_blocking';if(($gap['blocking_mode']??'')!==$autoBlocking){$this->pdo->prepare('UPDATE student_learning_gaps SET blocking_mode=? WHERE id=?')->execute([$autoBlocking,$gap['id']]);$gap['blocking_mode']=$autoBlocking;}}$previousMastered=$gap&&(($gap['status']??'')==='resolved'||($gap['last_mastery_status']??'')==='mastered');
        $regression=$previousMastered&&($recentAccuracy<$cfg['teacher_intervention_threshold']||((float)($gap['final_mastery']??$cfg['mastery_threshold'])-$recentAccuracy)>=$cfg['regression_drop_threshold']);
        if($regression)$status='regression_detected';elseif($evidenceCount<$cfg['minimum_evidence']||$sourceDiversity<$cfg['minimum_source_diversity'])$status='not_enough_evidence';elseif($mastery>=$cfg['mastery_threshold']&&$confidence!=='low'&&$hasChallenge)$status='mastered';elseif($mastery>=$cfg['near_mastery_threshold'])$status='near_mastery';else $status='developing';
        $cycles=$gap?$this->completedCycles((int)$gap['id']):0;
        $recommendation=match(true){
            $status==='mastered'=>'advance',$status==='regression_detected'=>'teacher_intervention',
            $status==='not_enough_evidence'=>'monitor',
            $cycles>=$cfg['remediation_retry_limit']&&$mastery<$cfg['near_mastery_threshold']=>'teacher_intervention',
            $mastery<$cfg['teacher_intervention_threshold']&&$confidence==='high'=>'teacher_intervention',
            $status==='near_mastery'=>'continue_practice',$cycles===0=>'retry_remediation',default=>'continue_practice'};
        $rationale=['threshold'=>$cfg['mastery_threshold'],'minimum_evidence'=>$cfg['minimum_evidence'],'evidence_count'=>$evidenceCount,'source_diversity'=>$sourceDiversity,'minimum_source_diversity'=>$cfg['minimum_source_diversity'],'recent_accuracy'=>$recentAccuracy,'consistency'=>$consistency,'has_medium_or_hard_evidence'=>$hasChallenge,'completed_cycles'=>$cycles,'verification_score'=>$verificationScore];
        $type=$options['check_type']??'evidence_evaluation';$idempotency=$options['idempotency_key']??hash('sha256',implode('|',[$studentId,$courseId,$topic,$skill,$type,$options['submission_id']??0,$evidenceCount,$evidence?end($evidence)['id']:0]));
        $this->pdo->prepare("INSERT INTO mastery_checks(idempotency_key,student_id,course_id,gap_id,topic_name,skill_name,check_type,assignment_id,submission_id,mastery_score,recent_accuracy,evidence_count,source_diversity,difficulty_diversity,consistency_score,confidence,trend,mastery_status,recommendation,config_snapshot_json,rationale_json)
          VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)")
          ->execute([$idempotency,$studentId,$courseId,$gap['id']??null,$topic,$skill?:null,$type,$options['assignment_id']??null,$submissionId?:null,$mastery,$recentAccuracy,$evidenceCount,$sourceDiversity,$difficultyDiversity,$consistency,$confidence,$trend,$status,$recommendation,json_encode($cfg,JSON_UNESCAPED_UNICODE),json_encode($rationale,JSON_UNESCAPED_UNICODE)]);
        $checkId=(int)$this->pdo->lastInsertId();if(!$checkId){$q=$this->pdo->prepare('SELECT id FROM mastery_checks WHERE idempotency_key=?');$q->execute([$idempotency]);$checkId=(int)$q->fetchColumn();}
        $result=['id'=>$checkId,'student_id'=>$studentId,'course_id'=>$courseId,'gap_id'=>(int)($gap['id']??0),'topic_name'=>$topic,'skill_name'=>$skill,'mastery_score'=>$mastery,'recent_accuracy'=>$recentAccuracy,'mastery_status'=>$status,'evidence_count'=>$evidenceCount,'source_diversity'=>$sourceDiversity,'difficulty_diversity'=>$difficultyDiversity,'confidence'=>$confidence,'trend'=>$trend,'recommendation'=>$recommendation,'rationale'=>$rationale,'check_type'=>$type];
        if($regression&&$gap)$this->reopenForRegression($gap,$result,$idempotency);
        $this->audit->record('mastery_check_completed',['student_id'=>$studentId,'course_id'=>$courseId,'gap_id'=>$gap['id']??null,'entity_type'=>'mastery_check','entity_id'=>$checkId],'Mastery evaluation: '.$status,$result,'mastery-check-'.$idempotency);
        return $result;
    }

    public function recommendQuestions(int $studentId,int $courseId,int $gapId,int $count=0,array $options=[]): array
    {
        $cfg=$this->config->get();$count=$count?:$cfg['mastery_check_question_count'];$gap=$this->getGap($gapId,$studentId,$courseId);
        $exclude=$this->pdo->prepare('SELECT question_id FROM remediation_questions rq JOIN personalized_remediations r ON r.id=rq.remediation_id WHERE r.gap_id=? UNION SELECT question_id FROM mastery_check_questions q JOIN mastery_check_assignments a ON a.id=q.mastery_check_assignment_id WHERE a.gap_id=?');
        $exclude->execute([$gapId,$gapId]);$excludeIds=array_map('intval',$exclude->fetchAll(PDO::FETCH_COLUMN));
        $difficulty=(float)$gap['mastery_score']>=65?['medium','hard']:['easy','medium'];
        return $this->search->searchQuestions(array_merge($options,['student_id'=>$studentId,'course_id'=>$courseId,'gap_id'=>$gapId,'topic'=>$gap['topic_name'],'skill'=>$gap['skill_name'],'subject'=>$gap['subject'],'count'=>$count,'difficulty'=>$options['difficulty']??$difficulty,'seen_policy'=>'unseen_only','exclude_ids'=>array_values(array_unique(array_merge($excludeIds,(array)($options['exclude_ids']??[])))),'purpose'=>'mastery_check']));
    }

    public function createAssignment(array $params): array
    {
        $sid=(int)($params['student_id']??0);$cid=(int)($params['course_id']??0);$gapId=(int)($params['gap_id']??0);$actor=(int)($params['created_by']??0);$ids=array_values(array_unique(array_map('intval',(array)($params['question_ids']??[]))));
        if(!$sid||!$cid||!$gapId||!$ids)throw new InvalidArgumentException('student_id, course_id, gap_id and question_ids are required');$gap=$this->getGap($gapId,$sid,$cid);
        $active=$this->pdo->prepare("SELECT * FROM mastery_check_assignments WHERE gap_id=? AND status IN ('assigned','in_progress') ORDER BY id DESC LIMIT 1");$active->execute([$gapId]);if($row=$active->fetch(PDO::FETCH_ASSOC))return ['created'=>false,'duplicate'=>true,'mastery_check_assignment'=>$row];
        $marks=implode(',',array_fill(0,count($ids),'?'));$q=$this->pdo->prepare("SELECT * FROM question_bank_items WHERE id IN ({$marks}) AND status='active' AND review_status IN ('approved','reviewed')");$q->execute($ids);$items=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $item)$items[(int)$item['id']]=$item;if(count($items)!==count($ids))throw new RuntimeException('One or more mastery-check questions are unavailable');
        $title='ตรวจความเข้าใจ: '.$gap['topic_name'];$this->pdo->beginTransaction();
        try{$course=$this->pdo->prepare('SELECT subject,level FROM courses WHERE id=?');$course->execute([$cid]);$c=$course->fetch(PDO::FETCH_ASSOC);if(!$c)throw new RuntimeException('Course not found');
            $this->pdo->prepare("INSERT INTO worksheets(title,description,subject,level,topic,worksheet_type,difficulty,question_count,generation_source,creator_id,creator_name,tags,status) VALUES(?,?,?,?,?,'Mastery Check','medium',?,'manual',?,?,?,'published')")
              ->execute([$title,'คำถามใหม่สำหรับตรวจสอบการถ่ายโอนความเข้าใจ',$c['subject'],$c['level'],$gap['topic_name'],count($ids),$actor?:null,$params['created_by_name']??'ครูผู้สอน',json_encode(['mastery_check',$gap['topic_name']],JSON_UNESCAPED_UNICODE)]);$wid=(int)$this->pdo->lastInsertId();
            $insert=$this->pdo->prepare('INSERT INTO worksheet_questions(worksheet_id,canonical_question_id,sort_order,question_type,question_text,options,correct_answer,explanation,hint,skill,difficulty,learning_objective,points) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,1)');$wq=[];
            foreach($ids as $i=>$qid){$item=$items[$qid];$answer=json_decode((string)$item['correct_answer_json'],true);if(is_array($answer))$answer=json_encode($answer,JSON_UNESCAPED_UNICODE);elseif($answer===null)$answer=(string)$item['correct_answer_json'];$insert->execute([$wid,$qid,$i+1,$item['question_type'],$item['question_text'],$item['choices_json'],$answer,$item['explanation'],$item['hint'],$item['skill_name'],$item['difficulty'],$item['learning_objective']]);$wq[$qid]=(int)$this->pdo->lastInsertId();}
            $due=!empty($params['due_at'])?date('Y-m-d H:i:s',strtotime((string)$params['due_at'])):null;$this->pdo->prepare("INSERT INTO worksheet_assignments(worksheet_id,course_id,target_type,student_ids,due_date,assigned_by,assigned_by_name,activity_type,title,topic_name,max_attempts,status,created_at) VALUES(?,?,'selected',?,?,?,?,'mastery_check',?,?,1,'active',NOW())")
              ->execute([$wid,$cid,json_encode([$sid]),$due,$actor?:null,$params['created_by_name']??'ครูผู้สอน',$title,$gap['topic_name']]);$aid=(int)$this->pdo->lastInsertId();
            $this->pdo->prepare('INSERT INTO mastery_check_assignments(student_id,course_id,gap_id,worksheet_id,assignment_id,created_by) VALUES(?,?,?,?,?,?)')->execute([$sid,$cid,$gapId,$wid,$aid,$actor?:null]);$mcaId=(int)$this->pdo->lastInsertId();$rel=$this->pdo->prepare('INSERT INTO mastery_check_questions(mastery_check_assignment_id,question_id,worksheet_question_id,order_index) VALUES(?,?,?,?)');foreach($ids as $i=>$qid)$rel->execute([$mcaId,$qid,$wq[$qid],$i+1]);$this->pdo->prepare("INSERT INTO adaptive_learning_cycles(gap_id,student_id,cycle_number,status) VALUES(?,?,?,'checking') ON DUPLICATE KEY UPDATE status='checking'")->execute([$gapId,$sid,(int)($gap['cycle_number']??1)]);
            $this->upsertStep('mastery-check-assignment-'.$mcaId,$sid,$cid,$gapId,'mastery_check',$title,'available',($gap['blocking_mode']??'non_blocking')==='blocking','activity.php?assignment_id='.$aid,null,20);
            $this->pdo->commit();$this->audit->record('mastery_check_assigned',['student_id'=>$sid,'course_id'=>$cid,'gap_id'=>$gapId,'actor_id'=>$actor,'actor_type'=>'teacher','entity_type'=>'assignment','entity_id'=>$aid],$title,['question_ids'=>$ids],'mastery-check-assigned-'.$mcaId);
            return ['created'=>true,'duplicate'=>false,'mastery_check_assignment_id'=>$mcaId,'assignment_id'=>$aid,'worksheet_id'=>$wid,'question_count'=>count($ids)];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function handleAssignmentCompletion(int $assignmentId,int $submissionId,int $studentId): array
    {
        $stmt=$this->pdo->prepare('SELECT m.*,g.topic_name,g.skill_name FROM mastery_check_assignments m JOIN student_learning_gaps g ON g.id=m.gap_id WHERE m.assignment_id=? AND m.student_id=?');$stmt->execute([$assignmentId,$studentId]);$m=$stmt->fetch(PDO::FETCH_ASSOC);if(!$m)return [];
        $this->pdo->prepare("UPDATE mastery_check_assignments SET status='completed',completed_at=NOW() WHERE id=?")->execute([$m['id']]);
        $result=$this->evaluate($studentId,(int)$m['course_id'],$m['topic_name'],(string)$m['skill_name'],['check_type'=>'explicit_activity','assignment_id'=>$assignmentId,'submission_id'=>$submissionId,'idempotency_key'=>'mastery-assignment-'.$assignmentId.'-submission-'.$submissionId]);$this->pdo->prepare('UPDATE adaptive_learning_cycles SET mastery_check_id=? WHERE gap_id=? AND cycle_number=(SELECT cycle_number FROM student_learning_gaps WHERE id=?)')->execute([$result['id'],$m['gap_id'],$m['gap_id']]);
        $this->pdo->prepare("UPDATE adaptive_roadmap_steps SET status='completed',completed_at=NOW() WHERE idempotency_key=?")->execute(['mastery-check-assignment-'.$m['id']]);
        return (new LearningDecisionService($this->pdo))->applySystemDecision($result);
    }

    private function weightedAverage(array $scores,array $weights): float{if(!$scores)return 0.0;$sum=0.0;$ws=0.0;foreach($scores as $i=>$s){$w=$weights[$i]??1;$sum+=$s*$w;$ws+=$w;}return round($sum/max(.0001,$ws),2);}
    private function consistency(array $scores): float{if(count($scores)<2)return 50.0;$avg=array_sum($scores)/count($scores);$variance=0.0;foreach($scores as $s)$variance+=($s-$avg)**2;$sd=sqrt($variance/count($scores));return round(max(0,100-$sd*2),2);}
    private function trend(array $scores,int $window): string{$slice=array_slice($scores,-max(4,$window));if(count($slice)<4)return'stable';$mid=(int)ceil(count($slice)/2);$old=array_slice($slice,0,$mid);$new=array_slice($slice,$mid);$delta=array_sum($new)/count($new)-array_sum($old)/count($old);return$delta>=8?'improving':($delta<=-8?'declining':'stable');}
    private function completedCycles(int $gapId): int{$s=$this->pdo->prepare("SELECT COUNT(*) FROM personalized_remediations WHERE gap_id=? AND status='completed'");$s->execute([$gapId]);return(int)$s->fetchColumn();}
    private function findGap(int $sid,int $cid,string $topic,string $skill): ?array{$s=$this->pdo->prepare("SELECT * FROM student_learning_gaps WHERE student_id=? AND course_id=? AND topic_name=? AND (?='' OR COALESCE(skill_name,'')=?) ORDER BY id DESC LIMIT 1");$s->execute([$sid,$cid,$topic,$skill,$skill]);return$s->fetch(PDO::FETCH_ASSOC)?:null;}
    private function getGap(int $id,int $sid,int $cid): array{$s=$this->pdo->prepare('SELECT * FROM student_learning_gaps WHERE id=? AND student_id=? AND course_id=?');$s->execute([$id,$sid,$cid]);$g=$s->fetch(PDO::FETCH_ASSOC);if(!$g)throw new RuntimeException('Learning gap not found');return$g;}
    private function reopenForRegression(array $gap,array $result,string $key): void{$this->pdo->beginTransaction();try{$this->pdo->prepare("UPDATE student_learning_gaps SET status='open',resolved_at=NULL,resolved_by=NULL,resolution_type='regression',cycle_number=cycle_number+1,last_mastery_status='regression_detected',last_detected_at=NOW() WHERE id=? AND status='resolved'")->execute([$gap['id']]);$this->pdo->prepare("INSERT INTO gap_resolution_history(idempotency_key,gap_id,student_id,event_type,resolution_type,mastery_score,evidence_count,intervention_count,reason) VALUES(?,?,?,'reopened','regression',?,?,?,'Recent evidence indicates regression') ON DUPLICATE KEY UPDATE id=id")->execute(['reopen-'.$key,$gap['id'],$gap['student_id'],$result['mastery_score'],$result['evidence_count'],$gap['intervention_count']??0]);$this->pdo->commit();}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}}
    private function upsertStep(string $key,int $sid,int $cid,int $gapId,string $type,string $title,string $status,bool $blocking,?string $url,?string $due,int $sort): void{$this->pdo->prepare("INSERT INTO adaptive_roadmap_steps(idempotency_key,student_id,course_id,gap_id,step_type,title,topic_name,status,is_blocking,action_url,due_at,sort_order) SELECT ?,?,?,?,?,?,topic_name,?,?,?,?,? FROM student_learning_gaps WHERE id=? ON DUPLICATE KEY UPDATE title=VALUES(title),action_url=VALUES(action_url),status=IF(adaptive_roadmap_steps.status='completed',adaptive_roadmap_steps.status,VALUES(status))")->execute([$key,$sid,$cid,$gapId,$type,$title,$status,$blocking?1:0,$url,$due,$sort,$gapId]);}
}

final class LearningDecisionService
{
    private LearningAuditService $audit;
    public function __construct(private PDO $pdo)
    {
        ensurePhase5Schema($this->pdo);
        $this->audit = new LearningAuditService($pdo);
    }
    public function applySystemDecision(array $check): array
    {
        $key='decision-mastery-check-'.$check['id'];$this->pdo->prepare("INSERT INTO learning_decisions(idempotency_key,student_id,course_id,gap_id,mastery_check_id,system_recommendation,effective_decision,rationale_json) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)")
          ->execute([$key,$check['student_id'],$check['course_id'],$check['gap_id']?:null,$check['id'],$check['recommendation'],$check['recommendation'],json_encode($check['rationale'],JSON_UNESCAPED_UNICODE)]);$id=(int)$this->pdo->lastInsertId();if(!$id){$s=$this->pdo->prepare('SELECT id FROM learning_decisions WHERE idempotency_key=?');$s->execute([$key]);$id=(int)$s->fetchColumn();}
        if($check['recommendation']==='advance'&&$check['check_type']==='explicit_activity')$this->resolveGap((int)$check['gap_id'],$check,null,'mastery_check','Passed explicit mastery check','resolve-decision-'.$id);
        elseif($check['recommendation']==='teacher_intervention'&&$check['gap_id'])(new InterventionService($this->pdo))->ensureRecommended((int)$check['gap_id'],$check,$id);
        elseif($check['gap_id'])$this->ensureDecisionStep($check,$id);
        $this->pdo->prepare('UPDATE learning_decisions SET applied_at=COALESCE(applied_at,NOW()) WHERE id=?')->execute([$id]);
        $this->audit->record('decision_result',['student_id'=>$check['student_id'],'course_id'=>$check['course_id'],'gap_id'=>$check['gap_id']?:null,'entity_type'=>'learning_decision','entity_id'=>$id],'System recommendation: '.$check['recommendation'],['mastery_status'=>$check['mastery_status'],'mastery_score'=>$check['mastery_score'],'confidence'=>$check['confidence'],'rationale'=>$check['rationale']],'decision-result-'.$id);
        return ['decision_id'=>$id,'system_recommendation'=>$check['recommendation'],'effective_decision'=>$check['recommendation'],'mastery_check'=>$check];
    }
    public function overrideDecision(int $decisionId,string $teacherDecision,string $reason,int $teacherId): array
    {
        $allowed=['advance','continue_practice','retry_remediation','teacher_intervention','monitor'];if(!in_array($teacherDecision,$allowed,true)||trim($reason)==='')throw new InvalidArgumentException('A valid teacher decision and override reason are required');
        $stmt=$this->pdo->prepare('SELECT d.*,m.* FROM learning_decisions d JOIN mastery_checks m ON m.id=d.mastery_check_id WHERE d.id=?');$stmt->execute([$decisionId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Decision not found');
        $this->pdo->prepare('UPDATE learning_decisions SET teacher_decision=?,effective_decision=?,override_reason=?,decided_by=?,applied_at=NOW() WHERE id=?')->execute([$teacherDecision,$teacherDecision,$reason,$teacherId,$decisionId]);
        $check=['id'=>(int)$row['mastery_check_id'],'student_id'=>(int)$row['student_id'],'course_id'=>(int)$row['course_id'],'gap_id'=>(int)$row['gap_id'],'topic_name'=>$row['topic_name'],'skill_name'=>$row['skill_name'],'mastery_score'=>(float)$row['mastery_score'],'evidence_count'=>(int)$row['evidence_count'],'recommendation'=>$teacherDecision,'rationale'=>json_decode((string)$row['rationale_json'],true)?:[],'check_type'=>$row['check_type']];
        if($teacherDecision==='advance')$this->resolveGap((int)$row['gap_id'],$check,$teacherId,'teacher_override',$reason,'resolve-override-'.$decisionId);
        elseif($teacherDecision==='teacher_intervention')(new InterventionService($this->pdo))->ensureRecommended((int)$row['gap_id'],$check,$decisionId,$teacherId,'teacher_manual');
        $this->audit->record('teacher_override',['student_id'=>$row['student_id'],'course_id'=>$row['course_id'],'gap_id'=>$row['gap_id'],'actor_id'=>$teacherId,'actor_type'=>'teacher','entity_type'=>'learning_decision','entity_id'=>$decisionId],'Teacher overrode '.$row['system_recommendation'].' with '.$teacherDecision,['reason'=>$reason],'teacher-override-'.$decisionId);
        return ['decision_id'=>$decisionId,'system_recommendation'=>$row['system_recommendation'],'teacher_decision'=>$teacherDecision,'effective_decision'=>$teacherDecision];
    }
    public function manualResolve(int $gapId,int $teacherId,string $reason): array
    {
        if(trim($reason)==='')throw new InvalidArgumentException('Resolution reason is required');$g=$this->gap($gapId);$check=['student_id'=>(int)$g['student_id'],'course_id'=>(int)$g['course_id'],'gap_id'=>$gapId,'topic_name'=>$g['topic_name'],'mastery_score'=>(float)$g['mastery_score'],'evidence_count'=>(int)$g['evidence_count']];$this->resolveGap($gapId,$check,$teacherId,'teacher_override',$reason,'manual-resolve-'.$gapId.'-'.hash('sha256',$reason));return['resolved'=>true,'gap_id'=>$gapId];
    }
    public function manualReopen(int $gapId,int $teacherId,string $reason): array
    {
        if(trim($reason)==='')throw new InvalidArgumentException('Reopen reason is required');$g=$this->gap($gapId);$key='manual-reopen-'.$gapId.'-'.hash('sha256',$reason);$this->pdo->beginTransaction();try{$this->pdo->prepare("UPDATE student_learning_gaps SET status='open',resolved_at=NULL,resolved_by=NULL,resolution_type='manual_reopen',cycle_number=cycle_number+1 WHERE id=?")->execute([$gapId]);$this->pdo->prepare("INSERT INTO gap_resolution_history(idempotency_key,gap_id,student_id,event_type,resolution_type,mastery_score,evidence_count,intervention_count,performed_by,reason) VALUES(?,?,?,'reopened','manual_reopen',?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id")->execute([$key,$gapId,$g['student_id'],$g['mastery_score'],$g['evidence_count'],$g['intervention_count'],$teacherId,$reason]);$this->pdo->commit();}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}$this->audit->record('gap_reopened',['student_id'=>$g['student_id'],'course_id'=>$g['course_id'],'gap_id'=>$gapId,'actor_id'=>$teacherId,'actor_type'=>'teacher','entity_type'=>'gap','entity_id'=>$gapId],'Teacher reopened gap',['reason'=>$reason],$key.'-audit');return['reopened'=>true,'gap_id'=>$gapId];
    }
    public function setBlockingMode(int $gapId,string $mode,int $teacherId,string $reason): array
    {
        if(!in_array($mode,['blocking','non_blocking'],true)||trim($reason)==='')throw new InvalidArgumentException('A blocking mode and reason are required');$g=$this->gap($gapId);$this->pdo->prepare('UPDATE student_learning_gaps SET blocking_mode=?,blocking_overridden_by=?,blocking_override_reason=? WHERE id=?')->execute([$mode,$teacherId,$reason,$gapId]);$this->pdo->prepare('UPDATE adaptive_roadmap_steps SET is_blocking=? WHERE gap_id=? AND status<>\'completed\'')->execute([$mode==='blocking'?1:0,$gapId]);$this->audit->record('roadmap_adjusted',['student_id'=>$g['student_id'],'course_id'=>$g['course_id'],'gap_id'=>$gapId,'actor_id'=>$teacherId,'actor_type'=>'teacher','entity_type'=>'gap','entity_id'=>$gapId],'Teacher set gap to '.$mode,['reason'=>$reason],'gap-blocking-'.$gapId.'-'.$mode.'-'.hash('sha256',$reason));return['updated'=>true,'gap_id'=>$gapId,'blocking_mode'=>$mode];
    }
    private function resolveGap(int $gapId,array $check,?int $actor,string $type,string $reason,string $key): void
    {
        if(!$gapId)return;$g=$this->gap($gapId);$this->pdo->beginTransaction();try{$this->pdo->prepare("UPDATE student_learning_gaps SET status='resolved',resolved_at=COALESCE(resolved_at,NOW()),resolved_by=?,resolution_type=?,final_mastery=?,resolution_evidence_count=?,last_mastery_status='mastered' WHERE id=?")->execute([$actor,$type,$check['mastery_score'],$check['evidence_count'],$gapId]);$this->pdo->prepare("INSERT INTO gap_resolution_history(idempotency_key,gap_id,student_id,event_type,resolution_type,mastery_score,evidence_count,intervention_count,performed_by,reason) VALUES(?,?,?,'resolved',?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id")->execute([$key,$gapId,$g['student_id'],$type,$check['mastery_score'],$check['evidence_count'],$g['intervention_count'],$actor,$reason]);$this->pdo->prepare("UPDATE adaptive_roadmap_steps SET status='completed',completed_at=COALESCE(completed_at,NOW()) WHERE gap_id=? AND status IN ('available','in_progress','locked')")->execute([$gapId]);$this->pdo->prepare("UPDATE teacher_interventions SET status=IF(status IN ('recommended','planned','in_progress','monitoring'),'completed',status),outcome=COALESCE(outcome,'resolved'),completed_at=COALESCE(completed_at,NOW()),mastery_after=? WHERE gap_id=?")->execute([$check['mastery_score'],$gapId]);$this->pdo->prepare("UPDATE adaptive_learning_cycles SET status='resolved',completed_at=COALESCE(completed_at,NOW()) WHERE gap_id=? AND status<>'closed'")->execute([$gapId]);$this->pdo->commit();$this->refreshProfile((int)$g['student_id'],(int)$g['course_id']);}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        $this->audit->record('gap_resolved',['student_id'=>$g['student_id'],'course_id'=>$g['course_id'],'gap_id'=>$gapId,'actor_id'=>$actor,'actor_type'=>$actor?'teacher':'system','entity_type'=>'gap','entity_id'=>$gapId],'Gap resolved via '.$type,['mastery'=>$check['mastery_score'],'reason'=>$reason],$key.'-audit');
    }
    private function ensureDecisionStep(array $c,int $decisionId):void{$type=in_array($c['recommendation'],['monitor','advance'],true)?'mastery_check':($c['recommendation']==='retry_remediation'?'remediation':'additional_practice');$title=match($c['recommendation']){'monitor','advance'=>'พร้อมตรวจความเข้าใจอีกครั้ง','retry_remediation'=>'ทบทวน '.$c['topic_name'].' อีกครั้ง',default=>'ฝึกเพิ่มเติม: '.$c['topic_name']};$g=$this->gap((int)$c['gap_id']);$this->pdo->prepare("INSERT INTO adaptive_roadmap_steps(idempotency_key,student_id,course_id,gap_id,step_type,title,topic_name,status,is_blocking,sort_order) VALUES(?,?,?,?,?,?,?,'available',?,30) ON DUPLICATE KEY UPDATE id=id")->execute(['decision-step-'.$decisionId,$c['student_id'],$c['course_id'],$c['gap_id'],$type,$title,$c['topic_name'],$g['blocking_mode']==='blocking'?1:0]);}
    private function refreshProfile(int $sid,int $cid):void{$cfg=(new AdaptiveConfigService($this->pdo))->get();$topics=$this->pdo->prepare('SELECT topic_name,mastery_score FROM topic_mastery WHERE student_id=? AND course_id=?');$topics->execute([$sid,$cid]);$mastered=[];$developing=[];$strengths=[];$scores=[];foreach($topics->fetchAll(PDO::FETCH_ASSOC)as$t){$score=(float)$t['mastery_score'];$scores[]=$score;if($score>=$cfg['mastery_threshold']){$mastered[]=$t['topic_name'];$strengths[]=['topic'=>$t['topic_name'],'score'=>$score];}elseif($score>=$cfg['developing_threshold'])$developing[]=$t['topic_name'];}$g=$this->pdo->prepare("SELECT topic_name FROM student_learning_gaps WHERE student_id=? AND course_id=? AND status<>'resolved'");$g->execute([$sid,$cid]);$i=$this->pdo->prepare('SELECT id,recommended_action,status,outcome,completed_at,duration_minutes,mastery_before,mastery_after FROM teacher_interventions WHERE student_id=? AND course_id=? ORDER BY created_at DESC LIMIT 20');$i->execute([$sid,$cid]);$avg=$scores?array_sum($scores)/count($scores):0;$goal=$this->pdo->prepare("SELECT target_score FROM learning_goals WHERE student_id=? AND course_id=? AND status='active' ORDER BY priority,id LIMIT 1");$goal->execute([$sid,$cid]);$target=$goal->fetchColumn();$goalStatus=$target===false?'tracking_readiness':($avg>=(float)$target?'on_track':($avg>=(float)$target-10?'progressing':'needs_focus'));$this->pdo->prepare('UPDATE student_learning_profiles SET strengths_json=?,mastered_topics_json=?,developing_skills_json=?,needs_improvement_json=?,intervention_history_json=?,goal_progress_status=?,updated_at=NOW() WHERE student_id=? AND course_id=?')->execute([json_encode($strengths,JSON_UNESCAPED_UNICODE),json_encode($mastered,JSON_UNESCAPED_UNICODE),json_encode($developing,JSON_UNESCAPED_UNICODE),json_encode($g->fetchAll(PDO::FETCH_COLUMN),JSON_UNESCAPED_UNICODE),json_encode($i->fetchAll(PDO::FETCH_ASSOC),JSON_UNESCAPED_UNICODE),$goalStatus,$sid,$cid]);}
    private function gap(int$id):array{$s=$this->pdo->prepare('SELECT * FROM student_learning_gaps WHERE id=?');$s->execute([$id]);$g=$s->fetch(PDO::FETCH_ASSOC);if(!$g)throw new RuntimeException('Learning gap not found');return$g;}
}

final class InterventionService
{
    private LearningAuditService $audit;
    public function __construct(private PDO $pdo)
    {
        ensurePhase5Schema($this->pdo);
        $this->audit = new LearningAuditService($pdo);
    }
    public function ensureRecommended(int $gapId,array $check,int $decisionId,?int $teacherId=null,string $trigger='system_recommendation'): array
    {
        $active=$this->pdo->prepare("SELECT * FROM teacher_interventions WHERE gap_id=? AND status IN ('recommended','planned','in_progress','monitoring') ORDER BY id DESC LIMIT 1");$active->execute([$gapId]);if($row=$active->fetch(PDO::FETCH_ASSOC))return['created'=>false,'duplicate'=>true,'intervention'=>$row];$gap=$this->gap($gapId);$priority=min(100,40+(['low'=>5,'moderate'=>15,'high'=>30,'critical'=>45][$gap['severity']]??10)+($check['trend']==='declining'?15:0)+min(20,$gap['cycle_number']*5));$action=$check['mastery_score']<50?'prerequisite_review':'one_on_one';
        $this->pdo->prepare("INSERT INTO teacher_interventions(student_id,course_id,gap_id,topic_name,skill_name,trigger_type,severity,priority_score,recommended_action,assigned_teacher_id,status,mastery_before,override_decision_id) VALUES(?,?,?,?,?,?,?,?,?,?, 'recommended',?,?)")->execute([$gap['student_id'],$gap['course_id'],$gapId,$gap['topic_name'],$gap['skill_name'],$trigger,$gap['severity'],$priority,$action,$teacherId,$check['mastery_score'],$decisionId]);$id=(int)$this->pdo->lastInsertId();$this->pdo->prepare('UPDATE student_learning_gaps SET intervention_count=intervention_count+1 WHERE id=?')->execute([$gapId]);$this->pdo->prepare("INSERT INTO adaptive_learning_cycles(gap_id,student_id,cycle_number,intervention_id,status) VALUES(?,?,?,?,'support') ON DUPLICATE KEY UPDATE intervention_id=VALUES(intervention_id),status='support'")->execute([$gapId,$gap['student_id'],(int)($gap['cycle_number']??1),$id]);$this->step('intervention-'.$id,$gap,$id,'teacher_review','ครูเตรียมแผนทบทวน '.$gap['topic_name'],'available');$this->syncProfileHistory((int)$gap['student_id'],(int)$gap['course_id']);$this->audit->record('intervention_created',['student_id'=>$gap['student_id'],'course_id'=>$gap['course_id'],'gap_id'=>$gapId,'actor_id'=>$teacherId,'actor_type'=>$teacherId?'teacher':'system','entity_type'=>'intervention','entity_id'=>$id],'Teacher intervention recommended',['mastery'=>$check['mastery_score'],'reason'=>$check['rationale']??[]],'intervention-created-'.$id);return['created'=>true,'duplicate'=>false,'intervention_id'=>$id];
    }
    public function createManual(array $p,int $teacherId): array
    {
        $gapId=(int)($p['gap_id']??0);$gap=$gapId?$this->gap($gapId):null;$sid=(int)($p['student_id']??$gap['student_id']??0);$cid=(int)($p['course_id']??$gap['course_id']??0);$topic=trim((string)($p['topic_name']??$gap['topic_name']??''));if(!$sid||!$cid||$topic==='')throw new InvalidArgumentException('Student, course and topic are required');if($gapId){$active=$this->pdo->prepare("SELECT * FROM teacher_interventions WHERE gap_id=? AND status IN ('recommended','planned','in_progress','monitoring') ORDER BY id DESC LIMIT 1");$active->execute([$gapId]);if($row=$active->fetch(PDO::FETCH_ASSOC))return['created'=>false,'duplicate'=>true,'intervention'=>$row];}$action=(string)($p['recommended_action']??'custom');$valid=['reteach_in_class','small_group','one_on_one','extra_practice','prerequisite_review','teacher_feedback','makeup_class','onsite_support','online_support','custom'];if(!in_array($action,$valid,true))$action='custom';$trigger=(string)($p['trigger_type']??'teacher_manual');if(!in_array($trigger,['teacher_manual','live_class_followup','regression','prerequisite_gap'],true))$trigger='teacher_manual';$this->pdo->prepare("INSERT INTO teacher_interventions(student_id,course_id,gap_id,topic_name,skill_name,trigger_type,severity,priority_score,recommended_action,assigned_teacher_id,status,teacher_notes,mastery_before) VALUES(?,?,?,?,?,?,?,?,?,?, 'recommended',?,?)")->execute([$sid,$cid,$gapId?:null,$topic,$p['skill_name']??$gap['skill_name']??null,$trigger,$p['severity']??$gap['severity']??'moderate',(float)($p['priority_score']??60),$action,$teacherId,$p['teacher_notes']??null,$gap['mastery_score']??null]);$id=(int)$this->pdo->lastInsertId();if($gap){$this->pdo->prepare('UPDATE student_learning_gaps SET intervention_count=intervention_count+1 WHERE id=?')->execute([$gapId]);$this->pdo->prepare("INSERT INTO adaptive_learning_cycles(gap_id,student_id,cycle_number,intervention_id,status) VALUES(?,?,?,?,'support') ON DUPLICATE KEY UPDATE intervention_id=VALUES(intervention_id),status='support'")->execute([$gapId,$sid,(int)($gap['cycle_number']??1),$id]);}$this->step('intervention-'.$id,$gap?:['student_id'=>$sid,'course_id'=>$cid,'id'=>null,'topic_name'=>$topic,'blocking_mode'=>'non_blocking'],$id,'teacher_review','ครูเตรียมแผนทบทวน '.$topic,'available');$this->syncProfileHistory($sid,$cid);$this->audit->record('intervention_created',['student_id'=>$sid,'course_id'=>$cid,'gap_id'=>$gapId?:null,'actor_id'=>$teacherId,'actor_type'=>'teacher','entity_type'=>'intervention','entity_id'=>$id],'Teacher created a support intervention',['notes'=>$p['teacher_notes']??null,'trigger_type'=>$trigger],'intervention-created-'.$id);return['created'=>true,'duplicate'=>false,'intervention_id'=>$id];
    }
    public function schedule(int $interventionId,array $p,int $teacherId): array
    {
        $i=$this->get($interventionId);$date=(string)($p['event_date']??'');$start=(string)($p['start_time']??'');$duration=max(10,min(240,(int)($p['duration_minutes']??20)));if(!$date||!$start)throw new InvalidArgumentException('Date and start time are required');$startAt=new DateTimeImmutable($date.' '.$start);$end=$startAt->modify('+'.$duration.' minutes')->format('H:i:s');$title=trim((string)($p['title']??''))?:'คลาสทบทวน '.$i['topic_name'];$mode=(string)($p['mode']??'online');$this->pdo->beginTransaction();
        try{$this->pdo->prepare("INSERT INTO calendar_events(title,event_type,course_id,teacher_id,event_date,start_time,end_time,location,notes,color,status,created_by) VALUES(?,'meeting',?,?,?,?,?,?,?,'#8b5cf6','scheduled',?)")->execute([$title,$i['course_id'],$teacherId,$date,$startAt->format('H:i:s'),$end,$mode==='onsite'?($p['location']??'On-site'):'Online','Intervention #'.$interventionId,$teacherId]);$eventId=(int)$this->pdo->lastInsertId();$sessionId='intv_'.$interventionId.'_'.bin2hex(random_bytes(4));$pin=$this->uniquePin();$this->pdo->prepare("INSERT INTO classroom_sessions(id,teacher_id,calendar_event_id,title,session_pin,status,has_time_limit,time_limit_minutes,education_stage,intervention_id,gap_id) VALUES(?,?,?,?,?,'active',1,?,'support',?,?)")->execute([$sessionId,$teacherId,$eventId,$title,$pin,$duration,$interventionId,$i['gap_id']]);$this->pdo->prepare('UPDATE calendar_events SET session_id=? WHERE id=?')->execute([$sessionId,$eventId]);$this->pdo->prepare('INSERT INTO session_participants(id,session_id,student_id,status) VALUES(?,?,?,\'joined\')')->execute(['sp_'.$sessionId.'_'.$i['student_id'],$sessionId,$i['student_id']]);$this->pdo->prepare('INSERT INTO session_topics(session_id,calendar_event_id,topic_name,sort_order) VALUES(?,?,?,0)')->execute([$sessionId,$eventId,$i['topic_name']]);$this->pdo->prepare("UPDATE teacher_interventions SET assigned_teacher_id=?,status='planned',calendar_event_id=?,scheduled_session_id=?,duration_minutes=?,teacher_notes=CONCAT_WS('\n',teacher_notes,?) WHERE id=?")->execute([$teacherId,$eventId,$sessionId,$duration,$p['teacher_notes']??null,$interventionId]);$this->pdo->prepare("UPDATE adaptive_roadmap_steps SET step_type='teacher_session',title=?,status='available',action_url=?,due_at=? WHERE idempotency_key=?")->execute([$title,'live-session.php?pin='.$pin,$startAt->format('Y-m-d H:i:s'),'intervention-'.$interventionId]);$this->pdo->commit();}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        $this->audit->record('intervention_scheduled',['student_id'=>$i['student_id'],'course_id'=>$i['course_id'],'gap_id'=>$i['gap_id'],'actor_id'=>$teacherId,'actor_type'=>'teacher','entity_type'=>'intervention','entity_id'=>$interventionId],$title,['calendar_event_id'=>$eventId,'session_id'=>$sessionId,'duration'=>$duration],'intervention-scheduled-'.$interventionId);return['scheduled'=>true,'calendar_event_id'=>$eventId,'session_id'=>$sessionId,'session_pin'=>$pin];
    }
    public function updateOutcome(int $id,array $p,int $teacherId): array{$i=$this->get($id);$status=(string)($p['status']??'completed');$allowed=['recommended','planned','in_progress','completed','cancelled','monitoring'];if(!in_array($status,$allowed,true))throw new InvalidArgumentException('Invalid intervention status');$outcome=$p['outcome']??null;$this->pdo->prepare('UPDATE teacher_interventions SET status=?,outcome=?,teacher_notes=CONCAT_WS(\'\n\',teacher_notes,?),started_at=IF(?=\'in_progress\',COALESCE(started_at,NOW()),started_at),completed_at=IF(?=\'completed\',COALESCE(completed_at,NOW()),completed_at) WHERE id=?')->execute([$status,$outcome,$p['teacher_notes']??null,$status,$status,$id]);if($status==='completed')$this->pdo->prepare("UPDATE adaptive_roadmap_steps SET status='completed',completed_at=NOW() WHERE intervention_id=?")->execute([$id]);if($outcome==='resolved'&&!empty($p['resolution_reason']))(new LearningDecisionService($this->pdo))->manualResolve((int)$i['gap_id'],$teacherId,(string)$p['resolution_reason']);$this->syncProfileHistory((int)$i['student_id'],(int)$i['course_id']);$this->audit->record('intervention_outcome',['student_id'=>$i['student_id'],'course_id'=>$i['course_id'],'gap_id'=>$i['gap_id'],'actor_id'=>$teacherId,'actor_type'=>'teacher','entity_type'=>'intervention','entity_id'=>$id],'Intervention updated: '.$status,['outcome'=>$outcome,'notes'=>$p['teacher_notes']??null],'intervention-outcome-'.$id.'-'.$status.'-'.hash('sha256',(string)$outcome));return['updated'=>true,'intervention_id'=>$id];}
    public function queue(array $filters=[]): array
    {
        try {
            $where=["i.status<>'cancelled'"];$params=[];
            if(!empty($filters['teacher_id'])){$where[]='(i.assigned_teacher_id=? OR i.assigned_teacher_id IS NULL)';$params[]=(int)$filters['teacher_id'];}
            if(!empty($filters['course_id'])){$where[]='i.course_id=?';$params[]=(int)$filters['course_id'];}
            if(!empty($filters['status'])){$where[]='i.status=?';$params[]=$filters['status'];}
            if(!empty($filters['severity'])){$where[]='i.severity=?';$params[]=$filters['severity'];}
            $sql="SELECT i.*,CONCAT_WS(' ',u.first_name,u.last_name) student_name,c.title course_title,g.confidence gap_confidence,g.evidence_count,(SELECT COUNT(*) FROM personalized_remediations r WHERE r.gap_id=i.gap_id AND r.status='completed') remediation_attempts FROM teacher_interventions i JOIN users u ON u.id=i.student_id JOIN courses c ON c.id=i.course_id LEFT JOIN student_learning_gaps g ON g.id=i.gap_id WHERE ".implode(' AND ',$where)." ORDER BY FIELD(i.status,'recommended','planned','in_progress','monitoring','completed'),i.priority_score DESC,i.created_at ASC LIMIT 200";
            $s=$this->pdo->prepare($sql);
            $s->execute($params);
            return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('InterventionService::queue error: ' . $e->getMessage());
            return [];
        }
    }
    public function get(int$id):array{$s=$this->pdo->prepare("SELECT i.*,CONCAT_WS(' ',u.first_name,u.last_name) student_name,c.title course_title,g.mastery_score,g.evidence_count,g.confidence gap_confidence,g.trend,g.blocking_mode FROM teacher_interventions i JOIN users u ON u.id=i.student_id JOIN courses c ON c.id=i.course_id LEFT JOIN student_learning_gaps g ON g.id=i.gap_id WHERE i.id=?");$s->execute([$id]);$i=$s->fetch(PDO::FETCH_ASSOC);if(!$i)throw new RuntimeException('Intervention not found');return$i;}
    private function gap(int$id):array{$s=$this->pdo->prepare('SELECT * FROM student_learning_gaps WHERE id=?');$s->execute([$id]);$g=$s->fetch(PDO::FETCH_ASSOC);if(!$g)throw new RuntimeException('Learning gap not found');return$g;}
    private function uniquePin():string{do{$pin=(string)random_int(100000,999999);$s=$this->pdo->prepare('SELECT 1 FROM classroom_sessions WHERE session_pin=?');$s->execute([$pin]);}while($s->fetchColumn());return$pin;}
    private function step(string$key,array$g,int$id,string$type,string$title,string$status):void{$this->pdo->prepare('INSERT INTO adaptive_roadmap_steps(idempotency_key,student_id,course_id,gap_id,intervention_id,step_type,title,topic_name,status,is_blocking,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,40) ON DUPLICATE KEY UPDATE id=id')->execute([$key,$g['student_id'],$g['course_id'],$g['id'],$id,$type,$title,$g['topic_name'],$status,$g['blocking_mode']==='blocking'?1:0]);}
    private function syncProfileHistory(int$sid,int$cid):void{$s=$this->pdo->prepare('SELECT id,recommended_action,status,outcome,duration_minutes,mastery_before,mastery_after,teacher_notes,completed_at FROM teacher_interventions WHERE student_id=? AND course_id=? ORDER BY created_at DESC LIMIT 20');$s->execute([$sid,$cid]);$this->pdo->prepare('UPDATE student_learning_profiles SET intervention_history_json=?,updated_at=NOW() WHERE student_id=? AND course_id=?')->execute([json_encode($s->fetchAll(PDO::FETCH_ASSOC),JSON_UNESCAPED_UNICODE),$sid,$cid]);}
}
