<?php
declare(strict_types=1);
function aiSettingsEnsure(PDO $pdo): void {
    // Keep for backward compatibility
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value MEDIUMTEXT NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function aiSettingsSetKey(string $key): void {
    $file = __DIR__ . '/ai-key.php';
    $content = "<?php\n// อย่าอัปโหลดไฟล์นี้ขึ้น GitHub (Keep this secret)\nreturn '" . addslashes($key) . "';\n";
    file_put_contents($file, $content);
}
function aiSettingsEncrypt(string $value): string {
    $key=hash('sha256', ADMIN_DB_PASS, true); $iv=random_bytes(16);
    $encrypted=openssl_encrypt($value,'aes-256-cbc',$key,OPENSSL_RAW_DATA,$iv);
    if($encrypted===false) throw new RuntimeException('เข้ารหัส API Key ไม่สำเร็จ');
    return base64_encode($iv.$encrypted);
}
function aiSettingsDecrypt(string $value): string {
    $raw=base64_decode($value,true); if($raw===false||strlen($raw)<17)return '';
    return (string)openssl_decrypt(substr($raw,16),'aes-256-cbc',hash('sha256',ADMIN_DB_PASS,true),OPENSSL_RAW_DATA,substr($raw,0,16));
}
function aiSettingsGetKey(PDO $pdo = null): string {
    $file = __DIR__ . '/ai-key.php';
    if (file_exists($file)) {
        return (string) include $file;
    }
    // Fallback to database if file doesn't exist yet
    if ($pdo) {
        aiSettingsEnsure($pdo); $q=$pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key='gemini_api_key'");$q->execute();$value=$q->fetchColumn();
        return is_string($value)?aiSettingsDecrypt($value):'';
    }
    return '';
}
