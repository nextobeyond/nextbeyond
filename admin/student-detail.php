<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/enrollments-service.php';

$studentId = (int)($_GET['id'] ?? 0);
if (!$studentId) {
    header('Location: students.php');
    exit;
}

$service = new EnrollmentService($pdo);

// Fetch Student Profile
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(DISTINCT CASE WHEN e.status IN ('active', 'trial') THEN e.id END) AS active_courses_count
    FROM users u
    LEFT JOIN enrollments e ON e.user_id = u.id AND e.status IN ('active', 'trial')
    WHERE u.id = ? AND u.role = 'student'
    GROUP BY u.id
");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo '<meta charset="utf-8"><p>ไม่พบข้อมูลนักเรียน</p><a href="students.php">กลับไปรายชื่อนักเรียน</a>';
    exit;
}

$pageTitle = "จัดการสิทธิ์คอร์ส — " . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
$pageDesc = "กำหนดสิทธิ์คอร์สเรียน จัดกลุ่มเรียน และตารางเรียนของนักเรียน";
$currentPage = 'students.php';

// Fetch Enrollments, Schedule & Audit Logs
$enrollments = $service->getStudentCourseAccess($studentId);
$schedule = $service->getStudentCombinedSchedule($studentId);
$auditLogs = $service->getStudentAuditLogs($studentId);

