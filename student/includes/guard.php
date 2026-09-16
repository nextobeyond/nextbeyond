<?php
/**
 * student/includes/guard.php
 * ป้องกันหน้า Student — ถ้ายังไม่ Login redirect ไป /auth
 * ถ้า Login แล้วแต่เป็น admin ก็ผ่านได้ (admin ดูในนามนักเรียนได้)
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

session_start();

function ensureUserColumns(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $cols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM users')->fetchAll() as $c) {
            $cols[(string)$c['Field']] = true;
        }
        if (!isset($cols['nickname'])) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `nickname` VARCHAR(100) NULL AFTER `last_name`");
        }
        if (!isset($cols['avatar_url'])) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `avatar_url` VARCHAR(500) NULL AFTER `role`");
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

    // ถ้าไม่มี session → redirect ไป auth
    if (empty($_SESSION['user_id'])) {
        $back = urlencode($_SERVER['REQUEST_URI'] ?? '/student/');
        header('Location: /auth?redirect=' . $back);
        exit;
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
        header('Location: /auth?error=session_expired');
        exit;
    }

    return $user;
}

$currentUser = studentGuard();
