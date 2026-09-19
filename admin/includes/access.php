<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function teacherMenuSettings(): array
{
    return [
        'teacher_overview_access' => ['ภาพรวม', ['index.php']],
        'teacher_analytics_access' => ['Analytics และรายงาน', ['analytics.php']],
        'teacher_course_access' => ['คอร์สเรียน', ['courses.php', 'courses-api.php']],
        'teacher_curriculum_access' => ['บทเรียนในคอร์ส', ['curriculum.php', 'curriculum-api.php']],
        'teacher_calendar_access' => ['ปฏิทินและตารางสอน', ['calendar.php', 'calendar-api.php']],
        'teacher_test_access' => ['แบบทดสอบและคลังข้อสอบ', ['tests.php', 'question-bank.php', 'exams-api.php', 'export-exam-docx.php', 'live-sessions.php', 'live-session-room.php', 'live-sessions-api.php', 'question-search-api.php']],
        'teacher_worksheet_access' => ['คลังใบงานและ AI สร้างใบงาน', ['worksheets.php', 'worksheet-detail.php', 'ai-worksheet.php', 'worksheets-api.php', 'question-search-api.php']],
        'teacher_ai_access' => ['AI สร้างข้อสอบ', ['ai-exam.php', 'ai-exam-app/']],
        'teacher_students_access' => ['นักเรียน', ['students.php', 'students-api.php']],
        'teacher_orders_access' => ['คำสั่งซื้อและบัญชี', ['orders.php', 'orders-api.php']],
    ];
}

function consoleAllowed(string $page): bool
{
    global $consoleUser, $teacherPermissions;
    if ($consoleUser['role'] === 'admin') return true;
    if ($page === 'welcome.php') return true;
    foreach (teacherMenuSettings() as $key => [, $pages]) {
        if (in_array($page, $pages, true)) return ($teacherPermissions[$key] ?? '0') === '1';
    }
    return false;
}

function consoleDeny(int $status): void
{
    http_response_code($status);
    header('Cache-Control: no-store');
    if (str_contains(basename($_SERVER['SCRIPT_FILENAME'] ?? ''), 'api')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $status === 401 ? 'กรุณาเข้าสู่ระบบ' : 'ไม่มีสิทธิ์เข้าถึงเมนูนี้'], JSON_UNESCAPED_UNICODE);
    } else {
        echo '<meta charset="utf-8"><p>ไม่มีสิทธิ์เข้าถึงหน้านี้</p><a href="../auth.php">เข้าสู่ระบบ</a>';
    }
    exit;
}

$stmt = $pdo->prepare('SELECT id, first_name, last_name, role, is_active FROM users WHERE id = ?');
$stmt->execute([(int) ($_SESSION['user_id'] ?? 0)]);
$consoleUser = $stmt->fetch();
if (!$consoleUser) consoleDeny(401);
if (!$consoleUser['is_active'] || !in_array($consoleUser['role'], ['admin', 'teacher'], true)) consoleDeny(403);
$teacherPermissions = [];
if ($consoleUser['role'] === 'teacher') {
    try {
        $teacherPermissions = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'teacher_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $error) {
        if ($error->getCode() !== '42S02') throw $error;
    }
}
$requestedPage = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($requestedPage === 'ai-settings-api.php' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $consoleUser['role'] === 'teacher') $requestedPage = 'ai-exam.php';
if (str_contains($_SERVER['SCRIPT_FILENAME'] ?? '', '/AI-EXAM/')) $requestedPage = 'ai-exam.php';
if (str_contains($_SERVER['SCRIPT_FILENAME'] ?? '', '/ai-exam-app/')) $requestedPage = 'ai-exam.php';
if (!consoleAllowed($requestedPage)) {
    if ($requestedPage === 'index.php' && $consoleUser['role'] === 'teacher') {
        header('Location: welcome.php');
        exit;
    }
    consoleDeny(403);
}
header('Cache-Control: no-store');
