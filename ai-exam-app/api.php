<?php
require_once __DIR__ . '/../admin/includes/access.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

// ── ดึง API Key: จาก Server DB หรือจาก request body (legacy) ──
$useServerKey = !empty($input['useServerKey']);

if ($useServerKey) {
    // โหลด key จากฐานข้อมูล (เข้ารหัส AES-256 ใน system_settings)
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/ai-settings.php';
    $apiKey = aiSettingsGetKey($pdo);
    if (empty($apiKey)) {
        http_response_code(400);
        echo json_encode(["error" => "ยังไม่ได้ตั้งค่า Gemini API Key กรุณาตั้งค่าในหน้า AI Exam → ตั้งค่า"]);
        exit();
    }
} else {
    // fallback: รับ apiKey จาก request (legacy mode)
    if (!isset($input['apiKey']) || empty($input['apiKey'])) {
        http_response_code(400);
        echo json_encode(["error" => "ไม่พบ API Key"]);
        exit();
    }
    $apiKey = $input['apiKey'];
}

$url = trim((string) ($input['url'] ?? ''));
$storedFile = basename(trim((string) ($input['storedFile'] ?? '')));
$type = $input['type'] ?? 'copy';
$count = max(1, min(50, isset($input['count']) ? (int)$input['count'] : 10));
$counts = $input['counts'] ?? null;
$details = $input['details'] ?? '';
$difficulty = $input['difficulty'] ?? '';
$shuffle = $input['shuffle'] ?? false;

function extractFileId($url) {
    if (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return $matches[1];
    }
    if (preg_match('/id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return $matches[1];
    }
    return null;
}

$fileType = 'text';
$fileData = null;

if ($storedFile !== '') {
    if (!preg_match('/^source-[a-zA-Z0-9-]+\.(pdf|txt|docx)$/', $storedFile)) {
        http_response_code(400);
        echo json_encode(["error" => "ข้อมูลไฟล์บน Server ไม่ถูกต้อง"]);
        exit();
    }
    $uploadRoot = realpath(__DIR__ . '/../assets/uploads/ai-exam');
    $serverPath = $uploadRoot ? realpath($uploadRoot . '/' . $storedFile) : false;
    if (!$serverPath || !$uploadRoot || !str_starts_with($serverPath, $uploadRoot . DIRECTORY_SEPARATOR) || !is_file($serverPath)) {
        http_response_code(404);
        echo json_encode(["error" => "ไม่พบเอกสารที่อัปโหลดบน Server"]);
        exit();
    }
    $extension = strtolower(pathinfo($serverPath, PATHINFO_EXTENSION));
    if ($extension === 'pdf') {
        $fileType = 'pdf';
        $fileData = base64_encode((string) file_get_contents($serverPath));
    } elseif ($extension === 'docx') {
        $zip = new ZipArchive();
        if ($zip->open($serverPath) !== true) {
            http_response_code(400);
            echo json_encode(["error" => "ไม่สามารถอ่านไฟล์ DOCX ได้"]);
            exit();
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            http_response_code(400);
            echo json_encode(["error" => "ไฟล์ DOCX ไม่มีเนื้อหาที่อ่านได้"]);
            exit();
        }
        $xml = str_replace(['</w:p>', '</w:tr>', '<w:tab/>'], ["\n", "\n", "\t"], $xml);
        $fileData = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    } else {
        $fileData = (string) file_get_contents($serverPath);
    }
} else {
    $fileId = extractFileId($url);
    if (!$fileId) {
        http_response_code(400);
        echo json_encode(["error" => "Google Drive URL ไม่ถูกต้อง"]);
        exit();
    }
    $exportUrl = "https://docs.google.com/document/d/{$fileId}/export?format=txt";
    $ch = curl_init($exportUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200 && !empty($response) && stripos($response, '<html') === false) {
        $fileData = $response;
    } else {
        $genericUrl = "https://drive.google.com/uc?export=download&id={$fileId}";
        $ch = curl_init($genericUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200 || stripos((string) $contentType, 'html') !== false) {
            http_response_code(400);
            echo json_encode(["error" => "ไม่สามารถอ่านไฟล์ได้ โปรดตั้งค่าแชร์เป็น 'ทุกคนที่มีลิงก์ (Anyone with the link)'"]);
            exit();
        }
        if (stripos((string) $contentType, 'pdf') !== false || substr((string) $response, 0, 5) === '%PDF-') {
            $fileType = 'pdf';
            $fileData = base64_encode((string) $response);
        } else {
            $fileData = (string) $response;
        }
    }
}

function getBestModels($apiKey) {
    $blacklist = ['deep-research', 'vision', 'embedding', 'aqa', 'tts', 'stt', 'imagen'];
    $url = "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $models = [];
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['models'])) {
            $available = [];
            foreach ($data['models'] as $m) {
                if (isset($m['supportedGenerationMethods']) && in_array('generateContent', $m['supportedGenerationMethods'])) {
                    $name = str_replace('models/', '', $m['name']);
                    $isBad = false;
                    foreach ($blacklist as $bad) {
                        if (stripos($name, $bad) !== false) {
                            $isBad = true; break;
                        }
                    }
                    if (!$isBad) {
                        $available[] = $name;
                    }
                }
            }
            $proModels = array_filter($available, function($m) { return stripos($m, 'pro') !== false; });
            $flashModels = array_filter($available, function($m) { return stripos($m, 'flash') !== false; });
            rsort($proModels);
            rsort($flashModels);
            $models = array_merge($proModels, $flashModels);
        }
    }
    if (empty($models)) {
        $models = ["gemini-1.5-pro", "gemini-1.5-flash"];
    }
    return $models;
}

