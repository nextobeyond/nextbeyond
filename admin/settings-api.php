<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/access.php';

function settingsRespond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ensureSettingsTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value MEDIUMTEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

$defaults = [
    'school_name' => 'Nextbeyond Compass',
    'school_phone' => '',
    'school_email' => '',
    'school_address' => '',
    'school_logo' => '',
    'registration_enabled' => '1',
    'maintenance_mode' => '0',
    'bank_name' => '',
    'bank_account_name' => '',
    'bank_account_number' => '',
    'promptpay_number' => '',
    'tax_id' => '',
    'payment_instructions' => '',
    'student_portal_enabled' => '1',
    'teacher_course_access' => '0',
    'teacher_test_access' => '0',
    'guest_test_access' => '1',
    'email_notifications' => '1',
    'calculator_enabled' => '1',
];
$booleanKeys = [
    'registration_enabled', 'maintenance_mode', 'student_portal_enabled',
    'teacher_course_access', 'teacher_test_access', 'guest_test_access', 'email_notifications', 'calculator_enabled'
];

foreach (teacherMenuSettings() as $key => $menu) {
    $defaults[$key] = '0';
    if (!in_array($key, $booleanKeys, true)) $booleanKeys[] = $key;
}

try {
    ensureSettingsTable($pdo);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $keys = array_keys($defaults);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ({$placeholders})");
        $stmt->execute($keys);
        $settings = $defaults;
        foreach ($stmt->fetchAll() as $row) {
            $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
        }
        foreach ($booleanKeys as $key) $settings[$key] = $settings[$key] === '1';
        settingsRespond(['settings' => $settings]);
    }

    if ($method !== 'POST') settingsRespond(['error' => 'Method not allowed'], 405);

    if (str_starts_with((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data')) {
        if (empty($_FILES['logo']) || !is_uploaded_file($_FILES['logo']['tmp_name'])) {
            settingsRespond(['error' => 'กรุณาเลือกไฟล์โลโก้'], 422);
        }
        $file = $_FILES['logo'];
        if ((int) $file['size'] > 5 * 1024 * 1024) settingsRespond(['error' => 'ไฟล์โลโก้ต้องมีขนาดไม่เกิน 5MB'], 422);
        
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $origExt = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        
        $extensions = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/svg' => 'svg',
            'image/gif' => 'gif',
        ];

        $ext = $extensions[$mime] ?? null;
        if (!$ext && in_array($origExt, ['svg', 'png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            // Fallback check for SVG text MIME
            if ($origExt === 'svg') {
                $contentStart = file_get_contents($file['tmp_name'], false, null, 0, 500);
                if (str_contains($contentStart, '<svg')) {
                    $ext = 'svg';
                }
            }
        }

        if (!$ext) {
            settingsRespond(['error' => 'รองรับเฉพาะไฟล์ PNG, JPG, WEBP, SVG และ GIF'], 422);
        }

        $uploadDir = __DIR__ . '/../assets/uploads/settings';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('สร้างโฟลเดอร์อัปโหลดไม่สำเร็จ');
        }
        @chmod($uploadDir, 0777);

        foreach (glob($uploadDir . '/school-logo.*') ?: [] as $oldFile) @unlink($oldFile);
        $filename = 'school-logo.' . $ext;
        $destination = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('บันทึกไฟล์โลโก้ไม่สำเร็จ');
        }
        @chmod($destination, 0666);

        // Store clean relative path: assets/uploads/settings/school-logo.ext
        $path = 'assets/uploads/settings/' . $filename;
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES ('school_logo', :value, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        $stmt->execute([':value' => $path]);
        
        require_once __DIR__ . '/../includes/settings-service.php';
        SettingsService::set('school_logo', $path, $pdo);

        settingsRespond(['success' => true, 'path' => $path, 'message' => 'อัปโหลดโลโก้สำเร็จ']);
    }

    $body = json_decode(file_get_contents('php://input'), true);
    
    // Support removing logo
    if (($body['action'] ?? '') === 'remove_logo') {
        $uploadDir = __DIR__ . '/../assets/uploads/settings';
        foreach (glob($uploadDir . '/school-logo.*') ?: [] as $oldFile) @unlink($oldFile);
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES ('school_logo', '', NOW())
            ON DUPLICATE KEY UPDATE setting_value = '', updated_at = NOW()")->execute();
        require_once __DIR__ . '/../includes/settings-service.php';
        SettingsService::set('school_logo', '', $pdo);
        settingsRespond(['success' => true, 'path' => '', 'message' => 'ลบโลโก้เรียบร้อยแล้ว']);
    }

    $incoming = is_array($body['settings'] ?? null) ? $body['settings'] : [];
    if (!$incoming) settingsRespond(['error' => 'ไม่มีข้อมูลที่ต้องการบันทึก'], 422);

    require_once __DIR__ . '/../includes/settings-service.php';
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES (:key, :value, NOW())
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');
    $saved = [];
    foreach ($incoming as $key => $value) {
        if (!array_key_exists($key, $defaults) || $key === 'school_logo') continue;
        if (in_array($key, $booleanKeys, true)) {
            $value = $value ? '1' : '0';
        } else {
            $value = trim((string) $value);
            if (mb_strlen($value) > 2000) settingsRespond(['error' => 'ข้อมูลยาวเกินกำหนด'], 422);
        }
        $stmt->execute([':key' => $key, ':value' => $value]);
        SettingsService::set($key, in_array($key, $booleanKeys, true) ? ($value === '1') : $value, $pdo);
        $saved[$key] = in_array($key, $booleanKeys, true) ? $value === '1' : $value;
    }
    $pdo->commit();
    settingsRespond(['success' => true, 'settings' => $saved]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Settings API: ' . $error->getMessage());
    settingsRespond(['error' => 'บันทึกการตั้งค่าไม่สำเร็จ กรุณาลองใหม่'], 500);
}
