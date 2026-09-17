<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/live-sessions-helper.php';

ensureLiveSessionSchema($pdo);

$pageTitle = 'Live Session ห้องเรียนสด';
$pageDesc = 'ควบคุมห้องเรียนสดแบบ Real-time, ระบบล็อกหน้าจอ Eyes On Me และบอสไฟท์';
$currentPage = 'live-sessions.php';

$currentUserId = (int) ($consoleUser['id'] ?? 0);
$isAdmin = $consoleUser['role'] === 'admin';

// Fetch active session if any
$stmtActive = $pdo->prepare("SELECT s.*, e.title AS exam_title, (SELECT COUNT(*) FROM session_participants WHERE session_id = s.id) AS student_count FROM classroom_sessions s LEFT JOIN exams e ON e.id = s.exam_id WHERE (s.teacher_id = :tid OR :isAdmin = 1) AND s.status = 'active' ORDER BY s.started_at DESC LIMIT 1");
$stmtActive->execute([':tid' => $currentUserId, ':isAdmin' => ($isAdmin ? 1 : 0)]);
$activeSession = $stmtActive->fetch();

// Fetch previous sessions
$stmtPrev = $pdo->prepare("SELECT s.*, e.title AS exam_title, (SELECT COUNT(*) FROM session_participants WHERE session_id = s.id) AS student_count FROM classroom_sessions s LEFT JOIN exams e ON e.id = s.exam_id WHERE (s.teacher_id = :tid OR :isAdmin = 1) AND s.status = 'closed' ORDER BY s.ended_at DESC LIMIT 20");
$stmtPrev->execute([':tid' => $currentUserId, ':isAdmin' => ($isAdmin ? 1 : 0)]);
$closedSessions = $stmtPrev->fetchAll();

