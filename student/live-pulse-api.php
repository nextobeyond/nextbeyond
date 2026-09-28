<?php
declare(strict_types=1);

/**
 * student/live-pulse-api.php  — P1.1: Student side — answer a pulse question
 *
 * POST ?action=answer
 *   body: { sessionId, questionId, chosenKey }
 *
 * GET  ?action=status&sessionId=X
 *   Returns whether a pulse question is active and whether this student already answered
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/guard.php';

function studentPulseRespond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function studentPulseBody(): array
{
    $raw = file_get_contents('php://input');
    $d   = json_decode($raw ?: '{}', true);
    return is_array($d) ? $d : [];
}

$method    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action    = trim((string) ($_GET['action'] ?? ''));
$studentId = (int) ($currentUser['id'] ?? 0);

try {

    // ── GET: Active pulse question status for student ─────────────────────────
    if ($method === 'GET' && $action === 'status') {
        $sessionId = trim((string) ($_GET['sessionId'] ?? ''));
        if ($sessionId === '') studentPulseRespond(['error' => 'sessionId required'], 422);

        // Verify participation
        $chk = $pdo->prepare("SELECT 1 FROM session_participants WHERE session_id = :sid AND student_id = :uid LIMIT 1");
        $chk->execute([':sid' => $sessionId, ':uid' => $studentId]);
        if (!$chk->fetchColumn()) studentPulseRespond(['error' => 'ไม่พบการเข้าร่วม session'], 403);

        // Active pulse question (hide correct_key from student)
        $stmt = $pdo->prepare("
            SELECT id, question_text, options, expires_at
            FROM session_pulse_questions
            WHERE session_id = :sid AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':sid' => $sessionId]);
        $pq = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pq) {
            studentPulseRespond(['active' => false, 'question' => null, 'answered' => false]);
        }

        // Did this student already answer?
        $ansStmt = $pdo->prepare("SELECT chosen_key, is_correct FROM session_pulse_answers WHERE question_id = :qid AND student_id = :uid LIMIT 1");
        $ansStmt->execute([':qid' => $pq['id'], ':uid' => $studentId]);
        $existingAnswer = $ansStmt->fetch(PDO::FETCH_ASSOC);

        studentPulseRespond([
            'active'   => true,
            'question' => [
                'id'        => $pq['id'],
                'text'      => $pq['question_text'],
                'options'   => json_decode((string)$pq['options'], true) ?: [],
                'expiresAt' => $pq['expires_at'],
            ],
            'answered'   => (bool)$existingAnswer,
            'chosenKey'  => $existingAnswer ? $existingAnswer['chosen_key'] : null,
            'isCorrect'  => $existingAnswer ? (bool)$existingAnswer['is_correct'] : null,
        ]);
    }

    // ── POST: Student submits answer to pulse question ────────────────────────
    if ($method === 'POST' && $action === 'answer') {
        $body       = studentPulseBody();
        $sessionId  = trim((string) ($body['sessionId']  ?? ''));
        $questionId = trim((string) ($body['questionId'] ?? ''));
        $chosenKey  = trim((string) ($body['chosenKey']  ?? ''));

        if ($sessionId === '' || $questionId === '' || $chosenKey === '') {
            studentPulseRespond(['error' => 'sessionId, questionId, chosenKey required'], 422);
        }

        // Verify participation
        $chk = $pdo->prepare("SELECT 1 FROM session_participants WHERE session_id = :sid AND student_id = :uid LIMIT 1");
        $chk->execute([':sid' => $sessionId, ':uid' => $studentId]);
        if (!$chk->fetchColumn()) studentPulseRespond(['error' => 'ไม่พบการเข้าร่วม session'], 403);

        // Verify question is active and belongs to this session
        $pqStmt = $pdo->prepare("
            SELECT id, correct_key, is_active, expires_at
            FROM session_pulse_questions
            WHERE id = :qid AND session_id = :sid
            LIMIT 1
        ");
        $pqStmt->execute([':qid' => $questionId, ':sid' => $sessionId]);
        $pq = $pqStmt->fetch(PDO::FETCH_ASSOC);

        if (!$pq) studentPulseRespond(['error' => 'ไม่พบคำถาม'], 404);
        if (empty($pq['is_active'])) studentPulseRespond(['ok' => false, 'error' => 'คำถามนี้ปิดรับคำตอบแล้ว'], 409);

        // Check expiry
        if ($pq['expires_at'] && strtotime($pq['expires_at']) < time()) {
            studentPulseRespond(['ok' => false, 'error' => 'หมดเวลาตอบคำถาม'], 409);
        }

        // Determine correctness (null correct_key = opinion poll, always "correct")
        $isCorrect = $pq['correct_key'] === null ? 1
                   : ((string)$pq['correct_key'] === $chosenKey ? 1 : 0);

        // Upsert: one answer per student per question
        $pdo->prepare("
            INSERT INTO session_pulse_answers (question_id, session_id, student_id, chosen_key, is_correct)
            VALUES (:qid, :sid, :uid, :key, :correct)
            ON DUPLICATE KEY UPDATE chosen_key = :key, is_correct = :correct, answered_at = NOW()
        ")->execute([
            ':qid'     => $questionId,
            ':sid'     => $sessionId,
            ':uid'     => $studentId,
            ':key'     => $chosenKey,
            ':correct' => $isCorrect,
        ]);

        // Touch session updated_at so SSE + teacher poll picks up new vote count
        $pdo->prepare("UPDATE classroom_sessions SET updated_at = NOW() WHERE id = :sid")
            ->execute([':sid' => $sessionId]);

        studentPulseRespond([
            'ok'        => true,
            'success'   => true,
            'chosenKey' => $chosenKey,
            'isCorrect' => (bool)$isCorrect,
            'hasCorrectAnswer' => $pq['correct_key'] !== null,
        ]);
    }

    studentPulseRespond(['error' => 'Invalid action or method'], 400);

} catch (Throwable $e) {
    error_log('Student Pulse API Error: ' . $e->getMessage());
    studentPulseRespond(['error' => 'ระบบเกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
}
