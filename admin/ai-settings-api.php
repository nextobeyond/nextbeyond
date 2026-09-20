<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ai-settings.php';
require_once __DIR__ . '/includes/access.php';

function aiRespond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $savedKey = aiSettingsGetKey($pdo);
        $maskedKey = $savedKey !== '' ? '••••••••' . substr($savedKey, -4) : null;
        aiRespond([
            'configured' => $savedKey !== '',
            'maskedKey' => $maskedKey,
        ]);
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = (string)($body['action'] ?? 'save');

        // Test Connection
        if ($action === 'test') {
            $testKey = trim((string)($body['apiKey'] ?? ''));
            if ($testKey === '') {
                $testKey = aiSettingsGetKey($pdo);
            }
            if ($testKey === '') {
                aiRespond(['error' => 'กรุณากรอก API Key หรือบันทึก API Key ก่อนทดสอบ'], 422);
            }

            $startTime = microtime(true);
            $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($testKey);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json']
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

            if ($curlError) {
                aiRespond(['error' => 'การเชื่อมต่อไปยัง Google API ขัดข้อง: ' . $curlError], 502);
            }

            $resData = json_decode((string)$response, true);
            if ($httpCode >= 200 && $httpCode < 300 && !empty($resData['models'])) {
                $modelNames = array_map(fn($m) => str_replace('models/', '', $m['name'] ?? ''), array_slice($resData['models'], 0, 5));
                aiRespond([
                    'success' => true,
                    'message' => "เชื่อมต่อ Gemini API สำเร็จ! ความเร็วตอบสนอง {$latencyMs} ms",
                    'latency_ms' => $latencyMs,
                    'models' => $modelNames
                ]);
            } else {
                $msg = $resData['error']['message'] ?? "Google API ส่งคืนสถานะ HTTP {$httpCode}";
                aiRespond(['error' => "API Key ไม่ถูกต้องหรือถูกปฏิเสธ: {$msg}"], 400);
            }
        }

        // Save Key
        $key = trim((string) ($body['apiKey'] ?? ''));
        if ($key === '') {
            aiRespond(['error' => 'กรุณากรอก API Key'], 422);
        }

        aiSettingsSetKey($key, $pdo);

        aiRespond([
            'success' => true,
            'configured' => true,
            'maskedKey' => '••••••••' . substr($key, -4),
            'message' => 'บันทึก API Key สำเร็จ'
        ]);
    }

    aiRespond(['error' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    error_log('AI settings error: ' . $e->getMessage());
    aiRespond(['error' => 'ระบบตั้งค่าขัดข้อง: ' . $e->getMessage()], 500);
}
