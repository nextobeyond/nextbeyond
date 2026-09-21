<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/live-sessions-helper.php';
require_once __DIR__ . '/../includes/phase2-session-service.php';

ensureLiveSessionSchema($pdo);

$pageTitle = 'ห้องเรียนสด (Live Session)';
$currentPage = 'live-session.php';

$currentStudentId = (int) ($currentUser['id'] ?? 0);
$studentName = trim($currentUser['first_name'] . ' ' . $currentUser['last_name']);
$studentFirstName = $currentUser['first_name'] ?: 'น้องๆ';

$reqSessionId = trim((string)($_GET['sessionId'] ?? ''));
$reqPin = preg_replace('/[^\d]/', '', (string)($_GET['pin'] ?? ''));

$currentActiveSession = null;

// 1. If explicit sessionId is requested
if ($reqSessionId !== '') {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               COALESCE(CONCAT_WS(' ', u.first_name, u.last_name), 'คุณครู') AS teacher_name,
               u.avatar_url AS teacher_avatar,
               e.title AS exam_title, e.subject AS exam_subject, e.grade AS exam_grade,
               ce.location, ce.notes AS calendar_notes, ce.event_date, ce.start_time, ce.end_time,
               sp.status AS participant_status, sp.attempt_id,
               ta.score, ta.correct_count, ta.total_questions, ta.completed_at
        FROM classroom_sessions s
        LEFT JOIN users u ON u.id = s.teacher_id
        LEFT JOIN exams e ON e.id = s.exam_id
        LEFT JOIN calendar_events ce ON ce.id = s.calendar_event_id
        LEFT JOIN session_participants sp ON sp.session_id = s.id AND sp.student_id = :uid
        LEFT JOIN test_attempts ta ON ta.id = sp.attempt_id
        WHERE s.id = :sid AND s.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([':sid' => $reqSessionId, ':uid' => $currentStudentId]);
    $currentActiveSession = $stmt->fetch();
}

// 2. If explicit 6-digit PIN is requested
if (!$currentActiveSession && strlen($reqPin) === 6) {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               COALESCE(CONCAT_WS(' ', u.first_name, u.last_name), 'คุณครู') AS teacher_name,
               u.avatar_url AS teacher_avatar,
               e.title AS exam_title, e.subject AS exam_subject, e.grade AS exam_grade,
               ce.location, ce.notes AS calendar_notes, ce.event_date, ce.start_time, ce.end_time,
               sp.status AS participant_status, sp.attempt_id,
               ta.score, ta.correct_count, ta.total_questions, ta.completed_at
        FROM classroom_sessions s
        LEFT JOIN users u ON u.id = s.teacher_id
        LEFT JOIN exams e ON e.id = s.exam_id
        LEFT JOIN calendar_events ce ON ce.id = s.calendar_event_id
        LEFT JOIN session_participants sp ON sp.session_id = s.id AND sp.student_id = :uid
        LEFT JOIN test_attempts ta ON ta.id = sp.attempt_id
        WHERE s.session_pin = :pin AND s.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([':pin' => $reqPin, ':uid' => $currentStudentId]);
    $currentActiveSession = $stmt->fetch();
}

// 3. Otherwise, check if student is currently joined in an ongoing active session
if (!$currentActiveSession) {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               COALESCE(CONCAT_WS(' ', u.first_name, u.last_name), 'คุณครู') AS teacher_name,
               u.avatar_url AS teacher_avatar,
               e.title AS exam_title, e.subject AS exam_subject, e.grade AS exam_grade,
               ce.location, ce.notes AS calendar_notes, ce.event_date, ce.start_time, ce.end_time,
               sp.status AS participant_status, sp.attempt_id,
               ta.score, ta.correct_count, ta.total_questions, ta.completed_at
        FROM session_participants sp
        JOIN classroom_sessions s ON s.id = sp.session_id
        LEFT JOIN users u ON u.id = s.teacher_id
        LEFT JOIN exams e ON e.id = s.exam_id
        LEFT JOIN calendar_events ce ON ce.id = s.calendar_event_id
        LEFT JOIN test_attempts ta ON ta.id = sp.attempt_id
        WHERE sp.student_id = :uid AND s.status = 'active'
        ORDER BY s.started_at DESC LIMIT 1
    ");
    $stmt->execute([':uid' => $currentStudentId]);
    $currentActiveSession = $stmt->fetch();
}

