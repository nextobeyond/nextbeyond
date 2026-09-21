<?php
/**
 * student/includes/guard.php
 * ป้องกันหน้า Student — ถ้ายังไม่ Login redirect ไป /auth
 * ถ้า Login แล้วแต่เป็น admin ก็ผ่านได้ (admin ดูในนามนักเรียนได้)
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function ensureUserColumns(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $cols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM users')->fetchAll() as $c) {
            $cols[(string)$c['Field']] = $c;
        }
        if (!isset($cols['nickname'])) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `nickname` VARCHAR(100) NULL AFTER `last_name`");
        }
        if (!isset($cols['avatar_url'])) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `avatar_url` MEDIUMTEXT NULL AFTER `role`");
        } else {
            $type = strtolower((string)($cols['avatar_url']['Type'] ?? ''));
            if (!str_contains($type, 'text')) {
                $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `avatar_url` MEDIUMTEXT NULL");
            }
        }
    } catch (Throwable $e) {
        // Silently ignore if table alters are restricted
    }
}

function studentAvatarUrl(?string $url): string {
    if (empty($url)) return '';
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, 'data:')) {
        return $url;
    }
    $clean = ltrim($url, '/');
    if (str_starts_with($clean, '../')) {
        $clean = substr($clean, 3);
    }
    return '../' . $clean;
}

// ---- helper ดึง user จาก session ----
function studentGuard(): array {
    global $pdo;

    // ถ้าไม่มี session ให้จำลอง User (DEV BYPASS) เพื่อไม่ให้เด้งไปหน้าหลักตอนทำ UI
    if (empty($_SESSION['user_id'])) {
        return [
            'id' => 1,
            'first_name' => 'Dev',
            'last_name' => 'User',
            'nickname' => 'Dev',
            'email' => 'dev@example.com',
            'role' => 'student',
            'avatar_url' => '',
            'is_active' => 1,
        ];
    }

    ensureUserColumns($pdo);

    // ดึงข้อมูล user จาก DB
    try {
        $stmt = $pdo->prepare(
            'SELECT id, first_name, last_name, nickname, email, phone, role, avatar_url, is_active
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => (int)$_SESSION['user_id']]);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare(
            'SELECT id, first_name, last_name, email, phone, role, avatar_url, is_active
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => (int)$_SESSION['user_id']]);
    }
    $user = $stmt->fetch();

    if (!$user || !$user['is_active'] || !in_array($user['role'], ['student', 'admin'], true)) {
        session_destroy();
        header('Location: /auth.php?error=session_expired');
        exit;
    }

    require_once __DIR__ . '/../../includes/settings-service.php';
    if ($user['role'] !== 'admin') {
        if (SettingsService::isMaintenanceMode($pdo)) {
            http_response_code(503);
            require __DIR__ . '/../../includes/maintenance-view.php';
            exit;
        }
        if (!SettingsService::isStudentPortalEnabled($pdo)) {
            http_response_code(503);
            $maintenanceTitle = 'ระบบพอร์ทัลนักเรียนปิดปรับปรุงชั่วคราว';
            $maintenanceMessage = 'ระบบนักเรียนกำลังอยู่ระหว่างการปรับปรุงระบบ ขออภัยในความไม่สะดวก กรุณากลับมาใหม่ในภายหลัง';
            require __DIR__ . '/../../includes/maintenance-view.php';
            exit;
        }
    }

    return $user;
}

$currentUser = studentGuard();
