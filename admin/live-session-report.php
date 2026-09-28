<?php
declare(strict_types=1);

/**
 * admin/live-session-report.php  — P1.4: Post-Session Report
 *
 * Renders a full summary report for a completed (closed) live session:
 *   - Attendance list with scores
 *   - Learning Gain (Δ Score = Post-test score − Pre-test score)
 *   - Question difficulty heatmap (correct %)
 *   - Understanding Check breakdown (got_it / somewhat / confused)
 *   - Boss Fight summary
 *   - Download as CSV button
 *
 * Access: Teacher who owns the session OR Admin (same ownership rule as API)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/live-sessions-helper.php';
require_once __DIR__ . '/../includes/phase2-session-service.php';

ensureLiveSessionSchema($pdo);

$sessionId = trim((string) ($_GET['sessionId'] ?? ''));
$currentUserId   = (int) ($consoleUser['id'] ?? 0);
$currentUserRole = (string) ($consoleUser['role'] ?? 'teacher');

if ($sessionId === '') {
    http_response_code(400);
    echo '<p>กรุณาระบุ sessionId</p>';
    exit;
}

// ── Fetch session ─────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT s.*,
           COALESCE(CONCAT_WS(' ', u.first_name, u.last_name), 'คุณครู') AS teacher_name,
           e.title AS exam_title, e.subject AS exam_subject
    FROM classroom_sessions s
    LEFT JOIN users u ON u.id = s.teacher_id
    LEFT JOIN exams e ON e.id = s.exam_id
    WHERE s.id = :id LIMIT 1
");
$stmt->execute([':id' => $sessionId]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) { http_response_code(404); echo '<p>ไม่พบ Session</p>'; exit; }

// Ownership check (same guard as API)
if ($currentUserRole !== 'admin' && (int)$session['teacher_id'] !== $currentUserId) {
    http_response_code(403);
    echo '<p>ไม่มีสิทธิ์ดูรายงานนี้</p>';
    exit;
}

// ── Fetch participants + scores ────────────────────────────────────────────────
$stmtP = $pdo->prepare("
    SELECT sp.student_id, sp.joined_at, sp.status,
           u.first_name, u.last_name, u.email,
           ta.score AS post_score, ta.correct_count, ta.total_questions,
           ta.started_at AS exam_started, ta.completed_at AS exam_completed,
           COALESCE(ans.db_answers_count, 0) AS answers_count
    FROM session_participants sp
    JOIN users u ON u.id = sp.student_id
    LEFT JOIN test_attempts ta ON ta.id = sp.attempt_id
    LEFT JOIN (
        SELECT attempt_id, COUNT(*) AS db_answers_count
        FROM test_answers GROUP BY attempt_id
    ) ans ON ans.attempt_id = ta.id
    WHERE sp.session_id = :sid
    ORDER BY ta.score DESC, u.first_name ASC
");
$stmtP->execute([':sid' => $sessionId]);
$participants = $stmtP->fetchAll(PDO::FETCH_ASSOC);

// ── P1.2: Fetch Pre-test scores (linked via session_readiness) ────────────────
$preScores = [];
try {
    $stmtPre = $pdo->prepare("
        SELECT student_id, readiness_score
        FROM session_readiness
        WHERE session_id = :sid
    ");
    $stmtPre->execute([':sid' => $sessionId]);
    foreach ($stmtPre->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $preScores[(int)$row['student_id']] = (float)$row['readiness_score'];
    }
} catch (Throwable $e) { /* table may not exist yet */ }

