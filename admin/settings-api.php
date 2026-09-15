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
        if ((int) $file['size'] > 2 * 1024 * 1024) settingsRespond(['error' => 'ไฟล์โลโก้ต้องมีขนาดไม่เกิน 2MB'], 422);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) settingsRespond(['error' => 'รองรับเฉพาะไฟล์ PNG, JPG และ WEBP'], 422);

        $uploadDir = __DIR__ . '/../assets/uploads/settings';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('สร้างโฟลเดอร์อัปโหลดไม่สำเร็จ');
        }
        foreach (glob($uploadDir . '/school-logo.*') ?: [] as $oldFile) @unlink($oldFile);
        $filename = 'school-logo.' . $extensions[$mime];
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
            throw new RuntimeException('บันทึกไฟล์โลโก้ไม่สำเร็จ');
        }
        $path = '/assets/uploads/settings/' . $filename;
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('school_logo', :value)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([':value' => $path]);
        settingsRespond(['success' => true, 'path' => $path]);
    }

    $body = json_decode(file_get_contents('php://input'), true);
    $incoming = is_array($body['settings'] ?? null) ? $body['settings'] : [];
    if (!$incoming) settingsRespond(['error' => 'ไม่มีข้อมูลที่ต้องการบันทึก'], 422);

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (:key, :value)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
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
        $saved[$key] = in_array($key, $booleanKeys, true) ? $value === '1' : $value;
    }
    $pdo->commit();
    settingsRespond(['success' => true, 'settings' => $saved]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Settings API: ' . $error->getMessage());
    settingsRespond(['error' => 'บันทึกการตั้งค่าไม่สำเร็จ กรุณาลองใหม่'], 500);
}
