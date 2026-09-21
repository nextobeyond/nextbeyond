<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/enrollments-service.php';

function out(array $d, int $s = 200): never {
    http_response_code($s);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function body(): array {
    $d = json_decode(file_get_contents('php://input'), true);
    if (!is_array($d)) out(['error' => 'ข้อมูลไม่ถูกต้อง'], 400);
    return $d;
}

function ensureAvatarColumn(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $cols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM users')->fetchAll() as $c) {
            $cols[(string)$c['Field']] = $c;
        }
        if (!isset($cols['avatar_url'])) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `avatar_url` MEDIUMTEXT NULL AFTER `role`");
        } else {
            $type = strtolower((string)($cols['avatar_url']['Type'] ?? ''));
            if (!str_contains($type, 'text')) {
                $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `avatar_url` MEDIUMTEXT NULL");
            }
        }
    } catch (Throwable $e) {}
}

try {
    ensureAvatarColumn($pdo);
    $service = new EnrollmentService($pdo);
    $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($m === 'GET') {
        $rows = $pdo->query("
            SELECT u.id, u.email, u.first_name, u.last_name, u.nickname, u.avatar_url, u.grade, u.phone, u.is_active, u.created_at,
                   COUNT(DISTINCT CASE WHEN e.status IN ('active', 'trial') THEN e.id END) AS enrollment_count,
                   COUNT(DISTINCT a.id) AS attempt_count,
                   ROUND(AVG(a.score), 2) AS average_score
            FROM users u
            LEFT JOIN enrollments e ON e.user_id = u.id AND e.status IN ('active', 'trial')
            LEFT JOIN test_attempts a ON a.user_id = u.id AND a.completed_at IS NOT NULL
            WHERE u.role = 'student'
            GROUP BY u.id
            ORDER BY u.created_at DESC, u.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Fetch courses and class groups for all students
        $allEnrollments = $pdo->query("
            SELECT e.user_id, e.course_id, e.status, e.access_type, e.learning_mode,
                   c.title AS course_title, c.subject, cg.name AS class_group_name
            FROM enrollments e
            INNER JOIN courses c ON c.id = e.course_id
            LEFT JOIN class_groups cg ON cg.id = e.class_group_id
            WHERE e.status IN ('active', 'trial')
            ORDER BY c.title ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $enMap = [];
        foreach ($allEnrollments as $en) {
            $enMap[$en['user_id']][] = $en;
        }

        foreach ($rows as &$st) {
            $stEn = $enMap[$st['id']] ?? [];
            $st['courses'] = array_column($stEn, 'course_title');
            $st['class_groups'] = array_values(array_filter(array_column($stEn, 'class_group_name')));
            $st['enrollments_detail'] = $stEn;
            $st['grade'] = $st['grade'] ?: 'ม.5';
            $st['avatar_url'] = !empty($st['avatar_url']) ? (string)$st['avatar_url'] : null;
        }
        unset($st);

        $metrics = $service->getMetrics();

        out([
            'students' => $rows,
            'metrics' => $metrics
        ]);
    }

    if ($m === 'POST') {
        if (($_GET['action'] ?? '') === 'upload_avatar') {
            $studentId = (int)($_POST['student_id'] ?? ($_GET['student_id'] ?? 0));
            $base64 = '';
            if (!$studentId) {
                $rawBody = json_decode(file_get_contents('php://input'), true);
                $studentId = (int)($rawBody['student_id'] ?? 0);
                $base64 = trim((string)($rawBody['avatar_base64'] ?? ''));
            } else {
                $base64 = trim((string)($_POST['avatar_base64'] ?? ''));
            }

            if (!$studentId) out(['error' => 'ไม่พบรหัสนักเรียน'], 400);

            ensureAvatarColumn($pdo);
            $uploadDir = __DIR__ . '/../assets/uploads/avatars';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
            @chmod($uploadDir, 0777);

            $avatarUrl = '';
            if (!empty($base64)) {
                if (preg_match('/^data:image\/(jpeg|png|webp);base64,(.+)$/', $base64, $matches)) {
                    $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
                    $decoded = base64_decode($matches[2], true);
                    if ($decoded && strlen($decoded) > 0) {
                        $filename = 'avatar-' . $studentId . '-' . time() . '.' . $ext;
                        $targetPath = $uploadDir . '/' . $filename;
                        foreach (glob($uploadDir . '/avatar-' . $studentId . '-*') ?: [] as $old) {
                            @unlink($old);
                        }
                        if (@file_put_contents($targetPath, $decoded) !== false) {
                            @chmod($targetPath, 0666);
                            $avatarUrl = '/assets/uploads/avatars/' . $filename;
                        } else {
                            $avatarUrl = $base64;
                        }
                    }
                }
            } elseif (!empty($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $file = $_FILES['avatar'];
                $origExt = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
                $ext = in_array($origExt, ['jpg', 'jpeg', 'png', 'webp'], true) ? ($origExt === 'jpeg' ? 'jpg' : $origExt) : 'jpg';
                $filename = 'avatar-' . $studentId . '-' . time() . '.' . $ext;
                $targetPath = $uploadDir . '/' . $filename;
                foreach (glob($uploadDir . '/avatar-' . $studentId . '-*') ?: [] as $old) {
                    @unlink($old);
                }
                if (@move_uploaded_file($file['tmp_name'], $targetPath)) {
                    @chmod($targetPath, 0666);
                    $avatarUrl = '/assets/uploads/avatars/' . $filename;
                } else {
                    $raw = @file_get_contents($file['tmp_name']);
                    if ($raw) {
                        $avatarUrl = 'data:image/' . ($ext === 'jpg' ? 'jpeg' : $ext) . ';base64,' . base64_encode($raw);
                    }
                }
            }

            if ($avatarUrl === '') out(['error' => 'กรุณาเลือกไฟล์รูปภาพที่ถูกต้อง'], 422);

            $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ? AND role = 'student'")->execute([$avatarUrl, $studentId]);
            out(['success' => true, 'avatar_url' => $avatarUrl]);
        }

        $d = body();
        $first = trim((string)($d['firstName'] ?? ''));
        $last = trim((string)($d['lastName'] ?? ''));
        $nickname = trim((string)($d['nickname'] ?? ''));
        $grade = trim((string)($d['grade'] ?? 'ม.5'));
        $email = mb_strtolower(trim((string)($d['email'] ?? '')));
        $pass = (string)($d['password'] ?? '');

        if ($first === '' || $last === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($pass) < 8) {
            out(['error' => 'กรอกชื่อ อีเมล และรหัสผ่านอย่างน้อย 8 ตัวให้ถูกต้อง'], 422);
        }

        $q = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $q->execute([':email' => $email]);
        if ($q->fetch()) out(['error' => 'อีเมลนี้มีบัญชีแล้ว'], 409);

        $q = $pdo->prepare("
            INSERT INTO users (email, password_hash, first_name, last_name, nickname, grade, phone, role, is_active)
            VALUES (:email, :password, :first, :last, :nickname, :grade, :phone, 'student', 1)
        ");
        $q->execute([
            ':email' => $email,
            ':password' => password_hash($pass, PASSWORD_DEFAULT),
            ':first' => $first,
            ':last' => $last,
            ':nickname' => $nickname ?: null,
            ':grade' => $grade ?: 'ม.5',
            ':phone' => trim((string)($d['phone'] ?? '')) ?: null
        ]);

        out(['success' => true, 'studentId' => (int)$pdo->lastInsertId()], 201);
    }

    if ($m === 'PATCH') {
        $d = body();
        $q = $pdo->prepare("UPDATE users SET is_active = :active WHERE id = :id AND role = 'student'");
        $q->execute([':active' => !empty($d['isActive']) ? 1 : 0, ':id' => (int)($d['id'] ?? 0)]);
        out(['success' => true]);
    }

    if ($m === 'DELETE') {
        $q = $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'student'");
        $q->execute([':id' => (int)($_GET['id'] ?? 0)]);
        out(['success' => true]);
    }

    out(['error' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    error_log('Students API: ' . $e->getMessage());
    out(['error' => 'ระบบข้อมูลนักเรียนขัดข้อง: ' . $e->getMessage()], 500);
}