// ── Question stats ─────────────────────────────────────────────────────────────
$questionStats = [];
$examId = (int)($session['exam_id'] ?? 0);
if ($examId > 0) {
    $stmtQ = $pdo->prepare("
        SELECT q.id, q.sort_order, q.question_text, q.skill,
               COUNT(ta.id) AS total, SUM(ta.is_correct = 1) AS correct
        FROM exam_questions q
        LEFT JOIN test_answers ta ON ta.question_id = q.id
        LEFT JOIN test_attempts att ON att.id = ta.attempt_id
        LEFT JOIN session_participants sp ON sp.attempt_id = att.id AND sp.session_id = :sid
        WHERE q.exam_id = :eid
        GROUP BY q.id
        ORDER BY q.sort_order, q.id
    ");
    $stmtQ->execute([':sid' => $sessionId, ':eid' => $examId]);
    $questionStats = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
}

// ── Understanding check breakdown ─────────────────────────────────────────────
$p2      = new Phase2SessionService($pdo);
$ucStats = $p2->getUnderstandingStats($sessionId);
$topics  = $p2->getSessionTopics($sessionId);

// ── Aggregate stats ────────────────────────────────────────────────────────────
$totalStudents  = count($participants);
$submitted      = array_filter($participants, fn($p) => !empty($p['exam_completed']));
$avgPost        = $totalStudents > 0
                  ? round(array_sum(array_column(array_filter($participants, fn($p) => $p['post_score'] !== null), 'post_score')) / max(1, count(array_filter($participants, fn($p) => $p['post_score'] !== null))), 1)
                  : 0;
$avgPre         = count($preScores) > 0 ? round(array_sum($preScores) / count($preScores), 1) : null;
$learningGain   = ($avgPre !== null) ? round($avgPost - $avgPre, 1) : null;

$duration = '';
if ($session['started_at'] && $session['ended_at']) {
    $diffSec = strtotime($session['ended_at']) - strtotime($session['started_at']);
    $duration = floor($diffSec / 60) . ' นาที ' . ($diffSec % 60) . ' วินาที';
}

// ── CSV export ─────────────────────────────────────────────────────────────────
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="session-report-' . $sessionId . '.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['ชื่อ', 'อีเมล', 'เข้าร่วม', 'สถานะ', 'คะแนน Pre (%)', 'คะแนน Post (%)', 'พัฒนาการ (Δ)', 'ข้อที่ตอบ', 'ข้อทั้งหมด']);
    foreach ($participants as $p) {
        $sid   = (int)$p['student_id'];
        $pre   = $preScores[$sid] ?? null;
        $post  = $p['post_score'] !== null ? (float)$p['post_score'] : null;
        $gain  = ($pre !== null && $post !== null) ? round($post - $pre, 1) : '';
        fputcsv($out, [
            trim($p['first_name'] . ' ' . $p['last_name']),
            $p['email'],
            $p['joined_at'],
            $p['status'],
            $pre !== null ? $pre : '',
            $post !== null ? $post : '',
            $gain,
            $p['answers_count'],
            $p['total_questions'] ?? 0,
        ]);
    }
    fclose($out);
    exit;
}

