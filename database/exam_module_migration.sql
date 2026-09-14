-- Run this only when `exams` and related tables already existed before
-- importing exam_module.sql. Designed for MariaDB / MySQL installations
-- that support ADD COLUMN IF NOT EXISTS.

SET NAMES utf8mb4;

ALTER TABLE `exams`
    MODIFY COLUMN `type` ENUM('pretest','posttest','quiz','placement') NOT NULL DEFAULT 'quiz',
    MODIFY COLUMN `status` ENUM('draft','active','closed','archived') NOT NULL DEFAULT 'draft',
    ADD COLUMN IF NOT EXISTS `generation_request_id` VARCHAR(36) NULL AFTER `is_ai_generated`,
    ADD COLUMN IF NOT EXISTS `source_url` VARCHAR(1000) NULL AFTER `is_ai_generated`,
    ADD COLUMN IF NOT EXISTS `source_mode` VARCHAR(20) NOT NULL DEFAULT 'document' AFTER `source_url`,
    ADD COLUMN IF NOT EXISTS `generation_mode` ENUM('copy','similar','levels') NULL AFTER `source_url`,
    ADD COLUMN IF NOT EXISTS `is_published` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
    ADD COLUMN IF NOT EXISTS `requires_login` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_published`;

ALTER TABLE `exam_questions`
    ADD COLUMN IF NOT EXISTS `difficulty` VARCHAR(50) NULL AFTER `skill`,
    ADD COLUMN IF NOT EXISTS `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `difficulty`;

ALTER TABLE `test_attempts`
    ADD COLUMN IF NOT EXISTS `guest_token` CHAR(36) NULL AFTER `exam_id`;

CREATE INDEX IF NOT EXISTS `idx_exams_public_list`
    ON `exams` (`is_published`, `status`, `type`);
CREATE UNIQUE INDEX IF NOT EXISTS `uq_exams_generation_request`
    ON `exams` (`generation_request_id`);
CREATE INDEX IF NOT EXISTS `idx_attempts_guest`
    ON `test_attempts` (`guest_token`);

-- Verify the required columns after migration:
-- SHOW COLUMNS FROM exams;
-- SHOW COLUMNS FROM exam_questions;
