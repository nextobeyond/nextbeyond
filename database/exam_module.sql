-- Nextbeyond Compass: AI Exam + Test Results module
-- Import this file after selecting the database you want to use.
-- Compatible with the existing database/schema.sql (`users.id` is INT).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `role` ENUM('student','teacher','admin') NOT NULL DEFAULT 'student',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role_active` (`role`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `exams` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(300) NOT NULL,
    `subject` VARCHAR(100) NULL,
    `grade` VARCHAR(50) NULL,
    `topic` VARCHAR(300) NULL,
    `difficulty` VARCHAR(50) NULL,
    `type` ENUM('pretest','posttest','quiz','placement') NOT NULL DEFAULT 'quiz',
    `time_limit_minutes` SMALLINT UNSIGNED NULL,
    `is_ai_generated` TINYINT(1) NOT NULL DEFAULT 0,
    `generation_request_id` VARCHAR(36) NULL,
    `source_url` VARCHAR(1000) NULL,
    `source_mode` VARCHAR(20) NOT NULL DEFAULT 'document',
    `generation_mode` ENUM('copy','similar','levels') NULL,
    `created_by` INT NULL,
    `status` ENUM('draft','active','closed','archived') NOT NULL DEFAULT 'draft',
    `is_published` TINYINT(1) NOT NULL DEFAULT 0,
    `requires_login` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_exams_generation_request` (`generation_request_id`),
    KEY `idx_exams_public_list` (`is_published`, `status`, `type`),
    KEY `idx_exams_created_by` (`created_by`),
    CONSTRAINT `fk_exams_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `exam_questions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `exam_id` INT NOT NULL,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `question_text` TEXT NOT NULL,
    `passage` MEDIUMTEXT NULL,
    `options` JSON NOT NULL,
    `correct_answer` SMALLINT UNSIGNED NOT NULL,
    `explanation` TEXT NULL,
    `skill` VARCHAR(100) NULL,
    `difficulty` VARCHAR(50) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_exam_question_order` (`exam_id`, `sort_order`),
    CONSTRAINT `fk_exam_questions_exam`
        FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `test_attempts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT NULL,
    `exam_id` INT NOT NULL,
    `guest_token` CHAR(36) NULL,
    `score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `correct_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `total_questions` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `time_spent_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_attempts_user_history` (`user_id`, `completed_at`),
    KEY `idx_attempts_exam` (`exam_id`, `completed_at`),
    KEY `idx_attempts_guest` (`guest_token`),
    CONSTRAINT `fk_test_attempts_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_test_attempts_exam`
        FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_attempt_owner`
        CHECK (`user_id` IS NOT NULL OR `guest_token` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `test_answers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempt_id` BIGINT UNSIGNED NOT NULL,
    `question_id` INT NOT NULL,
    `selected_answer` SMALLINT UNSIGNED NULL,
    `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_attempt_question` (`attempt_id`, `question_id`),
    KEY `idx_test_answers_question` (`question_id`),
    CONSTRAINT `fk_test_answers_attempt`
        FOREIGN KEY (`attempt_id`) REFERENCES `test_attempts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_test_answers_question`
        FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Quick verification after import:
-- SHOW TABLES LIKE '%exam%';
-- DESCRIBE exams;
-- DESCRIBE exam_questions;
-- DESCRIBE test_attempts;
-- DESCRIBE test_answers;
