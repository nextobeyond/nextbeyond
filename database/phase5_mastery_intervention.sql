-- NEXTBEYOND V2 — Phase 5: Mastery & Human Intervention Loop
-- Additive/repeatable. Original roadmap, mastery, evidence, assignment and session data remains intact.

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
INSERT IGNORE INTO adaptive_learning_config (`id`) VALUES (1);

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

CREATE TABLE IF NOT EXISTS `mastery_check_questions` (
  `mastery_check_assignment_id` BIGINT UNSIGNED NOT NULL,
  `question_id` BIGINT UNSIGNED NOT NULL,
  `worksheet_question_id` INT NOT NULL,
  `order_index` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`mastery_check_assignment_id`,`question_id`),
  KEY `idx_mcq_worksheet_question` (`worksheet_question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Repeatable additive columns.
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_learning_gaps' AND COLUMN_NAME='blocking_mode'),'SELECT 1',"ALTER TABLE student_learning_gaps ADD blocking_mode ENUM('blocking','non_blocking') NOT NULL DEFAULT 'non_blocking', ADD resolved_by INT NULL, ADD resolution_type VARCHAR(50) NULL, ADD final_mastery DECIMAL(5,2) NULL, ADD resolution_evidence_count INT UNSIGNED NULL, ADD intervention_count INT UNSIGNED NOT NULL DEFAULT 0, ADD cycle_number INT UNSIGNED NOT NULL DEFAULT 1, ADD last_mastery_status VARCHAR(50) NULL"); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_learning_gaps' AND COLUMN_NAME='blocking_overridden_by'),'SELECT 1',"ALTER TABLE student_learning_gaps ADD blocking_overridden_by INT NULL, ADD blocking_override_reason TEXT NULL"); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_learning_profiles' AND COLUMN_NAME='developing_skills_json'),'SELECT 1',"ALTER TABLE student_learning_profiles ADD developing_skills_json TEXT NULL, ADD mastered_topics_json TEXT NULL, ADD intervention_history_json TEXT NULL, ADD goal_progress_status VARCHAR(50) NULL"); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='classroom_sessions' AND COLUMN_NAME='intervention_id'),'SELECT 1',"ALTER TABLE classroom_sessions ADD intervention_id BIGINT UNSIGNED NULL, ADD gap_id BIGINT UNSIGNED NULL, ADD INDEX idx_session_intervention (intervention_id)"); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

ALTER TABLE `worksheet_assignments` MODIFY COLUMN `activity_type`
  ENUM('worksheet','practice','homework','posttest','remediation','mastery_check') NOT NULL DEFAULT 'worksheet';
ALTER TABLE `learning_evidence` MODIFY COLUMN `source_type`
  ENUM('diagnostic','get_ready','in_class_check','worksheet','practice','homework','posttest','mock_exam','remediation','mastery_check','teacher_assessment') NOT NULL;
