<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/access.php';

function courseResponse(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function courseBody(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) courseResponse(['error' => 'ข้อมูล JSON ไม่ถูกต้อง'], 400);
    return $data;
}
function nullableNumber(mixed $value): int|float|null {
    return $value === '' || $value === null ? null : (float) $value;
}
function validateCourse(array $body): array {
    $title = trim((string) ($body['title'] ?? ''));
    $status = (string) ($body['status'] ?? 'draft');
    if ($title === '') courseResponse(['error' => 'กรุณากรอกชื่อคอร์ส'], 422);
    if (!in_array($status, ['draft', 'active', 'archived'], true)) courseResponse(['error' => 'สถานะคอร์สไม่ถูกต้อง'], 422);
    $price = nullableNumber($body['price'] ?? 0) ?? 0;
    $originalPrice = nullableNumber($body['originalPrice'] ?? null);
    if ($price < 0 || ($originalPrice !== null && $originalPrice < 0)) courseResponse(['error' => 'ราคาคอร์สต้องไม่ติดลบ'], 422);
    return [
        'title' => $title,
        'subject' => trim((string) ($body['subject'] ?? '')) ?: null,
        'level' => trim((string) ($body['level'] ?? '')) ?: null,
        'description' => trim((string) ($body['description'] ?? '')) ?: null,
        'cover_image' => trim((string) ($body['coverImage'] ?? '')) ?: null,
        'price' => $price,
        'original_price' => $originalPrice,
        'duration_hours' => nullableNumber($body['durationHours'] ?? null),
        'duration_weeks' => nullableNumber($body['durationWeeks'] ?? null),
        'max_students' => nullableNumber($body['maxStudents'] ?? null),
        'teacher_id' => nullableNumber($body['teacherId'] ?? null),
        'status' => $status,
        'is_free' => !empty($body['isFree']) ? 1 : 0,
    ];
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        $courses = $pdo->query(
            "SELECT c.*, 
                    CASE 
                        WHEN u.nickname IS NOT NULL AND TRIM(u.nickname) <> '' THEN CONCAT(u.first_name, ' ', u.last_name, ' (', u.nickname, ')')
                        ELSE CONCAT_WS(' ', u.first_name, u.last_name)
                    END AS teacher_name,
                    COUNT(DISTINCT e.id) AS enrollment_count
             FROM courses c
             LEFT JOIN users u ON u.id = c.teacher_id AND u.role = 'teacher'
             LEFT JOIN enrollments e ON e.course_id = c.id AND e.status = 'active'
             GROUP BY c.id ORDER BY c.created_at DESC, c.id DESC"
        )->fetchAll();
        $teachers = $pdo->query(
            "SELECT id, first_name, last_name, nickname FROM users
             WHERE role = 'teacher' AND is_active = 1 ORDER BY first_name, last_name"
        )->fetchAll();
        courseResponse([
            'courses' => array_map(static fn(array $r): array => [
                'id'=>(string)$r['id'], 'title'=>$r['title'], 'slug'=>$r['slug'],
                'subject'=>$r['subject'], 'level'=>$r['level'], 'description'=>$r['description'],
                'coverImage'=>$r['cover_image'], 'price'=>(float)$r['price'],
                'originalPrice'=>$r['original_price'] === null ? null : (float)$r['original_price'],
                'durationHours'=>$r['duration_hours'] === null ? null : (int)$r['duration_hours'],
                'durationWeeks'=>$r['duration_weeks'] === null ? null : (int)$r['duration_weeks'],
                'maxStudents'=>$r['max_students'] === null ? null : (int)$r['max_students'],
                'teacherId'=>$r['teacher_id'] === null ? null : (string)$r['teacher_id'],
                'teacherName'=>$r['teacher_name'], 'status'=>$r['status'],
                'isFree'=>(bool)$r['is_free'], 'enrollmentCount'=>(int)$r['enrollment_count'],
            ], $courses),
            'teachers' => array_map(static fn(array $r): array => [
                'id'=>(string)$r['id'], 
                'name'=>trim($r['first_name'].' '.$r['last_name']) . (!empty($r['nickname']) ? ' (' . $r['nickname'] . ')' : '')
            ], $teachers),
        ]);
    }
    if ($method === 'POST') {
        $values = validateCourse(courseBody());
        $columns = array_keys($values);
        $sql = 'INSERT INTO courses (' . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_combine(array_map(fn($k)=>':'.$k, $columns), array_values($values)));
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE courses SET slug = :slug WHERE id = :id')->execute([':slug'=>'course-'.$id, ':id'=>$id]);
        courseResponse(['success'=>true, 'courseId'=>$id], 201);
    }
    if ($method === 'PATCH') {
        $body = courseBody();
        $id = (int)($body['id'] ?? 0);
        if ($id < 1) courseResponse(['error'=>'ไม่พบรหัสคอร์ส'], 422);
        $values = validateCourse($body);
        $sets = implode(', ', array_map(fn($k)=>$k.' = :'.$k, array_keys($values)));
        $params = array_combine(array_map(fn($k)=>':'.$k, array_keys($values)), array_values($values));
        $params[':id'] = $id;
        $pdo->prepare("UPDATE courses SET {$sets} WHERE id = :id")->execute($params);
        courseResponse(['success'=>true]);
    }
    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id < 1) courseResponse(['error'=>'ไม่พบรหัสคอร์ส'], 422);
        $pdo->prepare('DELETE FROM courses WHERE id = :id')->execute([':id'=>$id]);
        courseResponse(['success'=>true]);
    }
    courseResponse(['error'=>'Method not allowed'], 405);
} catch (Throwable $error) {
    error_log('Course API error: '.$error->getMessage());
    courseResponse(['error'=>'ระบบข้อมูลคอร์สขัดข้อง กรุณาลองใหม่'], 500);
}
