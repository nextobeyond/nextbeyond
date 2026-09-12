CREATE TABLE IF NOT EXISTS `ai_subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_name` VARCHAR(255) NOT NULL,
  `prompt_md` TEXT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ai_subjects` (`id`, `subject_name`, `prompt_md`, `is_active`, `sort_order`) VALUES
(1, 'คณิตศาสตร์ (Math)', '', 1, 1),
(2, 'วิทยาศาสตร์ (Science)', '', 1, 2),
(3, 'ภาษาอังกฤษ (English)', '', 1, 3),
(4, 'ภาษาไทย (Thai)', '', 1, 4),
(5, 'สังคมศึกษา (Social Studies)', '', 1, 5)
ON DUPLICATE KEY UPDATE `subject_name` = VALUES(`subject_name`);