// ── Page render ────────────────────────────────────────────────────────────────
$pageTitle = 'รายงานสรุปผล: ' . htmlspecialchars($session['title']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?> — Nextbeyond</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0f172a; --card: #1e293b; --card2: #263044; --border: #334155;
    --text: #f1f5f9; --muted: #94a3b8; --accent: #6366f1; --accent2: #818cf8;
    --green: #22c55e; --red: #ef4444; --yellow: #f59e0b; --cyan: #06b6d4;
    --radius: 12px; --radius-sm: 8px;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Noto Sans Thai', 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding: 0 0 60px; }

  .header { background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 60%); border-bottom: 1px solid var(--border); padding: 24px 32px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
  .header h1 { font-size: 1.4rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
  .header .badge { background: #22c55e22; color: var(--green); border: 1px solid #22c55e44; padding: 4px 12px; border-radius: 99px; font-size: 0.78rem; font-weight: 600; }
  .header .badge.closed { background: #94a3b822; color: var(--muted); border-color: var(--border); }

  .actions { display: flex; gap: 10px; flex-wrap: wrap; }
  .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: var(--radius-sm); font-size: 0.85rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all .2s; }
  .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text); }
  .btn-outline:hover { background: var(--card2); border-color: var(--accent2); }
  .btn-primary { background: var(--accent); color: #fff; }
  .btn-primary:hover { background: var(--accent2); }

  .container { max-width: 1200px; margin: 32px auto; padding: 0 24px; display: grid; gap: 24px; }

  /* Stat Cards */
  .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
  .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; display: flex; flex-direction: column; gap: 6px; transition: transform .2s; }
  .stat-card:hover { transform: translateY(-2px); }
  .stat-card .label { font-size: 0.78rem; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
  .stat-card .value { font-size: 2rem; font-weight: 700; line-height: 1; }
  .stat-card .sub { font-size: 0.78rem; color: var(--muted); }
  .stat-card.green .value { color: var(--green); }
  .stat-card.yellow .value { color: var(--yellow); }
  .stat-card.cyan .value { color: var(--cyan); }
  .stat-card.accent .value { color: var(--accent2); }
  .gain-positive { color: var(--green); }
  .gain-negative { color: var(--red); }

  /* Section */
  .section { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
  .section-header { padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
  .section-header h2 { font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
  .section-body { padding: 0; }

  /* Table */
  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
  th { padding: 12px 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); border-bottom: 1px solid var(--border); white-space: nowrap; }
  td { padding: 12px 16px; border-bottom: 1px solid #1e293b; vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #263044; }
  .avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), #7c3aed); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; color: #fff; flex-shrink: 0; }
  .name-cell { display: flex; align-items: center; gap: 10px; }

  /* Status badges */
  .status { display: inline-block; padding: 2px 10px; border-radius: 99px; font-size: 0.72rem; font-weight: 600; }
  .status.submitted { background: #22c55e22; color: var(--green); }
  .status.in-progress { background: #f59e0b22; color: var(--yellow); }
  .status.joined { background: #94a3b822; color: var(--muted); }

  /* Score bar */
  .score-bar-wrap { display: flex; align-items: center; gap: 8px; min-width: 120px; }
  .score-bar { flex: 1; height: 6px; background: var(--border); border-radius: 3px; overflow: hidden; }
  .score-bar-fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg, var(--accent), var(--accent2)); transition: width .3s; }
  .score-bar-fill.low { background: linear-gradient(90deg, var(--red), #f97316); }
  .score-bar-fill.mid { background: linear-gradient(90deg, var(--yellow), #84cc16); }
  .score-val { font-weight: 600; font-size: 0.85rem; min-width: 42px; text-align: right; }

  /* Question heatmap */
  .q-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; padding: 20px; }
  .q-card { background: var(--card2); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 14px; }
  .q-card .q-num { font-size: 0.72rem; color: var(--muted); margin-bottom: 4px; }
  .q-card .q-topic { font-size: 0.8rem; font-weight: 600; margin-bottom: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .q-pct { font-size: 1.4rem; font-weight: 700; }
  .q-pct.good { color: var(--green); }
  .q-pct.mid { color: var(--yellow); }
  .q-pct.bad { color: var(--red); }
  .q-pct-label { font-size: 0.72rem; color: var(--muted); }

  /* Understanding breakdown */
  .uc-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; padding: 20px; }
  .uc-card { background: var(--card2); border-radius: var(--radius-sm); padding: 16px; text-align: center; border: 1px solid var(--border); }
  .uc-card .uc-emoji { font-size: 2rem; margin-bottom: 6px; }
  .uc-card .uc-label { font-size: 0.78rem; color: var(--muted); }
  .uc-card .uc-val { font-size: 1.6rem; font-weight: 700; }
  .uc-card.got_it { border-color: #22c55e44; }
  .uc-card.got_it .uc-val { color: var(--green); }
  .uc-card.somewhat { border-color: #f59e0b44; }
  .uc-card.somewhat .uc-val { color: var(--yellow); }
  .uc-card.confused { border-color: #ef444444; }
  .uc-card.confused .uc-val { color: var(--red); }

  /* Boss */
  .boss-row { display: flex; align-items: center; gap: 20px; padding: 20px 24px; flex-wrap: wrap; }
  .boss-emoji { font-size: 3rem; }
  .boss-info { flex: 1; min-width: 200px; }
  .boss-name { font-size: 1.1rem; font-weight: 700; margin-bottom: 4px; }
  .boss-hp-bar { height: 10px; background: var(--border); border-radius: 5px; overflow: hidden; margin-top: 8px; }
  .boss-hp-fill { height: 100%; background: linear-gradient(90deg, var(--red), #f97316); border-radius: 5px; }

  @media (max-width: 640px) {
    .header { padding: 16px; }
    .container { padding: 0 12px; }
    .uc-grid { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<!-- ── Header ─────────────────────────────────────────────────────────────── -->
<div class="header">
  <h1>
    📋 รายงานสรุปผล
    <span class="badge <?= $session['status'] === 'closed' ? 'closed' : '' ?>">
      <?= $session['status'] === 'closed' ? '✅ จบแล้ว' : '🔴 กำลังสอน' ?>
    </span>
  </h1>
  <div class="actions">
    <a href="live-session-room.php?sessionId=<?= urlencode($sessionId) ?>" class="btn btn-outline">← กลับห้องเรียน</a>
    <a href="live-session-report.php?sessionId=<?= urlencode($sessionId) ?>&export=csv" class="btn btn-primary">⬇ ดาวน์โหลด CSV</a>
  </div>
</div>

<!-- ── Container ──────────────────────────────────────────────────────────── -->
<div class="container">

  <!-- Session meta -->
  <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <div style="color:var(--muted);font-size:.85rem">
      🎓 <strong><?= htmlspecialchars($session['teacher_name']) ?></strong> &nbsp;|&nbsp;
      📚 <?= htmlspecialchars($session['exam_title'] ?: 'ไม่ได้กำหนดข้อสอบ') ?> &nbsp;|&nbsp;
      🕐 <?= htmlspecialchars($session['started_at']) ?>
      <?php if ($session['ended_at']): ?>
        → <?= htmlspecialchars($session['ended_at']) ?> (<?= $duration ?>)
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Overview Stats ── -->
  <div class="stats-grid">
    <div class="stat-card accent">
      <div class="label">นักเรียนทั้งหมด</div>
      <div class="value"><?= $totalStudents ?></div>
      <div class="sub"><?= count($submitted) ?> คนส่งคำตอบ</div>
    </div>
    <div class="stat-card <?= ($avgPost >= 70) ? 'green' : (($avgPost >= 50) ? 'yellow' : '') ?>">
      <div class="label">คะแนนเฉลี่ย (Post-test)</div>
      <div class="value"><?= $avgPost ?>%</div>
      <div class="sub">จากนักเรียนที่ส่งแล้ว</div>
    </div>
    <?php if ($avgPre !== null): ?>
    <div class="stat-card <?= $learningGain >= 0 ? 'green' : '' ?>">
      <div class="label">คะแนนเฉลี่ย (Pre-test)</div>
      <div class="value"><?= $avgPre ?>%</div>
      <div class="sub">วัดก่อนเรียน</div>
    </div>
    <div class="stat-card <?= $learningGain >= 0 ? 'green' : '' ?>">
      <div class="label">พัฒนาการ (Δ Score)</div>
      <div class="value <?= $learningGain >= 0 ? 'gain-positive' : 'gain-negative' ?>">
        <?= ($learningGain >= 0 ? '+' : '') . $learningGain ?>%
      </div>
      <div class="sub">Post − Pre</div>
    </div>
    <?php endif; ?>
    <div class="stat-card cyan">
      <div class="label">PIN ห้องเรียน</div>
      <div class="value" style="font-size:1.6rem;letter-spacing:3px"><?= htmlspecialchars($session['session_pin']) ?></div>
      <div class="sub">รหัสเข้าร่วม</div>
    </div>
  </div>

  <!-- ── Attendance & Scores Table ── -->
  <div class="section">
    <div class="section-header">
      <h2>👥 รายชื่อผู้เข้าเรียนและคะแนน</h2>
      <span style="color:var(--muted);font-size:.8rem"><?= $totalStudents ?> คน</span>
    </div>
    <div class="section-body table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>ชื่อ-นามสกุล</th>
            <th>สถานะ</th>
            <?php if ($avgPre !== null): ?><th>Pre-test</th><?php endif; ?>
            <th>Post-test</th>
            <?php if ($avgPre !== null): ?><th>Δ Score</th><?php endif; ?>
            <th>คำตอบ</th>
            <th>เวลาเข้าร่วม</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($participants as $i => $p):
          $sid    = (int)$p['student_id'];
          $pre    = $preScores[$sid] ?? null;
          $post   = $p['post_score'] !== null ? round((float)$p['post_score'], 1) : null;
          $gain   = ($pre !== null && $post !== null) ? round($post - $pre, 1) : null;
          $initials = strtoupper(mb_substr($p['first_name'], 0, 1) . mb_substr($p['last_name'], 0, 1));
          $statusClass = match($p['status']) {
              'submitted' => 'submitted', 'in_progress' => 'in-progress', default => 'joined'
          };
          $statusLabel = match($p['status']) {
              'submitted' => '✅ ส่งแล้ว', 'in_progress' => '⏳ กำลังทำ', default => '🟡 เข้าร่วม'
          };
          $pct = $post ?? 0;
          $barClass = $pct >= 70 ? '' : ($pct >= 50 ? 'mid' : 'low');
        ?>
          <tr>
            <td style="color:var(--muted);font-size:.8rem"><?= $i + 1 ?></td>
            <td>
              <div class="name-cell">
                <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                <div>
                  <div style="font-weight:600"><?= htmlspecialchars(trim($p['first_name'] . ' ' . $p['last_name'])) ?></div>
                  <div style="font-size:.75rem;color:var(--muted)"><?= htmlspecialchars($p['email']) ?></div>
                </div>
              </div>
            </td>
            <td><span class="status <?= $statusClass ?>"><?= $statusLabel ?></span></td>
            <?php if ($avgPre !== null): ?>
            <td><?= $pre !== null ? $pre . '%' : '<span style="color:var(--muted)">—</span>' ?></td>
            <?php endif; ?>
            <td>
              <?php if ($post !== null): ?>
              <div class="score-bar-wrap">
                <div class="score-bar"><div class="score-bar-fill <?= $barClass ?>" style="width:<?= min(100,$pct) ?>%"></div></div>
                <span class="score-val"><?= $post ?>%</span>
              </div>
              <?php else: ?>
              <span style="color:var(--muted)">—</span>
              <?php endif; ?>
            </td>
            <?php if ($avgPre !== null): ?>
            <td>
              <?php if ($gain !== null): ?>
              <span class="<?= $gain >= 0 ? 'gain-positive' : 'gain-negative' ?>" style="font-weight:700">
                <?= ($gain >= 0 ? '+' : '') . $gain ?>%
              </span>
              <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
            </td>
            <?php endif; ?>
            <td style="color:var(--muted);font-size:.85rem"><?= (int)$p['answers_count'] ?> / <?= (int)($p['total_questions'] ?? 0) ?></td>
            <td style="color:var(--muted);font-size:.8rem"><?= date('H:i', strtotime($p['joined_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Question Heatmap ── -->
  <?php if ($questionStats): ?>
  <div class="section">
    <div class="section-header">
      <h2>🎯 ความยากของแต่ละข้อ (% ตอบถูก)</h2>
      <span style="color:var(--muted);font-size:.8rem"><?= count($questionStats) ?> ข้อ</span>
    </div>
    <div class="section-body">
      <div class="q-grid">
        <?php foreach ($questionStats as $qi => $q):
          $tot = (int)$q['total'];
          $cor = (int)$q['correct'];
          $pct = $tot > 0 ? round($cor / $tot * 100) : 0;
          $cls = $pct >= 70 ? 'good' : ($pct >= 40 ? 'mid' : 'bad');
        ?>
        <div class="q-card">
          <div class="q-num">ข้อที่ <?= $qi + 1 ?> <?= $q['skill'] ? '· ' . htmlspecialchars($q['skill']) : '' ?></div>
          <div class="q-topic"><?= htmlspecialchars(mb_substr($q['question_text'], 0, 60)) ?>…</div>
          <div class="q-pct <?= $cls ?>"><?= $pct ?>%</div>
          <div class="q-pct-label">ตอบถูก (<?= $cor ?>/<?= $tot ?>)</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Understanding Check ── -->
  <?php if (!empty($ucStats)): ?>
  <div class="section">
    <div class="section-header">
      <h2>💬 Understanding Check — สัญญาณความเข้าใจ</h2>
    </div>
    <div class="section-body">
      <div class="uc-grid">
        <div class="uc-card got_it">
          <div class="uc-emoji">✅</div>
          <div class="uc-val"><?= (int)($ucStats['got_it'] ?? 0) ?></div>
          <div class="uc-label">เข้าใจแล้ว</div>
        </div>
        <div class="uc-card somewhat">
          <div class="uc-emoji">🤔</div>
          <div class="uc-val"><?= (int)($ucStats['somewhat'] ?? 0) ?></div>
          <div class="uc-label">พอเข้าใจ</div>
        </div>
        <div class="uc-card confused">
          <div class="uc-emoji">❓</div>
          <div class="uc-val"><?= (int)($ucStats['confused'] ?? 0) ?></div>
          <div class="uc-label">ยังงง</div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Boss Fight Summary ── -->
  <?php if ($session['boss_fight_active']): ?>
  <div class="section">
    <div class="section-header">
      <h2>⚔️ Boss Fight Summary</h2>
    </div>
    <div class="section-body">
      <div class="boss-row">
        <div class="boss-emoji">
          <?= match($session['boss_theme']) {
            'dragon' => '🐲', 'golem' => '🗿', 'phoenix' => '🔥',
            'shadow' => '🌑', 'lightning' => '⚡', 'ocean' => '🌊', default => '👹'
          } ?>
        </div>
        <div class="boss-info">
          <div class="boss-name"><?= htmlspecialchars($session['boss_name'] ?: 'Boss') ?></div>
          <?php if ($session['boss_defeated']): ?>
            <div style="color:var(--green);font-weight:700;margin-top:4px">✅ ถูกพิชิตแล้ว!</div>
          <?php else: ?>
            <div style="color:var(--muted);font-size:.85rem">HP เหลือ <?= (int)$session['boss_current_hp'] ?> / <?= (int)$session['boss_max_hp'] ?></div>
          <?php endif; ?>
          <div class="boss-hp-bar" style="max-width:300px">
            <div class="boss-hp-fill" style="width:<?= (int)$session['boss_max_hp'] > 0 ? round((int)$session['boss_current_hp'] / (int)$session['boss_max_hp'] * 100) : 0 ?>%"></div>
          </div>
        </div>
        <div style="text-align:right">
          <div style="font-size:.78rem;color:var(--muted)">รางวัล</div>
          <div style="font-size:1.5rem;font-weight:700;color:var(--yellow)">+<?= (int)$session['boss_reward_points'] ?> คะแนน</div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /.container -->

</body>
</html>