// Fetch available published exams
$exams = $pdo->query("SELECT id, title, subject, grade, time_limit_minutes FROM exams WHERE is_published = 1 AND status = 'active' ORDER BY title ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> - Next Beyond Admin</title>
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
      <div class="max-w-6xl mx-auto space-y-6">

        <!-- Top Header Banner -->
        <div class="p-6 rounded-3xl bg-gradient-to-r from-navy-950 via-slate-900 to-indigo-950 text-white shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative overflow-hidden">
          <div class="relative z-10">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-pink-500/20 text-pink-300 border border-pink-500/30 text-xs font-bold mb-2">
              ⚡ TEACHER COMMAND CENTER
            </span>
            <h1 class="text-2xl font-black text-white">Live Session ห้องเรียนสด</h1>
            <p class="text-xs text-slate-300 max-w-lg mt-1">
              สร้างรหัส PIN 6 หลักให้นักเรียนเข้าร่วมสด ควบคุมการทดสอบพร้อมกันด้วย Eyes On Me และต่อสู้บอสไฟท์แบบกลุ่ม
            </p>
          </div>

          <div class="relative z-10 flex items-center gap-3">
            <button type="button" id="btn-open-create-modal" class="px-5 py-3 rounded-2xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-sm shadow-[0_8px_20px_rgba(231,45,130,.35)] flex items-center gap-2 cursor-pointer transition-transform hover:scale-102">
              <span>＋</span> สร้างห้องเรียนสดใหม่
            </button>
          </div>
        </div>

        <!-- ACTIVE SESSION CARD -->
        <?php if ($activeSession): ?>
          <div class="p-6 rounded-3xl bg-white border-2 border-emerald-400 shadow-[0_12px_32px_rgba(16,185,129,0.12)] space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
              <div class="flex items-center gap-3">
                <span class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl font-black shrink-0">
                  🔴
                </span>
                <div>
                  <div class="flex items-center gap-2">
                    <h3 class="text-base font-black text-slate-900"><?= htmlspecialchars($activeSession['title']) ?></h3>
                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-extrabold uppercase animate-pulse">
                      กำลังเปิดสอน (LIVE)
                    </span>
                  </div>
                  <p class="text-xs text-slate-500 mt-0.5">
                    แบบทดสอบ: <b><?= htmlspecialchars((string) ($activeSession['exam_title'] ?: 'แบบทดสอบประจำคาบ')) ?></b> • เริ่มเมื่อ <?= date('d/m/Y H:i', strtotime($activeSession['started_at'])) ?> น.
                  </p>
                </div>
              </div>

              <div class="flex items-center gap-3">
                <div class="text-right">
                  <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">PIN เข้าร่วม</span>
                  <span class="text-2xl font-black font-mono tracking-widest text-pink-600 select-all">
                    <?= htmlspecialchars($activeSession['session_pin']) ?>
                  </span>
                </div>
                <a href="live-session-room.php?id=<?= urlencode($activeSession['id']) ?>" class="px-5 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm flex items-center gap-2 shadow-md transition-transform hover:scale-102">
                  <span>⚡</span> เข้าสู่ห้องควบคุมสด →
                </a>
              </div>
            </div>

            <!-- Quick Snapshot Stats -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
              <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                <span class="text-slate-500 block">นักเรียนในห้อง</span>
                <strong class="text-lg font-black text-slate-900"><?= number_format((int) $activeSession['student_count']) ?> คน</strong>
              </div>
              <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                <span class="text-slate-500 block">Eyes On Me</span>
                <strong class="text-sm font-black <?= $activeSession['eyes_on_me_enabled'] ? 'text-pink-600' : 'text-emerald-600' ?>">
                  <?= $activeSession['eyes_on_me_enabled'] ? '🔒 ล็อกหน้าจออยู่' : '🔓 ทำข้อสอบปกติ' ?>
                </strong>
              </div>
              <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                <span class="text-slate-500 block">เวลาสอบ</span>
                <strong class="text-sm font-black text-slate-800">
                  <?= !empty($activeSession['time_limit_minutes']) ? (int) $activeSession['time_limit_minutes'] . ' นาที' : 'ไม่จำกัดเวลา' ?>
                </strong>
              </div>
              <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-end">
                <button type="button" data-close-session="<?= htmlspecialchars($activeSession['id']) ?>" class="px-3 py-2 rounded-xl bg-slate-200 hover:bg-rose-100 hover:text-rose-700 text-slate-700 font-bold text-xs cursor-pointer transition-colors">
                  ⏹ สิ้นสุดห้องเรียนนี้
                </button>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- PREVIOUS SESSIONS TABLE -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
          <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900">ประวัติห้องเรียนสดย้อนหลัง</h3>
            <?php if ($closedSessions): ?>
              <button type="button" id="btn-delete-all-closed" class="text-xs text-rose-600 hover:text-rose-800 font-bold cursor-pointer">
                ล้างประวัติห้องเรียนที่ปิดแล้วทั้งหมด
              </button>
            <?php endif; ?>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                <tr>
                  <th class="p-4">PIN / ชื่อห้อง</th>
                  <th class="p-4">แบบทดสอบ</th>
                  <th class="p-4">นักเรียน</th>
                  <th class="p-4">เวลาเปิด - ปิด</th>
                  <th class="p-4 text-right">การจัดการ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php if ($closedSessions): ?>
                  <?php foreach ($closedSessions as $cs): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                      <td class="p-4">
                        <span class="font-mono font-black text-slate-900 text-sm">#<?= htmlspecialchars($cs['session_pin']) ?></span>
                        <strong class="block text-slate-800 text-xs mt-0.5"><?= htmlspecialchars($cs['title']) ?></strong>
                      </td>
                      <td class="p-4 text-slate-600">
                        <?= htmlspecialchars((string) ($cs['exam_title'] ?: '—')) ?>
                      </td>
                      <td class="p-4 font-bold text-slate-800">
                        <?= number_format((int) $cs['student_count']) ?> คน
                      </td>
                      <td class="p-4 text-slate-500">
                        <?= date('d/m/Y H:i', strtotime($cs['started_at'])) ?>
                        <?php if ($cs['ended_at']): ?>
                          – <?= date('H:i', strtotime($cs['ended_at'])) ?>
                        <?php endif; ?>
                      </td>
                      <td class="p-4 text-right space-x-2">
                        <a href="live-session-room.php?id=<?= urlencode($cs['id']) ?>" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold inline-block">
                          ดูรายงาน
                        </a>
                        <button type="button" data-delete-session="<?= htmlspecialchars($cs['id']) ?>" class="px-3 py-1.5 rounded-lg text-rose-600 hover:bg-rose-50 font-bold">
                          ลบ
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="5" class="p-12 text-center text-slate-400">ยังไม่มีประวัติห้องเรียนสดย้อนหลัง</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </main>
  </div>
</div>

<!-- CREATE LIVE SESSION MODAL -->
<div id="create-modal-overlay" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
  <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 space-y-4 my-auto">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <div>
        <h3 class="text-lg font-black text-slate-900">สร้างห้องเรียนสด (New Live Session)</h3>
        <p class="text-xs text-slate-500">สร้างรหัส PIN 6 หลักเพื่อให้นักเรียนทำข้อสอบพร้อมกัน</p>
      </div>
      <button type="button" id="btn-close-create-modal" class="text-slate-400 hover:text-slate-600 font-black text-lg p-1 cursor-pointer">✕</button>
    </div>

    <form id="create-session-form" class="space-y-4 text-xs">
      <div>
        <label class="block font-bold text-slate-700 mb-1">ชื่อห้องเรียนสด *</label>
        <input name="title" required placeholder="เช่น แบบทดสอบกลางภาค คาบเรียนที่ 1..." class="w-full h-11 px-4 rounded-xl border border-slate-300 outline-none focus:border-pink-500 text-sm font-semibold">
      </div>

      <div>
        <label class="block font-bold text-slate-700 mb-1">เลือกแบบทดสอบที่ใช้สอบ *</label>
        <select name="examId" required class="w-full h-11 px-3 rounded-xl border border-slate-300 outline-none focus:border-pink-500 text-sm font-semibold bg-white">
          <option value="">-- กรุณาเลือกแบบทดสอบ --</option>
          <?php foreach ($exams as $ex): ?>
            <option value="<?= $ex['id'] ?>">
              <?= htmlspecialchars($ex['title']) ?> (<?= htmlspecialchars((string) $ex['subject']) ?>, <?= htmlspecialchars((string) $ex['grade']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-bold text-slate-700 mb-1">ระดับการศึกษา</label>
          <select name="educationStage" class="w-full h-11 px-3 rounded-xl border border-slate-300 outline-none focus:border-pink-500 text-xs bg-white">
            <option value="m1">มัธยมศึกษาตอนต้น (ม.1 - ม.3)</option>
            <option value="m4">มัธยมศึกษาตอนปลาย (ม.4 - ม.6)</option>
            <option value="university" selected>เตรียมสอบมหาวิทยาลัย (TCAS)</option>
          </select>
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">จำกัดเวลา (นาที)</label>
          <input name="timeLimitMinutes" type="number" min="1" max="300" value="30" class="w-full h-11 px-4 rounded-xl border border-slate-300 outline-none focus:border-pink-500 text-xs font-semibold">
        </div>
      </div>

      <div class="space-y-2 pt-2 border-t border-slate-100">
        <label class="flex items-center gap-2 cursor-pointer font-semibold text-slate-700">
          <input name="hasTimeLimit" type="checkbox" checked class="w-4 h-4 rounded text-pink-600 focus:ring-pink-500">
          เปิดระบบนับเวลาถอยหลัง
        </label>
        <label class="flex items-center gap-2 cursor-pointer font-semibold text-slate-700">
          <input name="allowLateJoin" type="checkbox" checked class="w-4 h-4 rounded text-pink-600 focus:ring-pink-500">
          อนุญาตให้นักเรียนเข้าสายได้หลังจากเริ่มเซสชันแล้ว
        </label>
      </div>

      <div class="pt-2 flex items-center justify-end gap-2">
        <button type="button" id="btn-cancel-create" class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 font-bold cursor-pointer">
          ยกเลิก
        </button>
        <button type="submit" id="btn-submit-create" class="px-5 py-2.5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold cursor-pointer transition-colors shadow-md">
          🚀 เปิดห้องเรียนสดทันที
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(() => {
  const modal = document.getElementById("create-modal-overlay");
  const openBtn = document.getElementById("btn-open-create-modal");
  const closeBtn = document.getElementById("btn-close-create-modal");
  const cancelBtn = document.getElementById("btn-cancel-create");
  const form = document.getElementById("create-session-form");

  const toggleModal = (show) => {
    modal.classList.toggle("hidden", !show);
    if (show) form.reset();
  };

  if (openBtn) openBtn.onclick = () => toggleModal(true);
  if (closeBtn) closeBtn.onclick = () => toggleModal(false);
  if (cancelBtn) cancelBtn.onclick = () => toggleModal(false);

  form.onsubmit = async (e) => {
    e.preventDefault();
    const submitBtn = document.getElementById("btn-submit-create");
    submitBtn.disabled = true;
    submitBtn.textContent = "กำลังเปิดห้องเรียน...";

    const fd = new FormData(form);
    const body = {
      title: fd.get("title"),
      examId: fd.get("examId"),
      educationStage: fd.get("educationStage"),
      timeLimitMinutes: fd.get("timeLimitMinutes"),
      hasTimeLimit: fd.get("hasTimeLimit") !== null,
      allowLateJoin: fd.get("allowLateJoin") !== null,
    };

    try {
      const res = await fetch("live-sessions-api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "สร้างห้องเรียนไม่สำเร็จ");
      window.location.href = "live-session-room.php?id=" + encodeURIComponent(data.sessionId);
    } catch (err) {
      alert(err.message);
      submitBtn.disabled = false;
      submitBtn.textContent = "🚀 เปิดห้องเรียนสดทันที";
    }
  };

  document.body.onclick = async (e) => {
    const closeBtn = e.target.closest("[data-close-session]");
    if (closeBtn && confirm("ต้องการสิ้นสุดห้องเรียนสดนี้ใช่หรือไม่?")) {
      const sid = closeBtn.dataset.closeSession;
      await fetch("live-sessions-api.php", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ sessionId: sid, status: "closed" }),
      });
      location.reload();
    }

    const delBtn = e.target.closest("[data-delete-session]");
    if (delBtn && confirm("ต้องการลบข้อมูลห้องเรียนนี้ใช่หรือไม่?")) {
      const sid = delBtn.dataset.deleteSession;
      await fetch("live-sessions-api.php?sessionId=" + encodeURIComponent(sid), { method: "DELETE" });
      location.reload();
    }

    const delAllBtn = e.target.closest("#btn-delete-all-closed");
    if (delAllBtn && confirm("ต้องการลบประวัติห้องเรียนที่ปิดแล้วทั้งหมดใช่หรือไม่?")) {
      await fetch("live-sessions-api.php?action=delete_all_closed", { method: "DELETE" });
      location.reload();
    }
  };
})();
</script>
</body>
</html>
