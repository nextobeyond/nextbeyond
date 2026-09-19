<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/access.php';

function teacherResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function teacherBody(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) teacherResponse(['error' => 'ข้อมูล JSON ไม่ถูกต้อง'], 400);
    return $data;
}

// Ensure nickname and subjects columns exist in users table
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('nickname', $cols, true)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `nickname` VARCHAR(100) NULL AFTER `last_name`");
    }
    if (!in_array('subjects', $cols, true)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `subjects` VARCHAR(255) NULL AFTER `nickname`");
    }
} catch (Throwable $e) {
    // Ignore if table schema already modified or cannot alter
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $rows = $pdo->query(
            "SELECT u.id, u.email, u.first_name, u.last_name, u.nickname, u.subjects AS user_subjects, u.phone, u.avatar_url,
                    u.is_active, u.created_at,
                    COUNT(DISTINCT c.id) AS course_count,
                    GROUP_CONCAT(DISTINCT c.subject ORDER BY c.subject SEPARATOR ', ') AS course_subjects
             FROM users u
             LEFT JOIN courses c ON c.teacher_id = u.id AND c.status = 'active'
             WHERE u.role = 'teacher'
             GROUP BY u.id
             ORDER BY u.created_at DESC, u.id DESC"
        )->fetchAll();
        teacherResponse(['teachers' => array_map(static function (array $row): array {
            $userSubjects = trim((string)($row['user_subjects'] ?? ''));
            $courseSubjects = trim((string)($row['course_subjects'] ?? ''));
            $finalSubjects = $userSubjects !== '' ? $userSubjects : $courseSubjects;
            return [
                'id' => (string) $row['id'],
                'email' => $row['email'],
                'firstName' => $row['first_name'],
                'lastName' => $row['last_name'],
                'nickname' => $row['nickname'] ?? '',
                'subjects' => $finalSubjects,
                'phone' => $row['phone'] ?? '',
                'avatarUrl' => $row['avatar_url'],
                'isActive' => (bool) $row['is_active'],
                'courseCount' => (int) $row['course_count'],
                'createdAt' => $row['created_at'],
            ];
        }, $rows)]);
    }

    if ($method === 'POST') {
        $body = teacherBody();
        $firstName = trim((string) ($body['firstName'] ?? ''));
        $lastName = trim((string) ($body['lastName'] ?? ''));
        $nickname = trim((string) ($body['nickname'] ?? ''));
        $subjects = trim((string) ($body['subjects'] ?? ''));
        $email = mb_strtolower(trim((string) ($body['email'] ?? '')));
        $phone = trim((string) ($body['phone'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            teacherResponse(['error' => 'กรุณากรอกชื่อ นามสกุล และอีเมลให้ถูกต้อง'], 422);
        }
        if (mb_strlen($password) < 8) {
            teacherResponse(['error' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร'], 422);
        }
        $exists = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $exists->execute([':email' => $email]);
        if ($exists->fetch()) teacherResponse(['error' => 'อีเมลนี้มีบัญชีอยู่แล้ว'], 409);

        $stmt = $pdo->prepare(
            "INSERT INTO users (email, password_hash, first_name, last_name, nickname, subjects, phone, role, is_active)
             VALUES (:email, :password_hash, :first_name, :last_name, :nickname, :subjects, :phone, 'teacher', 1)"
        );
        $stmt->execute([
            ':email' => $email,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':nickname' => $nickname !== '' ? $nickname : null,
            ':subjects' => $subjects !== '' ? $subjects : null,
            ':phone' => $phone !== '' ? $phone : null,
        ]);
        teacherResponse(['success' => true, 'teacherId' => (int) $pdo->lastInsertId()], 201);
    }

    if ($method === 'PATCH') {
        $body = teacherBody();
        $id = (int) ($body['id'] ?? 0);
        if ($id < 1) {
            teacherResponse(['error' => 'ข้อมูลที่ต้องการแก้ไขไม่ถูกต้อง'], 422);
        }

        // Toggle active status
        if (array_key_exists('isActive', $body)) {
            $stmt = $pdo->prepare("UPDATE users SET is_active = :active WHERE id = :id AND role = 'teacher'");
            $stmt->execute([':active' => $body['isActive'] ? 1 : 0, ':id' => $id]);
            teacherResponse(['success' => true]);
        }

        // Full update (firstName, lastName, nickname, subjects, email, phone, optional password)
        $firstName = trim((string) ($body['firstName'] ?? ''));
        $lastName = trim((string) ($body['lastName'] ?? ''));
        $nickname = trim((string) ($body['nickname'] ?? ''));
        $subjects = trim((string) ($body['subjects'] ?? ''));
        $email = mb_strtolower(trim((string) ($body['email'] ?? '')));
        $phone = trim((string) ($body['phone'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            teacherResponse(['error' => 'กรุณากรอกชื่อ นามสกุล และอีเมลให้ถูกต้อง'], 422);
        }

        $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
        $emailCheck->execute([':email' => $email, ':id' => $id]);
        if ($emailCheck->fetch()) teacherResponse(['error' => 'อีเมลนี้ถูกใช้งานแล้วโดยบัญชีอื่น'], 409);

        if ($password !== '') {
            if (mb_strlen($password) < 8) {
                teacherResponse(['error' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร'], 422);
            }
            $stmt = $pdo->prepare(
                "UPDATE users 
                 SET first_name = :first_name, last_name = :last_name, nickname = :nickname, 
                     subjects = :subjects, email = :email, phone = :phone, password_hash = :password_hash 
                 WHERE id = :id AND role = 'teacher'"
            );
            $stmt->execute([
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':nickname' => $nickname !== '' ? $nickname : null,
                ':subjects' => $subjects !== '' ? $subjects : null,
                ':email' => $email,
                ':phone' => $phone !== '' ? $phone : null,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':id' => $id,
            ]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE users 
                 SET first_name = :first_name, last_name = :last_name, nickname = :nickname, 
                     subjects = :subjects, email = :email, phone = :phone 
                 WHERE id = :id AND role = 'teacher'"
            );
            $stmt->execute([
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':nickname' => $nickname !== '' ? $nickname : null,
                ':subjects' => $subjects !== '' ? $subjects : null,
                ':email' => $email,
                ':phone' => $phone !== '' ? $phone : null,
                ':id' => $id,
            ]);
        }

        teacherResponse(['success' => true]);
    }

    if ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id < 1) teacherResponse(['error' => 'ไม่พบรหัสคุณครู'], 422);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'teacher'");
        $stmt->execute([':id' => $id]);
        teacherResponse(['success' => true]);
    }

    teacherResponse(['error' => 'Method not allowed'], 405);
} catch (Throwable $error) {
    error_log('Teacher API error: ' . $error->getMessage());
    teacherResponse(['error' => 'ระบบข้อมูลคุณครูขัดข้อง กรุณาลองใหม่'], 500);
}
