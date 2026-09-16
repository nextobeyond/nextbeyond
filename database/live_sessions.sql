-- Nextbeyond Compass: Live Session Module Schema
-- Character Set: utf8mb4

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `classroom_sessions` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `teacher_id` INT NOT NULL,
    `classroom_id` INT NULL,
    `title` VARCHAR(300) NOT NULL,
    `session_pin` VARCHAR(10) NOT NULL UNIQUE,
    `assignment_id` INT NULL,
    `exam_id` INT NULL,
    `status` ENUM('active','closed') NOT NULL DEFAULT 'active',
    `allow_late_join` TINYINT(1) NOT NULL DEFAULT 1,
    `has_time_limit` TINYINT(1) NOT NULL DEFAULT 1,
    `time_limit_minutes` INT NULL DEFAULT 30,
    `education_stage` VARCHAR(50) NOT NULL DEFAULT 'university',
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ended_at` DATETIME NULL,
    `eyes_on_me_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `locked_student_ids` JSON NULL,
    `announcement_message` TEXT NULL,
    `boss_fight_active` TINYINT(1) NOT NULL DEFAULT 0,
    `boss_name` VARCHAR(255) NULL DEFAULT 'มังกรเพลิงแห่งความรู้ ไครอส',
    `boss_theme` VARCHAR(50) NULL DEFAULT 'dragon',
    `boss_current_hp` INT NOT NULL DEFAULT 100,
    `boss_max_hp` INT NOT NULL DEFAULT 100,
    `boss_reward_points` INT NOT NULL DEFAULT 50,
    `boss_defeated` TINYINT(1) NOT NULL DEFAULT 0,
    `boss_combat_log` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_session_pin` (`session_pin`),
    INDEX `idx_session_status` (`status`),
    INDEX `idx_session_teacher` (`teacher_id`),
    CONSTRAINT `fk_classroom_sessions_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `session_participants` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `session_id` VARCHAR(64) NOT NULL,
    `student_id` INT NOT NULL,
    `attempt_id` BIGINT UNSIGNED NULL,
    `status` ENUM('joined','in_progress','submitted') NOT NULL DEFAULT 'joined',
    `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_session_student` (`session_id`, `student_id`),
    INDEX `idx_sp_session` (`session_id`),
    INDEX `idx_sp_student` (`student_id`),
    CONSTRAINT `fk_sp_session` FOREIGN KEY (`session_id`) REFERENCES `classroom_sessions`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sp_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