$candidateModels = getBestModels($apiKey);
$finalQuestions = [];

function extractJSON($text) {
    $start = strpos($text, '[');
    $end = strrpos($text, ']');
    if ($start !== false && $end !== false) {
        $jsonStr = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($jsonStr, true);
        if ($decoded !== null) return $decoded;
    }
    throw new Exception("AI ไม่ได้ตอบกลับมาเป็นโครงสร้าง JSON Array ที่ถูกต้อง");
}

function callGemini($modelName, $apiKey, $payload, $temperature) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";
    
    if (!isset($payload['generationConfig'])) {
        $payload['generationConfig'] = [];
    }
    $payload['generationConfig']['temperature'] = (float)$temperature;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("HTTP {$httpCode}: " . $response);
    }

    $data = json_decode($response, true);
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        return $data['candidates'][0]['content']['parts'][0]['text'];
    }
    throw new Exception("รูปแบบการตอบกลับจาก API ไม่ถูกต้อง");
}

function buildPrompt($qCount, $type, $difficulty, $details, $isPdf = false, $chunkText = "", $partIndex = 1, $totalParts = 1) {
    $prompt = "";
    if ($isPdf) {
        if ($type === 'copy') {
            $prompt = "จงอ่านเอกสาร PDF ที่แนบมานี้ สกัดข้อสอบออกมาจำนวน {$qCount} ข้อ คัดลอกให้เหมือนเดิม 100% ห้ามสลับข้อเด็ดขาด";
        } else if ($type === 'similar') {
            $prompt = "จงอ่านเอกสาร PDF ที่แนบมานี้ วิเคราะห์เนื้อหาและแนวข้อสอบ จากนั้นสร้างข้อสอบใหม่จำนวน {$qCount} ข้อ ที่มีความยากและรูปแบบคล้ายกับต้นฉบับ\nเงื่อนไขเพิ่มเติม: " . ($details ?: 'ไม่มี');
        } else if ($type === 'levels') {
            $levelText = "ปานกลาง";
            if ($difficulty === 'easy') $levelText = "ง่าย (ถามตรงไปตรงมา)";
            if ($difficulty === 'medium') $levelText = "ปานกลาง (มีวิเคราะห์เล็กน้อย)";
            if ($difficulty === 'hard') $levelText = "ยาก (ต้องวิเคราะห์ลึก)";
            if ($difficulty === 'expert') $levelText = "ยากมาก (ประยุกต์สูง ซับซ้อน)";
            $prompt = "จงอ่านเอกสาร PDF ที่แนบมานี้ จากนั้นสร้างข้อสอบใหม่คุณภาพสูงสุดจำนวน {$qCount} ข้อ โดยปรับความยากให้อยู่ในระดับ **{$levelText}**\nเงื่อนไขเพิ่มเติม: " . ($details ?: 'ไม่มี');
        }
    } else {
        if ($type === 'copy') {
            $prompt = "นี่คือเนื้อหาต้นฉบับ (Part {$partIndex}/{$totalParts}):\n{$chunkText}\n\nจงสกัดข้อสอบออกมาจำนวน {$qCount} ข้อที่มีในเนื้อหานี้ คัดลอกให้เหมือนเดิม 100% ห้ามสลับข้อเด็ดขาด";
        } else if ($type === 'similar') {
            $prompt = "นี่คือเนื้อหาต้นฉบับ (Part {$partIndex}/{$totalParts}):\n{$chunkText}\n\nจงสร้างข้อสอบใหม่จำนวน {$qCount} ข้อ ที่มีความยากและรูปแบบคล้ายกับเนื้อหาในส่วนนี้\nเงื่อนไขเพิ่มเติม: " . ($details ?: 'ไม่มี');
        } else if ($type === 'levels') {
            $levelText = "ปานกลาง";
            if ($difficulty === 'easy') $levelText = "ง่าย";
            if ($difficulty === 'medium') $levelText = "ปานกลาง";
            if ($difficulty === 'hard') $levelText = "ยาก";
            if ($difficulty === 'expert') $levelText = "ยากมาก";
            $prompt = "นี่คือเนื้อหาต้นฉบับ (Part {$partIndex}/{$totalParts}):\n{$chunkText}\n\nจงสร้างข้อสอบใหม่คุณภาพสูงสุดจำนวน {$qCount} ข้อ โดยปรับความยากให้อยู่ในระดับ **{$levelText}**\nเงื่อนไขเพิ่มเติม: " . ($details ?: 'ไม่มี');
        }
    }

    $prompt .= "\n\nคำแนะนำสำคัญอย่างยิ่งในการสร้าง `questionText` (โดยเฉพาะข้อสอบ Reading / Conversation / Cloze Test):\n";
    $prompt .= "1. **ต้องรวมเนื้อเรื่อง (Passage/Conversation) ไว้ใน `questionText` ด้วยเสมอ** เพื่อให้ผู้สอบมีเนื้อหาอ่านก่อนตอบ\n";
    $prompt .= "2. **ห้ามเติมคำตอบลงในช่องว่างของเนื้อเรื่องเด็ดขาด!** หากเป็นบทสนทนาที่มีช่องว่างหลายจุด (เช่น (1), (2), (3)) ให้คงช่องว่าง `____` เอาไว้ตามเดิมทุกข้อ ห้ามนำเฉลยของข้อก่อนหน้ามาแอบเติมใส่ในเนื้อเรื่องของข้อถัดไปอย่างเด็ดขาด\n";
    $prompt .= "3. **ต้องมีประโยคคำถามที่ชัดเจนอยู่ด้านล่างสุดของ `questionText` ก่อนถึงตัวเลือก** เช่น \"ข้อ (2) ควรเติมคำใด\"\n";
    $prompt .= "4. หากไฟล์ต้นฉบับไม่มีเฉลย ให้ใช้องค์ความรู้ในฐานะครูผู้เชี่ยวชาญ ค้นหาคำตอบที่ถูกต้องที่สุดและใส่เฉลยมาใน `correctAnswerIndex` ทันที\n";
    $prompt .= "5. **ต้องอธิบายเหตุผลของคำตอบ** และใส่มาใน `explanation` ทุกข้อ เพื่อให้ผู้สอบเข้าใจว่าทำไมถึงตอบข้อนี้\n";
    $prompt .= "6. **ห้ามใส่เลขข้อ (เช่น 1., 2.) นำหน้าคำถามใน `questionText` เด็ดขาด** เพราะระบบจะรันหมายเลขข้อให้อัตโนมัติ\n\n";

    $prompt .= "ให้ตอบกลับมาเป็น JSON Array เท่านั้น โดยมีโครงสร้างดังนี้:\n";
    $prompt .= "[\n  {\n    \"questionText\": \"[เนื้อเรื่องที่ยังเว้นช่องว่างครบทุกจุด]\\n\\n[คำถาม]\",\n    \"options\": [\"A. ...\", \"B. ...\", \"C. ...\", \"D. ...\"],\n    \"correctAnswerIndex\": 0,\n    \"explanation\": \"เหตุผลที่ตอบข้อนี้...\"\n  }\n]\n";
    $prompt .= "* options สามารถใช้ ก,ข,ค,ง หรือ A,B,C,D หรือ 1,2,3,4 ได้ตามความเหมาะสมของวิชา\n";
    $prompt .= "* correctAnswerIndex ต้องเป็นตัวเลข 0, 1, 2, หรือ 3 เท่านั้น (ตำแหน่งใน options)\n";
    $prompt .= "* ห้ามใส่ Markdown คำอธิบายเพิ่มเติมใดๆ นอกเหนือจาก JSON";

    return $prompt;
}

