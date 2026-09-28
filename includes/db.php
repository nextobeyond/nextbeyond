<?php

if (!defined('ADMIN_DB_HOST')) {
    define('ADMIN_DB_HOST', getenv('ADMIN_DB_HOST') ?: 'localhost');
    define('ADMIN_DB_NAME', getenv('ADMIN_DB_NAME') ?: 'u292923614_nextbeyond_M');
    define('ADMIN_DB_USER', getenv('ADMIN_DB_USER') ?: 'u292923614_nextbeyond_M');
    define('ADMIN_DB_PASS', getenv('ADMIN_DB_PASS') ?: '@Nextbeyond1234');
    define('ADMIN_DB_CHARSET', getenv('ADMIN_DB_CHARSET') ?: 'utf8mb4');
}

try {
    // Build DSN — ใช้ unix_socket เฉพาะเมื่อตั้งค่า ADMIN_DB_SOCKET ไว้ใน env
    $adminDbSocket = getenv('ADMIN_DB_SOCKET') ?: '';
    if ($adminDbSocket && file_exists($adminDbSocket)) {
        $dsn = 'mysql:unix_socket=' . $adminDbSocket . ';dbname=' . ADMIN_DB_NAME . ';charset=' . ADMIN_DB_CHARSET;
    } else {
        $dsn = 'mysql:host=' . ADMIN_DB_HOST . ';dbname=' . ADMIN_DB_NAME . ';charset=' . ADMIN_DB_CHARSET;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
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
            'error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ',
            'code'  => 'DATABASE_UNAVAILABLE',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(503);
    die('Database service is temporarily unavailable.');
}
