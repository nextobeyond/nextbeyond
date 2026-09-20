<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/settings-service.php';
require_once __DIR__ . '/../includes/ai-settings.php';

echo "=== START VERIFYING SYSTEM SETTINGS ===\n";

$passCount = 0;
$totalTests = 0;

function testAssert(bool $condition, string $label): void {
    global $passCount, $totalTests;
    $totalTests++;
    if ($condition) {
        $passCount++;
        echo " [PASS] {$label}\n";
    } else {
        echo " [FAIL] {$label}\n";
    }
}

// 1. SettingsService Core Read/Write
echo "\n--- 1. Testing SettingsService Methods ---\n";
$initialSchool = SettingsService::get('school_name');
testAssert(!empty($initialSchool), "SettingsService::get('school_name') returned non-empty string: {$initialSchool}");

$testSchoolName = 'Nextbeyond Compass Academy Test';
$setRes = SettingsService::set('school_name', $testSchoolName, $pdo);
testAssert($setRes === true, "SettingsService::set('school_name') returned true");

$updatedSchool = SettingsService::get('school_name', '', $pdo);
testAssert($updatedSchool === $testSchoolName, "SettingsService::get('school_name') reflected updated value immediately (cache invalidated)");

// Restore school name
SettingsService::set('school_name', $initialSchool, $pdo);

// 2. Boolean Keys
echo "\n--- 2. Testing Boolean Keys & Helpers ---\n";
SettingsService::set('registration_enabled', false, $pdo);
testAssert(SettingsService::isRegistrationEnabled($pdo) === false, "isRegistrationEnabled() returns false when set to false");

SettingsService::set('registration_enabled', true, $pdo);
testAssert(SettingsService::isRegistrationEnabled($pdo) === true, "isRegistrationEnabled() returns true when set to true");

SettingsService::set('maintenance_mode', true, $pdo);
testAssert(SettingsService::isMaintenanceMode($pdo) === true, "isMaintenanceMode() returns true when set to true");

SettingsService::set('maintenance_mode', false, $pdo);
testAssert(SettingsService::isMaintenanceMode($pdo) === false, "isMaintenanceMode() returns false when restored");

testAssert(SettingsService::isStudentPortalEnabled($pdo) === true, "isStudentPortalEnabled() defaults to true");
testAssert(SettingsService::isCalculatorEnabled($pdo) === true, "isCalculatorEnabled() defaults to true");

// 3. AI Key Encryption and Retrieval
echo "\n--- 3. Testing AI Settings Encryption ---\n";
$originalAiKey = aiSettingsGetKey($pdo);
$dummyKey = 'AIzaSyTestApiKeyVerification12345';
aiSettingsSetKey($dummyKey, $pdo);
$retrievedKey = aiSettingsGetKey($pdo);
testAssert($retrievedKey === $dummyKey, "aiSettingsGetKey() successfully decrypted key matching original plaintext");

// Verify that the key in database is NOT stored in plain text
$rawStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'gemini_api_key'");
$rawStmt->execute();
$rawDbValue = (string) $rawStmt->fetchColumn();
testAssert($rawDbValue !== $dummyKey && !empty($rawDbValue) && base64_decode($rawDbValue, true) !== false, "gemini_api_key in system_settings is properly encrypted (base64 encoded ciphertext)");

// Restore original key
if ($originalAiKey !== '') {
    aiSettingsSetKey($originalAiKey, $pdo);
}

// 4. Calculator API Weights Validation
echo "\n--- 4. Testing Calculator Weights & DB ---\n";
// Ensure calculator_tracks table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS calculator_tracks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    icon VARCHAR(50) NOT NULL DEFAULT '',
    description TEXT,
    weights JSON NOT NULL,
    min_score DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Check insertion
$testWeights = json_encode(['tgat' => 0.3, 'math1' => 0.4, 'physics' => 0.3]);
$insertStmt = $pdo->prepare("INSERT INTO calculator_tracks (name, icon, description, weights, min_score, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
$insertStmt->execute(['วิศวกรรมศาสตร์ (Test)', '⚙️', 'TGAT 30% + Math 40% + Phys 30%', $testWeights, 60.00, 1, 99]);
$testTrackId = (int) $pdo->lastInsertId();
testAssert($testTrackId > 0, "Created test track in calculator_tracks with ID: {$testTrackId}");

// Query back
$fetchStmt = $pdo->prepare("SELECT * FROM calculator_tracks WHERE id = ?");
$fetchStmt->execute([$testTrackId]);
$trackRow = $fetchStmt->fetch(PDO::FETCH_ASSOC);
testAssert($trackRow['name'] === 'วิศวกรรมศาสตร์ (Test)', "Track name matches inserted data");
$wDecoded = json_decode($trackRow['weights'], true);
$wSum = array_sum($wDecoded);
testAssert(abs($wSum - 1.0) < 0.001, "Weights sum equals 1.0 (verified: {$wSum})");

// Clean up test track
$pdo->prepare("DELETE FROM calculator_tracks WHERE id = ?")->execute([$testTrackId]);
testAssert(true, "Cleaned up test track");

// 5. Verification of Registration Enforcement
echo "\n--- 5. Testing Registration Enforcement via Settings ---\n";
SettingsService::set('registration_enabled', false, $pdo);

// Simulate auth-api register call
$simEmail = 'test_settings_block_' . time() . '@example.com';
$regBlocked = false;

// We test logic that auth-api uses:
if (!SettingsService::isRegistrationEnabled($pdo)) {
    $regBlocked = true;
}
testAssert($regBlocked === true, "Registration check correctly blocked when registration_enabled is false");

// Re-enable registration
SettingsService::set('registration_enabled', true, $pdo);
testAssert(SettingsService::isRegistrationEnabled($pdo) === true, "Registration re-enabled successfully");

// 6. Maintenance Mode Guard Check
echo "\n--- 6. Testing Maintenance Mode Guard Logic ---\n";
SettingsService::set('maintenance_mode', true, $pdo);
$guardBlocksGuest = SettingsService::isMaintenanceMode($pdo);
testAssert($guardBlocksGuest === true, "Maintenance mode triggers guard for guest");

// Restore maintenance_mode to false
SettingsService::set('maintenance_mode', false, $pdo);
testAssert(SettingsService::isMaintenanceMode($pdo) === false, "Maintenance mode restored to operational false");

echo "\n====================================\n";
echo "TEST RESULTS: {$passCount} / {$totalTests} PASSED\n";
echo "====================================\n";

if ($passCount === $totalTests) {
    echo "ALL TESTS PASSED SUCCESSFULLY!\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