function generateQuestions($payload, $candidateModels, $apiKey, $temperature) {
    $lastErr = null;
    foreach ($candidateModels as $modelName) {
        try {
            $responseTxt = callGemini($modelName, $apiKey, $payload, $temperature);
            return extractJSON($responseTxt);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $lastErr = $msg;
            if (stripos($msg, '429') !== false || stripos($msg, 'Quota') !== false || stripos($msg, '404') !== false || stripos($msg, '503') !== false || stripos($msg, '500') !== false) {
                continue;
            }
            throw $e;
        }
    }
    throw new Exception("สร้างข้อสอบล้มเหลว: " . $lastErr);
}

try {
    if ($fileType === 'pdf') {
        if ($type === 'levels' && !empty($counts)) {
            foreach ($counts as $lvl => $cText) {
                $c = (int)$cText;
                if ($c > 0) {
                    $prompt = buildPrompt($c, $type, $lvl, $details, true);
                    $payload = [
                        "contents" => [
                            [
                                "parts" => [
                                    ["inlineData" => ["data" => $fileData, "mimeType" => "application/pdf"]],
                                    ["text" => $prompt]
                                ]
                            ]
                        ]
                    ];
                    $qs = generateQuestions($payload, $candidateModels, $apiKey, ($type === 'copy' ? 0.1 : 0.7));
                    if (count($qs) > $c) $qs = array_slice($qs, 0, $c);
                    $finalQuestions = array_merge($finalQuestions, $qs);
                }
            }
        } else {
            $prompt = buildPrompt($count, $type, $difficulty, $details, true);
            $payload = [
                "contents" => [
                    [
                        "parts" => [
                            ["inlineData" => ["data" => $fileData, "mimeType" => "application/pdf"]],
                            ["text" => $prompt]
                        ]
                    ]
                ]
            ];
            $qs = generateQuestions($payload, $candidateModels, $apiKey, ($type === 'copy' ? 0.1 : 0.7));
            if (count($qs) > $count) $qs = array_slice($qs, 0, $count);
            $finalQuestions = $qs;
        }
    } else {
        if (trim($fileData) === '') {
            http_response_code(400);
            echo json_encode(["error" => "ไฟล์ว่างเปล่า ไม่มีเนื้อหา"]);
            exit();
        }

        function splitTextIntoChunks($text, $maxChunkSize = 25000) {
            if (strlen($text) <= $maxChunkSize) return [$text];
            $chunks = [];
            $currentChunk = "";
            $lines = explode("\n", $text);
            foreach ($lines as $line) {
                if (strlen($currentChunk) + strlen($line) > $maxChunkSize && strlen($currentChunk) > 0) {
                    $chunks[] = trim($currentChunk);
                    $currentChunk = "";
                }
                $currentChunk .= $line . "\n";
            }
            if (strlen(trim($currentChunk)) > 0) $chunks[] = trim($currentChunk);
            return $chunks;
        }

        $chunks = splitTextIntoChunks($fileData, 25000);
        $numChunks = count($chunks);

        function distributeQuestionCount($totalCount, $numParts) {
            $base = floor($totalCount / $numParts);
            $remainder = $totalCount % $numParts;
            $dist = [];
            for ($i = 0; $i < $numParts; $i++) {
                $dist[] = $base + ($i < $remainder ? 1 : 0);
            }
            return $dist;
        }

        if ($type === 'levels' && !empty($counts)) {
            foreach ($counts as $lvl => $cText) {
                $c = (int)$cText;
                if ($c > 0) {
                    $dist = distributeQuestionCount($c, $numChunks);
                    $lvlQs = [];
                    for ($i = 0; $i < $numChunks; $i++) {
                        if ($dist[$i] === 0) continue;
                        $prompt = buildPrompt($dist[$i], $type, $lvl, $details, false, $chunks[$i], $i + 1, $numChunks);
                        $payload = ["contents" => [["parts" => [["text" => $prompt]]]]];
                        $qs = generateQuestions($payload, $candidateModels, $apiKey, 0.7);
                        $lvlQs = array_merge($lvlQs, $qs);
                        if ($i < $numChunks - 1) sleep(1);
                    }
                    if (count($lvlQs) > $c) $lvlQs = array_slice($lvlQs, 0, $c);
                    $finalQuestions = array_merge($finalQuestions, $lvlQs);
                }
            }
        } else {
            $dist = distributeQuestionCount($count, $numChunks);
            for ($i = 0; $i < $numChunks; $i++) {
                if ($dist[$i] === 0 && $type !== 'copy') continue;
                $prompt = buildPrompt($dist[$i], $type, $difficulty, $details, false, $chunks[$i], $i + 1, $numChunks);
                $payload = ["contents" => [["parts" => [["text" => $prompt]]]]];
                $qs = generateQuestions($payload, $candidateModels, $apiKey, ($type === 'copy' ? 0.1 : 0.7));
                $finalQuestions = array_merge($finalQuestions, $qs);
                if ($i < $numChunks - 1) sleep(1);
            }
            if (count($finalQuestions) > $count) $finalQuestions = array_slice($finalQuestions, 0, $count);
        }
    }

    if ($shuffle) {
        $countQs = count($finalQuestions);
        for ($i = $countQs - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $temp = $finalQuestions[$i];
            $finalQuestions[$i] = $finalQuestions[$j];
            $finalQuestions[$j] = $temp;
        }
    }

    foreach ($finalQuestions as &$q) {
        if (isset($q['questionText'])) {
            $q['questionText'] = trim(preg_replace('/^(?:\*?\*?\d+\.?\)?\s*)/', '', $q['questionText']));
        }
    }
    unset($q);

    $seenQuestions = [];
    $finalQuestions = array_values(array_filter($finalQuestions, static function ($question) use (&$seenQuestions) {
        $text = trim((string) ($question['questionText'] ?? ''));
        if ($text === '') return false;
        $signature = hash('sha256', preg_replace('/\s+/u', '', mb_strtolower($text, 'UTF-8')));
        if (isset($seenQuestions[$signature])) return false;
        $seenQuestions[$signature] = true;
        return true;
    }));

    echo json_encode(["questions" => $finalQuestions]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
