-- NEXTBEYOND V2 — Phase 4: Adaptive Learning Engine
-- Additive and repeatable migration. Existing exam/worksheet snapshots remain authoritative
-- for historical attempts; canonical links are backfilled without deleting or rewriting them.

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

CREATE TABLE IF NOT EXISTS `question_exposures` (
    `question_id` BIGINT UNSIGNED NOT NULL,
    `student_id` INT NOT NULL,
    `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `correct_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `incorrect_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_result` ENUM('correct','incorrect','unanswered') NOT NULL DEFAULT 'unanswered',
    PRIMARY KEY (`question_id`,`student_id`),
    KEY `idx_exposure_student_recent` (`student_id`,`last_seen_at`),
    KEY `idx_exposure_student_result` (`student_id`,`last_result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_learning_gaps` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `subject` VARCHAR(100) NULL,
    `domain_name` VARCHAR(150) NULL,
    `topic_id` INT NULL,
    `topic_name` VARCHAR(255) NOT NULL,
    `subtopic_id` INT NULL,
    `subtopic_name` VARCHAR(255) NULL,
    `skill_id` INT NULL,
    `skill_name` VARCHAR(150) NULL,
    `gap_score` DECIMAL(5,2) NOT NULL,
    `severity` ENUM('low','moderate','high','critical') NOT NULL,
    `confidence` ENUM('low','medium','high') NOT NULL,
    `evidence_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `recent_accuracy` DECIMAL(5,2) NULL,
    `mastery_score` DECIMAL(5,2) NULL,
    `trend` ENUM('improving','stable','declining') NOT NULL DEFAULT 'stable',
    `first_detected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_detected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_at` DATETIME NULL,
    `status` ENUM('open','monitoring','improving','resolved') NOT NULL DEFAULT 'open',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_gap_active_dimension` (`student_id`,`course_id`,`topic_name`,`skill_name`),
    KEY `idx_gap_student_status` (`student_id`,`status`,`severity`),
    KEY `idx_gap_course_status` (`course_id`,`status`,`topic_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personalized_remediations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `gap_id` BIGINT UNSIGNED NOT NULL,
    `worksheet_id` INT NULL,
    `assignment_id` INT NULL,
    `topic_name` VARCHAR(255) NOT NULL,
    `skill_name` VARCHAR(150) NULL,
    `source` VARCHAR(50) NOT NULL DEFAULT 'smart_retrieval',
    `question_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `seen_policy` ENUM('unseen_only','prefer_unseen','allow_repeat','retry_incorrect') NOT NULL DEFAULT 'prefer_unseen',
    `automation_policy` ENUM('manual','teacher_approved','auto_assign') NOT NULL DEFAULT 'teacher_approved',
    `status` ENUM('draft','assigned','in_progress','completed','cancelled') NOT NULL DEFAULT 'draft',
    `created_by` INT NULL,
    `assigned_at` DATETIME NULL,
    `due_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `pre_remediation_mastery` DECIMAL(5,2) NULL,
    `post_remediation_mastery` DECIMAL(5,2) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_remediation_student_status` (`student_id`,`status`),
    KEY `idx_remediation_gap_status` (`gap_id`,`status`),
    KEY `idx_remediation_assignment` (`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `remediation_questions` (
    `remediation_id` BIGINT UNSIGNED NOT NULL,
    `question_id` BIGINT UNSIGNED NOT NULL,
    `worksheet_question_id` INT NULL,
    `order_index` INT UNSIGNED NOT NULL,
    `selection_reason` VARCHAR(500) NULL,
    `was_seen_before` TINYINT(1) NOT NULL DEFAULT 0,
    `difficulty_at_assignment` VARCHAR(50) NULL,
    PRIMARY KEY (`remediation_id`,`question_id`),
    KEY `idx_remediation_order` (`remediation_id`,`order_index`),
    KEY `idx_remediation_question` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `question_search_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_id` INT NULL,
    `student_id` INT NULL,
    `gap_id` BIGINT UNSIGNED NULL,
    `purpose` VARCHAR(50) NOT NULL DEFAULT 'question_bank',
    `query_text` VARCHAR(500) NULL,
    `filters_json` TEXT NULL,
    `selected_question_ids_json` TEXT NULL,
    `event_type` ENUM('search','select','replace','assignment_created','provider_failure') NOT NULL DEFAULT 'search',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_search_log_actor` (`actor_id`,`created_at`),
    KEY `idx_search_log_gap` (`gap_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Run these ALTER statements once on MariaDB versions that do not support ADD COLUMN IF NOT EXISTS.
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='exam_questions' AND COLUMN_NAME='canonical_question_id'), 'SELECT 1', 'ALTER TABLE exam_questions ADD COLUMN canonical_question_id BIGINT UNSIGNED NULL AFTER exam_id, ADD INDEX idx_exam_canonical (canonical_question_id)');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='worksheet_questions' AND COLUMN_NAME='canonical_question_id'), 'SELECT 1', 'ALTER TABLE worksheet_questions ADD COLUMN canonical_question_id BIGINT UNSIGNED NULL AFTER source_question_id, ADD INDEX idx_ws_canonical (canonical_question_id)');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Phase 4 adds remediation as a learning-evidence source while retaining every old value.
ALTER TABLE `worksheet_assignments` MODIFY COLUMN `activity_type`
  ENUM('worksheet','practice','homework','posttest','remediation') NOT NULL DEFAULT 'worksheet';
ALTER TABLE `learning_evidence` MODIFY COLUMN `source_type`
  ENUM('diagnostic','get_ready','in_class_check','worksheet','practice','homework','posttest','mock_exam','remediation') NOT NULL;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_evidence' AND COLUMN_NAME='canonical_question_id'), 'SELECT 1', 'ALTER TABLE learning_evidence ADD COLUMN canonical_question_id BIGINT UNSIGNED NULL AFTER question_id, ADD COLUMN difficulty VARCHAR(50) NULL AFTER canonical_question_id, ADD COLUMN is_correct TINYINT(1) NULL AFTER difficulty, ADD INDEX idx_evidence_question (canonical_question_id,student_id,occurred_at)');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill canonical assets from exams. ON DUPLICATE makes this repeatable.
INSERT INTO question_bank_items (
    subject, grade_level, topic_name, subtopic_name, skill_name, learning_objective,
    question_type, question_text, choices_json, correct_answer_json, explanation,
    difficulty, tags_json, source_type, source_record_id, review_status, quality_score,
    visibility, created_by, status, index_status, created_at
)
SELECT e.subject, e.grade, e.topic, q.subtopic, q.skill, q.learning_objective,
       'multipleChoice', q.question_text, q.options, CAST(q.correct_answer AS CHAR), q.explanation,
       CASE WHEN q.difficulty IN ('easy','medium','hard','expert') THEN q.difficulty ELSE 'medium' END,
       q.tags, 'exam', q.id,
       CASE q.review_status WHEN 'approved' THEN 'approved' WHEN 'draft' THEN 'draft' WHEN 'rejected' THEN 'rejected' ELSE 'reviewed' END,
       CASE q.review_status WHEN 'approved' THEN 0.9000 WHEN 'reviewed' THEN 0.7500 ELSE 0.5000 END,
       'institution', e.created_by, IF(e.status='archived','archived','active'),
       IF(q.indexed_at IS NULL,'pending','indexed'), COALESCE(q.created_at,NOW())
FROM exam_questions q JOIN exams e ON e.id=q.exam_id
ON DUPLICATE KEY UPDATE id=question_bank_items.id;

UPDATE exam_questions q
JOIN question_bank_items b ON b.source_type='exam' AND b.source_record_id=q.id
SET q.canonical_question_id=b.id
WHERE q.canonical_question_id IS NULL;

-- Reused worksheet snapshots retain the exam canonical identity, including local overrides.
UPDATE worksheet_questions wq
JOIN exam_questions eq ON eq.id=wq.source_question_id
JOIN question_bank_items b ON b.source_type='exam' AND b.source_record_id=eq.id
SET wq.canonical_question_id=b.id;

-- Archive any obsolete standalone canonical row created by an earlier partial migration.
UPDATE question_bank_items old_item
JOIN worksheet_questions wq ON old_item.source_type='worksheet' AND old_item.source_record_id=wq.id
JOIN exam_questions eq ON eq.id=wq.source_question_id
SET old_item.status='archived',old_item.review_status='archived',old_item.index_status='pending';

-- Worksheet-local questions become canonical only when they are not already linked.
INSERT INTO question_bank_items (
    subject, grade_level, topic_name, skill_name, learning_objective, question_type,
    question_text, choices_json, correct_answer_json, explanation, hint, difficulty,
    tags_json, source_type, source_record_id, review_status, quality_score, visibility,
    created_by, status, index_status, created_at
)
SELECT w.subject, w.level, w.topic, q.skill, q.learning_objective, q.question_type,
       q.question_text, q.options, JSON_QUOTE(COALESCE(q.correct_answer,'')), q.explanation, q.hint,
       CASE WHEN q.difficulty IN ('easy','medium','hard','expert') THEN q.difficulty ELSE 'medium' END,
       w.tags, 'worksheet', q.id,
       IF(w.generation_source='ai','ai_generated','reviewed'), 0.6000,
       IF(w.creator_id IS NULL,'institution','private'), w.creator_id,
       IF(w.status='archived','archived','active'), 'pending', q.created_at
FROM worksheet_questions q JOIN worksheets w ON w.id=q.worksheet_id
WHERE q.canonical_question_id IS NULL
ON DUPLICATE KEY UPDATE id=question_bank_items.id;

UPDATE worksheet_questions q
JOIN question_bank_items b ON b.source_type='worksheet' AND b.source_record_id=q.id
SET q.canonical_question_id=b.id
WHERE q.canonical_question_id IS NULL;
