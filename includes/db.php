<?php

$localConfig = __DIR__ . '/db.local.php';
if (is_file($localConfig)) {
    require_once $localConfig;
}

if (!defined('ADMIN_DB_HOST')) {
    define('ADMIN_DB_HOST', getenv('ADMIN_DB_HOST') ?: 'localhost');
    define('ADMIN_DB_NAME', getenv('ADMIN_DB_NAME') ?: 'nextbeyond');
    define('ADMIN_DB_USER', getenv('ADMIN_DB_USER') ?: 'root');
    define('ADMIN_DB_PASS', getenv('ADMIN_DB_PASS') ?: '');
    define('ADMIN_DB_CHARSET', getenv('ADMIN_DB_CHARSET') ?: 'utf8mb4');
}

try {
    $dsn = 'mysql:host=' . ADMIN_DB_HOST;
    $xamppSocket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
    if (ADMIN_DB_HOST === 'localhost' && file_exists($xamppSocket)) {
        $dsn .= ';unix_socket=' . $xamppSocket;
    }
    $dsn .= ';dbname=' . ADMIN_DB_NAME . ';charset=' . ADMIN_DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, ADMIN_DB_USER, ADMIN_DB_PASS, $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());

    $requestPath = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    $expectsJson = stripos($requestPath, 'api') !== false || stripos($accept, 'application/json') !== false;

    if ($expectsJson) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'เซิร์ฟเวอร์ยังไม่ได้ตั้งค่าการเชื่อมต่อฐานข้อมูล กรุณาตั้งค่า ADMIN_DB_HOST, ADMIN_DB_NAME, ADMIN_DB_USER และ ADMIN_DB_PASS',
            'code' => 'DATABASE_NOT_CONFIGURED',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(503);
    die('Database service is temporarily unavailable.');
}
