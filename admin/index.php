<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/access.php';

$pageTitle = 'ภาพรวม (Dashboard)';
$pageDesc = 'ภาพรวมข้อมูลและสถิติการใช้งานล่าสุดของระบบ';
$currentPage = 'index.php';
$dashboardToolbar = true;

function dashboardRows(PDO $pdo, string $sql, array $params = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function dashboardValue(PDO $pdo, string $sql, array $params = []): mixed
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
}

function dashboardInitials(string $name): string
{
    $name = trim($name);
    return $name === '' ? '—' : mb_substr($name, 0, 1);
}

function dashboardIcon(string $name, string $class = 'w-5 h-5'): string
{
    $paths = [
        'sales' => '<path d="M3 3h2l2.3 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 1.9-1.4L20 7H6.2"/><circle cx="10" cy="20" r="1"/><circle cx="17" cy="20" r="1"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 10h6M9 14h6M9 18h3"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    ];
    return '<svg class="' . $class . '" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

$now = new DateTimeImmutable('now');
$monthStart = $now->modify('first day of this month')->setTime(0, 0);
$previousMonthStart = $monthStart->modify('-1 month');
$year = (int) $now->format('Y');
$thaiYear = $year + 543;
$today = $now->format('Y-m-d');

$salesThisMonth = (float) dashboardValue($pdo, "SELECT COALESCE(SUM(net_amount), 0) FROM orders WHERE status = 'confirmed' AND created_at >= ?", [$monthStart->format('Y-m-d H:i:s')]);
$salesPreviousMonth = (float) dashboardValue($pdo, "SELECT COALESCE(SUM(net_amount), 0) FROM orders WHERE status = 'confirmed' AND created_at >= ? AND created_at < ?", [$previousMonthStart->format('Y-m-d H:i:s'), $monthStart->format('Y-m-d H:i:s')]);
$newStudents = (int) dashboardValue($pdo, "SELECT COUNT(*) FROM users WHERE role = 'student' AND created_at >= ?", [$monthStart->format('Y-m-d H:i:s')]);
$previousNewStudents = (int) dashboardValue($pdo, "SELECT COUNT(*) FROM users WHERE role = 'student' AND created_at >= ? AND created_at < ?", [$previousMonthStart->format('Y-m-d H:i:s'), $monthStart->format('Y-m-d H:i:s')]);
$activeCourses = (int) dashboardValue($pdo, "SELECT COUNT(*) FROM courses WHERE status = 'active'");
$pendingOrders = (int) dashboardValue($pdo, "SELECT COUNT(*) FROM orders WHERE status = 'pending'");
$draftExams = (int) dashboardValue($pdo, "SELECT COUNT(*) FROM exams WHERE status = 'draft'");
$pendingWork = $pendingOrders + $draftExams;

$months = array_fill(1, 12, 0);
foreach (dashboardRows($pdo, "SELECT MONTH(created_at) AS month_number, COUNT(*) AS total FROM users WHERE role = 'student' AND YEAR(created_at) = ? GROUP BY MONTH(created_at)", [$year]) as $row) {
    $months[(int) $row['month_number']] = (int) $row['total'];
}
$hasStudentChart = array_sum($months) > 0;
$chartMax = max(1, max($months));
$chartMonthLabels = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

$todaySchedule = dashboardRows($pdo, "SELECT ce.title, ce.start_time, ce.end_time, ce.event_type, ce.color, c.title AS course_name, CONCAT_WS(' ', u.first_name, u.last_name) AS teacher_name FROM calendar_events ce JOIN users u ON u.id = ce.teacher_id LEFT JOIN courses c ON c.id = ce.course_id WHERE ce.event_date = ? AND ce.status = 'scheduled' ORDER BY ce.start_time, ce.id LIMIT 6", [$today]);
$tasks = dashboardRows($pdo, "SELECT 'order' AS task_type, o.id, CONCAT('ตรวจสอบคำสั่งซื้อ ', o.order_number) AS title, CONCAT_WS(' ', u.first_name, u.last_name) AS detail, o.created_at AS occurred_at FROM orders o JOIN users u ON u.id = o.user_id WHERE o.status = 'pending' UNION ALL SELECT 'exam' AS task_type, e.id, CONCAT('ตรวจสอบแบบทดสอบ: ', e.title) AS title, COALESCE(e.subject, 'แบบทดสอบฉบับร่าง') AS detail, e.updated_at AS occurred_at FROM exams e WHERE e.status = 'draft' UNION ALL SELECT 'course' AS task_type, c.id, CONCAT('ตรวจสอบคอร์ส: ', c.title) AS title, COALESCE(c.subject, 'คอร์สฉบับร่าง') AS detail, c.updated_at AS occurred_at FROM courses c WHERE c.status = 'draft' ORDER BY occurred_at DESC LIMIT 5");
$activities = dashboardRows($pdo, "SELECT * FROM (SELECT 'student' AS activity_type, CONCAT_WS(' ', first_name, last_name) AS title, 'ลงทะเบียนเป็นนักเรียนใหม่' AS detail, created_at AS occurred_at FROM users WHERE role = 'student' UNION ALL SELECT 'order' AS activity_type, CONCAT('คำสั่งซื้อ ', order_number) AS title, 'ได้รับการยืนยันการชำระเงิน' AS detail, COALESCE(confirmed_at, created_at) AS occurred_at FROM orders WHERE status = 'confirmed' UNION ALL SELECT 'course' AS activity_type, title, 'มีการเพิ่มหรืออัปเดตคอร์ส' AS detail, updated_at AS occurred_at FROM courses) AS recent_activity ORDER BY occurred_at DESC LIMIT 5");
$courseGroups = dashboardRows($pdo, "SELECT COALESCE(NULLIF(TRIM(c.subject), ''), 'ไม่ระบุหมวด') AS label, COUNT(DISTINCT e.user_id) AS total FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.status = 'active' GROUP BY COALESCE(NULLIF(TRIM(c.subject), ''), 'ไม่ระบุหมวด') ORDER BY total DESC, label ASC LIMIT 5");
$enrolledStudents = array_sum(array_map(static fn(array $group): int => (int) $group['total'], $courseGroups));
$colors = ['#f54696', '#3981f5', '#3bc98b', '#8466e9', '#aebbd0'];
$gradientParts = [];
if ($enrolledStudents > 0) {
    $offset = 0;
    foreach ($courseGroups as $index => $group) {
        $portion = ((int) $group['total'] / $enrolledStudents) * 100;
        $gradientParts[] = $colors[$index] . ' ' . $offset . '% ' . ($offset + $portion) . '%';
        $offset += $portion;
    }
}
$courseGradient = $gradientParts ? 'conic-gradient(' . implode(', ', $gradientParts) . ')' : '#e8ecf2';

function dashboardChange(float $current, float $previous): array
{
    if ($previous <= 0) return ['text' => $current > 0 ? 'เริ่มมีข้อมูลเดือนนี้' : 'ยังไม่มีข้อมูลเดือนนี้', 'positive' => $current > 0];
    $change = (($current - $previous) / $previous) * 100;
    return ['text' => sprintf('%s%.1f%% จากเดือนก่อน', $change >= 0 ? '+' : '', $change), 'positive' => $change >= 0];
}

$salesChange = dashboardChange($salesThisMonth, $salesPreviousMonth);
$studentChange = dashboardChange($newStudents, $previousNewStudents);
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> - Next Beyond Admin</title>
  <link rel="stylesheet" href="../assets/css/output.css">
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Inter:wght@500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="../assets/js/admin-guard.js"></script>
</head>
<body class="bg-[#f5f8fc] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0">
    <?php include 'includes/topbar.php'; ?>
    <main class="flex-1 p-6 max-[640px]:p-4">
      <div class="flex items-center justify-end gap-2 text-[12px] text-[#7890b4] mb-3"><span>อัปเดตล่าสุด <?= htmlspecialchars($now->format('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') ?> น.</span><span class="text-primary-500"><?= dashboardIcon('clock', 'w-4 h-4') ?></span></div>
      <section class="grid grid-cols-4 gap-4 mb-4 max-[1280px]:grid-cols-2 max-[640px]:grid-cols-1" aria-label="สรุปข้อมูลสำคัญ">
        <?php
        $summaryCards = [
          ['ยอดขายเดือนนี้', '฿' . number_format($salesThisMonth, 0), 'sales', 'bg-pink-100 text-pink-500', $salesChange],
          ['นักเรียนใหม่', number_format($newStudents) . ' คน', 'users', 'bg-primary-100 text-primary-600', $studentChange],
          ['คอร์สที่กำลัง Active', number_format($activeCourses) . ' คอร์ส', 'book', 'bg-[#dff8eb] text-[#109e55]', ['text' => 'ข้อมูลจากคอร์สที่เปิดสอน', 'positive' => true]],
          ['งานรอตรวจ / Feedback', number_format($pendingWork) . ' รายการ', 'clipboard', 'bg-[#fff0d8] text-[#e8870e]', ['text' => $pendingOrders . ' คำสั่งซื้อ · ' . $draftExams . ' แบบทดสอบ', 'positive' => false]],
        ];
        foreach ($summaryCards as [$label, $value, $icon, $iconClass, $change]): ?>
          <article class="min-h-[132px] bg-white rounded-[14px] border border-[#e6edf5] px-5 py-4 shadow-[0_8px_22px_rgba(15,42,83,.04)] flex gap-4"><span class="w-14 h-14 rounded-xl shrink-0 flex items-center justify-center <?= $iconClass ?>"><?= dashboardIcon($icon, 'w-7 h-7') ?></span><div class="min-w-0 flex-1"><h2 class="text-[15px] font-bold text-navy-900 leading-tight"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2><p class="text-[27px] font-black text-navy-950 leading-none mt-2 tracking-[-.03em] truncate"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></p><p class="text-[12px] mt-2 <?= $change['positive'] ? 'text-success' : 'text-[#7d8da5]' ?>"><?= $change['positive'] ? '↑ ' : '' ?><?= htmlspecialchars($change['text'], ENT_QUOTES, 'UTF-8') ?></p></div></article>
        <?php endforeach; ?>
      </section>
      <div class="grid grid-cols-[minmax(0,1.65fr)_minmax(330px,1fr)] gap-4 mb-4 max-[1200px]:grid-cols-1">
        <section class="bg-white rounded-[14px] border border-[#e6edf5] shadow-[0_8px_22px_rgba(15,42,83,.035)] overflow-hidden" aria-labelledby="student-chart-title"><div class="px-5 pt-4 flex items-start justify-between gap-4"><div class="flex gap-3"><span class="w-10 h-10 rounded-lg bg-primary-100 text-primary-600 flex items-center justify-center"><?= dashboardIcon('chart') ?></span><div><h2 id="student-chart-title" class="text-[18px] font-black">ภาพแนวโน้มจำนวนนักเรียนใหม่</h2><p class="text-[13px] text-[#7890b4]">แสดงจำนวนผู้เรียนที่ลงทะเบียนในปี <?= $thaiYear ?></p></div></div><span class="shrink-0 px-3 py-2 text-[13px] font-bold border border-[#dce4ef] rounded-lg text-[#526b93]">ปี <?= $thaiYear ?></span></div>
          <?php if ($hasStudentChart): ?><div class="h-[260px] px-5 pt-4 pb-5"><div class="h-full flex items-end gap-2 border-b border-[#cfdceb]"><?php foreach ($months as $monthNumber => $total): $height = max($total > 0 ? 10 : 2, round(($total / $chartMax) * 190)); ?><div class="h-full flex-1 min-w-0 flex flex-col justify-end items-center group relative"><span class="absolute bottom-[calc(100%+6px)] opacity-0 group-hover:opacity-100 bg-navy-950 text-white text-[11px] rounded px-2 py-1 transition-opacity whitespace-nowrap"><?= number_format($total) ?> คน</span><div class="w-full max-w-[32px] rounded-t-md <?= $monthNumber === (int) $now->format('n') ? 'bg-pink-500' : 'bg-pink-300' ?>" style="height: <?= $height ?>px"></div><span class="text-[11px] text-[#60799f] mt-2 whitespace-nowrap"><?= $chartMonthLabels[$monthNumber - 1] ?></span></div><?php endforeach; ?></div></div>
          <?php else: ?><div class="h-[260px] flex flex-col items-center justify-center text-center px-6"><span class="w-12 h-12 bg-[#f1f5fa] text-[#8ba0bd] rounded-full flex items-center justify-center mb-3"><?= dashboardIcon('chart', 'w-6 h-6') ?></span><p class="font-bold text-[#526b72]">ยังไม่มีข้อมูลนักเรียนใหม่ในปี <?= $thaiYear ?></p><p class="text-[13px] text-[#8ba0bd] mt-1">กราฟจะแสดงโดยอัตโนมัติเมื่อมีผู้เรียนลงทะเบียน</p></div><?php endif; ?>
        </section>
        <section class="bg-white rounded-[14px] border border-[#e6edf5] shadow-[0_8px_22px_rgba(15,42,83,.035)] overflow-hidden" aria-labelledby="activity-title"><div class="px-5 py-4 flex items-center justify-between border-b border-[#edf1f6]"><div class="flex gap-3 items-center"><span class="w-10 h-10 rounded-lg bg-primary-100 text-primary-600 flex items-center justify-center"><?= dashboardIcon('clock') ?></span><h2 id="activity-title" class="text-[18px] font-black">กิจกรรมล่าสุด</h2></div><a href="students.php" class="text-[13px] font-bold text-pink-500 hover:text-pink-600">ดูทั้งหมด <?= dashboardIcon('arrow', 'inline w-4 h-4') ?></a></div>
          <?php if ($activities): ?><ol class="px-5 py-1 divide-y divide-[#edf1f6]"><?php foreach ($activities as $activity): $activityColors = ['student' => 'bg-primary-100 text-primary-600', 'order' => 'bg-pink-100 text-pink-500', 'course' => 'bg-[#dff8eb] text-[#109e55]']; ?><li class="py-3 flex gap-3"><span class="w-8 h-8 mt-0.5 shrink-0 rounded-full flex items-center justify-center font-bold text-[13px] <?= $activityColors[$activity['activity_type']] ?? 'bg-[#f1f5fa] text-[#60799f]' ?>"><?= htmlspecialchars(dashboardInitials((string)$activity['title']), ENT_QUOTES, 'UTF-8') ?></span><div class="min-w-0 flex-1"><p class="font-bold text-[13px] truncate"><?= htmlspecialchars((string)$activity['title'], ENT_QUOTES, 'UTF-8') ?></p><p class="text-[12px] text-[#7890b4] truncate"><?= htmlspecialchars((string)$activity['detail'], ENT_QUOTES, 'UTF-8') ?></p></div><time class="text-[11px] text-[#7890b4] whitespace-nowrap"><?= htmlspecialchars((new DateTimeImmutable((string)$activity['occurred_at']))->format('d/m H:i'), ENT_QUOTES, 'UTF-8') ?></time></li><?php endforeach; ?></ol><?php else: ?><div class="h-[220px] flex flex-col items-center justify-center text-center px-5"><p class="font-bold text-[#526b72]">ยังไม่มีกิจกรรมในระบบ</p><p class="text-[12px] text-[#8ba0bd] mt-1">รายการจริงจะแสดงที่นี่เมื่อเริ่มใช้งาน</p></div><?php endif; ?>
        </section>
      </div>
      <div class="grid grid-cols-[minmax(0,1.25fr)_minmax(0,1.1fr)_minmax(340px,1fr)] gap-4 max-[1280px]:grid-cols-2 max-[900px]:grid-cols-1">
        <section class="bg-white rounded-[14px] border border-[#e6edf5] shadow-[0_8px_22px_rgba(15,42,83,.035)] overflow-hidden" aria-labelledby="task-title"><div class="px-5 py-4 flex items-center justify-between border-b border-[#edf1f6]"><h2 id="task-title" class="text-[17px] font-black">Task Queue (งานที่ต้องทำ)</h2><a href="orders.php" class="text-[13px] font-bold text-pink-500">ดูทั้งหมด <?= dashboardIcon('arrow', 'inline w-4 h-4') ?></a></div>
          <?php if ($tasks): ?><ol class="px-5 py-1 divide-y divide-[#edf1f6]"><?php foreach ($tasks as $task): $taskLink = $task['task_type'] === 'order' ? 'orders.php' : ($task['task_type'] === 'exam' ? 'tests.php' : 'courses.php'); ?><li><a href="<?= $taskLink ?>" class="py-3 flex items-center gap-3 hover:bg-[#fafcff] -mx-2 px-2 rounded-lg"><span class="w-5 h-5 shrink-0 rounded border-2 border-[#b6c5da]"></span><span class="min-w-0 flex-1"><span class="block text-[13px] font-bold truncate"><?= htmlspecialchars((string)$task['title'], ENT_QUOTES, 'UTF-8') ?></span><span class="block text-[11px] text-[#7890b4] truncate"><?= htmlspecialchars((string)$task['detail'], ENT_QUOTES, 'UTF-8') ?></span></span><time class="text-[11px] text-pink-500 font-bold whitespace-nowrap"><?= htmlspecialchars((new DateTimeImmutable((string)$task['occurred_at']))->format('d/m H:i'), ENT_QUOTES, 'UTF-8') ?></time></a></li><?php endforeach; ?></ol><?php else: ?><div class="h-[210px] flex flex-col items-center justify-center text-center px-5"><span class="w-10 h-10 rounded-full bg-[#e8f9f1] text-success flex items-center justify-center mb-3">✓</span><p class="font-bold text-[#526b72]">ไม่มีงานรอตรวจ</p><p class="text-[12px] text-[#8ba0bd] mt-1">รายการที่ต้องดำเนินการจะแสดงที่นี่</p></div><?php endif; ?>
        </section>
        <section class="bg-white rounded-[14px] border border-[#e6edf5] shadow-[0_8px_22px_rgba(15,42,83,.035)] overflow-hidden" aria-labelledby="schedule-title"><div class="px-5 py-4 flex items-center justify-between border-b border-[#edf1f6]"><div class="flex gap-2 items-center"><span class="w-8 h-8 rounded-lg bg-primary-100 text-primary-600 flex items-center justify-center"><?= dashboardIcon('calendar', 'w-[18px] h-[18px]') ?></span><h2 id="schedule-title" class="text-[17px] font-black">ตารางสอนวันนี้</h2></div><a href="calendar.php" class="text-[13px] font-bold text-pink-500">ดูทั้งหมด <?= dashboardIcon('arrow', 'inline w-4 h-4') ?></a></div>
          <?php if ($todaySchedule): ?><ol class="px-5 py-2"><?php foreach ($todaySchedule as $event): ?><li class="py-2 flex gap-3"><span class="mt-2 w-2.5 h-2.5 rounded-full shrink-0" style="background-color: <?= htmlspecialchars((string)$event['color'], ENT_QUOTES, 'UTF-8') ?>"></span><time class="text-[12px] text-[#60799f] w-[82px] shrink-0"><?= htmlspecialchars(substr((string)$event['start_time'], 0, 5) . ' – ' . substr((string)$event['end_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?></time><div class="min-w-0"><p class="text-[13px] font-bold truncate"><?= htmlspecialchars((string)$event['title'], ENT_QUOTES, 'UTF-8') ?></p><p class="text-[11px] text-[#7890b4] truncate"><?= htmlspecialchars((string)($event['course_name'] ?: $event['teacher_name']), ENT_QUOTES, 'UTF-8') ?></p></div></li><?php endforeach; ?></ol><?php else: ?><div class="h-[210px] flex flex-col items-center justify-center text-center px-5"><span class="w-10 h-10 rounded-full bg-[#f1f5fa] text-[#8ba0bd] flex items-center justify-center mb-3"><?= dashboardIcon('calendar') ?></span><p class="font-bold text-[#526b72]">วันนี้ยังไม่มีตารางสอน</p><a class="text-[12px] text-pink-500 font-bold mt-2" href="calendar.php">เพิ่มกิจกรรมในปฏิทิน</a></div><?php endif; ?>
        </section>
        <section class="bg-white rounded-[14px] border border-[#e6edf5] shadow-[0_8px_22px_rgba(15,42,83,.035)] overflow-hidden" aria-labelledby="course-distribution-title"><div class="px-5 py-4 flex items-center gap-2 border-b border-[#edf1f6]"><span class="w-8 h-8 rounded-lg bg-violet-100 text-[#8466e9] flex items-center justify-center"><?= dashboardIcon('users', 'w-[18px] h-[18px]') ?></span><h2 id="course-distribution-title" class="text-[17px] font-black">สัดส่วนนักเรียนตามคอร์ส</h2></div>
          <?php if ($enrolledStudents > 0): ?><div class="p-5 flex items-center gap-5"><div class="relative shrink-0 w-[132px] h-[132px] rounded-full" style="background: <?= htmlspecialchars($courseGradient, ENT_QUOTES, 'UTF-8') ?>"><div class="absolute inset-[27px] rounded-full bg-white flex flex-col items-center justify-center"><strong class="text-[23px] font-black leading-none"><?= number_format($enrolledStudents) ?></strong><small class="text-[10px] text-[#7890b4] mt-1">ผู้เรียนทั้งหมด</small></div></div><ul class="min-w-0 flex-1 space-y-2"><?php foreach ($courseGroups as $index => $group): ?><li class="flex items-center gap-2 text-[12px]"><span class="w-2.5 h-2.5 rounded-full shrink-0" style="background: <?= $colors[$index] ?>"></span><span class="text-[#526b72] truncate flex-1"><?= htmlspecialchars((string)$group['label'], ENT_QUOTES, 'UTF-8') ?></span><strong><?= number_format(((int)$group['total'] / $enrolledStudents) * 100, 0) ?>%</strong></li><?php endforeach; ?></ul></div><?php else: ?><div class="h-[210px] flex flex-col items-center justify-center text-center px-5"><span class="w-10 h-10 rounded-full bg-[#f1f5fa] text-[#8ba0bd] flex items-center justify-center mb-3"><?= dashboardIcon('users') ?></span><p class="font-bold text-[#526b72]">ยังไม่มีผู้เรียนที่ลงทะเบียนคอร์ส</p><p class="text-[12px] text-[#8ba0bd] mt-1">สัดส่วนจะแสดงจากการลงทะเบียนจริง</p></div><?php endif; ?>
        </section>
      </div>
    </main>
  </div>
</div>
</body>
</html>
