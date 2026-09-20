<?php
/**
 * student/get-ready-api.php
 * NEXTBEYOND V2 — Phase 2: Get Ready API
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/../includes/phase2-session-service.php';

function grRespond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$p2 = new Phase2SessionService($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$currentStudentId = (int)($currentUser['id'] ?? 0);

try {
    // GET: Fetch student readiness for an event
    if ($method === 'GET') {
        $eventId = (int)($_GET['event_id'] ?? 0);
        if ($eventId < 1) grRespond(['error' => 'event_id required'], 422);

        $event = $p2->getEventForStudent($eventId, $currentStudentId);
        if (!$event) grRespond(['error' => 'Not found or not enrolled'], 404);

        $readiness = $p2->getStudentReadiness($currentStudentId, $eventId);
        $topics    = $p2->getEventTopics($eventId);
        grRespond(['readiness' => $readiness, 'topics' => $topics]);
    }

    // POST: Save topic readiness
    if ($method === 'POST') {
        $raw  = file_get_contents('php://input');
        $body = json_decode($raw ?: '{}', true);
        if (!is_array($body)) grRespond(['error' => 'Invalid JSON'], 400);

        $eventId   = (int)($body['event_id'] ?? 0);
        $topicName = trim((string)($body['topic_name'] ?? ''));
        $status    = trim((string)($body['status'] ?? ''));

        if ($eventId < 1) grRespond(['error' => 'event_id required'], 422);
        if ($topicName === '') grRespond(['error' => 'topic_name required'], 422);
        if (!in_array($status, ['not_started', 'reviewed'], true)) {
            grRespond(['error' => 'status must be not_started or reviewed'], 422);
        }

        $event = $p2->getEventForStudent($eventId, $currentStudentId);
        if (!$event) grRespond(['error' => 'Not found or not enrolled'], 404);

        $p2->saveReadiness($currentStudentId, $eventId, $topicName, $status);
        grRespond(['success' => true, 'status' => $status]);
    }

    grRespond(['error' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    error_log('get-ready-api error: ' . $e->getMessage());
    grRespond(['error' => 'Server error'], 500);
}