// Auto-register as participant if student found session but wasn't in participant table yet
if ($currentActiveSession && empty($currentActiveSession['participant_status'])) {
    $partId = 'sp-' . time() . '-' . random_int(1000, 9999);
    $pdo->prepare("INSERT IGNORE INTO session_participants (id, session_id, student_id, status, current_question, answered_count, joined_at) VALUES (:pid, :sid, :uid, 'joined', 1, 0, NOW())")
        ->execute([':pid' => $partId, ':sid' => $currentActiveSession['id'], ':uid' => $currentStudentId]);
    $currentActiveSession['participant_status'] = 'joined';
}

// Fetch session topics & question counts if in session
$sessionTopics = [];
$examQuestionsCount = 0;
$activeParticipants = [];
$meetingUrl = '';
$locationText = '';

if ($currentActiveSession) {
    $sessionId = (string)$currentActiveSession['id'];

    if (!empty($currentActiveSession['exam_id'])) {
        $stmtQC = $pdo->prepare("SELECT COUNT(*) FROM exam_questions WHERE exam_id = :eid");
        $stmtQC->execute([':eid' => $currentActiveSession['exam_id']]);
        $examQuestionsCount = (int) $stmtQC->fetchColumn();
    }

    try {
        $stmtT = $pdo->prepare("SELECT topic_name FROM session_topics WHERE session_id = :sid ORDER BY sort_order, id");
        $stmtT->execute([':sid' => $sessionId]);
        $sessionTopics = $stmtT->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (\Throwable $e) {}

    try {
        $stmtParts = $pdo->prepare("
            SELECT sp.student_id, sp.status, u.first_name, u.last_name, u.avatar_url
            FROM session_participants sp
            JOIN users u ON u.id = sp.student_id
            WHERE sp.session_id = :sid
            ORDER BY sp.joined_at ASC
            LIMIT 24
        ");
        $stmtParts->execute([':sid' => $sessionId]);
        $activeParticipants = $stmtParts->fetchAll();
    } catch (\Throwable $e) {}

    $rawLoc = trim((string)($currentActiveSession['location'] ?? ''));
    if ($rawLoc !== '') {
        if (preg_match('#^(https?://|meet\.google\.com|zoom\.us|teams\.microsoft\.com)#i', $rawLoc)) {
            $meetingUrl = preg_match('#^https?://#i', $rawLoc) ? $rawLoc : ('https://' . $rawLoc);
        } else {
            $locationText = $rawLoc;
        }
    }
} else {
    // If not in session, fetch today's active live sessions for quick 1-click joining
    $todaySessions = [];
    try {
        $stmtToday = $pdo->prepare("
            SELECT s.id, s.title, s.session_pin, s.started_at,
                   COALESCE(CONCAT_WS(' ', u.first_name, u.last_name), 'คุณครู') AS teacher_name,
                   e.title AS exam_title
            FROM classroom_sessions s
            LEFT JOIN users u ON u.id = s.teacher_id
            LEFT JOIN exams e ON e.id = s.exam_id
            WHERE s.status = 'active'
            ORDER BY s.started_at DESC LIMIT 5
        ");
        $stmtToday->execute();
        $todaySessions = $stmtToday->fetchAll();
    } catch (\Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> - Next Beyond</title>
  <link rel="stylesheet" href="../assets/css/output.css">
  <link rel="stylesheet" href="../assets/css/student-portal.css">
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700;800&family=Inter:wght@600;700;800;900&display=swap" rel="stylesheet">
  <script src="../assets/js/student-guard.js"></script>
  <style>
    .student-live-bg {
      background: radial-gradient(circle at 50% 10%, rgba(231, 45, 130, 0.07) 0%, rgba(99, 102, 241, 0.04) 50%, #f4f7fb 100%);
      min-height: calc(100vh - 64px);
    }
    .btn-join-gamified {
      background: linear-gradient(135deg, #e72d82 0%, #ff4b98 50%, #f43f5e 100%);
      color: #ffffff !important;
      box-shadow: 0 10px 24px -4px rgba(231, 45, 130, 0.45);
      transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .btn-join-gamified:hover:not(:disabled) {
      transform: translateY(-2px) scale(1.02);
      box-shadow: 0 14px 28px -4px rgba(231, 45, 130, 0.6);
    }
    .btn-join-meeting {
      background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
      box-shadow: 0 8px 20px -2px rgba(2, 132, 199, 0.4);
      transition: all 0.2s ease;
    }
    .btn-join-meeting:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 24px -2px rgba(2, 132, 199, 0.55);
    }
    .pin-input-field {
      letter-spacing: 0.35em;
      font-feature-settings: "tnum";
      font-variant-numeric: tabular-nums;
      transition: all 0.25s ease;
      background: #ffffff;
      color: #1e1b4b;
      box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.04);
    }
    .pin-input-field:focus {
      border-color: #e72d82;
      box-shadow: 0 0 0 4px rgba(231, 45, 130, 0.15);
      transform: scale(1.01);
    }
    .card-lobby {
      background: #ffffff;
      border: 1.5px solid #e2e8f0;
      box-shadow: 0 20px 45px -12px rgba(30, 27, 75, 0.07), 0 0 0 1px rgba(226, 232, 240, 0.6);
    }
    .live-pulse {
      display: inline-block;
      width: 10px;
      height: 10px;
      border-radius: 9999px;
      background-color: #ef4444;
      box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
      animation: livePulseAnim 1.8s infinite;
    }
    @keyframes livePulseAnim {
      0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.8); }
      70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
      100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }
  </style>
</head>
<body class="student-portal bg-[#f4f7fb] text-navy-950 font-sans antialiased">

<!-- Eyes On Me Fullscreen Lock Overlay -->
<div id="eyes-on-me-overlay" class="<?= (!empty($currentActiveSession['eyes_on_me_enabled'])) ? '' : 'hidden' ?> fixed inset-0 z-50 bg-slate-950/90 backdrop-blur-md flex flex-col items-center justify-center p-6 text-center text-white select-none">
  <div class="text-7xl mb-4 animate-bounce">👀</div>
  <h2 class="text-2xl sm:text-3xl font-black text-pink-400 mb-2">ครูกำลังอธิบาย โปรดดูกระดาน</h2>
  <p class="text-slate-300 text-xs sm:text-sm max-w-md">หน้าจอของคุณถูกหยุดไว้ชั่วคราว เพื่อร่วมรับฟังคำอธิบายจากคุณครู จะเปิดให้ใช้งานต่อทันทีที่คุณครูปลดล็อก</p>
  <div class="mt-6 px-4 py-2 rounded-full bg-white/10 text-xs font-bold border border-white/20">
    🔒 ระบบ Eyes On Me กำลังล็อกหน้าจอ
  </div>
</div>

<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col min-w-0 ml-[240px] max-[1024px]:ml-0">
    <?php include 'includes/topbar.php'; ?>
    <main class="flex-1 p-4 sm:p-8 flex items-center justify-center student-live-bg">
      <div class="max-w-2xl w-full space-y-6">

        <?php if ($currentActiveSession): ?>
          <!-- ============================================================= -->
          <!-- MODE 1: LIVE CLASSROOM LOBBY (ห้องเรียนสด)                   -->
          <!-- ============================================================= -->
          <div class="card-lobby rounded-3xl p-6 sm:p-8 space-y-6">

            <!-- Hero Header Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100">
              <div>
                <div class="flex items-center gap-2 mb-1.5">
                  <span class="live-pulse"></span>
                  <span class="px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-800 text-[11px] font-black uppercase tracking-wider">
                    🔴 LIVE CLASSROOM
                  </span>
                  <span class="text-xs font-mono font-black px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">
                    PIN: #<?= htmlspecialchars($currentActiveSession['session_pin']) ?>
                  </span>
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                  <?= htmlspecialchars($currentActiveSession['title']) ?>
                </h1>
                <p class="text-xs text-slate-500 font-medium mt-0.5 flex items-center gap-2">
                  <span>👩‍🏫 ผู้สอน: <strong><?= htmlspecialchars($currentActiveSession['teacher_name']) ?></strong></span>
                  <?php if ($currentActiveSession['start_time']): ?>
                    <span>•</span>
                    <span>🕐 <?= substr((string)$currentActiveSession['start_time'], 0, 5) ?> - <?= substr((string)$currentActiveSession['end_time'], 0, 5) ?> น.</span>
                  <?php endif; ?>
                </p>
              </div>

              <!-- Leave Room Button -->
              <div class="shrink-0">
                <button type="button" id="btn-leave-room" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                  <span>🚪 ออกจากห้องเรียน</span>
                </button>
              </div>
            </div>

            <!-- Live Announcement Box -->
            <div id="lobby-announcement-card" class="<?= !empty($currentActiveSession['announcement_message']) ? '' : 'hidden' ?> p-4 rounded-2xl bg-gradient-to-r from-purple-50 to-pink-50 border border-purple-200/80 text-purple-900 space-y-1">
              <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-purple-700">
                <span>📢 ประกาศสดจากคุณครู</span>
              </div>
              <p id="lobby-announcement-text" class="text-sm font-semibold text-purple-950">
                <?= htmlspecialchars((string)($currentActiveSession['announcement_message'] ?? '')) ?>
              </p>
            </div>

            <!-- Online Meeting Link (if any) -->
            <?php if ($meetingUrl): ?>
              <div class="p-5 rounded-3xl bg-gradient-to-r from-sky-600 to-indigo-600 text-white shadow-lg shadow-sky-600/20 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                  <div class="w-12 h-12 rounded-2xl bg-white/15 backdrop-blur-xs flex items-center justify-center text-2xl shrink-0">
                    🎥
                  </div>
                  <div>
                    <span class="text-[11px] uppercase tracking-wider text-sky-200 font-black">ห้องเรียนสดออนไลน์</span>
                    <h3 class="text-base font-black">เข้าห้องเรียนสดผ่าน Google Meet / Zoom</h3>
                    <p class="text-xs text-sky-100/80">คลิกเพื่อเปิดหน้าต่างวิดีโอคอลร่วมเรียนสดกับคุณครู</p>
                  </div>
                </div>
                <a href="<?= htmlspecialchars($meetingUrl) ?>" target="_blank" rel="noopener noreferrer" class="px-5 py-3 rounded-2xl bg-white hover:bg-sky-50 text-sky-900 text-xs font-black shrink-0 inline-flex items-center justify-center gap-2 transition-transform hover:scale-102 shadow-md cursor-pointer">
                  <span>เปิดห้องเรียน ↗</span>
                </a>
              </div>
            <?php elseif ($locationText): ?>
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex items-center gap-3 text-xs text-slate-700">
                <span class="text-base">📍</span>
                <span>สถานที่เรียน: <strong><?= htmlspecialchars($locationText) ?></strong></span>
              </div>
            <?php endif; ?>

            <!-- Session Topics (เนื้อหาประจำคาบ) -->
            <?php if (!empty($sessionTopics)): ?>
              <div class="space-y-2.5">
                <div class="flex items-center gap-2 text-xs font-extrabold text-slate-700 uppercase tracking-wider">
                  <span>📚 หัวข้อประจำคาบเรียนนี้</span>
                  <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 text-[10px] font-bold"><?= count($sessionTopics) ?> หัวข้อ</span>
                </div>
                <div class="flex flex-wrap gap-2">
                  <?php foreach ($sessionTopics as $topic): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 text-xs font-bold">
                      <span class="w-1.5 h-1.5 rounded-full bg-pink-500"></span>
                      <?= htmlspecialchars($topic) ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <!-- EXAM / QUIZ CARD (แบบทดสอบประจำคาบ) -->
            <?php if (!empty($currentActiveSession['exam_id'])): ?>
              <div class="bg-gradient-to-br from-slate-50 to-pink-50/30 border-2 border-pink-200/80 rounded-3xl p-6 shadow-xs space-y-4">
                <div class="flex items-start justify-between gap-3">
                  <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-pink-500 text-white flex items-center justify-center text-2xl font-bold shadow-md shadow-pink-500/25 shrink-0">
                      📝
                    </div>
                    <div>
                      <span class="px-2.5 py-0.5 rounded-full bg-pink-100 text-pink-700 text-[11px] font-extrabold uppercase">
                        แบบทดสอบประจำคาบ
                      </span>
                      <h3 class="text-base sm:text-lg font-black text-slate-900 mt-1">
                        <?= htmlspecialchars($currentActiveSession['exam_title'] ?: 'แบบทดสอบประจำคาบ') ?>
                      </h3>
                      <p class="text-xs text-slate-500 font-medium mt-0.5">
                        <?= $examQuestionsCount ?> ข้อ • <?= $currentActiveSession['has_time_limit'] ? $currentActiveSession['time_limit_minutes'] . ' นาที' : 'ไม่จำกัดเวลา' ?>
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Exam Status & Action Buttons -->
                <?php if ($currentActiveSession['participant_status'] === 'submitted' || !empty($currentActiveSession['completed_at'])): ?>
                  <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                      <span class="text-xs font-bold text-emerald-800 flex items-center gap-1.5">
                        <span class="text-emerald-600 font-black">✓</span> ส่งคำตอบแบบทดสอบเรียบร้อยแล้ว
                      </span>
                      <div class="text-xl font-black text-emerald-950 mt-0.5">
                        คะแนน: <?= round((float)($currentActiveSession['score'] ?? 0)) ?>% 
                        <span class="text-xs font-semibold text-emerald-700">(<?= (int)($currentActiveSession['correct_count'] ?? 0) ?>/<?= (int)($currentActiveSession['total_questions'] ?? $examQuestionsCount) ?> ข้อ)</span>
                      </div>
                    </div>
                    <a href="test-result.php?attempt_id=<?= (int)$currentActiveSession['attempt_id'] ?>&sessionId=<?= urlencode($currentActiveSession['id']) ?>" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs inline-flex items-center justify-center gap-1.5 transition cursor-pointer shrink-0">
                      <span>📊 ดูผลสอบและเฉลย</span>
                    </a>
                  </div>
                <?php elseif ($currentActiveSession['participant_status'] === 'in_progress'): ?>
                  <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                      <span class="text-xs font-bold text-amber-800 flex items-center gap-1.5">
                        <span>⏳</span> กำลังทำข้อสอบอยู่
                      </span>
                      <p class="text-xs text-amber-700 mt-0.5">คุณสามารถกดกลับเข้าไปทำข้อสอบต่อได้ทันที</p>
                    </div>
                    <a href="take-test.php?id=<?= (int)$currentActiveSession['exam_id'] ?>&sessionId=<?= urlencode($currentActiveSession['id']) ?>" class="btn-join-gamified px-5 py-3 rounded-2xl font-black text-xs inline-flex items-center justify-center gap-1.5 cursor-pointer shrink-0">
                      <span>✏️ ทำข้อสอบต่อทันที</span>
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                  </div>
                <?php else: ?>
                  <div class="p-4 rounded-2xl bg-white border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                      <span class="text-xs font-bold text-slate-800">พร้อมสำหรับแบบทดสอบแล้ว</span>
                      <p class="text-xs text-slate-500 mt-0.5">กดเริ่มทำเมื่อคุณครูแจ้ง หรือเมื่อนักเรียนพร้อม</p>
                    </div>
                    <a href="take-test.php?id=<?= (int)$currentActiveSession['exam_id'] ?>&sessionId=<?= urlencode($currentActiveSession['id']) ?>" class="btn-join-gamified px-6 py-3 rounded-2xl font-black text-sm inline-flex items-center justify-center gap-2 cursor-pointer shrink-0">
                      <span>🚀 เริ่มทำแบบทดสอบ</span>
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                  </div>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="p-5 rounded-3xl bg-indigo-50/60 border border-indigo-100 flex items-center gap-3.5 text-indigo-950">
                <div class="w-10 h-10 rounded-2xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-xl shrink-0">📖</div>
                <div>
                  <h4 class="text-sm font-bold">คาบเรียนบรรยายสดและทบทวนเนื้อหา</h4>
                  <p class="text-xs text-indigo-800/80 mt-0.5">คาบนี้ไม่มีแบบทดสอบประจำคาบ ร่วมฟังคุณครูสอนและถามตอบในห้องเรียนได้เลยครับ</p>
                </div>
              </div>
            <?php endif; ?>

            <!-- Classmates Connected Section -->
            <div class="space-y-2.5 pt-2 border-t border-slate-100">
              <div class="flex items-center justify-between text-xs font-extrabold text-slate-600">
                <span class="flex items-center gap-1.5">
                  <span>👥 เพื่อนร่วมห้องเรียน</span>
                  <span id="lobby-participants-count" class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[10px]"><?= count($activeParticipants) ?> คน</span>
                </span>
                <span class="text-[11px] text-emerald-600 font-bold flex items-center gap-1">
                  <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> ออนไลน์อยู่
                </span>
              </div>
              <div id="lobby-participants-list" class="flex flex-wrap gap-2 max-h-32 overflow-y-auto">
                <?php foreach ($activeParticipants as $p): ?>
                  <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-slate-50 border border-slate-200 text-xs font-semibold text-slate-800">
                    <span class="w-2 h-2 rounded-full <?= $p['status'] === 'submitted' ? 'bg-emerald-500' : ($p['status'] === 'in_progress' ? 'bg-amber-500' : 'bg-slate-400') ?>"></span>
                    <span><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

          </div>

        <?php else: ?>
          <!-- ============================================================= -->
          <!-- MODE 2: PIN ENTRY & DISCOVERY (กรอกรหัสเข้าร่วมห้องเรียนสด)      -->
          <!-- ============================================================= -->
          <div class="card-lobby p-6 sm:p-8 rounded-3xl text-center space-y-6">
            
            <div class="space-y-2">
              <div class="w-20 h-20 rounded-3xl bg-gradient-to-tr from-pink-500 via-rose-500 to-indigo-500 text-white flex items-center justify-center text-4xl mx-auto shadow-xl shadow-pink-500/25">
                🎮
              </div>

              <div class="pt-1">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-pink-50 text-pink-600 font-extrabold text-[11px] mb-1.5 border border-pink-100">
                  <span>✨</span> <span>LIVE CLASSROOM SESSION</span> <span>✨</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-navy-950 tracking-tight">
                  เข้าร่วมห้องเรียนสด
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 font-medium max-w-xs mx-auto">
                  สวัสดี <strong><?= htmlspecialchars($studentFirstName) ?></strong>! 👋<br>
                  กรอกรหัส PIN 6 หลักที่คุณครูฉายหน้าห้องเพื่อเข้าห้องเรียน
                </p>
              </div>
            </div>

            <!-- PIN Form Area -->
            <form id="join-pin-form" class="space-y-4">
              <div id="join-error" class="hidden p-3.5 rounded-2xl bg-rose-50 text-rose-600 text-xs font-bold border border-rose-200"></div>

              <div class="space-y-2">
                <label for="session-pin" class="block text-xs font-black uppercase text-slate-600 tracking-wider">
                  🔑 รหัส PIN 6 หลัก (Room PIN)
                </label>
                
                <div class="relative">
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
                    value="<?= htmlspecialchars($reqPin) ?>"
                    class="pin-input-field w-full text-center text-3xl sm:text-4xl font-mono font-black h-18 sm:h-20 rounded-2xl border-2 border-slate-300 outline-none text-navy-950"
                    autofocus
                  >
                </div>
              </div>

              <button
                type="submit"
                id="btn-join-session"
                class="btn-join-gamified w-full h-14 rounded-2xl font-black text-base flex items-center justify-center gap-2.5 cursor-pointer shadow-lg"
              >
                <span>🚀 เข้าสู่ห้องเรียนสด</span>
              </button>
            </form>

            <!-- Active Sessions of Today (if any) -->
            <?php if (!empty($todaySessions)): ?>
              <div class="pt-4 border-t border-slate-100 text-left space-y-2.5">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-black text-slate-700 uppercase tracking-wider">🔴 ห้องเรียนสดที่กำลังเปิดสอนอยู่</span>
                </div>
                <div class="space-y-2">
                  <?php foreach ($todaySessions as $ts): ?>
                    <div class="p-3 rounded-2xl bg-slate-50 hover:bg-slate-100/80 border border-slate-200/80 flex items-center justify-between gap-3 transition">
                      <div>
                        <h4 class="text-xs font-bold text-slate-900"><?= htmlspecialchars($ts['title']) ?></h4>
                        <p class="text-[11px] text-slate-500">ครูผู้สอน: <?= htmlspecialchars($ts['teacher_name']) ?> • PIN: <strong class="font-mono font-bold text-slate-800">#<?= htmlspecialchars($ts['session_pin']) ?></strong></p>
                      </div>
                      <a href="live-session.php?sessionId=<?= urlencode($ts['id']) ?>" class="px-3.5 py-1.5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-bold text-xs transition shrink-0 cursor-pointer">
                        เข้าร่วม
                      </a>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <!-- Kid-Friendly Guidance Badges -->
            <div class="grid grid-cols-2 gap-2.5 pt-4 border-t border-slate-100 text-[11px] text-slate-600">
              <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100 flex items-center gap-2.5 text-left">
                <span class="text-xl shrink-0">👀</span>
                <div>
                  <strong class="block text-slate-800 font-bold">Eyes On Me</strong>
                  <span class="text-[10px] text-slate-500">รอฟังคุณครูอธิบายเมื่อจอล็อก</span>
                </div>
              </div>

              <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100 flex items-center gap-2.5 text-left">
                <span class="text-xl shrink-0">⚡</span>
                <div>
                  <strong class="block text-slate-800 font-bold">รู้ผลสอบสด</strong>
                  <span class="text-[10px] text-slate-500">ส่งแล้วตรวจผลคะแนนทันที</span>
                </div>
              </div>
            </div>

          </div>
        <?php endif; ?>

      </div>
    </main>
  </div>
</div>

<!-- Phase 2: Understanding Check Floating Panel -->
<div id="understanding-panel" style="display:none; position:fixed; bottom:24px; left:50%; transform:translateX(-50%); z-index:200; width:min(480px, calc(100vw - 32px));">
  <div style="background:linear-gradient(135deg,#1e1b4b,#312e81); border:1px solid rgba(255,255,255,.18); border-radius:20px; padding:20px 22px; box-shadow:0 16px 48px rgba(0,0,0,.4); color:#fff; backdrop-filter:blur(12px); position:relative;">
    <p style="font-size:11px; font-weight:700; opacity:.65; letter-spacing:.06em; margin-bottom:6px;" id="uc-topic-label">เช็กความเข้าใจ</p>
    <p style="font-size:16px; font-weight:800; margin-bottom:14px;">เข้าใจบทเรียนนี้ดีแค่ไหน? 🤔</p>
    <div style="display:flex; gap:10px;">
      <button type="button" onclick="submitUnderstanding('got_it')" id="uc-got-it"
        style="flex:1; padding:11px 0; border-radius:12px; border:none; background:#22c55e; color:#fff; font-size:13px; font-weight:800; cursor:pointer; transition:.15s;">
        👍 เข้าใจแล้ว
      </button>
      <button type="button" onclick="submitUnderstanding('somewhat')" id="uc-somewhat"
        style="flex:1; padding:11px 0; border-radius:12px; border:none; background:#f59e0b; color:#fff; font-size:13px; font-weight:800; cursor:pointer; transition:.15s;">
        😐 บางส่วน
      </button>
      <button type="button" onclick="submitUnderstanding('confused')" id="uc-confused"
        style="flex:1; padding:11px 0; border-radius:12px; border:none; background:#ef4444; color:#fff; font-size:13px; font-weight:800; cursor:pointer; transition:.15s;">
        🤷 ยังไม่เข้าใจ
      </button>
    </div>
    <button type="button" onclick="dismissUnderstanding()" style="position:absolute; top:12px; right:14px; background:none; border:none; color:rgba(255,255,255,.5); font-size:20px; cursor:pointer; line-height:1;">✕</button>
  </div>
</div>

<script>
(() => {
  const currentSessionId = <?= json_encode($currentActiveSession['id'] ?? null) ?>;
  const eyesOverlay = document.getElementById("eyes-on-me-overlay");
  const announceCard = document.getElementById("lobby-announcement-card");
  const announceText = document.getElementById("lobby-announcement-text");
  const leaveBtn = document.getElementById("btn-leave-room");

  // Leave room action
  if (leaveBtn && currentSessionId) {
    leaveBtn.onclick = async () => {
      if (!confirm("ต้องการออกจากห้องเรียนสดนี้ใช่หรือไม่?")) return;
      try {
        await fetch("live-session-api.php?action=leave", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ sessionId: currentSessionId }),
        });
      } catch (_) {}
      window.location.href = "live-session.php";
    };
  }

  // PIN Form submission
  const form = document.getElementById("join-pin-form");
  if (form) {
    const input = document.getElementById("session-pin");
    const btn = document.getElementById("btn-join-session");
    const errBox = document.getElementById("join-error");

    input.addEventListener("input", (e) => {
      e.target.value = e.target.value.replace(/[^0-9]/g, "");
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
      btn.innerHTML = `<span class="animate-pulse">⏳ กำลังเชื่อมต่อห้องเรียนสด...</span>`;

      try {
        const res = await fetch("live-session-api.php?action=join", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ sessionPin: pin }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || "ไม่สามารถเข้าร่วมห้องเรียนได้");
        
        btn.innerHTML = `<span>🎉 เชื่อมต่อสำเร็จ! เข้าสู่ห้องเรียน...</span>`;
        // Navigate to the Live Classroom Lobby (NOT take-test.php!)
        window.location.href = data.lobbyUrl || ("live-session.php?sessionId=" + encodeURIComponent(data.session.id));
      } catch (err) {
        errBox.textContent = err.message;
        errBox.classList.remove("hidden");
        btn.disabled = false;
        btn.innerHTML = `<span>🚀 เข้าสู่ห้องเรียนสด</span>`;
        input.select();
      }
    };
  }

  // Real-time polling for active lobby
  if (currentSessionId) {
    let lastAnnouncement = <?= json_encode($currentActiveSession['announcement_message'] ?? '') ?>;

    async function pollSessionStatus() {
      try {
        const res = await fetch(`live-session-api.php?action=status&sessionId=${encodeURIComponent(currentSessionId)}`);
        if (!res.ok) return;
        const data = await res.json();

        // 1. Session closed
        if (data.sessionClosed) {
          alert("คุณครูได้สิ้นสุดห้องเรียนสดนี้แล้ว ระบบจะนำคุณกลับสู่หน้าหลัก");
          window.location.href = "index.php";
          return;
        }

        // 2. Eyes On Me Screen Lock
        if (eyesOverlay) {
          eyesOverlay.classList.toggle("hidden", !data.isEyesOnMeLocked);
        }

        // 3. Live Announcement & Understanding Check
        if (data.announcementMessage !== lastAnnouncement) {
          lastAnnouncement = data.announcementMessage;
          if (lastAnnouncement && lastAnnouncement.startsWith("_check_")) {
            const topic = lastAnnouncement.replace("_check_", "").trim();
            window.__p2_triggerCheck && window.__p2_triggerCheck(currentSessionId, topic);
          } else if (announceCard && announceText) {
            if (lastAnnouncement) {
              announceText.textContent = lastAnnouncement;
              announceCard.classList.remove("hidden");
            } else {
              announceCard.classList.add("hidden");
            }
          }
        }

        // 4. Update participants count
        const countEl = document.getElementById("lobby-participants-count");
        if (countEl && data.participantsCount !== undefined) {
          countEl.textContent = `${data.participantsCount} คน`;
        }
      } catch (_) {}
    }

    setInterval(pollSessionStatus, 2500);
  }
})();

// Phase 2 Understanding Check Controller
(function () {
  'use strict';
  let _ucSessionId = null;
  let _ucCurrentTopic = '';
  let _ucSubmitted = false;

  window.__p2_triggerCheck = function (sessionId, topicName) {
    _ucSessionId = sessionId;
    _ucCurrentTopic = topicName || '';
    _ucSubmitted = false;
    const panel = document.getElementById('understanding-panel');
    const label = document.getElementById('uc-topic-label');
    if (panel) {
      if (label) label.textContent = topicName ? '📌 หัวข้อ: ' + topicName : 'เช็กความเข้าใจจากคุณครู';
      panel.style.display = 'block';
      ['uc-got-it','uc-somewhat','uc-confused'].forEach(id => {
        const b = document.getElementById(id);
        if (b) { b.disabled = false; b.style.opacity = '1'; }
      });
    }
  };

  window.submitUnderstanding = async function (val) {
    if (!_ucSessionId || _ucSubmitted) return;
    _ucSubmitted = true;
    ['uc-got-it','uc-somewhat','uc-confused'].forEach(id => {
      const b = document.getElementById(id);
      if (b) { b.disabled = true; b.style.opacity = '0.5'; }
    });
    try {
      await fetch('live-session-api.php?action=understanding_check', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sessionId: _ucSessionId, understanding: val, topicName: _ucCurrentTopic }),
      });
    } catch (_) {}
    setTimeout(dismissUnderstanding, 1200);
  };

  window.dismissUnderstanding = function () {
    const panel = document.getElementById('understanding-panel');
    if (panel) panel.style.display = 'none';
  };
})();
</script>
</body>
</html>