// Fetch All Active Courses for the Assignment Modal
$allCourses = $pdo->query("SELECT id, title, subject, level, price FROM courses WHERE status = 'active' ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> - Next Beyond Admin</title>
  <link rel="stylesheet" href="../assets/css/output.css?v=<?= filemtime(__DIR__ . '/../assets/css/output.css') ?>">
  <script src="../assets/js/admin-guard.js"></script>
</head>
<body class="bg-[#f4f7fb] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="flex-1 p-8 max-[640px]:p-4 max-w-[1400px] w-full mx-auto space-y-6">
      <!-- Breadcrumb Navigation -->
      <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-2 text-[13px] font-bold text-[#64748b]">
          <a href="students.php" class="hover:text-pink-600 transition flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>รายชื่อนักเรียน</span>
          </a>
          <span>/</span>
          <span class="text-navy-950 font-black"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ($student['nickname'] ? ' (' . $student['nickname'] . ')' : '')) ?></span>
        </div>

        <div class="flex items-center gap-2.5">
          <a href="course-matrix.php" class="h-10 px-4 rounded-xl border border-[#dce4ef] bg-white hover:bg-slate-50 text-[13px] font-bold text-navy-900 transition flex items-center gap-1.5 shadow-xs">
            <span>📊 Course Access Matrix</span>
          </a>
          <button type="button" id="btn-open-assign-modal" class="h-10 px-5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-[13px] transition shadow-[0_4px_12px_rgba(231,45,130,0.25)] flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>+ เพิ่มสิทธิ์คอร์ส</span>
          </button>
        </div>
      </div>

      <!-- Student Profile Overview Card (Section 3) -->
      <div class="bg-white rounded-3xl border border-[#e8ecf2] p-6 shadow-sm flex items-center justify-between gap-6 flex-wrap">
        <div class="flex items-center gap-4 min-w-[260px]">
          <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-pink-500 to-indigo-600 text-white font-black text-2xl flex items-center justify-center shadow-md shrink-0">
            <?= mb_substr($student['first_name'], 0, 1) ?>
          </div>
          <div>
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <h1 class="text-[22px] font-black text-navy-950">
                <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                <?php if (!empty($student['nickname'])): ?>
                  <span class="text-pink-600 font-bold text-[18px]">(<?= htmlspecialchars($student['nickname']) ?>)</span>
                <?php endif; ?>
              </h1>
              <span class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-800 text-[11px] font-black border border-slate-200">
                <?= htmlspecialchars($student['grade'] ?: 'ม.5') ?>
              </span>
              <span class="px-2.5 py-0.5 rounded-full <?= $student['is_active'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500' ?> text-[11px] font-bold">
                <?= $student['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </div>
            <div class="flex items-center gap-4 text-[13px] text-[#64748b] flex-wrap">
              <span>📧 <?= htmlspecialchars($student['email']) ?></span>
              <?php if (!empty($student['phone'])): ?>
                <span>📞 <?= htmlspecialchars($student['phone']) ?></span>
              <?php endif; ?>
              <span>🆔 รหัสนักเรียน: #<?= $student['id'] ?></span>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-4">
          <div class="p-3.5 rounded-2xl bg-pink-50/70 border border-pink-100 text-center min-w-[120px]">
            <div class="text-[11px] font-bold text-pink-700 uppercase">คอร์สที่กำลังเรียน</div>
            <div class="text-[24px] font-black text-pink-600 leading-tight mt-0.5">
              <?= count($enrollments) ?>
            </div>
          </div>
          <div class="p-3.5 rounded-2xl bg-blue-50/70 border border-blue-100 text-center min-w-[120px]">
            <div class="text-[11px] font-bold text-blue-700 uppercase">กลุ่มเรียน (Classes)</div>
            <div class="text-[24px] font-black text-blue-600 leading-tight mt-0.5">
              <?= count(array_filter(array_column($enrollments, 'class_group_name'))) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Two-Column Layout -->
      <div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-6 items-start">
        <!-- Left Column: Course Access List -->
        <div class="space-y-6">
          <div class="bg-white rounded-3xl border border-[#e8ecf2] p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3 mb-6 pb-4 border-b border-[#f1f5f9] flex-wrap">
              <div>
                <h2 class="text-[18px] font-black text-navy-950 flex items-center gap-2">
                  <span>สิทธิ์คอร์ส (Course Access)</span>
                  <span class="px-2.5 py-0.5 rounded-full bg-pink-100 text-pink-700 text-[11px] font-bold">
                    <?= count($enrollments) ?> รายการ
                  </span>
                </h2>
                <p class="text-[12px] text-[#64748b] mt-0.5">คอร์สที่นักเรียนได้รับสิทธิ์เข้าเรียน เนื้อหา และตารางสอน</p>
              </div>

              <button type="button" onclick="document.getElementById('btn-open-assign-modal').click()" class="h-9 px-4 rounded-xl border border-pink-200 text-pink-600 hover:bg-pink-50 text-[12px] font-bold transition flex items-center gap-1.5">
                <span>+ เพิ่มคอร์ส</span>
              </button>
            </div>

            <!-- Enrollment Cards List -->
            <div class="space-y-4" id="enrollment-list-container">
              <?php if (empty($enrollments)): ?>
                <div class="py-12 text-center text-[#64748b]">
                  <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3 text-xl">📚</div>
                  <p class="font-bold text-[15px] text-navy-950 mb-1">ยังไม่มีสิทธิ์ในคอร์สเรียนใด</p>
                  <p class="text-[13px] max-w-[340px] mx-auto mb-4">กดปุ่ม "+ เพิ่มคอร์ส" ด้านบนเพื่อมอบสิทธิ์การเรียนให้นักเรียน</p>
                  <button type="button" onclick="document.getElementById('btn-open-assign-modal').click()" class="h-9 px-4 rounded-xl bg-pink-500 hover:bg-pink-600 text-white text-[12px] font-bold transition shadow-xs">
                    + มอบสิทธิ์คอร์สแรก
                  </button>
                </div>
              <?php else: ?>
                <?php foreach ($enrollments as $en): ?>
                  <?php
                    $isExpired = (!empty($en['expires_at']) && strtotime($en['expires_at']) < time()) || $en['status'] === 'expired';
                    $isTrial = $en['status'] === 'trial' || $en['access_type'] === 'trial';
                    $isPaused = $en['status'] === 'paused';
                    $isRevoked = $en['status'] === 'revoked';

                    // Status Badge
                    $statusBadge = '<span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-black border border-emerald-200">Active (กำลังเรียน)</span>';
                    if ($isTrial) {
                        $statusBadge = '<span class="px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 text-[11px] font-black border border-blue-200">⏱️ Trial (ทดลองเรียน)</span>';
                    } elseif ($isExpired) {
                        $statusBadge = '<span class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[11px] font-black border border-slate-200">⚠️ Expired (หมดอายุ)</span>';
                    } elseif ($isPaused) {
                        $statusBadge = '<span class="px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[11px] font-black border border-amber-200">⏸️ Paused (พักสิทธิ์)</span>';
                    } elseif ($isRevoked) {
                        $statusBadge = '<span class="px-2.5 py-0.5 rounded-full bg-rose-50 text-rose-700 text-[11px] font-black border border-rose-200">🚫 Revoked (ยกเลิกสิทธิ์)</span>';
                    }

                    // Mode Badge
                    $mode = strtolower($en['learning_mode'] ?? 'online');
                    $modeBadge = '<span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold">Online</span>';
                    if ($mode === 'onsite') $modeBadge = '<span class="px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 text-[10px] font-bold">On-site</span>';
                    elseif ($mode === 'hybrid') $modeBadge = '<span class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 text-[10px] font-bold">Hybrid</span>';

                    // Access Type Badge
                    $accType = strtolower($en['access_type'] ?? 'paid');
                    $accTypeBadge = '<span class="px-2 py-0.5 rounded-md bg-pink-50 text-pink-700 text-[10px] font-bold">Paid</span>';
                    if ($accType === 'scholarship') $accTypeBadge = '<span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 text-[10px] font-bold">Scholarship</span>';
                    elseif ($accType === 'complimentary') $accTypeBadge = '<span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 text-[10px] font-bold">Complimentary</span>';
                    elseif ($accType === 'bundle') $accTypeBadge = '<span class="px-2 py-0.5 rounded-md bg-purple-50 text-purple-800 text-[10px] font-bold">Bundle</span>';
                  ?>
                  <div class="en-card rounded-2xl border border-[#e8ecf2] p-5 hover:border-pink-300 transition-all bg-white shadow-2xs space-y-3" data-id="<?= $en['id'] ?>">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                      <div>
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                          <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold"><?= htmlspecialchars($en['subject'] ?: 'วิชา') ?></span>
                          <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-bold"><?= htmlspecialchars($en['level'] ?: 'ม.5') ?></span>
                          <?= $statusBadge ?>
                          <?= $modeBadge ?>
                          <?= $accTypeBadge ?>
                        </div>
                        <h3 class="text-[17px] font-black text-navy-950">
                          <?= htmlspecialchars($en['course_title']) ?>
                        </h3>
                      </div>

                      <div class="text-right text-[12px] text-[#64748b]">
                        <span class="text-[11px] uppercase font-bold text-[#94a3b8]">หมดอายุ</span>
                        <div class="font-bold text-navy-900">
                          <?= !empty($en['expires_at']) ? date('d/m/Y', strtotime($en['expires_at'])) : 'ไม่จำกัดเวลา' ?>
                        </div>
                      </div>
                    </div>

                    <!-- Class Group & Schedule Details (Section 12, 13) -->
                    <div class="p-3 rounded-xl bg-[#f8fafc] border border-[#edf2f7] flex items-center justify-between gap-4 flex-wrap text-[12px]">
                      <div class="flex items-center gap-3 flex-wrap">
                        <span class="font-bold text-[#475569]">กลุ่มเรียน:</span>
                        <?php if (!empty($en['class_group_name'])): ?>
                          <span class="px-2.5 py-0.5 rounded-lg bg-pink-100 text-pink-700 font-bold flex items-center gap-1">
                            <span>👥 <?= htmlspecialchars($en['class_group_name']) ?></span>
                          </span>
                          <?php if (!empty($en['schedule_text'])): ?>
                            <span class="text-[#64748b] flex items-center gap-1">
                              <span>🕒 <?= htmlspecialchars($en['schedule_text']) ?></span>
                            </span>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="text-[#94a3b8] italic">ยังไม่ได้จัดกลุ่มเรียน</span>
                        <?php endif; ?>
                      </div>

                      <button type="button" class="btn-change-class text-pink-600 hover:text-pink-700 font-bold text-[11px]" data-en-id="<?= $en['id'] ?>" data-course-id="<?= $en['course_id'] ?>" data-current-cg="<?= $en['class_group_id'] ?? '' ?>">
                        เปลี่ยนกลุ่มเรียน →
                      </button>
                    </div>

                    <!-- Action Bar (Sections 22, 23) -->
                    <div class="flex items-center justify-between gap-3 pt-2 border-t border-[#f1f5f9] flex-wrap text-[12px]">
                      <div class="flex items-center gap-2">
                        <span class="text-[11px] font-bold text-[#94a3b8]">ต่ออายุเร็ว:</span>
                        <button type="button" class="btn-quick-extend px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 font-bold text-[11px] text-[#475569] transition" data-id="<?= $en['id'] ?>" data-days="30">
                          + 30 วัน
                        </button>
                        <button type="button" class="btn-quick-extend px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 font-bold text-[11px] text-[#475569] transition" data-id="<?= $en['id'] ?>" data-days="90">
                          + 90 วัน
                        </button>
                      </div>

                      <div class="flex items-center gap-2">
                        <?php if ($en['status'] === 'active'): ?>
                          <button type="button" class="btn-update-status px-2.5 py-1 rounded-lg border border-amber-300 bg-amber-50/60 text-amber-800 hover:bg-amber-100 text-[11px] font-bold transition" data-id="<?= $en['id'] ?>" data-status="paused">
                            ⏸️ พักสิทธิ์
                          </button>
                        <?php elseif ($en['status'] === 'paused'): ?>
                          <button type="button" class="btn-update-status px-2.5 py-1 rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 text-[11px] font-bold transition" data-id="<?= $en['id'] ?>" data-status="active">
                            ▶️ เปิดสิทธิ์
                          </button>
                        <?php endif; ?>

                        <?php if ($en['status'] !== 'revoked'): ?>
                          <button type="button" class="btn-update-status px-2.5 py-1 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 text-[11px] font-bold transition" data-id="<?= $en['id'] ?>" data-status="revoked">
                            🚫 ยกเลิกสิทธิ์
                          </button>
                        <?php else: ?>
                          <button type="button" class="btn-update-status px-2.5 py-1 rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 text-[11px] font-bold transition" data-id="<?= $en['id'] ?>" data-status="active">
                            คืนสิทธิ์
                          </button>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Section: Audit Log History (Section 41) -->
          <div class="bg-white rounded-3xl border border-[#e8ecf2] p-6 shadow-sm">
            <h3 class="text-[16px] font-black text-navy-950 mb-3 pb-2 border-b border-[#f1f5f9] flex items-center justify-between">
              <span>ประวัติการแก้ไขสิทธิ์ (Audit Log)</span>
              <span class="text-[11px] text-[#94a3b8] font-normal">บันทึกทุกการจัดสรรและเปลี่ยนกลุ่ม</span>
            </h3>

            <?php if (empty($auditLogs)): ?>
              <p class="text-[13px] text-[#94a3b8] py-3">ยังไม่มีประวัติการทำรายการ</p>
            <?php else: ?>
              <div class="divide-y divide-[#f1f5f9] text-[12px] max-h-[300px] overflow-y-auto pr-2">
                <?php foreach ($auditLogs as $log): ?>
                  <div class="py-2.5 flex items-start justify-between gap-3">
                    <div>
                      <div class="font-bold text-navy-950">
                        <?= htmlspecialchars($log['details']) ?>
                      </div>
                      <div class="text-[11px] text-[#64748b]">
                        <?= htmlspecialchars($log['course_title'] ?: 'คอร์สเรียน') ?> · โดย <?= htmlspecialchars($log['performed_by_name'] ?: 'Admin') ?>
                      </div>
                    </div>
                    <span class="text-[10px] text-[#94a3b8] shrink-0">
                      <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Right Column: Combined Schedule Preview (Section 34) -->
        <aside class="space-y-6">
          <div class="bg-white rounded-3xl border border-[#e8ecf2] p-6 shadow-sm sticky top-6">
            <div class="flex items-center justify-between gap-2 mb-4 pb-3 border-b border-[#f1f5f9]">
              <div>
                <h3 class="text-[16px] font-black text-navy-950">
                  ตารางเรียนรวม (Schedule)
                </h3>
                <p class="text-[11px] text-[#64748b]">รวมตารางจากทุกคอร์สที่ได้รับสิทธิ์</p>
              </div>
              <span class="px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 text-[11px] font-bold">
                <?= count($schedule) ?> คาบ/สัปดาห์
              </span>
            </div>

            <?php if (empty($schedule)): ?>
              <div class="py-8 text-center text-[#94a3b8] text-[13px]">
                <p>ยังไม่มีคาบเรียนในตาราง</p>
                <p class="text-[11px] mt-1">จัดกลุ่มเรียนให้นักเรียนเพื่อแสดงตาราง</p>
              </div>
            <?php else: ?>
              <div class="space-y-3">
                <?php foreach ($schedule as $sc): ?>
                  <div class="p-3.5 rounded-2xl bg-[#f8fafc] border border-[#e2e8f0] text-[12px] space-y-1">
                    <div class="flex items-center justify-between font-bold text-navy-950">
                      <span class="text-pink-600 font-black">📅 <?= htmlspecialchars($sc['schedule_day']) ?></span>
                      <span class="text-[#64748b]"><?= htmlspecialchars($sc['schedule_time']) ?></span>
                    </div>
                    <div class="font-black text-navy-950 text-[13px]">
                      <?= htmlspecialchars($sc['course_title']) ?>
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-[#64748b]">
                      <span>กลุ่ม: <strong><?= htmlspecialchars($sc['class_group_name']) ?></strong></span>
                      <?php if (!empty($sc['room'])): ?>
                        <span>ห้อง: <?= htmlspecialchars($sc['room']) ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </aside>
      </div>
    </main>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: ASSIGN COURSE ACCESS (Section 4, 5, 6, 7, 8) -->
<!-- ========================================================================= -->
<div id="assign-modal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-navy-950/60 backdrop-blur-xs cursor-pointer" onclick="closeAssignModal()"></div>
  <div class="relative w-full max-w-[560px] bg-white rounded-3xl shadow-2xl border border-[#e2e8f0] p-6 z-10 my-auto animate-in fade-in zoom-in duration-150">
    <div class="flex items-center justify-between mb-4 pb-3 border-b border-[#f1f5f9]">
      <h3 class="text-[18px] font-black text-navy-950 flex items-center gap-2">
        <span>+ กำหนดสิทธิ์คอร์สเรียน</span>
      </h3>
      <button type="button" onclick="closeAssignModal()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-[#64748b] flex items-center justify-center font-bold">✕</button>
    </div>

    <form id="form-assign-course" class="space-y-4 text-[13px]">
      <input type="hidden" name="student_id" value="<?= $studentId ?>">

      <!-- Student Name (Readonly) -->
      <div>
        <label class="block font-bold text-[#475569] mb-1">นักเรียน</label>
        <div class="h-10 px-3.5 rounded-xl bg-[#f8fafc] border border-[#dce4ef] flex items-center font-bold text-navy-950">
          <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ($student['nickname'] ? ' (' . $student['nickname'] . ')' : '')) ?> — <?= htmlspecialchars($student['grade'] ?: 'ม.5') ?>
        </div>
      </div>

      <!-- Course Selection -->
      <div>
        <label class="block font-bold text-[#475569] mb-1">เลือกคอร์สเรียน *</label>
        <select name="course_id" id="modal-select-course" required class="w-full h-11 px-3.5 rounded-xl border border-[#dce4ef] bg-white font-bold text-navy-950 focus:border-pink-500 outline-none">
          <option value="">-- เลือกคอร์สเรียน --</option>
          <?php foreach ($allCourses as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?> (<?= htmlspecialchars($c['subject'] ?: '') ?> · <?= htmlspecialchars($c['level'] ?: '') ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Class Group Selection (Dynamic) -->
      <div>
        <div class="flex items-center justify-between mb-1">
          <label class="font-bold text-[#475569]">กลุ่มเรียน (Class Group)</label>
          <span id="modal-cg-capacity" class="text-[11px] text-[#94a3b8]"></span>
        </div>
        <select name="class_group_id" id="modal-select-cg" class="w-full h-11 px-3.5 rounded-xl border border-[#dce4ef] bg-white font-bold text-navy-950 focus:border-pink-500 outline-none">
          <option value="">-- ไม่ระบุกลุ่มเรียน (เรียนออนไลน์ทั่วไป) --</option>
        </select>
      </div>

      <!-- Access Type & Learning Mode -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-[#475569] mb-1">ประเภทสิทธิ์ (Access Type)</label>
          <select name="access_type" class="w-full h-10 px-3 rounded-xl border border-[#dce4ef] font-bold text-navy-950 outline-none">
            <option value="paid">Paid (ชำระเงินปกติ)</option>
            <option value="trial">Trial (ทดลองเรียน 7 วัน)</option>
            <option value="scholarship">Scholarship (ทุนการศึกษา)</option>
            <option value="complimentary">Complimentary (ให้สิทธิ์ฟรี)</option>
            <option value="manual">Manual (กำหนดเฉพาะกิจ)</option>
            <option value="bundle">Bundle (รวมแพ็กเกจ)</option>
          </select>
        </div>
        <div>
          <label class="block font-bold text-[#475569] mb-1">โหมดการเรียน</label>
          <select name="learning_mode" class="w-full h-10 px-3 rounded-xl border border-[#dce4ef] font-bold text-navy-950 outline-none">
            <option value="online">Online</option>
            <option value="onsite">On-site</option>
            <option value="hybrid">Hybrid</option>
          </select>
        </div>
      </div>

      <!-- Expiration Date Preset -->
      <div>
        <label class="block font-bold text-[#475569] mb-1">วันหมดอายุสิทธิ์</label>
        <div class="flex items-center gap-2 mb-2">
          <button type="button" onclick="setExpirePreset(7)" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 font-bold text-[11px]">7 วัน</button>
          <button type="button" onclick="setExpirePreset(30)" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 font-bold text-[11px]">30 วัน</button>
          <button type="button" onclick="setExpirePreset(90)" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 font-bold text-[11px]">90 วัน</button>
          <button type="button" onclick="setExpirePreset(365)" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 font-bold text-[11px]">1 ปี</button>
          <button type="button" onclick="document.getElementById('modal-end-date').value=''" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-pink-50 hover:text-pink-600 font-bold text-[11px]">ไม่มีหมดอายุ</button>
        </div>
        <input type="date" name="end_date" id="modal-end-date" class="w-full h-10 px-3 rounded-xl border border-[#dce4ef] font-bold text-navy-950 outline-none">
      </div>

      <!-- Notes -->
      <div>
        <label class="block font-bold text-[#475569] mb-1">หมายเหตุ</label>
        <input type="text" name="notes" placeholder="เช่น รหัสนักเรียนเก่า, โปรโมชั่นเปิดเทอม" class="w-full h-10 px-3.5 rounded-xl border border-[#dce4ef] text-navy-950 outline-none">
      </div>

      <div id="assign-modal-error" class="hidden p-3 rounded-xl bg-rose-50 text-rose-700 text-[12px] font-bold"></div>

      <div class="flex items-center justify-end gap-3 pt-3 border-t border-[#f1f5f9]">
        <button type="button" onclick="closeAssignModal()" class="h-10 px-4 rounded-xl border border-[#dce4ef] hover:bg-slate-50 font-bold">ยกเลิก</button>
        <button type="submit" id="btn-submit-assign" class="h-10 px-6 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold transition shadow-xs">บันทึกสิทธิ์คอร์ส</button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: CHANGE CLASS GROUP (Section 25) -->
<!-- ========================================================================= -->
<div id="change-class-modal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
  <div class="fixed inset-0 bg-navy-950/60 backdrop-blur-xs cursor-pointer" onclick="closeChangeClassModal()"></div>
  <div class="relative w-full max-w-[460px] bg-white rounded-3xl shadow-2xl border border-[#e2e8f0] p-6 z-10 my-auto animate-in fade-in zoom-in duration-150">
    <h3 class="text-[17px] font-black text-navy-950 mb-3 pb-2 border-b border-[#f1f5f9]">ย้ายกลุ่มเรียน (Change Class Group)</h3>
    <form id="form-change-class" class="space-y-4 text-[13px]">
      <input type="hidden" name="enrollment_id" id="cc-enrollment-id">
      <div>
        <label class="block font-bold text-[#475569] mb-1">เลือกกลุ่มเรียนใหม่</label>
        <select name="class_group_id" id="cc-select-cg" required class="w-full h-11 px-3 rounded-xl border border-[#dce4ef] font-bold text-navy-950 outline-none"></select>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeChangeClassModal()" class="h-9 px-4 rounded-xl border font-bold">ยกเลิก</button>
        <button type="submit" class="h-9 px-5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold">ยืนยันการย้าย</button>
      </div>
    </form>
  </div>
</div>

<script>
(() => {
  const assignModal = document.getElementById("assign-modal");
  const changeClassModal = document.getElementById("change-class-modal");
  const courseSelect = document.getElementById("modal-select-course");
  const cgSelect = document.getElementById("modal-select-cg");
  const formAssign = document.getElementById("form-assign-course");
  const errorBox = document.getElementById("assign-modal-error");

  window.closeAssignModal = () => {
    assignModal.classList.add("hidden");
    formAssign.reset();
    errorBox.classList.add("hidden");
  };

  window.closeChangeClassModal = () => {
    changeClassModal.classList.add("hidden");
  };

  window.setExpirePreset = days => {
    const d = new Date();
    d.setDate(d.getDate() + days);
    document.getElementById("modal-end-date").value = d.toISOString().split("T")[0];
  };

  document.getElementById("btn-open-assign-modal")?.addEventListener("click", () => {
    assignModal.classList.remove("hidden");
  });

  // Load Class Groups when Course selection changes
  courseSelect?.addEventListener("change", async () => {
    const cId = courseSelect.value;
    cgSelect.innerHTML = '<option value="">-- ไม่ระบุกลุ่มเรียน (เรียนออนไลน์ทั่วไป) --</option>';
    if (!cId) return;

    try {
      const res = await fetch(`enrollments-api.php?action=class_groups&course_id=${cId}`);
      const d = await res.json();
      if (d.success && d.class_groups) {
        d.class_groups.forEach(cg => {
          const capText = `${cg.current_enrolled} / ${cg.capacity} คน`;
          const schedText = cg.schedule_text ? ` (${cg.schedule_text})` : '';
          const opt = document.createElement("option");
          opt.value = cg.id;
          opt.textContent = `${cg.name}${schedText} [${capText}]`;
          cgSelect.appendChild(opt);
        });
      }
    } catch (e) {
      console.error(e);
    }
  });

  // Handle Assign Form Submit
  formAssign?.addEventListener("submit", async e => {
    e.preventDefault();
    errorBox.classList.add("hidden");
    const formData = new FormData(formAssign);
    const body = Object.fromEntries(formData.entries());

    try {
      const res = await fetch("enrollments-api.php?action=assign", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body)
      });
      const d = await res.json();

      if (d.success) {
        alert("กำหนดสิทธิ์คอร์สเรียบร้อยแล้ว");
        window.location.reload();
      } else {
        errorBox.textContent = d.error || "เกิดข้อผิดพลาดในการกำหนดสิทธิ์";
        errorBox.classList.remove("hidden");
        if (d.is_duplicate) {
          if (confirm(`${d.error}\nต้องการขยายเวลาเรียนแทนหรือไม่?`)) {
            // Quick extend call
            await fetch("enrollments-api.php?action=extend", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ enrollment_id: d.existing_enrollment.id, days_or_date: 30 })
            });
            window.location.reload();
          }
        }
      }
    } catch (err) {
      errorBox.textContent = "เชื่อมต่อระบบล้มเหลว: " + err.message;
      errorBox.classList.remove("hidden");
    }
  });

  // Quick Extend Click (+30d, +90d)
  document.querySelectorAll(".btn-quick-extend").forEach(btn => {
    btn.addEventListener("click", async () => {
      const enId = btn.dataset.id;
      const days = btn.dataset.days;
      if (!confirm(`ต้องการขยายเวลาเรียนเพิ่ม ${days} วัน ใช่หรือไม่?`)) return;

      const res = await fetch("enrollments-api.php?action=extend", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ enrollment_id: enId, days_or_date: days })
      });
      const d = await res.json();
      if (d.success) {
        alert(d.message);
        window.location.reload();
      } else {
        alert(d.error || "เกิดข้อผิดพลาด");
      }
    });
  });

  // Update Status Click (pause, revoke, active)
  document.querySelectorAll(".btn-update-status").forEach(btn => {
    btn.addEventListener("click", async () => {
      const enId = btn.dataset.id;
      const status = btn.dataset.status;
      const reason = prompt("ระบุเหตุผลในการเปลี่ยนสถานะ (ไม่บังคับ):", "");

      const res = await fetch("enrollments-api.php?action=update_status", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ enrollment_id: enId, status: status, reason: reason || "" })
      });
      const d = await res.json();
      if (d.success) {
        alert(d.message);
        window.location.reload();
      } else {
        alert(d.error || "เกิดข้อผิดพลาด");
      }
    });
  });

  // Change Class Group Button
  document.querySelectorAll(".btn-change-class").forEach(btn => {
    btn.addEventListener("click", async () => {
      const enId = btn.dataset.enId;
      const courseId = btn.dataset.courseId;
      document.getElementById("cc-enrollment-id").value = enId;

      const ccSelect = document.getElementById("cc-select-cg");
      ccSelect.innerHTML = '<option value="">กำลังโหลดกลุ่มเรียน...</option>';
      changeClassModal.classList.remove("hidden");

      try {
        const res = await fetch(`enrollments-api.php?action=class_groups&course_id=${courseId}`);
        const d = await res.json();
        ccSelect.innerHTML = "";
        if (d.success && d.class_groups) {
          d.class_groups.forEach(cg => {
            const opt = document.createElement("option");
            opt.value = cg.id;
            opt.textContent = `${cg.name} (${cg.schedule_text || ''}) [${cg.current_enrolled}/${cg.capacity}]`;
            ccSelect.appendChild(opt);
          });
        }
      } catch (e) {
        ccSelect.innerHTML = '<option value="">โหลดกลุ่มเรียนไม่สำเร็จ</option>';
      }
    });
  });

  // Submit Change Class
  document.getElementById("form-change-class")?.addEventListener("submit", async e => {
    e.preventDefault();
    const enId = document.getElementById("cc-enrollment-id").value;
    const cgId = document.getElementById("cc-select-cg").value;
    if (!cgId) return;

    const res = await fetch("enrollments-api.php?action=change_class", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ enrollment_id: enId, class_group_id: cgId })
    });
    const d = await res.json();
    if (d.success) {
      alert(d.message);
      window.location.reload();
    } else {
      alert(d.error || "เกิดข้อผิดพลาด");
    }
  });
})();
</script>
</body>
</html>
