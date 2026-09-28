<?php
declare(strict_types=1);

/**
 * admin/live-remediation-api.php  — P2.2: Adaptive Remediation API
 *
 * Teacher-facing API to detect and trigger group remediation from a live session.
 *
 * GET  ?action=detect&sessionId=X        → Detect gap groups (no records created)
 * POST ?action=approve&sessionId=X       → Create remediation for approved groups
 * POST ?action=approve_all&sessionId=X   → Auto-approve all detected gaps
 * GET  ?action=status&sessionId=X        → List created interventions for session
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/live-sessions-helper.php';
require_once __DIR__ . '/../includes/live-remediation-service.php';

function remediationRespond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function remediationBody(): array
{
    $raw = file_get_contents('php://input');
    $d   = json_decode($raw ?: '{}', true);
    return is_array($d) ? $d : [];
}

$method         = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action         = trim((string) ($_GET['action'] ?? ''));
$sessionId      = trim((string) ($_GET['sessionId'] ?? (remediationBody()['sessionId'] ?? '')));
$currentUserId  = (int) ($consoleUser['id'] ?? 0);
$currentRole    = (string) ($consoleUser['role'] ?? 'teacher');

if ($sessionId === '') remediationRespond(['error' => 'sessionId required'], 422);

try {
    authorizeSessionControl($pdo, $sessionId, $currentUserId, $currentRole);
    $svc = new LiveRemediationService($pdo);

    // ── GET: Detect gap groups (read-only, no records created) ───────────────
    if ($method === 'GET' && $action === 'detect') {
        $minStudents = max(1, (int)($_GET['minStudents'] ?? 2));
        $groups      = $svc->detectGapGroups($sessionId, $minStudents);
        remediationRespond([
            'ok'           => true,
            'sessionId'    => $sessionId,
            'gapGroups'    => $groups,
            'groupCount'   => count($groups),
            'canAutoApprove' => count($groups) > 0,
        ]);
    }

    // ── POST: Approve specific groups for remediation ─────────────────────────
    if ($method === 'POST' && $action === 'approve') {
        $body   = remediationBody();
        $groups = $body['groups'] ?? []; // [{topic, studentIds, severity}]
        $note   = trim((string)($body['teacherNote'] ?? ''));

        if (empty($groups)) remediationRespond(['error' => 'groups array required'], 422);

        $allResults = [];
        foreach ($groups as $group) {
            $topic      = trim((string)($group['topic']      ?? ''));
            $studentIds = (array)($group['studentIds']   ?? []);
            $severity   = (string)($group['severity']    ?? 'moderate');
            if ($topic === '' || empty($studentIds)) continue;

            $results = $svc->createGroupRemediation($sessionId, $studentIds, $topic, $severity, $currentUserId, $note);
            $allResults[] = [
                'topic'      => $topic,
                'severity'   => $severity,
                'results'    => $results,
                'created'    => count(array_filter($results, fn($r) => empty($r['error']))),
            ];
        }

        remediationRespond([
            'ok'         => true,
            'sessionId'  => $sessionId,
            'processed'  => count($allResults),
            'details'    => $allResults,
        ], 201);
    }

    // ── POST: Approve ALL detected gap groups automatically ───────────────────
    if ($method === 'POST' && $action === 'approve_all') {
        $body        = remediationBody();
        $minStudents = max(1, (int)($body['minStudents'] ?? 2));
        $note        = trim((string)($body['teacherNote'] ?? 'Auto-remediation จาก AI Classroom Radar'));
        $groups      = $svc->detectGapGroups($sessionId, $minStudents);

        if (empty($groups)) remediationRespond(['ok' => true, 'message' => 'ไม่พบกลุ่มที่ต้องการซ่อมเสริม', 'processed' => 0]);

        $allResults = [];
        foreach ($groups as $group) {
            $results = $svc->createGroupRemediation(
                $sessionId,
                $group['studentIds'],
                $group['topic'],
                $group['severity'],
                $currentUserId,
                $note
            );
            $allResults[] = [
                'topic'    => $group['topic'],
                'severity' => $group['severity'],
                'results'  => $results,
                'created'  => count(array_filter($results, fn($r) => empty($r['error']))),
            ];
        }

        remediationRespond([
            'ok'        => true,
            'sessionId' => $sessionId,
            'processed' => count($allResults),
            'details'   => $allResults,
        ], 201);
    }

    // ── GET: Status — list interventions created from this session ────────────
    if ($method === 'GET' && $action === 'status') {
        $stmt = $pdo->prepare("
            SELECT i.id, i.student_id, i.topic_name, i.severity, i.status,
                   i.recommended_action, i.priority_score, i.teacher_notes,
                   CONCAT_WS(' ', u.first_name, u.last_name) AS student_name
            FROM teacher_interventions i
            JOIN users u ON u.id = i.student_id
            WHERE i.teacher_notes LIKE :note
            ORDER BY i.priority_score DESC, i.created_at DESC
            LIMIT 100
        ");
        $stmt->execute([':note' => '%Live Session #' . $sessionId . '%']);
        $interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        remediationRespond([
            'ok'            => true,
            'sessionId'     => $sessionId,
            'interventions' => $interventions,
            'count'         => count($interventions),
        ]);
    }

    remediationRespond(['error' => 'Invalid action'], 400);

} catch (Throwable $e) {
    error_log('Live Remediation API Error: ' . $e->getMessage());
    remediationRespond(['error' => 'ระบบเกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
}
