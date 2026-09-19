<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/includes/access.php';

function calendarRespond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function calendarBody(): array
{
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) calendarRespond(['error' => 'ข้อมูล JSON ไม่ถูกต้อง'], 400);
    return $body;
}

function ensureCalendarTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS calendar_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(300) NOT NULL,
        event_type ENUM('lesson','exam','meeting','other') NOT NULL DEFAULT 'lesson',
        course_id INT NULL,
        teacher_id INT NOT NULL,
        event_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        location VARCHAR(255) NULL,
        notes TEXT NULL,
        color VARCHAR(20) NOT NULL DEFAULT '#2563eb',
        status ENUM('scheduled','cancelled') NOT NULL DEFAULT 'scheduled',
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_calendar_date (event_date),
        INDEX idx_calendar_teacher_date (teacher_id, event_date),
        CONSTRAINT fk_calendar_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
        CONSTRAINT fk_calendar_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_calendar_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function validDate(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function validTime(string $time): bool
{
    return (bool) preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time);
}

function validateCalendarEvent(array $body, array $consoleUser, PDO $pdo): array
{
    $title = trim((string) ($body['title'] ?? ''));
    $date = trim((string) ($body['eventDate'] ?? ''));
    $start = trim((string) ($body['startTime'] ?? ''));
    $end = trim((string) ($body['endTime'] ?? ''));
    $type = (string) ($body['eventType'] ?? 'lesson');
    $status = (string) ($body['status'] ?? 'scheduled');
    $color = strtolower(trim((string) ($body['color'] ?? '#2563eb')));
    $teacherId = $consoleUser['role'] === 'teacher'
        ? (int) $consoleUser['id']
        : (int) ($body['teacherId'] ?? 0);
    $courseId = empty($body['courseId']) ? null : (int) $body['courseId'];

    if ($title === '' || mb_strlen($title) > 300) calendarRespond(['error' => 'กรุณากรอกชื่อกิจกรรมไม่เกิน 300 ตัวอักษร'], 422);
    if (!validDate($date) || !validTime($start) || !validTime($end) || $start >= $end) {
        calendarRespond(['error' => 'วันที่หรือช่วงเวลาไม่ถูกต้อง เวลาสิ้นสุดต้องอยู่หลังเวลาเริ่ม'], 422);
    }
    if (!in_array($type, ['lesson', 'exam', 'meeting', 'other'], true)) calendarRespond(['error' => 'ประเภทกิจกรรมไม่ถูกต้อง'], 422);
    if (!in_array($status, ['scheduled', 'cancelled'], true)) calendarRespond(['error' => 'สถานะกิจกรรมไม่ถูกต้อง'], 422);
    if (!preg_match('/^#[0-9a-f]{6}$/', $color)) calendarRespond(['error' => 'สีกิจกรรมไม่ถูกต้อง'], 422);
    if ($teacherId < 1) calendarRespond(['error' => 'กรุณาเลือกครูผู้สอน'], 422);

    $checkTeacher = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'teacher' AND is_active = 1");
    $checkTeacher->execute([$teacherId]);
    if (!$checkTeacher->fetchColumn()) calendarRespond(['error' => 'ไม่พบบัญชีครูที่เลือก'], 422);
    if ($courseId !== null) {
        $checkCourse = $pdo->prepare('SELECT id FROM courses WHERE id = ?');
        $checkCourse->execute([$courseId]);
        if (!$checkCourse->fetchColumn()) calendarRespond(['error' => 'ไม่พบคอร์สที่เลือก'], 422);
    }

    return [
        'title' => $title,
        'event_type' => $type,
        'course_id' => $courseId,
        'teacher_id' => $teacherId,
        'event_date' => $date,
        'start_time' => $start . ':00',
        'end_time' => $end . ':00',
        'location' => trim((string) ($body['location'] ?? '')) ?: null,
        'notes' => trim((string) ($body['notes'] ?? '')) ?: null,
        'color' => $color,
        'status' => $status,
    ];
}

