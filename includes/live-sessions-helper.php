<?php
declare(strict_types=1);

/**
 * includes/live-sessions-helper.php
 * Helper functions and schema initialization for Live Sessions
 */

function ensureLiveSessionSchema(PDO $pdo): void
{
    $pdo->exec("
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
            INDEX `idx_session_teacher` (`teacher_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
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
            INDEX `idx_sp_student` (`student_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

function getBossArchetypes(): array
{
    return [
        [
            'id' => 'dragon',
            'name' => 'มังกรเพลิงแห่งความรู้ ไครอส',
            'emoji' => '🐲',
            'category' => 'mythical',
            'description' => 'สัตว์อสูรเพลิงที่ท้าทายความรู้รอบตัวและการคิดวิเคราะห์',
            'color' => 'from-rose-500 to-red-600',
            'accent' => '#ef4444',
        ],
        [
            'id' => 'golem',
            'name' => 'โกเล็มหินแกร่งแห่งปัญญา',
            'emoji' => '🗿',
            'category' => 'elemental',
            'description' => 'ผู้พิทักษ์ศิลาโบราณ ต้องใช้ความแม่นยำเพื่อทะลวงการป้องกัน',
            'color' => 'from-amber-600 to-stone-700',
            'accent' => '#d97706',
        ],
        [
            'id' => 'phoenix',
            'name' => 'ฟีนิกซ์แห่งการฟื้นคืนและแสงสว่าง',
            'emoji' => '🔥',
            'category' => 'mythical',
            'description' => 'นกเพลิงอมตะที่ทวีพลังขึ้นเรื่อยๆ ตามความเร็วในการตอบ',
            'color' => 'from-orange-500 to-amber-500',
            'accent' => '#f97316',
        ],
        [
            'id' => 'shadow',
            'name' => 'เงามืดแห่งความสงสัย',
            'emoji' => '🌑',
            'category' => 'cosmic',
            'description' => 'ม่านหมอกที่บดบังความจริง สลายได้ด้วยคำตอบที่ถูกต้อง',
            'color' => 'from-purple-800 to-slate-900',
            'accent' => '#7e22ce',
        ],
        [
            'id' => 'lightning',
            'name' => 'เทพอัสนีบาต รามเสส',
            'emoji' => '⚡',
            'category' => 'elemental',
            'description' => 'สายฟ้าฟาดแห่งความเฉียบไว ยิ่งตอบเร็ว ยิ่งต้านทานได้ดี',
            'color' => 'from-yellow-500 to-amber-600',
            'accent' => '#eab308',
        ],
        [
            'id' => 'ocean',
            'name' => 'วาฬดึกดำบรรพ์ เลเวียธาน',
            'emoji' => '🌊',
            'category' => 'mythical',
            'description' => 'เจ้าแห่งมหาสมุทรลึก ผู้ทดสอบความอดทนและความรอบรู้',
            'color' => 'from-cyan-600 to-blue-700',
            'accent' => '#06b6d4',
        ],
    ];
}

function getBossArchetype(?string $theme): array
{
    $archetypes = getBossArchetypes();
    foreach ($archetypes as $arch) {
        if ($arch['id'] === $theme) {
            return $arch;
        }
    }
    return $archetypes[0];
}

function generateSessionPin(PDO $pdo): string
{
    for ($i = 0; $i < 20; $i++) {
        $pin = (string) random_int(100000, 999999);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM classroom_sessions WHERE session_pin = :pin AND status = 'active'");
        $stmt->execute([':pin' => $pin]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $pin;
        }
    }
    return (string) (time() % 900000 + 100000);
}

function closeOtherActiveSessions(PDO $pdo, int $teacherId, string $exceptSessionId = ''): void
{
    if ($exceptSessionId !== '') {
        $stmt = $pdo->prepare("UPDATE classroom_sessions SET status = 'closed', ended_at = NOW() WHERE teacher_id = :tid AND status = 'active' AND id != :exceptId");
        $stmt->execute([':tid' => $teacherId, ':exceptId' => $exceptSessionId]);
    } else {
        $stmt = $pdo->prepare("UPDATE classroom_sessions SET status = 'closed', ended_at = NOW() WHERE teacher_id = :tid AND status = 'active'");
        $stmt->execute([':tid' => $teacherId]);
    }
}
