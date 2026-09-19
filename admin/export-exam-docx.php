<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/db.php';

// Helper to respond with JSON error
function exportError(string $message, int $status = 400): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    // 1. Validate Exam ID
    $examId = (int) ($_POST['examId'] ?? $_POST['id'] ?? $_GET['examId'] ?? $_GET['id'] ?? 0);
    if ($examId <= 0) {
        exportError('ไม่พบรหัสแบบทดสอบ (Invalid Exam ID)', 422);
    }

    // Parse options
    $isJson = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    $body = [];
    if ($isJson) {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $body = json_decode($raw, true) ?: [];
        }
    }

    $docType = (string) ($body['docType'] ?? $_POST['docType'] ?? $_GET['docType'] ?? 'student');
    $showTitle = (bool) ($body['showTitle'] ?? $_POST['showTitle'] ?? $_GET['showTitle'] ?? true);
    $showDiagrams = (bool) ($body['showDiagrams'] ?? $_POST['showDiagrams'] ?? $_GET['showDiagrams'] ?? true);
    $showAnswers = (bool) ($body['showAnswers'] ?? $_POST['showAnswers'] ?? $_GET['showAnswers'] ?? ($docType === 'teacher'));
    $showExplanations = (bool) ($body['showExplanations'] ?? $_POST['showExplanations'] ?? $_GET['showExplanations'] ?? ($docType === 'teacher'));

    // 2. Fetch Exam Data
    $examStmt = $pdo->prepare('SELECT * FROM exams WHERE id = ? LIMIT 1');
    $examStmt->execute([$examId]);
    $exam = $examStmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        exportError('ไม่พบชุดข้อสอบนี้ในระบบ', 404);
    }

    // 3. Fetch Exam Questions
    $qStmt = $pdo->prepare(
        'SELECT id, sort_order, question_text, passage, image_url, image_prompt, options, correct_answer, explanation, skill, difficulty
         FROM exam_questions
         WHERE exam_id = ?
         ORDER BY sort_order ASC, id ASC'
    );
    $qStmt->execute([$examId]);
    $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($questions)) {
        exportError('แบบทดสอบนี้ยังไม่มีคำถาม ไม่สามารถส่งออกเป็นเอกสารได้', 422);
    }

    // 4. Normalize Exam Data into Unified Schema
    $items = [];
    $lastPassage = null;

    foreach ($questions as $index => $row) {
        $passage = trim((string) ($row['passage'] ?? ''));
        if ($passage !== '' && $passage !== $lastPassage) {
            $items[] = [
                'type' => 'passage',
                'text' => $passage,
            ];
            $lastPassage = $passage;
        }

        // Parse choices
        $rawOptions = $row['options'];
        $choices = [];
        if (is_string($rawOptions)) {
            $decoded = json_decode($rawOptions, true);
            if (is_array($decoded)) {
                $choices = array_values($decoded);
            }
        } elseif (is_array($rawOptions)) {
            $choices = array_values($rawOptions);
        }

        // Detect diagram
        $diagram = null;
        $qText = (string) ($row['question_text'] ?? '');

        // A. Check image_prompt field for JSON diagram configuration
        $imagePrompt = trim((string) ($row['image_prompt'] ?? ''));
        if ($imagePrompt !== '' && str_starts_with($imagePrompt, '{')) {
            $diagJson = json_decode($imagePrompt, true);
            if (is_array($diagJson) && !empty($diagJson['type'])) {
                $diagram = $diagJson;
            } elseif (is_array($diagJson) && !empty($diagJson['diagram'])) {
                $diagram = $diagJson['diagram'];
            }
        }

        // B. Check for embedded diagram blocks in question_text, e.g. [diagram: {...}] or ```diagram ... ```
        if (!$diagram && preg_match('/\[diagram:\s*(\{.*?\})\]/is', $qText, $matches)) {
            $diagJson = json_decode($matches[1], true);
            if (is_array($diagJson) && !empty($diagJson['type'])) {
                $diagram = $diagJson;
                // Strip the marker from question text so it doesn't display raw JSON
                $qText = trim(str_replace($matches[0], '', $qText));
            }
        }

        if (!$diagram && preg_match('/```diagram\s*(\{.*?\})\s*```/is', $qText, $matches)) {
            $diagJson = json_decode($matches[1], true);
            if (is_array($diagJson) && !empty($diagJson['type'])) {
                $diagram = $diagJson;
                $qText = trim(str_replace($matches[0], '', $qText));
            }
        }

        $items[] = [
            'type' => 'question',
            'number' => (int) ($row['sort_order'] ?: ($index + 1)),
            'text' => $qText,
            'choices' => $choices,
            'answer' => (int) ($row['correct_answer'] ?? 0),
            'explanation' => (string) ($row['explanation'] ?? ''),
            'diagram' => $diagram,
        ];
    }

    $normalizedPayload = [
        'title' => (string) ($exam['title'] ?? 'แบบทดสอบ'),
        'subtitle' => (string) ($exam['topic'] ?? ''),
        'subject' => (string) ($exam['subject'] ?? 'ทั่วไป'),
        'level' => (string) ($exam['grade'] ?? 'ทุกระดับ'),
        'time_limit_minutes' => $exam['time_limit_minutes'] !== null ? (int) $exam['time_limit_minutes'] : null,
        'options' => [
            'doc_type' => $docType,
            'show_title' => $showTitle,
            'show_diagrams' => $showDiagrams,
            'show_answers' => $showAnswers,
            'show_explanations' => $showExplanations,
        ],
        'items' => $items,
    ];

    // 5. Invoke Python Export Engine
    $serviceDir = realpath(__DIR__ . '/../services/exam-export');
    if (!$serviceDir || !is_dir($serviceDir)) {
        exportError('ไม่พบโฟลเดอร์ services/exam-export ของระบบ', 500);
    }

    // Locate Python executable
    $venvPython = $serviceDir . '/venv/bin/python3';
    $pythonCmd = (file_exists($venvPython) && is_executable($venvPython)) ? $venvPython : 'python3';
    $scriptPath = $serviceDir . '/main.py';

    $descriptors = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout (binary DOCX)
        2 => ['pipe', 'w'],  // stderr (logs / errors)
    ];

    // Detect architecture on macOS (XAMPP PHP runs as x86_64 under Rosetta, but native python is arm64)
    $archPrefix = '';
    if (PHP_OS_FAMILY === 'Darwin' && file_exists('/usr/bin/arch')) {
        $isArm64 = trim((string) @shell_exec('sysctl -n hw.optional.arm64 2>/dev/null')) === '1';
        if ($isArm64) {
            $archPrefix = '/usr/bin/arch -arm64 ';
        }
    }

    $cmd = $archPrefix . escapeshellcmd($pythonCmd) . ' ' . escapeshellarg($scriptPath);

    $process = proc_open(
        $cmd,
        $descriptors,
        $pipes,
        $serviceDir,
        ['PYTHONIOENCODING' => 'utf-8']
    );

    if (!is_resource($process)) {
        exportError('ไม่สามารถเริ่มต้นบริการสร้างเอกสาร DOCX ได้', 500);
    }

    // Send normalized JSON to Python stdin
    $jsonInput = json_encode($normalizedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    fwrite($pipes[0], $jsonInput);
    fclose($pipes[0]);

    // Read binary output from stdout
    $docxBinary = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    // Read stderr
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($exitCode !== 0 || empty($docxBinary)) {
        error_log("Exam DOCX Export Error (exit $exitCode): " . $stderr);
        $errDetail = 'เกิดข้อผิดพลาดในการประมวลผลไฟล์ Word';
        $jsonErr = json_decode($stderr, true);
        if (is_array($jsonErr) && !empty($jsonErr['error'])) {
            $errDetail = $jsonErr['error'];
        }
        exportError($errDetail, 500);
    }

    // 6. Return downloadable Word document
    $rawTitle = trim((string) ($exam['title'] ?? 'exam'));
    // Clean filename for fallback
    $safeTitle = preg_replace('/[^\w\s\p{Thai}\-]/u', '_', $rawTitle);
    if (empty($safeTitle)) {
        $safeTitle = 'exam-' . $examId;
    }
    $filenameUtf8 = $safeTitle . '.docx';
    $filenameAscii = 'exam-' . $examId . '.docx';

    // Clear any previous output buffering
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filenameAscii . '"; filename*=UTF-8\'\'' . rawurlencode($filenameUtf8));
    header('Content-Length: ' . strlen($docxBinary));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    echo $docxBinary;
    exit;

} catch (Throwable $e) {
    error_log('Export DOCX Exception: ' . $e->getMessage());
    exportError('เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(), 500);
}
