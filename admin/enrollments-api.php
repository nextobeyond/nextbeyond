<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/enrollments-service.php';

function jsonOut(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    $service = new EnrollmentService($pdo);
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $adminId = (int)($consoleUser['id'] ?? 2);
    $adminName = trim(($consoleUser['first_name'] ?? 'Admin') . ' ' . ($consoleUser['last_name'] ?? ''));

    // ─────────────────────────────────────────────────────────────
    // 1. GET STUDENT ENROLLMENTS & AUDIT LOGS (Section 3, 29)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'student_enrollments') {
        $studentId = (int)($_GET['student_id'] ?? 0);
        if (!$studentId) jsonOut(['error' => 'ไม่พบรหัสนักเรียน'], 400);

        $enrollments = $service->getStudentCourseAccess($studentId);
        $schedule = $service->getStudentCombinedSchedule($studentId);
        $auditLogs = $service->getStudentAuditLogs($studentId);

        jsonOut([
            'success' => true,
            'enrollments' => $enrollments,
            'schedule' => $schedule,
            'audit_logs' => $auditLogs
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 2. ASSIGN COURSE ACCESS (Section 4, 42)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'assign' && $method === 'POST') {
        $data = getJsonBody();
        $studentId = (int)($data['student_id'] ?? 0);
        $courseId = (int)($data['course_id'] ?? 0);

        if (!$studentId || !$courseId) {
            jsonOut(['error' => 'กรุณาระบุนักเรียนและคอร์สเรียน'], 422);
        }

        $res = $service->assignCourseAccess($studentId, $courseId, array_merge($data, [
            'assigned_by' => $adminId,
            'assigned_by_name' => $adminName
        ]));

        if (!$res['success']) {
            jsonOut($res, $res['is_duplicate'] ?? false ? 409 : 422);
        }

        jsonOut($res);
    }

    // ─────────────────────────────────────────────────────────────
    // 3. BULK ASSIGN COURSE (Section 17, 53)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'bulk_assign' && $method === 'POST') {
        $data = getJsonBody();
        $studentIds = $data['student_ids'] ?? [];
        $courseId = (int)($data['course_id'] ?? 0);

        if (empty($studentIds) || !is_array($studentIds) || !$courseId) {
            jsonOut(['error' => 'กรุณาเลือกนักเรียนและคอร์สเรียน'], 422);
        }

        $res = $service->bulkAssignCourse($studentIds, $courseId, array_merge($data, [
            'assigned_by' => $adminId,
            'assigned_by_name' => $adminName
        ]));

        jsonOut($res);
    }

    // ─────────────────────────────────────────────────────────────
    // 4. BULK ASSIGN CLASS GROUP (Section 18)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'bulk_assign_class' && $method === 'POST') {
        $data = getJsonBody();
        $studentIds = $data['student_ids'] ?? [];
        $classGroupId = (int)($data['class_group_id'] ?? 0);

        if (empty($studentIds) || !is_array($studentIds) || !$classGroupId) {
            jsonOut(['error' => 'กรุณาเลือกนักเรียนและกลุ่มเรียน'], 422);
        }

        $res = $service->bulkAssignClassGroup($studentIds, $classGroupId, $adminId, $adminName);
        if (!$res['success']) {
            jsonOut($res, 422);
        }

        jsonOut($res);
    }

    // ─────────────────────────────────────────────────────────────
    // 5. EXTEND COURSE ACCESS (Section 23)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'extend' && $method === 'POST') {
        $data = getJsonBody();
        $enrollmentId = (int)($data['enrollment_id'] ?? 0);
        $daysOrDate = $data['days_or_date'] ?? 30;

        if (!$enrollmentId) jsonOut(['error' => 'ไม่พบรหัสสิทธิ์คอร์ส'], 400);

        $res = $service->extendAccess($enrollmentId, $daysOrDate, $adminId, $adminName);
        if (!$res['success']) jsonOut($res, 422);

        jsonOut($res);
    }

    // ─────────────────────────────────────────────────────────────
    // 6. UPDATE STATUS: PAUSE / REVOKE / ACTIVE (Section 22, 24)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'update_status' && $method === 'POST') {
        $data = getJsonBody();
        $enrollmentId = (int)($data['enrollment_id'] ?? 0);
        $newStatus = trim((string)($data['status'] ?? ''));
        $reason = trim((string)($data['reason'] ?? ''));

        if (!$enrollmentId || !$newStatus) jsonOut(['error' => 'ข้อมูลไม่ครบถ้วน'], 422);

        $res = $service->updateStatus($enrollmentId, $newStatus, $adminId, $adminName, $reason);
        if (!$res['success']) jsonOut($res, 422);

        jsonOut($res);
    }

    // ─────────────────────────────────────────────────────────────
    // 7. CHANGE CLASS GROUP (Section 25)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'change_class' && $method === 'POST') {
        $data = getJsonBody();
        $enrollmentId = (int)($data['enrollment_id'] ?? 0);
        $newClassGroupId = (int)($data['class_group_id'] ?? 0);

        if (!$enrollmentId || !$newClassGroupId) jsonOut(['error' => 'ข้อมูลไม่ครบถ้วน'], 422);

        $res = $service->changeClassGroup($enrollmentId, $newClassGroupId, $adminId, $adminName);
        if (!$res['success']) jsonOut($res, 422);

        jsonOut($res);
    }

    // ─────────────────────────────────────────────────────────────
    // 8. GET CLASS GROUPS FOR COURSE (Section 13, 33)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'class_groups') {
        $courseId = (int)($_GET['course_id'] ?? 0);
        $where = $courseId ? "WHERE cg.course_id = {$courseId}" : "";

        $stmt = $pdo->query("
            SELECT cg.*, c.title AS course_title,
                   u.first_name AS teacher_first_name, u.last_name AS teacher_last_name, u.nickname AS teacher_nickname,
                   COUNT(e.id) AS current_enrolled
            FROM class_groups cg
            INNER JOIN courses c ON c.id = cg.course_id
            LEFT JOIN users u ON u.id = cg.teacher_id
            LEFT JOIN enrollments e ON e.class_group_id = cg.id AND e.status IN ('active', 'trial')
            {$where}
            GROUP BY cg.id
            ORDER BY c.title ASC, cg.name ASC
        ");
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonOut(['success' => true, 'class_groups' => $groups]);
    }

    // ─────────────────────────────────────────────────────────────
    // 9. CREATE CLASS GROUP (Section 13)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'create_class_group' && $method === 'POST') {
        $data = getJsonBody();
        $name = trim((string)($data['name'] ?? ''));
        $courseId = (int)($data['course_id'] ?? 0);
        $teacherId = !empty($data['teacher_id']) ? (int)$data['teacher_id'] : null;
        $day = trim((string)($data['schedule_day'] ?? ''));
        $time = trim((string)($data['schedule_time'] ?? ''));
        $text = trim((string)($data['schedule_text'] ?? ($day ? "{$day} {$time}" : '')));
        $capacity = max(1, (int)($data['capacity'] ?? 15));
        $mode = in_array($data['learning_mode'] ?? '', ['online', 'onsite', 'hybrid'], true) ? $data['learning_mode'] : 'online';
        $room = trim((string)($data['room'] ?? ''));

        if ($name === '' || !$courseId) {
            jsonOut(['error' => 'กรุณาระบุชื่อกลุ่มเรียนและคอร์ส'], 422);
        }

        $ins = $pdo->prepare("
            INSERT INTO class_groups (name, code, course_id, teacher_id, schedule_day, schedule_time, schedule_text, capacity, learning_mode, room, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $ins->execute([$name, $name, $courseId, $teacherId, $day, $time, $text, $capacity, $mode, $room]);

        jsonOut(['success' => true, 'class_group_id' => (int)$pdo->lastInsertId(), 'message' => 'สร้างกลุ่มเรียนเรียบร้อยแล้ว']);
    }

    // ─────────────────────────────────────────────────────────────
    // 10. COURSE ACCESS MATRIX (Section 38)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'matrix') {
        $grade = !empty($_GET['grade']) ? trim((string)$_GET['grade']) : null;
        $matrix = $service->getCourseAccessMatrix($grade);
        jsonOut(['success' => true, 'matrix' => $matrix]);
    }

    // ─────────────────────────────────────────────────────────────
    // 11. ENROLLMENT METRICS (Section 45, 46)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'metrics') {
        $metrics = $service->getMetrics();
        jsonOut(['success' => true, 'metrics' => $metrics]);
    }

    // ─────────────────────────────────────────────────────────────
    // 12. ASSIGN BUNDLE (Section 19, 20)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'assign_bundle' && $method === 'POST') {
        $data = getJsonBody();
        $studentId = (int)($data['student_id'] ?? 0);
        $bundleName = trim((string)($data['bundle_name'] ?? 'Package'));
        $courseIds = $data['course_ids'] ?? [];

        if (!$studentId || empty($courseIds) || !is_array($courseIds)) {
            jsonOut(['error' => 'กรุณาระบุนักเรียนและรายการคอร์สในแพ็กเกจ'], 422);
        }

        $res = $service->assignBundle($studentId, $bundleName, $courseIds, array_merge($data, [
            'assigned_by' => $adminId,
            'assigned_by_name' => $adminName
        ]));

        jsonOut($res);
    }

    jsonOut(['error' => 'Invalid action'], 400);
} catch (Throwable $e) {
    error_log('Enrollments API Error: ' . $e->getMessage());
    jsonOut(['error' => 'เกิดข้อผิดพลาดในระบบจัดการสิทธิ์: ' . $e->getMessage()], 500);
}
