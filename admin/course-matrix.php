<?php
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/enrollments-service.php';

$pageTitle = 'ตารางสิทธิ์คอร์ส (Course Access Matrix)';
$pageDesc = 'ภาพรวมสิทธิ์การเข้าเรียนรายบุคคลและกลุ่มเรียนทั้งหมด';
$currentPage = 'students.php';

$service = new EnrollmentService($pdo);
$grade = trim((string)($_GET['grade'] ?? ''));
$matrixData = $service->getCourseAccessMatrix($grade ?: null);
$courses = $matrixData['courses'] ?? [];
$students = $matrixData['students'] ?? [];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> - Next Beyond Admin</title>
  <link rel="stylesheet" href="../assets/css/output.css">
  <script src="../assets/js/admin-guard.js"></script>
</head>
<body class="bg-[#f4f7fb] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 ml-[240px] max-[1024px]:ml-0 flex flex-col min-w-0">
    <?php include 'includes/topbar.php'; ?>

    <main class="p-8 max-[640px]:p-4 max-w-[1700px] w-full mx-auto space-y-6">

      <!-- Header & Navigation -->
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <a href="students.php" class="text-xs font-semibold text-slate-500 hover:text-pink-600 transition-colors flex items-center gap-1">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
              <span>กลับหน้ารายชื่อนักเรียน</span>
            </a>
          </div>
          <h1 class="text-2xl font-black text-navy-950 flex items-center gap-2.5">
            <span class="p-2 rounded-xl bg-pink-50 text-pink-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 10h16M4 14h16M4 18h16M9 6v12M15 6v12"/></svg>
            </span>
            <span>ตารางสิทธิ์คอร์ส (Course Access Matrix)</span>
          </h1>
          <p class="text-xs text-slate-500 mt-1">เครื่องมือตรวจสอบความครอบคลุมของสิทธิ์เรียนแยกตามนักเรียนและรายวิชาแบบองค์รวม</p>
        </div>

        <div class="flex items-center gap-3">
          <form method="GET" class="flex items-center gap-2">
            <label class="text-xs font-semibold text-slate-600">ระดับชั้น:</label>
            <select name="grade" onchange="this.form.submit()" class="h-10 px-3 rounded-xl border border-slate-200 text-xs font-medium bg-white focus:outline-none focus:border-pink-500">
              <option value="" <?= $grade === '' ? 'selected' : '' ?>>ทุกระดับชั้น</option>
              <option value="ม.4" <?= $grade === 'ม.4' ? 'selected' : '' ?>>ม.4</option>
              <option value="ม.5" <?= $grade === 'ม.5' ? 'selected' : '' ?>>ม.5</option>
              <option value="ม.6" <?= $grade === 'ม.6' ? 'selected' : '' ?>>ม.6</option>
            </select>
          </form>

          <a href="students.php" class="h-10 px-4 rounded-xl bg-pink-500 hover:bg-pink-600 text-white text-xs font-bold shadow-sm flex items-center gap-1.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
            <span>มอบสิทธิ์คอร์ส</span>
          </a>
        </div>
      </div>

      <!-- Quick Summary Badges -->
      <div class="flex flex-wrap items-center gap-3 text-xs">
        <div class="px-3.5 py-2 rounded-xl bg-white border border-slate-200/80 shadow-xs flex items-center gap-2">
          <span class="text-slate-500 font-medium">นักเรียนในตาราง:</span>
          <span class="font-extrabold text-navy-950"><?= count($students) ?> คน</span>
        </div>
        <div class="px-3.5 py-2 rounded-xl bg-white border border-slate-200/80 shadow-xs flex items-center gap-2">
          <span class="text-slate-500 font-medium">คอร์สที่เปิดสอน:</span>
          <span class="font-extrabold text-pink-600"><?= count($courses) ?> คอร์ส</span>
        </div>
        <div class="px-3.5 py-2 rounded-xl bg-white border border-slate-200/80 shadow-xs flex items-center gap-2">
          <span class="text-slate-500 font-medium">คำอธิบายสัญลักษณ์:</span>
          <span class="inline-flex items-center gap-1 text-emerald-700 font-semibold"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> มีสิทธิ์เรียน</span>
          <span class="text-slate-300">|</span>
          <span class="inline-flex items-center gap-1 text-amber-700 font-semibold"><span class="w-2 h-2 rounded-full bg-amber-500"></span> ทดลองเรียน</span>
          <span class="text-slate-300">|</span>
          <span class="inline-flex items-center gap-1 text-slate-400 font-semibold">— ไม่มีสิทธิ์</span>
        </div>
      </div>

      <!-- Matrix Table -->
      <div class="bg-white rounded-[24px] border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto max-h-[720px]">
          <table class="w-full text-left border-collapse">
            <thead class="sticky top-0 z-20 bg-[#f8fafc] border-b border-slate-200">
              <tr>
                <th class="p-4 text-xs font-bold uppercase tracking-wider text-[#65738a] sticky left-0 z-30 bg-[#f8fafc] min-w-[220px] shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">
                  นักเรียน (Student)
                </th>
                <th class="p-4 text-xs font-bold uppercase tracking-wider text-[#65738a] min-w-[80px] text-center">
                  ระดับชั้น
                </th>
                <?php foreach ($courses as $c): ?>
                  <th class="p-4 text-xs font-bold uppercase tracking-wider text-[#65738a] min-w-[150px] text-center">
                    <div class="font-extrabold text-navy-950 truncate max-w-[160px]" title="<?= htmlspecialchars($c['title']) ?>">
                      <?= htmlspecialchars($c['title']) ?>
                    </div>
                    <div class="text-[10px] text-pink-600 font-semibold tracking-normal mt-0.5">
                      <?= htmlspecialchars($c['subject'] ?? 'ทั่วไป') ?>
                    </div>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
              <?php if (empty($students)): ?>
                <tr>
                  <td colspan="<?= count($courses) + 2 ?>" class="p-12 text-center text-slate-400">
                    ไม่พบข้อมูลนักเรียนในระดับชั้นนี้
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($students as $st): ?>
                  <tr class="hover:bg-slate-50/60 transition-colors">
                    <!-- Student Column (Sticky Left) -->
                    <td class="p-4 sticky left-0 z-10 bg-white hover:bg-slate-50 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">
                      <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-full bg-pink-100 text-pink-600 font-bold text-xs flex items-center justify-center shrink-0">
                          <?= mb_substr($st['name'], 0, 1) ?>
                        </div>
                        <div class="truncate">
                          <a href="student-detail.php?id=<?= $st['id'] ?>" class="font-bold text-navy-950 hover:text-pink-600 transition-colors">
                            <?= htmlspecialchars($st['name']) ?>
                          </a>
                          <div class="text-[10px] text-slate-400">ID: #<?= $st['id'] ?></div>
                        </div>
                      </div>
                    </td>

                    <!-- Grade Column -->
                    <td class="p-4 text-center">
                      <span class="inline-flex px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-bold text-[11px]">
                        <?= htmlspecialchars($st['grade']) ?>
                      </span>
                    </td>

                    <!-- Course Columns -->
                    <?php foreach ($courses as $c): 
                      $access = $st['courses'][$c['id']] ?? null;
                      $hasAccess = $access && !empty($access['has_access']);
                      $status = $access['status'] ?? '';
                      $classGroup = $access['class_group_name'] ?? '';
                      $mode = $access['learning_mode'] ?? '';
                    ?>
                      <td class="p-4 text-center">
                        <?php if ($hasAccess): ?>
                          <div class="inline-flex flex-col items-center gap-1">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold <?= $status === 'trial' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>">
                              <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                              <span><?= $status === 'trial' ? 'Trial' : 'Active' ?></span>
                            </span>
                            <?php if ($classGroup): ?>
                              <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200/60">
                                <?= htmlspecialchars($classGroup) ?>
                              </span>
                            <?php endif; ?>
                          </div>
                        <?php else: ?>
                          <span class="text-slate-300 font-bold select-none text-base">—</span>
                        <?php endif; ?>
                      </td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between text-xs text-slate-500">
          <span>คลิกที่ชื่อนักเรียนเพื่อเปิดหน้าจัดการสิทธิ์รายบุคคลและบันทึกประวัติ</span>
          <span>Nextbeyond Compass Enrollment Engine</span>
        </div>
      </div>

    </main>
  </div>
</div>
</body>
</html>