function assertNoScheduleConflict(PDO $pdo, array $values, int $excludeId = 0): void
{
    if ($values['status'] === 'cancelled') return;
    $sql = "SELECT title FROM calendar_events
            WHERE teacher_id = :teacher AND event_date = :event_date AND status = 'scheduled'
              AND start_time < :end_time AND end_time > :start_time";
    $params = [
        ':teacher' => $values['teacher_id'], ':event_date' => $values['event_date'],
        ':end_time' => $values['end_time'], ':start_time' => $values['start_time'],
    ];
    if ($excludeId > 0) {
        $sql .= ' AND id <> :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }
    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    $conflict = $stmt->fetchColumn();
    if ($conflict) calendarRespond(['error' => 'ช่วงเวลานี้ซ้ำกับ “' . $conflict . '” ของครูคนเดียวกัน'], 409);
}

try {
    ensureCalendarTable($pdo);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $isTeacher = $consoleUser['role'] === 'teacher';

    if ($method === 'GET') {
        $start = trim((string) ($_GET['start'] ?? date('Y-m-01')));
        $end = trim((string) ($_GET['end'] ?? date('Y-m-t')));
        if (!validDate($start) || !validDate($end) || $start > $end) calendarRespond(['error' => 'ช่วงวันที่ไม่ถูกต้อง'], 422);
        $where = ['ce.event_date BETWEEN :start AND :end'];
        $params = [':start' => $start, ':end' => $end];
        if ($isTeacher) {
            $where[] = 'ce.teacher_id = :current_teacher';
            $params[':current_teacher'] = (int) $consoleUser['id'];
        } elseif ((int) ($_GET['teacherId'] ?? 0) > 0) {
            $where[] = 'ce.teacher_id = :teacher_filter';
            $params[':teacher_filter'] = (int) $_GET['teacherId'];
        }
        if ((int) ($_GET['courseId'] ?? 0) > 0) {
            $where[] = 'ce.course_id = :course_filter';
            $params[':course_filter'] = (int) $_GET['courseId'];
        }
        $stmt = $pdo->prepare("SELECT ce.*, c.title AS course_name,
                    CASE 
                        WHEN u.nickname IS NOT NULL AND TRIM(u.nickname) <> '' THEN CONCAT(u.first_name, ' ', u.last_name, ' (', u.nickname, ')')
                        ELSE CONCAT_WS(' ', u.first_name, u.last_name)
                    END AS teacher_name
                FROM calendar_events ce
                LEFT JOIN courses c ON c.id = ce.course_id
                JOIN users u ON u.id = ce.teacher_id
                WHERE " . implode(' AND ', $where) . " ORDER BY ce.event_date, ce.start_time, ce.id");
        $stmt->execute($params);
        $events = array_map(static fn(array $row): array => [
            'id' => (string) $row['id'], 'title' => $row['title'], 'eventType' => $row['event_type'],
            'courseId' => $row['course_id'] === null ? null : (string) $row['course_id'], 'courseName' => $row['course_name'],
            'teacherId' => (string) $row['teacher_id'], 'teacherName' => $row['teacher_name'],
            'eventDate' => $row['event_date'], 'startTime' => substr($row['start_time'], 0, 5),
            'endTime' => substr($row['end_time'], 0, 5), 'location' => $row['location'],
            'notes' => $row['notes'], 'color' => $row['color'], 'status' => $row['status'],
        ], $stmt->fetchAll());
        $teachers = $isTeacher ? [[
            'id' => (string) $consoleUser['id'],
            'name' => trim($consoleUser['first_name'] . ' ' . $consoleUser['last_name']) . (!empty($consoleUser['nickname']) ? ' (' . $consoleUser['nickname'] . ')' : ''),
        ]] : array_map(static fn(array $row): array => [
            'id' => (string) $row['id'], 
            'name' => trim($row['first_name'] . ' ' . $row['last_name']) . (!empty($row['nickname']) ? ' (' . $row['nickname'] . ')' : ''),
        ], $pdo->query("SELECT id, first_name, last_name, nickname FROM users WHERE role = 'teacher' AND is_active = 1 ORDER BY first_name, last_name")->fetchAll());
        $courseSql = $isTeacher
            ? "SELECT id, title, teacher_id FROM courses WHERE teacher_id = " . (int) $consoleUser['id'] . " AND status <> 'archived' ORDER BY title"
            : "SELECT id, title, teacher_id FROM courses WHERE status <> 'archived' ORDER BY title";
        $courses = array_map(static fn(array $row): array => [
            'id' => (string) $row['id'], 'title' => $row['title'],
            'teacherId' => $row['teacher_id'] === null ? null : (string) $row['teacher_id'],
        ], $pdo->query($courseSql)->fetchAll());
        calendarRespond(['events' => $events, 'teachers' => $teachers, 'courses' => $courses, 'isTeacher' => $isTeacher]);
    }

    if ($method === 'POST') {
        $values = validateCalendarEvent(calendarBody(), $consoleUser, $pdo);
        assertNoScheduleConflict($pdo, $values);
        $values['created_by'] = (int) $consoleUser['id'];
        $columns = array_keys($values);
        $stmt = $pdo->prepare('INSERT INTO calendar_events (' . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')');
        $stmt->execute($values);
        calendarRespond(['success' => true, 'eventId' => (int) $pdo->lastInsertId()], 201);
    }

    if ($method === 'PATCH') {
        $body = calendarBody();
        $id = (int) ($body['id'] ?? 0);
        if ($id < 1) calendarRespond(['error' => 'ไม่พบรหัสกิจกรรม'], 422);
        $ownership = $pdo->prepare('SELECT teacher_id FROM calendar_events WHERE id = ?');
        $ownership->execute([$id]);
        $ownerId = $ownership->fetchColumn();
        if ($ownerId === false) calendarRespond(['error' => 'ไม่พบกิจกรรม'], 404);
        if ($isTeacher && (int) $ownerId !== (int) $consoleUser['id']) calendarRespond(['error' => 'ไม่มีสิทธิ์แก้ไขกิจกรรมนี้'], 403);
        $values = validateCalendarEvent($body, $consoleUser, $pdo);
        assertNoScheduleConflict($pdo, $values, $id);
        $sets = implode(', ', array_map(static fn(string $key): string => $key . ' = :' . $key, array_keys($values)));
        $values['id'] = $id;
        $pdo->prepare("UPDATE calendar_events SET {$sets} WHERE id = :id")->execute($values);
        calendarRespond(['success' => true]);
    }

    if ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id < 1) calendarRespond(['error' => 'ไม่พบรหัสกิจกรรม'], 422);
        $sql = 'DELETE FROM calendar_events WHERE id = :id';
        $params = [':id' => $id];
        if ($isTeacher) {
            $sql .= ' AND teacher_id = :teacher_id';
            $params[':teacher_id'] = (int) $consoleUser['id'];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->rowCount() < 1) calendarRespond(['error' => 'ไม่พบกิจกรรมหรือไม่มีสิทธิ์ลบ'], 404);
        calendarRespond(['success' => true]);
    }

    calendarRespond(['error' => 'Method not allowed'], 405);
} catch (Throwable $error) {
    error_log('Calendar API error: ' . $error->getMessage());
    calendarRespond(['error' => 'ระบบปฏิทินขัดข้อง กรุณาลองใหม่'], 500);
}
