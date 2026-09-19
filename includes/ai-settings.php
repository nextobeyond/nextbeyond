<?php
declare(strict_types=1);

/**
 * AI Settings Manager for Gemini API Key storage.
 * Stores and retrieves encrypted API keys from MySQL system_settings table with persistent caching.
 */

function aiSettingsEnsure(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value MEDIUMTEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function aiSettingsCipherKey(): string {
    // Stable application secret combined with database credentials
    $salt = 'NextBeyond_System_AI_Secret_Key_2026_Secure';
    if (defined('ADMIN_DB_PASS') && ADMIN_DB_PASS !== '') {
        $salt .= '_' . ADMIN_DB_PASS;
    }
    return hash('sha256', $salt, true);
}

function aiSettingsEncrypt(string $value): string {
    $key = aiSettingsCipherKey();
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        throw new RuntimeException('เข้ารหัส API Key ไม่สำเร็จ');
    }
    return base64_encode($iv . $encrypted);
}

function aiSettingsDecrypt(string $value): string {
    $trimmed = trim($value);
    if ($trimmed === '') return '';

    // If it is stored in plaintext (e.g. starts with AIzaSy)
    if (str_starts_with($trimmed, 'AIzaSy')) {
        return $trimmed;
    }

    $raw = base64_decode($trimmed, true);
    if ($raw === false || strlen($raw) < 17) {
        return '';
    }

    $iv = substr($raw, 0, 16);
    $ciphertext = substr($raw, 16);

    // 1. Try standard stable cipher key
    $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', aiSettingsCipherKey(), OPENSSL_RAW_DATA, $iv);
    if ($decrypted !== false && $decrypted !== '') {
        return (string) $decrypted;
    }

    // 2. Try legacy ADMIN_DB_PASS key
    if (defined('ADMIN_DB_PASS')) {
        $legacyKey = hash('sha256', ADMIN_DB_PASS, true);
        $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $legacyKey, OPENSSL_RAW_DATA, $iv);
        if ($decrypted !== false && $decrypted !== '') {
            return (string) $decrypted;
        }
    }

    // 3. Try empty string hash fallback
    $emptyKey = hash('sha256', '', true);
    $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $emptyKey, OPENSSL_RAW_DATA, $iv);
    if ($decrypted !== false && $decrypted !== '') {
        return (string) $decrypted;
    }

    return '';
}

function aiSettingsSetKey(string $key, ?PDO $pdo = null): void {
    $trimmedKey = trim($key);
    if ($trimmedKey === '') {
        return;
    }

    $targetPdo = $pdo ?? ($GLOBALS['pdo'] ?? null);

    // 1. Save to database table system_settings
    if ($targetPdo instanceof PDO) {
        aiSettingsEnsure($targetPdo);
        $encrypted = aiSettingsEncrypt($trimmedKey);
        $stmt = $targetPdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at)
            VALUES ('gemini_api_key', :val, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        $stmt->execute([':val' => $encrypted]);
    }

    // 2. Also save to local secret file as secondary cache if writable
    try {
        $file = __DIR__ . '/ai-key.php';
        $content = "<?php\n// อย่าอัปโหลดไฟล์นี้ขึ้น GitHub (Keep this secret)\nreturn '" . addslashes($trimmedKey) . "';\n";
        @file_put_contents($file, $content);
    } catch (Throwable) {
        // Silently continue if filesystem is read-only
    }
}

function aiSettingsGetKey(?PDO $pdo = null): string {
    $targetPdo = $pdo ?? ($GLOBALS['pdo'] ?? null);

    // 1. Primary source: MySQL database
    if ($targetPdo instanceof PDO) {
        try {
            aiSettingsEnsure($targetPdo);
            $q = $targetPdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'gemini_api_key' LIMIT 1");
            $q->execute();
            $val = $q->fetchColumn();
            if (is_string($val) && trim($val) !== '') {
                $decrypted = aiSettingsDecrypt($val);
                if ($decrypted !== '') {
                    return $decrypted;
                }
            }
        } catch (Throwable $e) {
            error_log('aiSettingsGetKey database query error: ' . $e->getMessage());
        }
    }

    // 2. Secondary fallback: local secret file
    $file = __DIR__ . '/ai-key.php';
    if (file_exists($file)) {
        try {
            $keyFromFile = trim((string) (include $file));
            if ($keyFromFile !== '') {
                // If found in file but missing from DB, sync to DB
                if ($targetPdo instanceof PDO) {
                    aiSettingsSetKey($keyFromFile, $targetPdo);
                }
                return $keyFromFile;
            }
        } catch (Throwable) {
            // Ignore file read issues
        }
    }

    return '';
}
