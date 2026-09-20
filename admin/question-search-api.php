<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/question-search-service.php';

function jsonExit(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    $engine = new HybridSearchEngine($pdo);
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // ─────────────────────────────────────────────────────────────
    // 1. HYBRID QUESTION SEARCH (Section 6, 7, 21, 22, 29)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'search' || ($method === 'GET' && $action === '')) {
        $body = ($method === 'POST') ? getJsonInput() : $_GET;

        $query = trim((string)($body['query'] ?? ''));
        $subject = trim((string)($body['subject'] ?? ''));
        $level = trim((string)($body['level'] ?? ''));
        $topic = trim((string)($body['topic'] ?? ''));
        $difficulty = trim((string)($body['difficulty'] ?? ''));
        $type = trim((string)($body['type'] ?? ''));
        $count = max(1, min(50, (int)($body['count'] ?? 10)));
        $worksheetId = !empty($body['worksheet_id']) ? (int)$body['worksheet_id'] : 0;
        $excludeExisting = !isset($body['exclude_existing']) || $body['exclude_existing'] === '1' || $body['exclude_existing'] === true || $body['exclude_existing'] === 'true';

        $excludeIds = [];
        if (!empty($body['exclude_ids'])) {
            $excludeIds = is_array($body['exclude_ids']) ? $body['exclude_ids'] : explode(',', (string)$body['exclude_ids']);
        }

        $result = $engine->search([
            'query' => $query,
            'subject' => $subject,
            'level' => $level,
            'topic' => $topic,
            'difficulty' => $difficulty,
            'type' => $type,
            'count' => $count,
            'worksheetId' => $worksheetId,
            'excludeExistingWorksheetQuestions' => $excludeExisting,
            'excludeIds' => $excludeIds
            ,'studentId' => (int)($body['student_id'] ?? 0)
            ,'actorId' => (int)($consoleUser['id'] ?? 0)
            ,'seenPolicy' => (string)($body['seen_policy'] ?? 'allow_repeat')
            ,'purpose' => (string)($body['purpose'] ?? 'question_bank')
        ]);

        jsonExit([
            'success' => true,
            'parsedIntent' => $result['parsedIntent'],
            'totalFound' => $result['totalCandidates'],
            'recommended' => $result['recommended'],
            'candidates' => $result['candidates'],
            'shortfall' => $result['shortfall'] ?? 0,
            'searchCapabilities' => $result['searchCapabilities'] ?? []
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 2. INSERT QUESTIONS TO WORKSHEET (Section 18, 19, 20)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'insert_to_worksheet' && $method === 'POST') {
        $body = getJsonInput();
        $worksheetId = (int)($body['worksheet_id'] ?? 0);
        $questionIds = isset($body['question_ids']) && is_array($body['question_ids']) ? $body['question_ids'] : [];
        $position = ($body['position'] ?? 'end') === 'start' ? 'start' : 'end';

        if (!$worksheetId) jsonExit(['error' => 'ไม่พบรหัสใบงาน'], 400);
        if (empty($questionIds)) jsonExit(['error' => 'กรุณาเลือกข้อสอบอย่างน้อย 1 ข้อ'], 422);

        $res = $engine->insertQuestionsToWorksheet($worksheetId, $questionIds, $position);

        jsonExit([
            'success' => true,
            'message' => "เพิ่มคำถาม {$res['insertedCount']} ข้อเข้าใบงานเรียบร้อยแล้ว",
            'insertedCount' => $res['insertedCount'],
            'totalQuestions' => $res['totalQuestions'],
            'topicBreakdown' => $res['topicBreakdown'],
            'difficultyBreakdown' => $res['difficultyBreakdown']
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 3. SMART MIX SEARCH (Section 15)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'smart_mix' && $method === 'POST') {
        $body = getJsonInput();
        $mixConfigs = $body['topics'] ?? []; // Array of { topic: 'Present Simple', count: 10, difficulty: 'medium' }
        if (!is_array($mixConfigs) || empty($mixConfigs)) {
            jsonExit(['error' => 'กรุณาระบุหัวข้อที่ต้องการผสมใน Smart Mix'], 422);
        }

        $allRecommended = [];
        $usedIds = [];

        foreach ($mixConfigs as $cfg) {
            $tName = trim((string)($cfg['topic'] ?? ''));
            $tCount = max(1, min(30, (int)($cfg['count'] ?? 10)));
            $tDiff = trim((string)($cfg['difficulty'] ?? ''));
            $tSubj = trim((string)($cfg['subject'] ?? ''));
            $tLvl = trim((string)($cfg['level'] ?? ''));

            if ($tName === '') continue;

            $subRes = $engine->search([
                'query' => $tName,
                'topic' => $tName,
                'subject' => $tSubj,
                'level' => $tLvl,
                'difficulty' => $tDiff,
                'count' => $tCount,
                'excludeIds' => $usedIds
            ]);

            foreach ($subRes['recommended'] as $q) {
                if (!in_array($q['id'], $usedIds, true)) {
                    $usedIds[] = $q['id'];
                    $allRecommended[] = $q;
                }
            }
        }

        jsonExit([
            'success' => true,
            'totalFound' => count($allRecommended),
            'recommended' => $allRecommended
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 4. TOPIC & DIFFICULTY DISTRIBUTION (Section 20)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'distribution') {
        $worksheetId = (int)($_GET['worksheet_id'] ?? 0);
        if (!$worksheetId) jsonExit(['error' => 'ไม่พบรหัสใบงาน'], 400);

        $dist = $engine->calculateWorksheetDistribution($worksheetId);
        jsonExit([
            'success' => true,
            'distribution' => $dist
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 5. REBUILD SEARCH INDEX (Section 25)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'reindex' && $method === 'POST') {
        $stats = $engine->reindexAll();
        jsonExit([
            'success' => true,
            'message' => 'สร้าง Search Index สำหรับคลังข้อสอบเรียบร้อยแล้ว',
            'stats' => $stats
        ]);
    }

    jsonExit(['error' => 'Invalid action'], 400);
} catch (Throwable $e) {
    jsonExit([
        'error' => 'เกิดข้อผิดพลาดในการค้นหาข้อสอบ: ' . $e->getMessage()
    ], 500);
}
