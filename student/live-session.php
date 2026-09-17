<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/live-sessions-helper.php';

ensureLiveSessionSchema($pdo);

$pageTitle = 'เข้าร่วมห้องเรียนสด (Live Session)';
$currentPage = 'live-session.php';

$currentStudentId = (int) ($currentUser['id'] ?? 0);

// Check if student is in an ongoing active session
$stmtActive = $pdo->prepare("
    SELECT s.*, sp.status AS participant_status, e.title AS exam_title
    FROM session_participants sp
    JOIN classroom_sessions s ON s.id = sp.session_id
    LEFT JOIN exams e ON e.id = s.exam_id
    WHERE sp.student_id = :uid AND s.status = 'active'
    ORDER BY s.started_at DESC LIMIT 1
");
$stmtActive->execute([':uid' => $currentStudentId]);
$currentActiveSession = $stmtActive->fetch();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> - Next Beyond</title>
  <link rel="stylesheet" href="../assets/css/output.css">
  <link rel="stylesheet" href="../assets/css/student-portal.css">
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Inter:wght@500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="../assets/js/student-guard.js"></script>
</head>
<body class="student-portal bg-[#f4f7fb] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0">
    <?php include 'includes/topbar.php'; ?>
    <main class="flex-1 p-8 max-[640px]:p-4 flex items-center justify-center">
      <div class="max-w-md w-full space-y-6">

        <?php if ($currentActiveSession): ?>
          <div class="p-5 rounded-3xl bg-emerald-50 border border-emerald-200 shadow-sm text-center space-y-3">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold animate-pulse">
              🔴 คุณอยู่ในห้องเรียนสดที่กำลังเปิดอยู่
            </span>
            <h3 class="text-lg font-black text-slate-900"><?= htmlspecialchars($currentActiveSession['title']) ?></h3>
            <p class="text-xs text-slate-600">
              PIN: <strong class="font-mono text-emerald-700 text-sm">#<?= htmlspecialchars($currentActiveSession['session_pin']) ?></strong> • <?= htmlspecialchars((string) $currentActiveSession['exam_title']) ?>
            </p>
            <a href="take-test.php?id=<?= (int) $currentActiveSession['exam_id'] ?>&sessionId=<?= urlencode($currentActiveSession['id']) ?>" class="inline-flex items-center justify-center px-6 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-md transition-transform hover:scale-102">
              ⚡ กลับเข้าห้องเรียนสดทันที →
            </a>
          </div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-xl text-center space-y-6">
          <div class="w-16 h-16 rounded-3xl bg-gradient-to-tr from-pink-500 to-rose-600 text-white flex items-center justify-center text-3xl mx-auto shadow-lg shadow-pink-500/25">
            ⚡
          </div>

          <div>
            <h1 class="text-2xl font-black text-navy-950">เข้าร่วมห้องเรียนสด</h1>
            <p class="text-xs text-slate-500 mt-1">กรอกรหัส PIN 6 หลักที่คุณครูแจ้งเพื่อเริ่มทำข้อสอบสด</p>
          </div>

          <form id="join-pin-form" class="space-y-4">
            <div id="join-error" class="hidden p-3 rounded-xl bg-red-50 text-red-600 text-xs font-bold border border-red-200"></div>

            <div>
              <label for="session-pin" class="block text-xs font-bold text-slate-700 mb-2">รหัส PIN 6 หลัก</label>
              <input
                id="session-pin"
                name="sessionPin"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                required
                autocomplete="off"
                placeholder="000000"
                value="<?= htmlspecialchars(preg_replace('/[^\d]/', '', (string)($_GET['pin'] ?? ''))) ?>"
                class="w-full text-center text-3xl font-mono font-black tracking-widest h-16 rounded-2xl border-2 border-slate-200 outline-none focus:border-pink-500 text-navy-950 transition-colors"
                autofocus
              >
            </div>

            <button
              type="submit"
              id="btn-join-session"
              class="w-full h-12 rounded-2xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-sm shadow-[0_8px_20px_rgba(231,45,130,.3)] flex items-center justify-center gap-2 cursor-pointer transition-transform hover:scale-102"
            >
              <span>เข้าร่วมทันที</span> →
            </button>
          </form>

          <!-- Feature badges -->
          <div class="grid grid-cols-2 gap-2 pt-4 border-t border-slate-100 text-[11px] text-slate-500">
            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-2 text-left">
              <span class="text-base">👀</span>
              <div>
                <strong class="block text-slate-800">Eyes On Me</strong>
                <span>ครูควบคุมหน้าจอสด</span>
              </div>
            </div>
            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-2 text-left">
              <span class="text-base">📊</span>
              <div>
                <strong class="block text-slate-800">Real-Time Quiz</strong>
                <span>ทำข้อสอบและตรวจผลสด</span>
              </div>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>
</div>

<script>
(() => {
  const form = document.getElementById("join-pin-form");
  const input = document.getElementById("session-pin");
  const btn = document.getElementById("btn-join-session");
  const errBox = document.getElementById("join-error");

  input.addEventListener("input", (e) => {
    e.target.value = e.target.value.replace(/[^0-9]/g, "");
    if (e.target.value.length === 6) {
      form.requestSubmit();
    }
  });

  form.onsubmit = async (e) => {
    e.preventDefault();
    errBox.classList.add("hidden");
    const pin = input.value.trim();
    if (pin.length !== 6) {
      errBox.textContent = "กรุณากรอกรหัส PIN ให้ครบ 6 หลัก";
      errBox.classList.remove("hidden");
      input.focus();
      return;
    }

    btn.disabled = true;
    btn.textContent = "กำลังตรวจสอบ PIN...";

    try {
      const res = await fetch("live-session-api.php?action=join", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ sessionPin: pin }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "ไม่สามารถเข้าร่วมห้องเรียนได้");
      window.location.href = data.redirectUrl;
    } catch (err) {
      errBox.textContent = err.message;
      errBox.classList.remove("hidden");
      btn.disabled = false;
      btn.innerHTML = "<span>เข้าร่วมทันที</span> →";
      input.select();
    }
  };
})();
</script>
</body>
</html>
