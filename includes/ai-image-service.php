<?php
declare(strict_types=1);

final class AiImageService
{
    private const MODELS = ['gemini-3.1-flash-image', 'gemini-2.5-flash-image'];
    private const MIME_EXTENSIONS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public static function generate(string $apiKey, string $prompt, array $context = []): array
    {
        $prompt = trim($prompt);
        if ($prompt === '' || mb_strlen($prompt) > 4000) {
            throw new InvalidArgumentException('คำสั่งสร้างภาพต้องมีความยาว 1-4,000 ตัวอักษร');
        }
        $grade = trim((string) ($context['grade'] ?? ''));
        $subject = trim((string) ($context['subject'] ?? ''));
        $style = trim((string) ($context['style'] ?? 'ภาพประกอบการศึกษาที่สะอาดและอ่านง่าย'));
        $fullPrompt = "Create one accurate educational illustration. Subject: {$subject}. Learner level: {$grade}. Style: {$style}. "
            . "Do not reveal an answer. Do not add captions, labels, letters, numbers, watermarks, logos, or decorative text unless essential to the requested scientific diagram. "
            . "Keep the main subject fully visible with a clear composition. Illustration brief: {$prompt}";

        $lastError = 'ไม่พบโมเดลสร้างภาพที่ใช้งานได้';
        foreach (self::MODELS as $model) {
            try {
                $response = self::request($apiKey, $model, $fullPrompt);
                foreach ($response['candidates'][0]['content']['parts'] ?? [] as $part) {
                    if (!empty($part['thought']) || empty($part['inlineData']['data'])) continue;
                    return self::store((string) ($part['inlineData']['mimeType'] ?? ''), (string) $part['inlineData']['data'], $model, $prompt);
                }
                $lastError = 'AI ตอบกลับโดยไม่มีไฟล์ภาพ';
            } catch (RuntimeException $error) {
                $lastError = $error->getMessage();
                if (!preg_match('/HTTP (404|429|500|503)/', $lastError)) throw $error;
            }
        }
        throw new RuntimeException($lastError);
    }

    private static function request(string $apiKey, string $model, string $prompt): array
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
        $payload = ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['responseModalities' => ['IMAGE']]];
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 180, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR)]);
        $body = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $curlError = curl_error($ch); curl_close($ch);
        if ($body === false) throw new RuntimeException('เชื่อมต่อ AI สร้างภาพไม่สำเร็จ: ' . $curlError);
        $data = json_decode((string) $body, true);
        if ($status !== 200) {
            $detail = (string) ($data['error']['message'] ?? 'Gemini Image API ตอบกลับผิดพลาด');
            throw new RuntimeException("HTTP {$status}: {$detail}");
        }
        if (!is_array($data)) throw new RuntimeException('AI ส่งข้อมูลภาพกลับมาไม่สมบูรณ์');
        return $data;
    }

    private static function store(string $declaredMime, string $encoded, string $model, string $prompt): array
    {
        $bytes = base64_decode($encoded, true);
        if ($bytes === false || strlen($bytes) < 100 || strlen($bytes) > 15 * 1024 * 1024) {
            throw new RuntimeException('ไฟล์ภาพจาก AI ไม่ถูกต้องหรือมีขนาดใหญ่เกินไป');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!isset(self::MIME_EXTENSIONS[$mime]) || ($declaredMime !== '' && !isset(self::MIME_EXTENSIONS[$declaredMime]))) {
            throw new RuntimeException('ชนิดไฟล์ภาพจาก AI ไม่ได้รับอนุญาต');
        }
        $relativeDir = 'uploads/ai-images/' . date('Y/m');
        $absoluteDir = dirname(__DIR__) . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('สร้างโฟลเดอร์เก็บภาพไม่ได้');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . self::MIME_EXTENSIONS[$mime];
        if (file_put_contents($absoluteDir . '/' . $filename, $bytes, LOCK_EX) === false) {
            throw new RuntimeException('บันทึกภาพลงเซิร์ฟเวอร์ไม่ได้');
        }
        return ['url' => '/' . $relativeDir . '/' . $filename, 'mimeType' => $mime, 'bytes' => strlen($bytes),
            'model' => $model, 'prompt' => $prompt, 'status' => 'draft'];
    }
}
