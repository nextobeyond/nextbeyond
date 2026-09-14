<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../admin/includes/access.php';

require_once __DIR__ . '/question-validation.php';

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestBody(): array
{
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        respond(['error' => 'ข้อมูล JSON ไม่ถูกต้อง'], 400);
    }
    return $body;
}

function tableColumns(PDO $pdo, string $table): array
{
    $columns = [];
    foreach ($pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll() as $column) {
        $columns[(string) $column['Field']] = (string) $column['Type'];
    }
    return $columns;
}

function ensureExamSchema(PDO $pdo): void
{
    $examColumns = tableColumns($pdo, 'exams');
    $examAdditions = [
        'source_mode' => "ADD COLUMN `source_mode` VARCHAR(20) NOT NULL DEFAULT 'document'",
        'generation_request_id' => "ADD COLUMN `generation_request_id` VARCHAR(36) NULL UNIQUE",
        'source_url' => "ADD COLUMN `source_url` VARCHAR(1000) NULL AFTER `is_ai_generated`",
        'generation_mode' => "ADD COLUMN `generation_mode` ENUM('copy','similar','levels') NULL AFTER `source_url`",
        'is_published' => "ADD COLUMN `is_published` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`",
        'requires_login' => "ADD COLUMN `requires_login` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_published`",
    ];
    foreach ($examAdditions as $name => $definition) {
        if (!isset($examColumns[$name])) {
            $pdo->exec("ALTER TABLE `exams` {$definition}");
        }
    }
    if (!str_contains(strtolower($examColumns['type'] ?? ''), "'quiz'")) {
        $pdo->exec("ALTER TABLE `exams` MODIFY COLUMN `type` ENUM('pretest','posttest','quiz','placement') NOT NULL DEFAULT 'quiz'");
    }
    if (!str_contains(strtolower($examColumns['status'] ?? ''), "'archived'")) {
        $pdo->exec("ALTER TABLE `exams` MODIFY COLUMN `status` ENUM('draft','active','closed','archived') NOT NULL DEFAULT 'draft'");
    }

    $questionColumns = tableColumns($pdo, 'exam_questions');
    $questionAdditions = [
        'passage' => "ADD COLUMN `passage` MEDIUMTEXT NULL AFTER `question_text`",
        'difficulty' => "ADD COLUMN `difficulty` VARCHAR(50) NULL AFTER `skill`",
        'created_at' => "ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `difficulty`",
    ];
    foreach ($questionAdditions as $name => $definition) {
        if (!isset($questionColumns[$name])) {
            $pdo->exec("ALTER TABLE `exam_questions` {$definition}");
        }
    }
}

