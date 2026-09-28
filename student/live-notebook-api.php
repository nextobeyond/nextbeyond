<?php
declare(strict_types=1);

/**
 * student/live-notebook-api.php  — P2.3: Student Live Notebook
 *
 * A per-session note-taking system. Students can jot notes during class.
 * Notes are auto-saved, session-linked, and available for review later.
 *
 * GET  ?action=load&sessionId=X    → Load notes for this session
 * POST ?action=save&sessionId=X    → Auto-save note content
 * GET  ?action=history             → List all sessions with notes (study review)
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/guard.php';

// ── Schema ────────────────────────────────────────────────────────────────────
function ensureNotebookSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `session_student_notes` (
            `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `session_id`   VARCHAR(64)  NOT NULL,
            `student_id`   INT          NOT NULL,
            `content`      MEDIUMTEXT   NOT NULL DEFAULT '',
            `word_count`   SMALLINT     NOT NULL DEFAULT 0,
            `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_note_session_student` (`session_id`, `student_id`),
            INDEX `idx_ssn_student` (`student_id`),
            INDEX `idx_ssn_session` (`session_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function notebookRespond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function notebookBody(): array
{
    $raw = file_get_contents('php://input');
    $d   = json_decode($raw ?: '{}', true);
    return is_array($d) ? $d : [];
}

ensureNotebookSchema($pdo);

$method    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action    = trim((string) ($_GET['action'] ?? ''));
$studentId = (int) ($currentUser['id'] ?? 0);

try {

    // ── GET: Load notes for a session ─────────────────────────────────────────
    if ($method === 'GET' && $action === 'load') {
        $sessionId = trim((string) ($_GET['sessionId'] ?? ''));
        if ($sessionId === '') notebookRespond(['error' => 'sessionId required'], 422);

        // Verify participation (student must be in session)
        $chk = $pdo->prepare("SELECT 1 FROM session_participants WHERE session_id = :sid AND student_id = :uid LIMIT 1");
        $chk->execute([':sid' => $sessionId, ':uid' => $studentId]);
        if (!$chk->fetchColumn()) notebookRespond(['error' => 'ไม่พบการเข้าร่วม session'], 403);

        $stmt = $pdo->prepare("
            SELECT content, word_count, created_at, updated_at
            FROM session_student_notes
            WHERE session_id = :sid AND student_id = :uid
            LIMIT 1
        ");
        $stmt->execute([':sid' => $sessionId, ':uid' => $studentId]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch session title for context
        $stmtS = $pdo->prepare("SELECT title, started_at FROM classroom_sessions WHERE id = :sid LIMIT 1");
        $stmtS->execute([':sid' => $sessionId]);
        $session = $stmtS->fetch(PDO::FETCH_ASSOC);

        notebookRespond([
            'ok'           => true,
            'sessionId'    => $sessionId,
            'sessionTitle' => $session ? $session['title'] : '',
            'sessionDate'  => $session ? $session['started_at'] : '',
            'content'      => $note ? (string)$note['content']  : '',
            'wordCount'    => $note ? (int)$note['word_count']   : 0,
            'updatedAt'    => $note ? $note['updated_at'] : null,
        ]);
    }

    // ── POST: Auto-save note content ──────────────────────────────────────────
    if ($method === 'POST' && $action === 'save') {
        $body      = notebookBody();
        $sessionId = trim((string) ($body['sessionId'] ?? ''));
        $content   = (string) ($body['content']   ?? '');
        if ($sessionId === '') notebookRespond(['error' => 'sessionId required'], 422);

        // Verify participation
        $chk = $pdo->prepare("SELECT 1 FROM session_participants WHERE session_id = :sid AND student_id = :uid LIMIT 1");
        $chk->execute([':sid' => $sessionId, ':uid' => $studentId]);
        if (!$chk->fetchColumn()) notebookRespond(['error' => 'ไม่พบการเข้าร่วม session'], 403);

        // Sanitize content (max 20,000 chars)
        $content   = mb_substr($content, 0, 20000);
        $wordCount = str_word_count(strip_tags($content));

        $pdo->prepare("
            INSERT INTO session_student_notes (session_id, student_id, content, word_count)
            VALUES (:sid, :uid, :content, :wc)
            ON DUPLICATE KEY UPDATE content = :content, word_count = :wc, updated_at = NOW()
        ")->execute([
            ':sid'     => $sessionId,
            ':uid'     => $studentId,
            ':content' => $content,
            ':wc'      => $wordCount,
        ]);

        notebookRespond([
            'ok'        => true,
            'saved'     => true,
            'wordCount' => $wordCount,
            'savedAt'   => date('Y-m-d H:i:s'),
        ]);
    }

    // ── GET: History — all sessions this student has notes in ────────────────
    if ($method === 'GET' && $action === 'history') {
        $stmt = $pdo->prepare("
            SELECT n.session_id, n.word_count, n.updated_at,
                   cs.title AS session_title, cs.started_at, cs.ended_at,
                   COALESCE(CONCAT_WS(' ', u.first_name, u.last_name), 'คุณครู') AS teacher_name
            FROM session_student_notes n
            JOIN classroom_sessions cs ON cs.id = n.session_id
            LEFT JOIN users u ON u.id = cs.teacher_id
            WHERE n.student_id = :uid AND n.content <> ''
            ORDER BY n.updated_at DESC
            LIMIT 30
        ");
        $stmt->execute([':uid' => $studentId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        notebookRespond([
            'ok'      => true,
            'history' => $history,
            'count'   => count($history),
        ]);
    }

    notebookRespond(['error' => 'Invalid action'], 400);

} catch (Throwable $e) {
    error_log('Notebook API Error: ' . $e->getMessage());
    notebookRespond(['error' => 'ระบบเกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
}
