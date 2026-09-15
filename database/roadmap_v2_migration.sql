-- Nextbeyond Compass: Roadmap v2 Migration
-- Import this file to upgrade the roadmap system

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. ตาราง roadmaps (แยกจาก tasks)
CREATE TABLE IF NOT EXISTS `roadmaps` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `stage` ENUM('m1','m4','tcas') NOT NULL DEFAULT 'tcas',
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    `assignment_mode` ENUM('self_select','admin_assign') NOT NULL DEFAULT 'self_select',
    `created_by` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ปรับ roadmap_tasks ให้เชื่อมกับ roadmap + เพิ่มเงื่อนไข
ALTER TABLE `roadmap_tasks`
  ADD COLUMN `roadmap_id` INT NULL AFTER `id`,
  ADD COLUMN `completion_type` ENUM(
    'manual',           -- ติ๊กด้วยตัวเอง
    'complete_lesson',  -- เรียนบทเรียนที่กำหนดจนจบ
    'complete_course',  -- เรียนครบทั้งคอร์ส
    'submit_test',      -- ส่งข้อสอบ (แค่ส่ง)
    'pass_test',        -- สอบผ่านคะแนนขั้นต่ำ
    'attend_schedule',  -- เข้าเรียนตามตาราง
    'multi_condition'   -- ทำหลายเงื่อนไขให้ครบ
  ) NOT NULL DEFAULT 'manual' AFTER `category`,
  ADD COLUMN `ref_course_id` INT NULL AFTER `completion_type`,
  ADD COLUMN `ref_lesson_id` INT NULL AFTER `ref_course_id`,
  ADD COLUMN `ref_exam_id` INT NULL AFTER `ref_lesson_id`,
  ADD COLUMN `ref_event_id` INT NULL AFTER `ref_exam_id`,
  ADD COLUMN `pass_score` DECIMAL(5,2) NULL COMMENT 'คะแนนขั้นต่ำ เช่น 70.00' AFTER `ref_event_id`,
  ADD COLUMN `score_mode` ENUM('latest','best') NULL DEFAULT 'latest' AFTER `pass_score`,
  ADD COLUMN `is_required` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'ภารกิจบังคับ/เสริม' AFTER `score_mode`,
  ADD COLUMN `prerequisite_task_id` INT NULL COMMENT 'ต้องทำภารกิจนี้ก่อน' AFTER `is_required`,
  ADD COLUMN `week_number` INT NULL COMMENT 'สัปดาห์ที่' AFTER `prerequisite_task_id`,
  ADD COLUMN `due_offset_days` INT NULL COMMENT 'จำนวนวันนับจากวันเลือก Roadmap' AFTER `week_number`,
  ADD CONSTRAINT `fk_roadmap_task_roadmap` FOREIGN KEY (`roadmap_id`) REFERENCES `roadmaps`(`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_roadmap_task_course` FOREIGN KEY (`ref_course_id`) REFERENCES `courses`(`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_roadmap_task_lesson` FOREIGN KEY (`ref_lesson_id`) REFERENCES `lessons`(`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_roadmap_task_exam` FOREIGN KEY (`ref_exam_id`) REFERENCES `exams`(`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_roadmap_task_prereq` FOREIGN KEY (`prerequisite_task_id`) REFERENCES `roadmap_tasks`(`id`) ON DELETE SET NULL;

-- Migrate existing tasks to a default roadmap (optional, depending on business need)
-- We'll create one default roadmap per stage for existing tasks
INSERT INTO `roadmaps` (`title`, `description`, `stage`, `status`, `assignment_mode`)
SELECT CONCAT('Roadmap พื้นฐาน ', stage), 'Roadmap เริ่มต้นสำหรับนักเรียน', stage, 'published', 'self_select'
FROM (SELECT DISTINCT stage FROM roadmap_tasks) AS stages;

UPDATE `roadmap_tasks` t
JOIN `roadmaps` r ON r.stage = t.stage
SET t.roadmap_id = r.id
WHERE t.roadmap_id IS NULL;


-- 3. ตาราง multi_condition สำหรับภารกิจที่ต้องทำหลายเงื่อนไข
CREATE TABLE IF NOT EXISTS `roadmap_task_conditions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `task_id` INT NOT NULL,
    `condition_type` ENUM('complete_lesson','complete_course','submit_test','pass_test','attend_schedule') NOT NULL,
    `ref_course_id` INT NULL,
    `ref_lesson_id` INT NULL,
    `ref_exam_id` INT NULL,
    `ref_event_id` INT NULL,
    `pass_score` DECIMAL(5,2) NULL,
    `score_mode` ENUM('latest','best') NULL DEFAULT 'latest',
    `sort_order` INT NOT NULL DEFAULT 0,
    FOREIGN KEY (`task_id`) REFERENCES `roadmap_tasks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ปรับ roadmap_task_progress เพิ่มสถานะละเอียด
ALTER TABLE `roadmap_task_progress`
  ADD COLUMN `status` ENUM(
    'not_started',  -- ยังไม่เริ่ม
    'in_progress',  -- กำลังทำ
    'completed',    -- สำเร็จ
    'locked',       -- ล็อกอยู่
    'overdue',      -- เลยกำหนด
    'exempted'      -- ยกเว้นโดย Admin
  ) NOT NULL DEFAULT 'not_started' AFTER `is_completed`,
  ADD COLUMN `completion_source` ENUM('manual','lesson','test','admin','system') NULL AFTER `status`,
  ADD COLUMN `ref_attempt_id` BIGINT UNSIGNED NULL COMMENT 'อ้างอิง test_attempts.id' AFTER `completion_source`,
  ADD COLUMN `ref_lesson_progress_id` INT NULL COMMENT 'อ้างอิง lesson_progress.id' AFTER `ref_attempt_id`,
  ADD COLUMN `exempted_by` INT NULL AFTER `ref_lesson_progress_id`,
  ADD COLUMN `exempted_at` DATETIME NULL AFTER `exempted_by`;

UPDATE `roadmap_task_progress` SET `status` = 'completed', `completion_source` = 'manual' WHERE `is_completed` = 1;


-- 5. ตารางนักเรียนเลือก/ถูกมอบหมาย Roadmap
CREATE TABLE IF NOT EXISTS `roadmap_enrollments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `roadmap_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `enrolled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `assigned_by` INT NULL COMMENT 'NULL = นักเรียนเลือกเอง',
    `is_mandatory` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = บังคับ, 0 = แนะนำ/เลือกเอง',
    `progress_percent` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `status` ENUM('active','completed','dropped') NOT NULL DEFAULT 'active',
    UNIQUE KEY `uq_roadmap_user` (`roadmap_id`, `user_id`),
    FOREIGN KEY (`roadmap_id`) REFERENCES `roadmaps`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing progress to enrollments
INSERT IGNORE INTO `roadmap_enrollments` (`roadmap_id`, `user_id`, `enrolled_at`, `is_mandatory`)
SELECT DISTINCT t.roadmap_id, p.user_id, MIN(p.completed_at), 0
FROM `roadmap_task_progress` p
JOIN `roadmap_tasks` t ON t.id = p.task_id
WHERE t.roadmap_id IS NOT NULL AND p.completed_at IS NOT NULL
GROUP BY t.roadmap_id, p.user_id;


-- 6. ตารางมอบหมาย Roadmap เป็นกลุ่ม
CREATE TABLE IF NOT EXISTS `roadmap_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `roadmap_id` INT NOT NULL,
    `assignment_type` ENUM('all','stage','classroom','group','individual') NOT NULL,
    `target_value` VARCHAR(255) NULL COMMENT 'เช่น stage=m4, classroom=ม.4/1, user_id=123',
    `is_mandatory` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = บังคับ, 0 = แนะนำ',
    `assigned_by` INT NOT NULL,
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`roadmap_id`) REFERENCES `roadmaps`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. lesson_progress (สำหรับ tracking บทเรียน)
CREATE TABLE IF NOT EXISTS `lesson_progress` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `lesson_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_watched_at` DATETIME NULL,
    `watch_duration_seconds` INT NOT NULL DEFAULT 0,
    `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
    `completed_at` DATETIME NULL,
    UNIQUE KEY `uq_lesson_user` (`lesson_id`, `user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`lesson_id`) REFERENCES `lessons`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. NC Points Transactions
CREATE TABLE IF NOT EXISTS `nc_point_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `task_id` INT NULL,
    `roadmap_id` INT NULL,
    `points` INT NOT NULL,
    `transaction_type` ENUM('earn','revoke','admin_adjust') NOT NULL,
    `reason` VARCHAR(255) NULL,
    `created_by` INT NULL COMMENT 'NULL = ระบบ, ไม่ NULL = Admin',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`task_id`) REFERENCES `roadmap_tasks`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`roadmap_id`) REFERENCES `roadmaps`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Audit Log
CREATE TABLE IF NOT EXISTS `roadmap_audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `task_id` INT NULL,
    `roadmap_id` INT NULL,
    `action` VARCHAR(50) NOT NULL,
    `old_value` TEXT NULL,
    `new_value` TEXT NULL,
    `performed_by` INT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
