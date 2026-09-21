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

/**
 * Retrieve active and available Gemini candidate models for generateContent.
 * Uses dynamic model discovery via Google API with fallback to verified stable models and auto-updating aliases.
 */
function aiGetCandidateModels(string $apiKey, ?PDO $pdo = null, bool $forceRefresh = false): array {
    $apiKey = trim($apiKey);
    if ($apiKey === '') {
        return [];
    }

    static $memoryCache = [];
    if (!$forceRefresh && isset($memoryCache[$apiKey])) {
        return $memoryCache[$apiKey];
    }

    $targetPdo = $pdo ?? ($GLOBALS['pdo'] ?? null);

    // Fallback list of models covering latest Gemini 2.5, 3.x and official auto-updating aliases
    $fallbackModels = [
        'gemini-flash-latest',
        'gemini-pro-latest',
        'gemini-2.5-flash',
        'gemini-2.5-pro',
        'gemini-3.8-flash',
        'gemini-3.5-flash',
        'gemini-2.5-flash-preview',
        'gemini-2.0-flash',
        'gemini-1.5-flash',
        'gemini-1.5-pro'
    ];

    // Check DB cache (valid for 1 hour)
    if (!$forceRefresh && $targetPdo instanceof PDO) {
        try {
            aiSettingsEnsure($targetPdo);
            $stmt = $targetPdo->prepare("SELECT setting_value, updated_at FROM system_settings WHERE setting_key = 'ai_models_cache' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['setting_value'])) {
                $cacheTime = strtotime((string)$row['updated_at']);
                if (time() - $cacheTime < 3600) {
                    $cached = json_decode((string)$row['setting_value'], true);
                    if (is_array($cached) && !empty($cached)) {
                        $memoryCache[$apiKey] = $cached;
                        return $cached;
                    }
                }
            }
        } catch (Throwable) {
            // Ignore DB cache errors
        }
    }

    // Dynamic model discovery via Google Generative Language API
    $blacklist = ['deep-research', 'vision', 'embedding', 'aqa', 'tts', 'stt', 'imagen', 'realtime', 'robotics'];
    $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($apiKey);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300 && $response) {
        $data = json_decode((string)$response, true);
        if (!empty($data['models']) && is_array($data['models'])) {
            $available = [];
            foreach ($data['models'] as $m) {
                $methods = $m['supportedGenerationMethods'] ?? [];
                if (is_array($methods) && in_array('generateContent', $methods)) {
                    $name = str_replace('models/', '', (string)($m['name'] ?? ''));
                    if ($name === '') continue;

                    $isBad = false;
                    foreach ($blacklist as $bad) {
                        if (stripos($name, $bad) !== false) {
                            $isBad = true;
                            break;
                        }
                    }
                    if (!$isBad) {
                        $available[] = $name;
                    }
                }
            }

            if (!empty($available)) {
                // Group models: Flash (fast/cheap/high-rate), Pro, and others
                $flashModels = array_values(array_filter($available, fn($m) => stripos($m, 'flash') !== false));
                $proModels = array_values(array_filter($available, fn($m) => stripos($m, 'pro') !== false));
                $otherModels = array_values(array_filter($available, fn($m) => stripos($m, 'flash') === false && stripos($m, 'pro') === false));

                rsort($flashModels, SORT_NATURAL | SORT_FLAG_CASE);
                rsort($proModels, SORT_NATURAL | SORT_FLAG_CASE);

                $discovered = array_values(array_unique(array_merge($flashModels, $proModels, $otherModels)));
                if (!empty($discovered)) {
                    // Ensure the resilient latest aliases are at the top if present or prepend
                    if (!in_array('gemini-flash-latest', $discovered, true)) {
                        array_unshift($discovered, 'gemini-flash-latest');
                    }

                    // Save to DB cache
                    if ($targetPdo instanceof PDO) {
                        try {
                            $saveStmt = $targetPdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at)
                                VALUES ('ai_models_cache', :val, NOW())
                                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
                            $saveStmt->execute([':val' => json_encode($discovered, JSON_UNESCAPED_UNICODE)]);
                        } catch (Throwable) {
                            // Non-critical
                        }
                    }

                    $memoryCache[$apiKey] = $discovered;
                    return $discovered;
                }
            }
        }
    }

    $memoryCache[$apiKey] = $fallbackModels;
    return $fallbackModels;
}