try {
    ensureExamSchema($pdo);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        if (($_GET['view'] ?? '') === 'questions') {
            $params = [];
            $where = [];
            if (!empty($_GET['exam'])) {
                $where[] = 'q.exam_id = :exam_id';
                $params[':exam_id'] = (int) $_GET['exam'];
            }
            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $stmt = $pdo->prepare(
                "SELECT q.id, q.exam_id, q.sort_order, q.question_text, q.options,
                        q.correct_answer, q.explanation, q.skill, q.difficulty,
                        e.title AS exam_title, e.subject, e.grade, e.type,
                        e.is_ai_generated, e.status
                 FROM exam_questions q
                 INNER JOIN exams e ON e.id = q.exam_id
                 {$whereSql}
                 ORDER BY q.exam_id DESC, q.sort_order ASC, q.id ASC"
            );
            $stmt->execute($params);
            $questions = array_map(static function (array $row): array {
                $options = json_decode((string) $row['options'], true);
                return [
                    'id' => (string) $row['id'],
                    'examId' => (string) $row['exam_id'],
                    'sortOrder' => (int) $row['sort_order'],
                    'questionText' => $row['question_text'],
                    'options' => is_array($options) ? $options : [],
                    'correctAnswer' => (int) $row['correct_answer'],
                    'explanation' => $row['explanation'],
                    'skill' => $row['skill'],
                    'difficulty' => $row['difficulty'],
                    'examTitle' => $row['exam_title'],
                    'subject' => $row['subject'],
                    'grade' => $row['grade'],
                    'type' => $row['type'],
                    'isAiGenerated' => (bool) $row['is_ai_generated'],
                    'status' => $row['status'],
                ];
            }, $stmt->fetchAll());
            respond(['questions' => $questions]);
        }
        $sql = "SELECT e.id, e.title, e.subject, e.grade, e.topic, e.difficulty,
                       e.type, e.time_limit_minutes, e.is_ai_generated, e.status, e.source_mode,
                       e.is_published, e.requires_login, e.created_at, e.updated_at,
                       COUNT(DISTINCT q.id) AS question_count,
                       COUNT(DISTINCT a.id) AS attempt_count
                FROM exams e
                LEFT JOIN exam_questions q ON q.exam_id = e.id
                LEFT JOIN test_attempts a ON a.exam_id = e.id AND a.completed_at IS NOT NULL
                GROUP BY e.id
                ORDER BY e.created_at DESC, e.id DESC";
        $rows = $pdo->query($sql)->fetchAll();
        $exams = array_map(static function (array $row): array {
            return [
                'id' => (string) $row['id'],
                'title' => $row['title'],
                'subject' => $row['subject'],
                'grade' => $row['grade'],
                'topic' => $row['topic'],
                'difficulty' => $row['difficulty'],
                'type' => $row['type'],
                'sourceMode' => $row['source_mode'],
                'timeLimitMinutes' => $row['time_limit_minutes'] === null ? null : (int) $row['time_limit_minutes'],
                'isAiGenerated' => (bool) $row['is_ai_generated'],
                'status' => $row['status'],
                'isPublished' => (bool) $row['is_published'],
                'requiresLogin' => (bool) $row['requires_login'],
                'questionCount' => (int) $row['question_count'],
                'attemptCount' => (int) $row['attempt_count'],
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at'],
            ];
        }, $rows);
        respond(['exams' => $exams]);
    }

    if ($method === 'POST') {
        $body = requestBody();
        $questions = $body['questions'] ?? null;
        if (!is_array($questions) || count($questions) === 0) {
            respond(['error' => 'ข้อสอบต้องมีคำถามอย่างน้อย 1 ข้อ'], 422);
        }

        try {
            $questions = validateExamQuestions($questions);
        } catch (InvalidArgumentException $error) {
            respond(['error' => $error->getMessage()], 422);
        }

        $requestId = $body['requestId'] ?? null;
        if ($requestId !== null && (!is_string($requestId) || !preg_match('/^[a-f0-9-]{36}$/i', $requestId))) {
            respond(['error' => 'รหัสการบันทึกไม่ถูกต้อง'], 422);
        }
        if ($requestId !== null) {
            $existing = $pdo->prepare('SELECT id FROM exams WHERE generation_request_id = ? AND created_by = ?');
            $existing->execute([$requestId, $consoleUser['id']]);
            if ($id = $existing->fetchColumn()) respond(['success' => true, 'examId' => (int) $id]);
        }

        $title = trim((string) ($body['title'] ?? ''));
        if ($title === '') {
            $title = 'แบบทดสอบจาก AI ' . date('d/m/Y H:i');
        }

        $pdo->beginTransaction();
        $examStmt = $pdo->prepare(
            "INSERT INTO exams
                (generation_request_id, title, subject, grade, topic, difficulty, type, time_limit_minutes,
                 is_ai_generated, source_url, source_mode, generation_mode, created_by, status, is_published, requires_login)
             VALUES
                (:request_id, :title, :subject, :grade, :topic, :difficulty, :type, :time_limit,
                 1, :source_url, :source_mode, :generation_mode, :created_by, 'draft', 0, 1)"
        );
        $examStmt->execute([
            ':request_id' => $requestId,
            ':created_by' => (int) $consoleUser['id'],
            ':title' => $title,
            ':subject' => trim((string) ($body['subject'] ?? 'ทั่วไป')),
            ':grade' => trim((string) ($body['grade'] ?? 'ทุกระดับ')),
            ':topic' => trim((string) ($body['topic'] ?? '')) ?: null,
            ':difficulty' => trim((string) ($body['difficulty'] ?? '')) ?: null,
            ':type' => 'quiz',
            ':time_limit' => isset($body['timeLimitMinutes']) ? (int) $body['timeLimitMinutes'] : null,
            ':source_mode' => ($body['sourceMode'] ?? 'document') === 'brief' ? 'brief' : 'document',
            ':source_url' => trim((string) ($body['sourceUrl'] ?? '')) ?: null,
            ':generation_mode' => in_array(($body['generationMode'] ?? null), ['copy', 'similar', 'levels'], true)
                ? $body['generationMode'] : null,
        ]);
        $examId = (int) $pdo->lastInsertId();

        $questionStmt = $pdo->prepare(
            "INSERT INTO exam_questions
                (exam_id, sort_order, question_text, passage, options, correct_answer, explanation, skill, difficulty)
             VALUES
                (:exam_id, :sort_order, :question_text, :passage, :options, :correct_answer, :explanation, :skill, :difficulty)"
        );
        foreach ($questions as $index => $question) {
            if (!is_array($question) || !isset($question['options']) || !is_array($question['options'])) {
                throw new RuntimeException('รูปแบบคำถามข้อที่ ' . ($index + 1) . ' ไม่ถูกต้อง');
            }
            $questionText = trim((string) ($question['questionText'] ?? $question['question'] ?? ''));
            if ($questionText === '') {
                throw new RuntimeException('คำถามข้อที่ ' . ($index + 1) . ' ไม่มีข้อความ');
            }
            $correctAnswer = (int) ($question['correctAnswerIndex'] ?? $question['correctAnswer'] ?? 0);
            $questionStmt->execute([
                ':exam_id' => $examId,
                ':sort_order' => $index + 1,
                ':question_text' => $questionText,
                ':passage' => $question['passage'] ?? null,
                ':options' => json_encode(array_values($question['options']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':correct_answer' => $correctAnswer,
                ':explanation' => $question['explanation'] ?? null,
                ':skill' => $question['skill'] ?? null,
                ':difficulty' => $question['difficulty'] ?? null,
            ]);
        }
        $pdo->commit();
        respond(['success' => true, 'examId' => $examId], 201);
    }

    if ($method === 'PATCH') {
        $body = requestBody();
        if (($body['entity'] ?? '') === 'question') {
            $id = (int) ($body['id'] ?? 0);
            $questionText = trim((string) ($body['questionText'] ?? ''));
            $options = $body['options'] ?? [];
            $correctAnswer = (int) ($body['correctAnswer'] ?? -1);
            if (is_array($options)) {
                $options = array_map(static fn(mixed $option): string => trim((string) $option), array_values($options));
            }
            if ($id < 1 || $questionText === '' || mb_strlen($questionText) > 10000 || !is_array($options) ||
                count($options) < 2 || count($options) > 10 || in_array('', $options, true) ||
                array_filter($options, static fn(string $option): bool => mb_strlen($option) > 2000) ||
                $correctAnswer < 0 || $correctAnswer >= count($options)) {
                respond(['error' => 'ข้อมูลคำถามไม่ถูกต้อง'], 422);
            }
            $stmt = $pdo->prepare(
                'UPDATE exam_questions SET question_text = :question_text, options = :options,
                 correct_answer = :correct_answer, explanation = :explanation, skill = :skill,
                 difficulty = :difficulty WHERE id = :id'
            );
            $stmt->execute([
                ':question_text' => $questionText,
                ':options' => json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':correct_answer' => $correctAnswer,
                ':explanation' => trim((string) ($body['explanation'] ?? '')) ?: null,
                ':skill' => trim((string) ($body['skill'] ?? '')) ?: null,
                ':difficulty' => trim((string) ($body['difficulty'] ?? '')) ?: null,
                ':id' => $id,
            ]);
            respond(['success' => true]);
        }
        $id = (int) ($body['id'] ?? 0);
        $fieldMap = [
            'title' => 'title',
            'type' => 'type',
            'status' => 'status',
            'timeLimitMinutes' => 'time_limit_minutes',
            'isPublished' => 'is_published',
            'requiresLogin' => 'requires_login',
        ];
        $field = (string) ($body['field'] ?? '');
        if ($id < 1 || !isset($fieldMap[$field])) {
            respond(['error' => 'ข้อมูลที่ต้องการแก้ไขไม่ถูกต้อง'], 422);
        }
        $value = $body['value'] ?? null;
        if ($field === 'title') {
            $value = trim((string) $value);
            if ($value === '' || mb_strlen($value) > 300) {
                respond(['error' => 'ชื่อแบบทดสอบต้องมี 1-300 ตัวอักษร'], 422);
            }
        }
        if ($field === 'timeLimitMinutes') {
            if ($value === '' || $value === null) {
                $value = null;
            } elseif (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1 || (int) $value > 600) {
                respond(['error' => 'เวลาทำข้อสอบต้องอยู่ระหว่าง 1-600 นาที หรือเว้นว่างหากไม่จำกัดเวลา'], 422);
            } else {
                $value = (int) $value;
            }
        }
        $allowedValues = [
            'type' => ['pretest', 'posttest', 'quiz', 'placement'],
            'status' => ['draft', 'active', 'closed', 'archived'],
        ];
        if (isset($allowedValues[$field]) && !in_array($value, $allowedValues[$field], true)) {
            respond(['error' => 'ค่าที่เลือกไม่ถูกต้อง'], 422);
        }
        if (in_array($field, ['isPublished', 'requiresLogin'], true)) {
            $value = $value ? 1 : 0;
        }
        if ($field === 'isPublished') {
            $status = $value === 1 ? 'active' : 'closed';
            $stmt = $pdo->prepare('UPDATE exams SET is_published = :published, status = :status WHERE id = :id');
            $stmt->execute([':published' => $value, ':status' => $status, ':id' => $id]);
            respond(['success' => true, 'status' => $status]);
        }
        $stmt = $pdo->prepare("UPDATE exams SET {$fieldMap[$field]} = :value WHERE id = :id");
        $stmt->execute([':value' => $value, ':id' => $id]);
        respond(['success' => true]);
    }

    if ($method === 'DELETE') {
        if (($_GET['entity'] ?? '') === 'question') {
            $id = (int) ($_GET['id'] ?? 0);
            if ($id < 1) respond(['error' => 'ไม่พบรหัสคำถาม'], 422);
            $stmt = $pdo->prepare('DELETE FROM exam_questions WHERE id = :id');
            $stmt->execute([':id' => $id]);
            respond(['success' => true]);
        }
        $id = (int) ($_GET['id'] ?? 0);
        if ($id < 1) {
            respond(['error' => 'ไม่พบรหัสข้อสอบ'], 422);
        }
        $stmt = $pdo->prepare('DELETE FROM exams WHERE id = :id');
        $stmt->execute([':id' => $id]);
        respond(['success' => true]);
    }

    respond(['error' => 'Method not allowed'], 405);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Exam API error: ' . $error->getMessage());
    respond(['error' => 'ระบบฐานข้อมูลข้อสอบขัดข้อง กรุณาตรวจสอบว่ารัน exam_module.sql แล้ว'], 500);
}
