<?php
/**
 * admin/inspect-api.php
 * API สำหรับ Admin เข้าโหมดตรวจระบบในมุมมองนักเรียน
 *
 * POST { action: "start", student_id: <int> }  → เริ่ม inspection
 * POST { action: "stop" }                        → ออกจาก inspection
 * GET                                            → สถานะปัจจุบัน
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/access.php'; // ตรวจสิทธิ์ admin/teacher

// เฉพาะ admin เท่านั้น
if ($consoleUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'เฉพาะ Admin เท่านั้น'], JSON_UNESCAPED_UNICODE);
    exit;
}

function outJson(array $d, int $s = 200): never {
    http_response_code($s);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// GET — คืนสถานะ
if ($method === 'GET') {
    $inspectId = $_SESSION['admin_inspect_student_id'] ?? null;
    if ($inspectId) {
        $stmt = $pdo->prepare('SELECT id, first_name, last_name, nickname, email, grade, avatar_url FROM users WHERE id = ? AND role = "student" LIMIT 1');
        $stmt->execute([(int)$inspectId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($student) {
            outJson(['inspecting' => true, 'student' => $student]);
        }
    }
    outJson(['inspecting' => false]);
}

// POST — start / stop
if ($method === 'POST') {
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) {
        outJson(['error' => 'ข้อมูลไม่ถูกต้อง'], 400);
    }

    $action = (string)($body['action'] ?? '');

    if ($action === 'stop') {
        unset($_SESSION['admin_inspect_student_id']);
        outJson(['success' => true, 'inspecting' => false]);
    }

    if ($action === 'start') {
        $studentId = (int)($body['student_id'] ?? 0);
        if ($studentId <= 0) {
            outJson(['error' => 'ไม่พบรหัสนักเรียน'], 400);
        }

        $stmt = $pdo->prepare('SELECT id, first_name, last_name, nickname, email, grade, avatar_url, is_active FROM users WHERE id = ? AND role = "student" LIMIT 1');
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            outJson(['error' => 'ไม่พบนักเรียนในระบบ'], 404);
        }
        if (!$student['is_active']) {
            outJson(['error' => 'บัญชีนักเรียนนี้ถูกระงับ'], 403);
        }

        $_SESSION['admin_inspect_student_id'] = $studentId;

        outJson([
            'success'    => true,
            'inspecting' => true,
            'student'    => $student,
            'redirect'   => '../student/index.php',
        ]);
    }

    outJson(['error' => 'action ไม่ถูกต้อง'], 400);
}

outJson(['error' => 'Method not allowed'], 405);
