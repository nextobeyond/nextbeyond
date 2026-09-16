-- Nextbeyond Compass Database Schema
-- Character Set: utf8mb4

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Users & Auth
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `nickname` VARCHAR(100),
    `phone` VARCHAR(20),
    `role` ENUM('student','teacher','admin') DEFAULT 'student',
    `avatar_url` VARCHAR(500),
    `pdpa_consent` TINYINT(1) DEFAULT 0,
    `pdpa_consent_at` DATETIME,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `parent_info` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `parent_name` VARCHAR(200),
    `parent_phone` VARCHAR(20),
    `parent_email` VARCHAR(255),
    `relationship` VARCHAR(50),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Courses
CREATE TABLE IF NOT EXISTS `courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(300) NOT NULL,
    `slug` VARCHAR(300) UNIQUE,
    `subject` VARCHAR(100),
    `level` VARCHAR(50),
    `description` TEXT,
    `cover_image` VARCHAR(500),
    `price` DECIMAL(10,2) DEFAULT 0,
    `original_price` DECIMAL(10,2),
    `duration_hours` INT,
    `duration_weeks` INT,
    `max_students` INT,
    `teacher_id` INT,
    `status` ENUM('draft','active','archived') DEFAULT 'draft',
    `is_free` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lessons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_id` INT NOT NULL,
    `title` VARCHAR(300) NOT NULL,
    `sort_order` INT DEFAULT 0,
    `content_type` ENUM('video','document','quiz','live'),
    `content_url` VARCHAR(500),
    `duration_minutes` INT,
    `is_preview` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `enrollments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `enrolled_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME,
    `progress_percent` DECIMAL(5,2) DEFAULT 0,
    `status` ENUM('active','completed','expired') DEFAULT 'active',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calendar_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(300) NOT NULL,
    `event_type` ENUM('lesson','exam','meeting','other') NOT NULL DEFAULT 'lesson',
    `course_id` INT,
    `teacher_id` INT NOT NULL,
    `event_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `location` VARCHAR(255),
    `notes` TEXT,
    `color` VARCHAR(20) NOT NULL DEFAULT '#2563eb',
    `status` ENUM('scheduled','cancelled') NOT NULL DEFAULT 'scheduled',
    `created_by` INT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_calendar_date` (`event_date`),
    INDEX `idx_calendar_teacher_date` (`teacher_id`, `event_date`),
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Exams & Placement Tests
CREATE TABLE IF NOT EXISTS `exams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(300) NOT NULL,
    `subject` VARCHAR(100),
    `grade` VARCHAR(50),
    `topic` VARCHAR(300),
    `difficulty` VARCHAR(50),
    `type` ENUM('draft','pretest','posttest','quiz','placement') DEFAULT 'draft',
    `time_limit_minutes` INT,
    `is_ai_generated` TINYINT(1) DEFAULT 0,
    `generation_request_id` VARCHAR(36),
    `source_url` VARCHAR(1000),
    `source_mode` VARCHAR(20) NOT NULL DEFAULT 'document',
    `generation_mode` ENUM('copy','similar','levels'),
    `created_by` INT,
    `status` ENUM('draft','active','closed','archived') DEFAULT 'draft',
    `is_published` TINYINT(1) DEFAULT 0,
    `requires_login` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_exams_generation_request` (`generation_request_id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `exam_questions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `exam_id` INT NOT NULL,
    `sort_order` INT DEFAULT 0,
    `question_text` TEXT NOT NULL,
    `passage` TEXT,
    `options` JSON NOT NULL,
    `correct_answer` INT NOT NULL,
    `explanation` TEXT,
    `skill` VARCHAR(100),
    FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `test_attempts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `exam_id` INT NOT NULL,
    `score` DECIMAL(5,2),
    `correct_count` INT,
    `total_questions` INT,
    `time_spent_seconds` INT,
    `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `test_answers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `attempt_id` INT NOT NULL,
    `question_id` INT NOT NULL,
    `selected_answer` INT,
    `is_correct` TINYINT(1),
    FOREIGN KEY (`attempt_id`) REFERENCES `test_attempts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `exam_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Orders
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(30) UNIQUE NOT NULL,
    `user_id` INT NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `discount_amount` DECIMAL(10,2) DEFAULT 0,
    `net_amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('bank_transfer','promptpay','credit_card'),
    `payment_slip_url` VARCHAR(500),
    `status` ENUM('pending','confirmed','cancelled','refunded') DEFAULT 'pending',
    `confirmed_by` INT,
    `confirmed_at` DATETIME,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`confirmed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `price` DECIMAL(10,2),
    `quantity` INT DEFAULT 1,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Articles
CREATE TABLE IF NOT EXISTS `articles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(500) NOT NULL,
    `slug` VARCHAR(500) UNIQUE,
    `excerpt` TEXT,
    `content` LONGTEXT,
    `cover_image` VARCHAR(500),
    `category` VARCHAR(100),
    `author_id` INT,
    `view_count` INT DEFAULT 0,
    `read_time_minutes` INT,
    `is_published` TINYINT(1) DEFAULT 0,
    `published_at` DATETIME,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `article_tags` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `article_id` INT NOT NULL,
    `tag` VARCHAR(100),
    FOREIGN KEY (`article_id`) REFERENCES `articles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Free Learning
CREATE TABLE IF NOT EXISTS `free_contents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(300) NOT NULL,
    `description` TEXT,
    `content_type` ENUM('video','article','exercise'),
    `content_url` VARCHAR(500),
    `subject` VARCHAR(100),
    `level` VARCHAR(50),
    `duration_minutes` INT,
    `cover_gradient` VARCHAR(100),
    `sort_order` INT DEFAULT 0,
    `is_published` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `free_content_tags` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `content_id` INT NOT NULL,
    `tag` VARCHAR(100),
    FOREIGN KEY (`content_id`) REFERENCES `free_contents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Learning Path
CREATE TABLE IF NOT EXISTS `learning_paths` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `subject` VARCHAR(100),
    `current_level` VARCHAR(50),
    `target_goal` VARCHAR(200),
    `hours_per_week` INT,
    `plan_data` JSON,
    `progress_percent` DECIMAL(5,2) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` MEDIUMTEXT NOT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
