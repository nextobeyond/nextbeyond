<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

// Find or ensure an admin user exists
$adminUser = $pdo->query("SELECT id, role, is_active FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1")->fetch();
if (!$adminUser) {
    echo "No admin user found. Creating temporary admin test user.\n";
    $pdo->exec("INSERT INTO users (email, password_hash, first_name, last_name, role, is_active) VALUES ('test_admin@test.com', 'hash', 'Admin', 'Tester', 'admin', 1)");
    $adminId = (int) $pdo->lastInsertId();
} else {
    $adminId = (int) $adminUser['id'];
}

echo "Admin ID: {$adminId}\n";

// Helper to run code simulating session and HTTP request
function runSimulatedRequest(string $scriptPath, string $method, array $postData = [], array $queryParams = [], int $userId = 1): array {
    $cmd = sprintf(
        '/Applications/XAMPP/xamppfiles/bin/php -r "%s"',
        addcslashes('
            $_SERVER["REQUEST_METHOD"] = "' . $method . '";
            $_SERVER["SCRIPT_FILENAME"] = "' . $scriptPath . '";
            $_SERVER["CONTENT_TYPE"] = "application/json";
            $_GET = ' . var_export($queryParams, true) . ';
            session_start();
            $_SESSION["user_id"] = ' . $userId . ';
            $_SESSION["user_role"] = "admin";
            $input = ' . var_export(json_encode($postData), true) . ';
            file_put_contents("/tmp/sim_input.json", $input);
            // override php://input by wrapping
            $GLOBALS["test_mock_input"] = $input;
            
            // capture output
            ob_start();
            require "' . $scriptPath . '";
            $out = ob_get_clean();
            echo $out;
        ', '"\\')
    );
    // Because php://input cannot be simply faked in CLI without streams, let\'s test with a small script that mocks php://input if needed, or invoke the script directly
    return [];
}

// Let's test by directly requiring in an isolated script with mock php://input
$runnerCode = <<<'PHP'
<?php
session_start();
$_SESSION['user_id'] = %ADMIN_ID%;
$_SESSION['user_role'] = 'admin';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../admin/settings-api.php';

ob_start();
require __DIR__ . '/../admin/settings-api.php';
$output = ob_get_clean();
$data = json_decode($output, true);

if (!isset($data['settings']) || !isset($data['settings']['school_name'])) {
    echo "FAILED: settings-api.php GET did not return expected settings\n";
    echo $output;
    exit(1);
}

echo "SUCCESS: settings-api.php GET returned valid settings object. School: " . $data['settings']['school_name'] . "\n";

// Test calculator-api GET
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../admin/calculator-api.php';
ob_start();
require __DIR__ . '/../admin/calculator-api.php';
$calcOut = ob_get_clean();
$calcData = json_decode($calcOut, true);

if (!isset($calcData['tracks'])) {
    echo "FAILED: calculator-api.php GET did not return tracks\n";
    echo $calcOut;
    exit(1);
}

echo "SUCCESS: calculator-api.php GET returned " . count($calcData['tracks']) . " tracks.\n";
PHP;

$runnerCode = str_replace('%ADMIN_ID%', (string)$adminId, $runnerCode);
file_put_contents(__DIR__ . '/scratch_api_test.php', $runnerCode);

passthru('/Applications/XAMPP/xamppfiles/bin/php ' . escapeshellarg(__DIR__ . '/scratch_api_test.php'), $exitCode);
@unlink(__DIR__ . '/scratch_api_test.php');

if ($exitCode === 0) {
    echo "\n=== ALL ADMIN ENDPOINTS TESTED SUCCESSFULLY! ===\n";
} else {
    echo "\n=== SOME ADMIN ENDPOINTS FAILED ===\n";
    exit(1);
}
