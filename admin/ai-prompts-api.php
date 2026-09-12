<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/includes/access.php';
require_once __DIR__.'/../includes/logger.php';

function out(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function body(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) out(['error' => 'Invalid JSON'], 400);
    return $data;
}

// Only admin can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    out(['error' => 'Unauthorized'], 401);
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT id, subject_name, prompt_md, is_active FROM ai_subjects ORDER BY sort_order ASC, id ASC");
        out(['subjects' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    
    if ($method === 'POST') {
        $data = body();
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['subject_name'] ?? ''));
        $prompt = (string)($data['prompt_md'] ?? '');
        $isActive = !empty($data['is_active']) ? 1 : 0;
        
        if ($name === '') {
            out(['error' => 'ชื่อวิชาห้ามว่าง'], 422);
        }
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE ai_subjects SET subject_name = :name, prompt_md = :prompt, is_active = :active WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':prompt' => $prompt,
                ':active' => $isActive,
                ':id' => $id
            ]);
            logAction($pdo, $_SESSION['user_id'], 'UPDATE_AI_SUBJECT', "Updated AI subject ID $id ($name)");
        } else {
            $stmt = $pdo->prepare("INSERT INTO ai_subjects (subject_name, prompt_md, is_active, sort_order) VALUES (:name, :prompt, :active, 99)");
            $stmt->execute([
                ':name' => $name,
                ':prompt' => $prompt,
                ':active' => $isActive
            ]);
            $id = (int) $pdo->lastInsertId();
            logAction($pdo, $_SESSION['user_id'], 'CREATE_AI_SUBJECT', "Created AI subject ID $id ($name)");
        }
        
        out(['success' => true, 'id' => $id]);
    }
    
    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM ai_subjects WHERE id = :id");
            $stmt->execute([':id' => $id]);
            logAction($pdo, $_SESSION['user_id'], 'DELETE_AI_SUBJECT', "Deleted AI subject ID $id");
        }
        out(['success' => true]);
    }
    
    out(['error' => 'Method Not Allowed'], 405);
} catch (Throwable $e) {
    error_log("AI Prompts API Error: " . $e->getMessage());
    out(['error' => 'Internal Server Error'], 500);
}
