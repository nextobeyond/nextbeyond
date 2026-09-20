<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/includes/db.php';

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
    'path' => '/',
]);
session_start();

function authRespond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function authBody(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function ensureAuthSchema(PDO $pdo): void
{
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM users')->fetchAll() as $column) {
        $columns[(string) $column['Field']] = true;
    }
    $additions = [
        'nickname' => 'ADD COLUMN `nickname` VARCHAR(100) NULL AFTER `last_name`',
        'phone' => 'ADD COLUMN `phone` VARCHAR(20) NULL AFTER `last_name`',
        'avatar_url' => 'ADD COLUMN `avatar_url` MEDIUMTEXT NULL AFTER `role`',
        'pdpa_consent' => 'ADD COLUMN `pdpa_consent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `avatar_url`',
        'pdpa_consent_at' => 'ADD COLUMN `pdpa_consent_at` DATETIME NULL AFTER `pdpa_consent`',
    ];
    foreach ($additions as $name => $definition) {
        if (!isset($columns[$name])) {
            $pdo->exec("ALTER TABLE `users` {$definition}");
        }
    }
}

try {
    ensureAuthSchema($pdo);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
        $sessionUser = null;
        if (!empty($_SESSION['user_id'])) {
            $sessionStmt = $pdo->prepare('SELECT id, email, first_name, last_name, role, is_active FROM users WHERE id = :id LIMIT 1');
            $sessionStmt->execute([':id' => (int) $_SESSION['user_id']]);
            $user = $sessionStmt->fetch();
            if ($user && $user['is_active']) {
                $_SESSION['user_role'] = (string) $user['role'];
                $sessionUser = [
                    'id' => (int) $user['id'],
                    'email' => (string) $user['email'],
                    'name' => trim((string) $user['first_name'] . ' ' . (string) $user['last_name']),
                    'role' => (string) $user['role'],
                ];
            } else {
                unset($_SESSION['user_id'], $_SESSION['user_role']);
            }
        }
        authRespond([
            'authenticated' => $sessionUser !== null,
            'role' => $sessionUser['role'] ?? null,
            'user' => $sessionUser,
            'adminConfigured' => $adminCount > 0,
        ]);
    }

    $data = authBody();
    $action = (string) ($data['action'] ?? '');

    if ($action === 'login') {
        $identity = trim((string) ($data['identity'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($identity === '' || $password === '') {
            authRespond(['error' => 'กรุณากรอกอีเมลหรือเบอร์โทรศัพท์และรหัสผ่าน'], 422);
        }
        if (strtolower($identity) === 'admin') {
            $stmt = $pdo->prepare("SELECT id, email, password_hash, first_name, last_name, role, is_active FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1");
            $stmt->execute();
        } else {
            $normalizedIdentity = strtolower($identity);
            if (filter_var($normalizedIdentity, FILTER_VALIDATE_EMAIL)) {
                $stmt = $pdo->prepare('SELECT id, email, password_hash, first_name, last_name, role, is_active FROM users WHERE email = :identity LIMIT 1');
                $stmt->execute([':identity' => $normalizedIdentity]);
            } else {
                // Remove spaces and dashes from input for phone search
                $cleanPhone = preg_replace('/[^0-9]/', '', $identity);
                // If it starts with 66, convert to 0 (e.g. 66812345678 -> 0812345678)
                if (str_starts_with($cleanPhone, '66') && strlen($cleanPhone) >= 10) {
                    $cleanPhone = '0' . substr($cleanPhone, 2);
                }
                
                $stmt = $pdo->prepare('SELECT id, email, password_hash, first_name, last_name, role, is_active FROM users WHERE REPLACE(REPLACE(phone, " ", ""), "-", "") = :cleanPhone OR phone = :rawIdentity LIMIT 1');
                $stmt->execute([':cleanPhone' => $cleanPhone, ':rawIdentity' => $identity]);
            }
        }
        $user = $stmt->fetch();
        if (!$user || !$user['is_active'] || !password_verify($password, (string) $user['password_hash'])) {
            authRespond(['error' => 'ไม่พบบัญชีหรือรหัสผ่านไม่ถูกต้อง'], 401);
        }

        require_once __DIR__ . '/includes/settings-service.php';
        if (SettingsService::isMaintenanceMode($pdo) && !in_array($user['role'], ['admin', 'teacher'], true)) {
            authRespond(['error' => 'ระบบอยู่ระหว่างการปิดปรับปรุงชั่วคราว (Maintenance Mode) อนุญาตเฉพาะผู้ดูแลระบบและอาจารย์เข้าสู่ระบบเท่านั้น'], 503);
        }
        if (!SettingsService::isStudentPortalEnabled($pdo) && $user['role'] === 'student') {
            authRespond(['error' => 'ระบบพอร์ทัลนักเรียนปิดปรับปรุงชั่วคราว ขออภัยในความไม่สะดวก'], 503);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_role'] = (string) $user['role'];
        authRespond(['success' => true, 'user' => [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'name' => trim($user['first_name'] . ' ' . $user['last_name']),
            'role' => $user['role'],
        ]]);
    }

    if ($action === 'register') {
        require_once __DIR__ . '/includes/settings-service.php';
        if (!SettingsService::isRegistrationEnabled($pdo)) {
            authRespond(['error' => 'ระบบปิดรับสมัครนักเรียนใหม่ชั่วคราว กรุณาติดต่อสถาบัน'], 403);
        }
        if (SettingsService::isMaintenanceMode($pdo)) {
            authRespond(['error' => 'ระบบอยู่ระหว่างการปิดปรับปรุงชั่วคราว (Maintenance Mode) ไม่สามารถสมัครสมาชิกได้ในขณะนี้'], 503);
        }

        $firstName = trim((string) ($data['firstName'] ?? ''));
        $lastName = trim((string) ($data['lastName'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = trim((string) ($data['phone'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            authRespond(['error' => 'กรุณากรอกชื่อ นามสกุล และอีเมลให้ถูกต้อง'], 422);
        }
        if (strlen($password) < 8) {
            authRespond(['error' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร'], 422);
        }
        $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $check->execute([':email' => $email]);
        if ($check->fetch()) {
            authRespond(['error' => 'อีเมลนี้มีบัญชีอยู่แล้ว กรุณาเข้าสู่ระบบ'], 409);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name, phone, role, pdpa_consent, pdpa_consent_at, is_active) VALUES (:email, :password, :first_name, :last_name, :phone, \'student\', 1, NOW(), 1)');
        $stmt->execute([
            ':email' => $email,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':phone' => $phone ?: null,
        ]);
        $userId = (int) $pdo->lastInsertId();

        $parentName = trim((string) ($data['parentName'] ?? ''));
        $parentPhone = trim((string) ($data['parentPhone'] ?? ''));
        if ($parentName !== '' || $parentPhone !== '') {
            $parent = $pdo->prepare('INSERT INTO parent_info (user_id, parent_name, parent_phone, parent_email, relationship) VALUES (:user_id, :name, :phone, :email, :relationship)');
            $parent->execute([
                ':user_id' => $userId,
                ':name' => $parentName ?: null,
                ':phone' => $parentPhone ?: null,
                ':email' => trim((string) ($data['parentEmail'] ?? '')) ?: null,
                ':relationship' => trim((string) ($data['relationship'] ?? '')) ?: null,
            ]);
        }
        $pdo->commit();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = 'student';
        authRespond(['success' => true, 'user' => ['id' => $userId, 'email' => $email, 'name' => $firstName . ' ' . $lastName, 'role' => 'student']], 201);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        authRespond(['success' => true]);
    }

    authRespond(['error' => 'คำสั่งไม่ถูกต้อง'], 400);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Auth API: ' . $error->getMessage());
    authRespond(['error' => 'ระบบบัญชีขัดข้อง กรุณาลองใหม่'], 500);
}
