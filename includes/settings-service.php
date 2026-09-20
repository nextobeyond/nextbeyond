<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

class SettingsService {
    private static ?array $cachedSettings = null;

    public static function getAll(?PDO $pdo = null): array {
        if (self::$cachedSettings !== null) {
            return self::$cachedSettings;
        }

        $targetPdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
        $defaults = [
            'school_name' => 'Nextbeyond Compass',
            'school_phone' => '02-123-4567',
            'school_email' => 'contact@nextbeyond.com',
            'school_address' => 'อาคาร Next Beyond ชั้น 3 เขตปทุมวัน กรุงเทพมหานคร 10330',
            'school_logo' => '',
            'registration_enabled' => true,
            'maintenance_mode' => false,
            'bank_name' => 'ธนาคารกสิกรไทย (KBANK)',
            'bank_account_name' => 'บจก. เน็กซ์ บียอนด์ เอ็ดดูเคชั่น',
            'bank_account_number' => '123-4-56789-0',
            'promptpay_number' => '0105566123456',
            'tax_id' => '0105566123456',
            'payment_instructions' => 'กรุณาแนบหลักฐานการโอนเงิน (สลิป) เพื่อความรวดเร็วในการตรวจสอบและเปิดสิทธิ์เข้าเรียน',
            'student_portal_enabled' => true,
            'guest_test_access' => true,
            'email_notifications' => true,
            'calculator_enabled' => true,
        ];

        if ($targetPdo instanceof PDO) {
            try {
                $targetPdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
                    setting_key VARCHAR(100) PRIMARY KEY,
                    setting_value MEDIUMTEXT NOT NULL,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                $rows = $targetPdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
                $booleanKeys = [
                    'registration_enabled', 'maintenance_mode', 'student_portal_enabled',
                    'guest_test_access', 'email_notifications', 'calculator_enabled',
                    'teacher_overview_access', 'teacher_analytics_access', 'teacher_course_access',
                    'teacher_curriculum_access', 'teacher_calendar_access', 'teacher_test_access',
                    'teacher_worksheet_access', 'teacher_ai_access', 'teacher_students_access',
                    'teacher_orders_access'
                ];

                foreach ($rows as $k => $v) {
                    if (in_array($k, $booleanKeys, true) || str_starts_with((string)$k, 'teacher_')) {
                        $defaults[$k] = ($v === '1' || $v === 1 || $v === true || $v === 'true');
                    } else {
                        $defaults[$k] = (string)$v;
                    }
                }
            } catch (Throwable $e) {
                error_log('SettingsService error: ' . $e->getMessage());
            }
        }

        self::$cachedSettings = $defaults;
        return self::$cachedSettings;
    }

    public static function get(string $key, $default = '', ?PDO $pdo = null) {
        $all = self::getAll($pdo);
        return $all[$key] ?? $default;
    }

    public static function set(string $key, $value, ?PDO $pdo = null): bool {
        $targetPdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
        if (!($targetPdo instanceof PDO)) return false;

        $dbVal = is_bool($value) ? ($value ? '1' : '0') : (string)$value;
        $stmt = $targetPdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES (:k, :v, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        $res = $stmt->execute([':k' => $key, ':v' => $dbVal]);
        self::$cachedSettings = null; // Clear cache
        return $res;
    }

    public static function isMaintenanceMode(?PDO $pdo = null): bool {
        return (bool)self::get('maintenance_mode', false, $pdo);
    }

    public static function isRegistrationEnabled(?PDO $pdo = null): bool {
        return (bool)self::get('registration_enabled', true, $pdo);
    }

    public static function isStudentPortalEnabled(?PDO $pdo = null): bool {
        return (bool)self::get('student_portal_enabled', true, $pdo);
    }

    public static function isCalculatorEnabled(?PDO $pdo = null): bool {
        return (bool)self::get('calculator_enabled', true, $pdo);
    }

    public static function isGuestTestAccessEnabled(?PDO $pdo = null): bool {
        return (bool)self::get('guest_test_access', true, $pdo);
    }
}

if (!function_exists('getSystemSetting')) {
    function getSystemSetting(string $key, $default = '') {
        return SettingsService::get($key, $default);
    }
}
