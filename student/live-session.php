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

    /* PIN entry — friendly, branded and intentionally simple for young learners. */
    .live-entry-stage {
      position: relative;
      isolation: isolate;
      width: 100%;
      max-width: 1040px;
      margin-inline: auto;
      font-family: "IBM Plex Sans Thai", Inter, sans-serif;
    }
    .live-entry-stage::before,
    .live-entry-stage::after {
      content: "";
      position: absolute;
      z-index: -1;
      border-radius: 999px;
      pointer-events: none;
    }
    .live-entry-stage::before {
      width: 180px;
      height: 180px;
      top: -56px;
      right: -72px;
      background: #ffe3ef;
    }
    .live-entry-stage::after {
      width: 110px;
      height: 110px;
      bottom: -36px;
      left: -42px;
      background: #dce9ff;
    }
    .live-entry-card {
      display: grid;
      grid-template-columns: minmax(280px, 0.78fr) minmax(420px, 1.22fr);
      min-height: 590px;
      overflow: hidden;
      border: 1px solid #dbe5f3;
      border-radius: 32px;
      background: #ffffff;
      box-shadow: 0 24px 70px rgba(19, 48, 99, 0.12);
    }
    .live-entry-hero {
      position: relative;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      padding: 46px 42px 32px;
      color: #ffffff;
      background: #123f9c;
    }
    .live-entry-hero::before {
      content: "";
      position: absolute;
      width: 260px;
      height: 260px;
      top: -124px;
      right: -116px;
      border: 42px solid rgba(255, 255, 255, 0.08);
      border-radius: 50%;
    }
    .live-entry-hero::after {
      content: "";
      position: absolute;
      width: 340px;
      height: 170px;
      left: -55px;
      bottom: -108px;
      border-radius: 50% 50% 0 0;
      background: #ffcc48;
      transform: rotate(-6deg);
    }
    .live-hero-label {
      position: relative;
      z-index: 2;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      width: max-content;
      min-height: 32px;
      padding: 0 13px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.12);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.08em;
    }
    .live-hero-label-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #ffcf4a;
      box-shadow: 0 0 0 4px rgba(255, 207, 74, 0.15);
    }
    .live-entry-hero h2 {
      position: relative;
      z-index: 2;
      max-width: 320px;
      margin-top: 28px;
      color: #ffffff;
      font-size: clamp(28px, 3vw, 40px);
      font-weight: 800;
      line-height: 1.2;
      letter-spacing: -0.035em;
    }
    .live-entry-hero p {
      position: relative;
      z-index: 2;
      max-width: 300px;
      margin-top: 12px;
      color: #dce8ff;
      font-size: 14px;
      font-weight: 500;
      line-height: 1.65;
    }
    .live-owl-wrap {
      position: relative;
      z-index: 2;
      display: flex;
      flex: 1;
      align-items: flex-end;
      justify-content: center;
      min-height: 260px;
    }
    .live-owl-wrap img {
      width: min(270px, 90%);
      max-height: 300px;
      object-fit: contain;
      object-position: bottom center;
      filter: drop-shadow(0 18px 18px rgba(4, 22, 65, 0.22));
    }
    .live-entry-form-panel {
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 48px clamp(36px, 5vw, 70px);
    }
    .live-entry-kicker {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: #e72d82;
      font-size: 12px;
      font-weight: 800;
    }
    .live-entry-kicker svg {
      width: 20px;
      height: 20px;
    }
    .live-entry-form-panel h1 {
      margin-top: 10px;
      color: #0b1f43;
      font-size: clamp(27px, 3vw, 38px);
      font-weight: 800;
      line-height: 1.2;
      letter-spacing: -0.035em;
    }
    .live-entry-welcome {
      margin-top: 9px;
      color: #60708a;
      font-size: 14px;
      font-weight: 500;
      line-height: 1.6;
    }
    .live-entry-form {
      margin-top: 31px;
    }
    .live-pin-label {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 11px;
      color: #243758;
      font-size: 13px;
      font-weight: 800;
    }
    .live-pin-label span:last-child {
      color: #8b98ac;
      font-size: 11px;
      font-weight: 600;
    }
    .pin-code-field {
      position: relative;
      display: grid;
      grid-template-columns: repeat(6, minmax(0, 1fr));
      gap: 10px;
      cursor: text;
    }
    .pin-code-field input {
      position: absolute;
      inset: 0;
      z-index: 2;
      width: 100%;
      height: 100%;
      border: 0;
      outline: 0;
      opacity: 0.01;
      cursor: text;
    }
    .pin-slot {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 72px;
      border: 2px solid #d9e2ef;
      border-radius: 15px;
      background: #f9fbfe;
      color: #0b1f43;
      font-family: Inter, sans-serif;
      font-size: 28px;
      font-weight: 800;
      transition: border-color .18s ease, background-color .18s ease, transform .18s ease, box-shadow .18s ease;
    }
    .pin-slot::after {
      content: "";
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #c9d4e4;
    }
    .pin-slot.is-filled {
      border-color: #aebfda;
      background: #ffffff;
    }
    .pin-slot.is-filled::after { display: none; }
    .pin-code-field.is-focused .pin-slot.is-next {
      border-color: #e72d82;
      background: #fff8fb;
      box-shadow: 0 0 0 4px rgba(231, 45, 130, 0.1);
      transform: translateY(-2px);
    }
    .live-join-button {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      min-height: 56px;
      margin-top: 18px;
      border: 0;
      border-radius: 16px;
      background: #e72d82;
      color: #ffffff;
      box-shadow: 0 10px 22px rgba(231, 45, 130, 0.22);
      font-size: 15px;
      font-weight: 800;
      cursor: pointer;
      transition: transform .18s ease, background-color .18s ease, box-shadow .18s ease;
    }
    .live-join-button:hover:not(:disabled) {
      background: #d72174;
      box-shadow: 0 13px 26px rgba(231, 45, 130, 0.28);
      transform: translateY(-2px);
    }
    .live-join-button:disabled {
      cursor: wait;
      opacity: .72;
    }
    .live-join-button svg {
      width: 19px;
      height: 19px;
    }
    .live-join-error {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      margin-bottom: 14px;
      padding: 11px 13px;
      border: 1px solid #fecdd3;
      border-radius: 12px;
      background: #fff1f2;
      color: #be123c;
      font-size: 12px;
      font-weight: 700;
      text-align: left;
    }
    .live-join-error.hidden { display: none; }
    .live-entry-help {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      margin-top: 14px;
      color: #7b889d;
      font-size: 11px;
      font-weight: 600;
    }
    .live-entry-help svg {
      width: 15px;
      height: 15px;
      color: #93a2b8;
    }
    .live-entry-steps {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 8px;
      margin-top: 30px;
      padding-top: 22px;
      border-top: 1px solid #edf1f7;
    }
    .live-entry-step {
      display: flex;
      align-items: center;
      gap: 8px;
      min-width: 0;
      color: #5f6f87;
      font-size: 10px;
      font-weight: 700;
      white-space: nowrap;
    }
    .live-entry-step-number {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
      flex: 0 0 auto;
      border-radius: 8px;
      background: #edf3ff;
      color: #1949aa;
      font-size: 10px;
      font-weight: 900;
    }
    .live-sessions-quicklist {
      margin-top: 24px;
      padding-top: 20px;
      border-top: 1px solid #edf1f7;
    }
    .live-sessions-quicklist > p {
      margin-bottom: 10px;
      color: #243758;
      font-size: 12px;
      font-weight: 800;
    }
    .live-session-quickitem {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 10px 12px;
      border: 1px solid #e2e9f3;
      border-radius: 13px;
      background: #f9fbfe;
      text-align: left;
    }
    .live-session-quickitem + .live-session-quickitem { margin-top: 7px; }
    .live-session-quickitem a {
      flex: 0 0 auto;
      padding: 7px 12px;
      border-radius: 10px;
      background: #123f9c;
      color: #ffffff;
      font-size: 11px;
      font-weight: 800;
    }
    @media (max-width: 900px) {
      .live-entry-card { grid-template-columns: minmax(220px, .68fr) minmax(390px, 1.32fr); }
      .live-entry-hero { padding-inline: 28px; }
      .live-entry-form-panel { padding-inline: 34px; }
    }
    @media (max-width: 720px) {
      .student-live-bg { align-items: flex-start !important; }
      .live-entry-stage::before,
      .live-entry-stage::after { display: none; }
      .live-entry-card {
        display: flex;
        min-height: 0;
        flex-direction: column;
        border-radius: 24px;
      }
      .live-entry-hero {
        min-height: 178px;
        padding: 25px 24px 20px;
      }
      .live-entry-hero h2 {
        max-width: 220px;
        margin-top: 15px;
        font-size: 24px;
      }
      .live-entry-hero p { display: none; }
      .live-owl-wrap {
        position: absolute;
        right: 9px;
        bottom: -6px;
        min-height: 0;
        width: 145px;
      }
      .live-owl-wrap img { width: 140px; max-height: 150px; }
      .live-entry-hero::after {
        width: 220px;
        height: 100px;
        right: -90px;
        left: auto;
        bottom: -75px;
      }
      .live-entry-form-panel { padding: 28px 21px 24px; }
      .live-entry-form-panel h1 { font-size: 25px; }
      .live-entry-form { margin-top: 24px; }
      .pin-code-field { gap: 6px; }
      .pin-slot { height: 52px; border-radius: 12px; font-size: 24px; }
      .live-entry-steps { margin-top: 24px; }
      .live-entry-step { flex-direction: column; gap: 5px; text-align: center; white-space: normal; }
    }
    @media (prefers-reduced-motion: reduce) {
      .pin-slot,
      .live-join-button { transition: none; }
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
      <div class="<?= $currentActiveSession ? 'max-w-2xl' : 'max-w-5xl' ?> w-full space-y-6">

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
          <div class="live-entry-stage">
            <section class="live-entry-card" aria-labelledby="live-entry-title">
              <div class="live-entry-hero">
                <div class="live-hero-label">
                  <span class="live-hero-label-dot" aria-hidden="true"></span>
                  ห้องเรียนสด
                </div>
                <h2>วันนี้เราจะเรียนรู้อะไรกันนะ?</h2>
                <p>เตรียมตัวให้พร้อม แล้วใช้รหัสจากคุณครูเพื่อเข้าห้องเรียนได้เลย</p>
                <div class="live-owl-wrap" aria-hidden="true">
                  <img src="../assets/images/next-owl.png" alt="" width="457" height="500">
                </div>
              </div>

              <div class="live-entry-form-panel">
                <div class="live-entry-kicker">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                  </svg>
                  พร้อมเข้าเรียน
                </div>
                <h1 id="live-entry-title">ใส่รหัสจากคุณครู</h1>
                <p class="live-entry-welcome">
                  สวัสดี <strong><?= htmlspecialchars($studentFirstName) ?></strong> กรอกรหัส 6 หลักที่คุณครูให้มาได้เลย
                </p>

                <form id="join-pin-form" class="live-entry-form">
                  <div id="join-error" class="live-join-error hidden" role="alert"></div>

                  <label for="session-pin" class="live-pin-label">
                    <span>รหัสเข้าห้องเรียน</span>
                    <span>ตัวเลข 6 หลัก</span>
                  </label>

                  <div id="pin-code-field" class="pin-code-field">
                    <input
                      id="session-pin"
                      name="sessionPin"
                      type="text"
                      inputmode="numeric"
                      pattern="[0-9]*"
                      maxlength="6"
                      required
                      autocomplete="one-time-code"
                      aria-describedby="pin-help"
                      value="<?= htmlspecialchars($reqPin) ?>"
                      autofocus
                    >
                    <?php for ($pinIndex = 0; $pinIndex < 6; $pinIndex++): ?>
                      <span class="pin-slot" aria-hidden="true"></span>
                    <?php endfor; ?>
                  </div>

                  <button type="submit" id="btn-join-session" class="live-join-button">
                    <span>เข้าห้องเรียน</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-5-5 5 5-5 5"/>
                    </svg>
                  </button>

                  <p id="pin-help" class="live-entry-help">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                      <circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 10v6m0-9h.01"/>
                    </svg>
                    ยังไม่มีรหัส? ยกมือถามคุณครูได้เลย
                  </p>
                </form>

                <?php if (!empty($todaySessions)): ?>
                  <div class="live-sessions-quicklist">
                    <p>ห้องที่กำลังเปิดอยู่</p>
                    <?php foreach ($todaySessions as $ts): ?>
                      <div class="live-session-quickitem">
                        <div class="min-w-0">
                          <h3 class="truncate text-xs font-bold text-slate-900"><?= htmlspecialchars($ts['title']) ?></h3>
                          <span class="text-[10px] text-slate-500">คุณครู <?= htmlspecialchars($ts['teacher_name']) ?></span>
                        </div>
                        <a href="live-session.php?sessionId=<?= urlencode($ts['id']) ?>">เข้าร่วม</a>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="live-entry-steps" aria-label="ขั้นตอนเข้าห้องเรียน">
                    <div class="live-entry-step"><span class="live-entry-step-number">1</span><span>รับรหัสจากครู</span></div>
                    <div class="live-entry-step"><span class="live-entry-step-number">2</span><span>กรอกให้ครบ 6 หลัก</span></div>
                    <div class="live-entry-step"><span class="live-entry-step-number">3</span><span>กดเข้าห้องเรียน</span></div>
                  </div>
                <?php endif; ?>
              </div>
            </section>
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
    const pinField = document.getElementById("pin-code-field");
    const pinSlots = Array.from(pinField?.querySelectorAll(".pin-slot") || []);

    const renderPin = () => {
      const normalizedPin = input.value.replace(/[^0-9]/g, "").slice(0, 6);
      if (input.value !== normalizedPin) input.value = normalizedPin;
      const digits = normalizedPin.split("");
      pinSlots.forEach((slot, index) => {
        slot.textContent = digits[index] || "";
        slot.classList.toggle("is-filled", Boolean(digits[index]));
        slot.classList.toggle("is-next", index === Math.min(digits.length, 5) && digits.length < 6);
      });
    };

    input.addEventListener("input", (e) => {
      e.target.value = e.target.value.replace(/[^0-9]/g, "");
      errBox.classList.add("hidden");
      renderPin();
    });
    input.addEventListener("focus", () => pinField?.classList.add("is-focused"));
    input.addEventListener("blur", () => pinField?.classList.remove("is-focused"));
    pinField?.addEventListener("click", () => input.focus());
    renderPin();
    if (document.activeElement === input) pinField?.classList.add("is-focused");

    form.onsubmit = async (e) => {
      e.preventDefault();
      errBox.classList.add("hidden");
      const pin = input.value.trim();
      if (pin.length !== 6) {
        errBox.textContent = "กรอกรหัสให้ครบ 6 หลักก่อนนะ";
        errBox.classList.remove("hidden");
        input.focus();
        return;
      }

      btn.disabled = true;
      btn.innerHTML = `<span class="animate-pulse">กำลังพาเข้าห้องเรียน...</span>`;

      try {
        const res = await fetch("live-session-api.php?action=join", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ sessionPin: pin }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || "ไม่สามารถเข้าร่วมห้องเรียนได้");
        
        btn.innerHTML = `<span>เชื่อมต่อแล้ว กำลังเข้าห้องเรียน...</span>`;
        // Navigate to the Live Classroom Lobby (NOT take-test.php!)
        window.location.href = data.lobbyUrl || ("live-session.php?sessionId=" + encodeURIComponent(data.session.id));
      } catch (err) {
        errBox.textContent = err.message;
        errBox.classList.remove("hidden");
        btn.disabled = false;
        btn.innerHTML = `<span>ลองเข้าห้องเรียนอีกครั้ง</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-5-5 5 5-5 5"/></svg>`;
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
