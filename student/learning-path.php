<?php
/**
 * student/learning-path.php
 * NEXTBEYOND V2 — Learning Navigation Refactor
 * 
 * The Unified Learning Journey Hub
 * Answers:
 * 1. "ฉันกำลังเดินไปทางไหน และต้องเรียนหัวข้ออะไรบ้าง?" -> Tab 1: เส้นทางของฉัน (Learning Path)
 * 2. "วันนี้ / สัปดาห์นี้ ฉันต้องทำอะไร?"               -> Tab 2: แผนสัปดาห์นี้ (Weekly Plan)
 * 3. "ฉันมีอะไรวันไหน?"                                 -> Tab 3: ปฏิทิน (Calendar)
 */
declare(strict_types=1);

$pageTitle   = 'เส้นทางการเรียนของฉัน';
$currentPage = 'learning-path.php';

require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/roadmap-service.php';
require_once __DIR__ . '/../includes/learning-journey-service.php';
require_once __DIR__ . '/../includes/phase3-mastery-service.php';

$journeyService = new LearningJourneyService($pdo);
$journeyService->ensureSchema();
$p3 = new Phase3MasteryService($pdo);

$studentId = (int)($currentUser['id'] ?? 0);

if (!function_exists('resolveStudentActionUrl')) {
    function resolveStudentActionUrl(?string $url): string {
        if (empty($url)) return 'my-courses.php';
        if (str_starts_with($url, 'student/')) return substr($url, 8);
        if (str_starts_with($url, 'lesson.php') || str_starts_with($url, 'course.php')) return '../' . $url;
        return $url;
    }
}

// 1. Fetch active enrolled courses for course selector
$stmtCourses = $pdo->prepare("
    SELECT c.id, c.title, c.subject, c.level
    FROM enrollments en
    INNER JOIN courses c ON c.id = en.course_id
    WHERE en.user_id = ? AND en.status IN ('active', 'trial')
      AND (en.end_date IS NULL OR en.end_date >= CURDATE())
    ORDER BY c.id ASC
");
$stmtCourses->execute([$studentId]);
$enrolledCourses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

// If no active enrollments found, check all enrollments as fallback
if (empty($enrolledCourses)) {
    $stmtFallback = $pdo->prepare("
        SELECT c.id, c.title, c.subject, c.level
        FROM enrollments en
        INNER JOIN courses c ON c.id = en.course_id
        WHERE en.user_id = ?
        ORDER BY c.id ASC
    ");
    $stmtFallback->execute([$studentId]);
    $enrolledCourses = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
}

// Selected course
$requestedCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$selectedCourse = null;
if ($requestedCourseId > 0) {
    foreach ($enrolledCourses as $c) {
        if ((int)$c['id'] === $requestedCourseId) {
            $selectedCourse = $c;
            break;
        }
    }
}
if (!$selectedCourse && !empty($enrolledCourses)) {
    $selectedCourse = $enrolledCourses[0];
}
$selectedCourseId = $selectedCourse ? (int)$selectedCourse['id'] : null;

// Active tab ('path', 'weekly', 'calendar')
$activeTab = $_GET['tab'] ?? 'path';
if (!in_array($activeTab, ['path', 'weekly', 'calendar'], true)) {
    $activeTab = 'path';
}

// Focused topic from URL query
$focusedTopic = trim((string)($_GET['topic'] ?? ''));

// 2. Fetch Course Goals, Next Action & Metrics
$activeGoal = null;
$courseGoals = [];
$nextAction = null;
$courseProgressPercent = 0;
$courseMasteryScore = 0.0;
$currentTopicName = 'กำลังเตรียมเนื้อหา';

if ($selectedCourseId) {
    $courseGoals = $journeyService->getStudentLearningGoals($studentId, $selectedCourseId, 'active');
    $activeGoal = $courseGoals[0] ?? null;

    $nextAction = $journeyService->getStudentNextAction($studentId, $selectedCourseId);

    // Course completion %
    $stmtProg = $pdo->prepare("SELECT progress_percent FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1");
    $stmtProg->execute([$studentId, $selectedCourseId]);
    $courseProgressPercent = (int)round((float)$stmtProg->fetchColumn());

    // Calculate course mastery score from topic_mastery
    $stmtTm = $pdo->prepare("SELECT AVG(mastery_score) FROM topic_mastery WHERE student_id = ? AND course_id = ?");
    $stmtTm->execute([$studentId, $selectedCourseId]);
    $avgMastery = $stmtTm->fetchColumn();
    if ($avgMastery !== false && $avgMastery !== null) {
        $courseMasteryScore = round((float)$avgMastery, 1);
    } else {
        $courseMasteryScore = 0.0;
    }

    // Determine current topic
    if ($nextAction && !empty($nextAction['topic'])) {
        $currentTopicName = $nextAction['topic'];
    } elseif ($nextAction && !empty($nextAction['title'])) {
        $currentTopicName = $nextAction['title'];
    }
}

// 3. Learning Path Steps (Tab 1)
$rawLearningPath = $selectedCourseId ? $journeyService->getStudentLearningPath($studentId, $selectedCourseId) : null;
$rawSteps = $rawLearningPath['steps'] ?? [];

// Default curriculum topics fallback if no custom steps in plan_data
if (!function_exists('getDefaultCurriculumTopics')) {
    function getDefaultCurriculumTopics(string $subject, string $level): array {
        $sub = mb_strtolower($subject);
        if (str_contains($sub, 'อังกฤษ') || str_contains($sub, 'english')) {
            return [
                ['title' => 'Grammar Foundation', 'desc' => 'ปูพื้นฐานไวยากรณ์และโครงสร้างประโยค'],
                ['title' => 'Present Simple', 'desc' => 'การใช้ Verb และเงื่อนไขบอกข้อเท็จจริง'],
                ['title' => 'Present Continuous', 'desc' => 'เหตุการณ์ที่กำลังดำเนินอยู่'],
                ['title' => 'Present Perfect', 'desc' => 'ความต่อเนื่องของเวลาและประสบการณ์'],
                ['title' => 'Reading Skills', 'desc' => 'เทคนิคการจับใจความและสรุปบทความ'],
                ['title' => 'Exam Strategy', 'desc' => 'กลยุทธ์ทำข้อสอบ A-Level / TGAT1'],
            ];
        }
        if (str_contains($sub, 'เคมี') || str_contains($sub, 'chem')) {
            return [
                ['title' => 'ปริมาณสารสัมพันธ์เบื้องต้น', 'desc' => 'โมล มวล และการคำนวณสมการ'],
                ['title' => 'พันธะเคมีและโครงสร้างโมเลกุล', 'desc' => 'โคเวเลนต์ ไอออนิก และโลหะ'],
                ['title' => 'สมดุลกรด-เบส', 'desc' => 'pH, pOH และการไทเทรต'],
                ['title' => 'เคมีไฟฟ้าและอัตราการเกิดปฏิกิริยา', 'desc' => 'เซลล์กัลวานิกและกฎอัตรา'],
            ];
        }
        if (str_contains($sub, 'ชีว') || str_contains($sub, 'bio')) {
            return [
                ['title' => 'โครงสร้างและการทำงานของเซลล์', 'desc' => 'ออร์แกเนลล์และเมมเบรน'],
                ['title' => 'สารชีวโมเลกุลและการสลายสารอาหาร', 'desc' => 'Glycolysis และ Krebs cycle'],
                ['title' => 'พันธุศาสตร์และวิวัฒนาการ', 'desc' => 'การถ่ายทอดลักษณะตามเมนเดล'],
                ['title' => 'ระบบนิเวศและความหลากหลาย', 'desc' => 'ความสัมพันธ์ในระบบนิเวศ'],
            ];
        }
        if (str_contains($sub, 'คณิต') || str_contains($sub, 'math')) {
            return [
                ['title' => 'จำนวนจริงและพหุนาม', 'desc' => 'สมการและอสมการพหุนาม'],
                ['title' => 'ฟังก์ชันกำลังสองและเรขาคณิตวิเคราะห์', 'desc' => 'พาราโบลาและภาคตัดกรวย'],
                ['title' => 'ตรีโกณมิติและการประยุกต์', 'desc' => 'เอกลักษณ์ตรีโกณและวงกลมหนึ่งหน่วย'],
                ['title' => 'สถิติและความน่าจะเป็น', 'desc' => 'การกระจายตัวและการสุ่ม'],
            ];
        }
        return [
            ['title' => 'เนื้อหาพื้นฐานบทที่ 1', 'desc' => 'ทำความเข้าใจหลักการสำคัญ'],
            ['title' => 'เนื้อหาหลักบทที่ 2', 'desc' => 'เจาะลึกโจทย์และการประยุกต์'],
            ['title' => 'แบบฝึกหัดพัฒนาทักษะ', 'desc' => 'ฝึกฝนความแม่นยำ'],
            ['title' => 'ข้อสอบวัดผลสัมฤทธิ์', 'desc' => 'ประเมินความพร้อมสู่เป้าหมาย'],
        ];
    }
}

// Build Enriched Learning Path Nodes
$pathNodes = [];
if ($selectedCourseId) {
    // Fetch topic mastery records
    $stmtTopicMastery = $pdo->prepare("
        SELECT topic_name, mastery_score, confidence_score, evidence_count
        FROM topic_mastery
        WHERE student_id = ? AND course_id = ?
    ");
    $stmtTopicMastery->execute([$studentId, $selectedCourseId]);
    $topicMasteryRows = $stmtTopicMastery->fetchAll(PDO::FETCH_ASSOC);

    // Fetch open gaps
    $stmtGaps = $pdo->prepare("
        SELECT * FROM student_learning_gaps
        WHERE student_id = ? AND course_id = ? AND status <> 'resolved'
    ");
    $stmtGaps->execute([$studentId, $selectedCourseId]);
    $gapsRows = $stmtGaps->fetchAll(PDO::FETCH_ASSOC);

    // Fetch adaptive roadmap steps
    $stmtAdaptive = $pdo->prepare("
        SELECT * FROM adaptive_roadmap_steps
        WHERE student_id = ? AND course_id = ? AND status IN ('available', 'in_progress')
        ORDER BY sort_order ASC, id ASC
    ");
    $stmtAdaptive->execute([$studentId, $selectedCourseId]);
    $adaptiveSteps = $stmtAdaptive->fetchAll(PDO::FETCH_ASSOC);

    // Compile topics list
    $baseTopics = [];
    if (!empty($rawSteps)) {
        foreach ($rawSteps as $st) {
            $tTitle = is_array($st) ? ($st['title'] ?? $st['name'] ?? 'หัวข้อการเรียน') : (string)$st;
            $baseTopics[] = ['title' => $tTitle, 'desc' => is_array($st) ? ($st['desc'] ?? '') : ''];
        }
    } else {
        $baseTopics = getDefaultCurriculumTopics($selectedCourse['subject'] ?? '', $selectedCourse['level'] ?? '');
    }

    // Process nodes with adaptive injection (Section 22 & Acceptance Test #6)
    $foundCurrent = false;
    foreach ($baseTopics as $idx => $bt) {
        $tName = $bt['title'];
        
        // Find matching topic mastery score
        $mRecord = null;
        foreach ($topicMasteryRows as $tm) {
            $tmName = trim((string)$tm['topic_name']);
            if (stripos($tName, $tmName) !== false || stripos($tmName, $tName) !== false) {
                $mRecord = $tm;
                break;
            }
        }
        $mScore = $mRecord ? (float)$mRecord['mastery_score'] : null;

        // Detect if gap exists
        $hasGap = false;
        if ($mScore !== null && $mScore < 65.0) {
            $hasGap = true;
        }
        if (!$hasGap) {
            foreach ($gapsRows as $g) {
                $gTopic = trim((string)$g['topic_name']);
                if (stripos($tName, $gTopic) !== false || stripos($gTopic, $tName) !== false) {
                    $hasGap = true;
                    break;
                }
            }
        }
        if (!$hasGap) {
            foreach ($adaptiveSteps as $as) {
                $asTopic = trim((string)$as['topic_name']);
                if (stripos($tName, $asTopic) !== false || stripos($asTopic, $tName) !== false) {
                    $hasGap = true;
                    break;
                }
            }
        }

        // Determine node status
        $status = 'AVAILABLE';
        if ($mScore !== null && $mScore >= 80.0) {
            $status = 'COMPLETED';
        } elseif ($hasGap) {
            $status = 'REVIEW_REQUIRED';
        } elseif (!$foundCurrent) {
            $status = 'CURRENT';
            $foundCurrent = true;
        } elseif ($idx > 3 && $mScore === null) {
            $status = 'LOCKED';
        }

        // If this topic matches current next action topic
        if ($nextAction && !empty($nextAction['topic']) && stripos($tName, $nextAction['topic']) !== false) {
            $status = 'CURRENT';
            $foundCurrent = true;
        }

        $node = [
            'type' => 'topic',
            'title' => $tName,
            'desc' => $bt['desc'],
            'status' => $status,
            'mastery_score' => $mScore,
            'is_focused' => !empty($focusedTopic) && stripos($tName, $focusedTopic) !== false,
            'action_url' => $nextAction && $status === 'CURRENT' ? resolveStudentActionUrl($nextAction['action_url']) : null,
        ];
        $pathNodes[] = $node;

        // INJECT ADAPTIVE STEPS IF GAP EXISTS (Section 22, Acceptance Test #6)
        if ($hasGap) {
            // 1. Adaptive Remediation Node (แบบฝึกเฉพาะจุด)
            $pathNodes[] = [
                'type' => 'adaptive_remediation',
                'title' => 'แบบฝึกเฉพาะจุด (' . $tName . ')',
                'desc' => 'แบบฝึกเสริมความเข้าใจเฉพาะจุด เพื่อปิดช่องว่างการเรียนรู้',
                'status' => 'REMEDIATION',
                'mastery_score' => $mScore,
                'is_focused' => false,
                'action_url' => 'tests.php',
            ];

            // 2. Mastery Check Node (ตรวจความเข้าใจ)
            $pathNodes[] = [
                'type' => 'mastery_check',
                'title' => 'Mastery Check (' . $tName . ')',
                'desc' => 'แบบทดสอบประเมินระดับความเชี่ยวชาญก่อนก้าวสู่หัวข้อถัดไป',
                'status' => 'AVAILABLE',
                'mastery_score' => null,
                'is_focused' => false,
                'action_url' => 'tests.php',
            ];
        }
    }
}

// 4. Weekly Plan Activities (Tab 2 — EXISTING Study Roadmap Data)
$weeklyActivities = [];
if ($selectedCourseId) {
    // A. Roadmap tasks for this course
    $stmtTasks = $pdo->prepare("
        SELECT t.*, r.title AS roadmap_title,
               COALESCE(p.status, 'not_started') AS progress_status,
               COALESCE(p.is_completed, 0) AS is_completed_flag
        FROM roadmap_tasks t
        INNER JOIN roadmaps r ON r.id = t.roadmap_id
        LEFT JOIN roadmap_task_progress p ON p.task_id = t.id AND p.user_id = :user_id
        WHERE (t.ref_course_id = :course_id OR t.subject = :course_subject)
          AND t.is_active = 1
        ORDER BY t.sort_order ASC, t.id ASC
    ");
    $stmtTasks->execute([
        ':user_id' => $studentId,
        ':course_id' => $selectedCourseId,
        ':course_subject' => $selectedCourse['subject'] ?? ''
    ]);
    $rawTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

    // If no course-specific tasks found, pull general roadmap tasks
    if (empty($rawTasks)) {
        $stmtGeneralTasks = $pdo->prepare("
            SELECT t.*, r.title AS roadmap_title,
                   COALESCE(p.status, 'not_started') AS progress_status,
                   COALESCE(p.is_completed, 0) AS is_completed_flag
            FROM roadmap_tasks t
            INNER JOIN roadmaps r ON r.id = t.roadmap_id
            LEFT JOIN roadmap_task_progress p ON p.task_id = t.id AND p.user_id = :user_id
            WHERE t.is_active = 1
            ORDER BY t.sort_order ASC, t.id ASC
            LIMIT 6
        ");
        $stmtGeneralTasks->execute([':user_id' => $studentId]);
        $rawTasks = $stmtGeneralTasks->fetchAll(PDO::FETCH_ASSOC);
    }

    $nowDay = (int)date('N'); // 1 = Monday, 7 = Sunday
    foreach ($rawTasks as $idx => $t) {
        $isDone = $t['is_completed_flag'] == 1 || in_array($t['progress_status'], ['completed', 'exempted'], true);
        
        // Map day slot
        $dayGroup = match($idx) {
            0 => 'วันนี้',
            1 => 'พรุ่งนี้',
            2 => 'วันพุธ',
            3 => 'วันพฤหัส',
            4 => 'วันศุกร์',
            default => 'สุดสัปดาห์ / สัปดาห์ถัดไป'
        };
        if ($isDone) {
            $dayGroup = 'เสร็จสิ้นแล้ว';
        }

        $typeLabel = match($t['completion_type']) {
            'complete_lesson' => 'Live Class / วิดีโอ',
            'submit_test', 'pass_test' => 'Post-Test / แบบทดสอบ',
            'attend_schedule' => 'ตารางเรียนสด',
            default => 'Worksheet / ภารกิจ'
        };

        $actionUrl = 'learning-path.php?tab=weekly';
        if (!empty($t['ref_lesson_id'])) {
            $actionUrl = '../lesson.php?id=' . (int)$t['ref_lesson_id'];
        } elseif (!empty($t['ref_exam_id'])) {
            $actionUrl = 'take-test.php?id=' . (int)$t['ref_exam_id'];
        }

        $weeklyActivities[] = [
            'id' => (int)$t['id'],
            'roadmap_id' => (int)$t['roadmap_id'],
            'title' => $t['title'],
            'category' => $t['category'] ?: 'ภารกิจประจำสัปดาห์',
            'type_label' => $typeLabel,
            'completion_type' => $t['completion_type'],
            'is_manual' => $t['completion_type'] === 'manual',
            'is_done' => $isDone,
            'status' => $isDone ? 'DONE' : ($idx === 0 ? 'CURRENT' : 'UPCOMING'),
            'day_group' => $dayGroup,
            'action_url' => $actionUrl,
            'points' => (int)($t['points_reward'] ?? 0),
        ];
    }
}

// 5. Calendar Events (Tab 3)
$calendarEvents = [];
if ($selectedCourseId) {
    $stmtCal = $pdo->prepare("
        SELECT ce.*, c.title AS course_title
        FROM calendar_events ce
        LEFT JOIN courses c ON c.id = ce.course_id
        WHERE (ce.course_id = :course_id OR ce.course_id IS NULL)
          AND ce.event_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
          AND ce.status = 'scheduled'
        ORDER BY ce.event_date ASC, ce.start_time ASC
        LIMIT 20
    ");
    $stmtCal->execute([':course_id' => $selectedCourseId]);
    $calendarEvents = $stmtCal->fetchAll(PDO::FETCH_ASSOC);
}

// 6. Skill Summary for Right Column (Section 20 — Compact Summary Only)
$skillSummary = [];
if ($selectedCourseId) {
    $skillData = $p3->getStudentSkillMap($studentId, $selectedCourseId);
    $allSkillTopics = $skillData['all'] ?? [];
    $skillSummary = array_slice($allSkillTopics, 0, 4);
}

// Helper: Status Badge HTML
if (!function_exists('renderPathStatusBadge')) {
    function renderPathStatusBadge(string $status): string {
        return match($status) {
            'COMPLETED' => '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">✓ เสร็จแล้ว</span>',
            'CURRENT' => '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-black bg-pink-100 text-pink-700 border border-pink-200 animate-pulse">● กำลังเรียน</span>',
            'AVAILABLE' => '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-black bg-blue-50 text-blue-700 border border-blue-200">พร้อมเรียน</span>',
            'REVIEW_REQUIRED' => '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-black bg-amber-100 text-amber-800 border border-amber-200">⚠️ ควรทบทวน</span>',
            'REMEDIATION' => '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-black bg-purple-100 text-purple-800 border border-purple-200">✦ แบบฝึกเฉพาะจุด</span>',
            'LOCKED' => '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-black bg-slate-100 text-slate-500 border border-slate-200">🔒 ยังไม่เปิด</span>',
            default => '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-black bg-slate-100 text-slate-600">รอเรียน</span>',
        };
    }
}
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> - Next Beyond</title>
  <link rel="stylesheet" href="../assets/css/output.css?v=<?= filemtime(__DIR__ . '/../assets/css/output.css') ?>">
  <link rel="stylesheet" href="../assets/css/student-portal.css?v=<?= filemtime(__DIR__ . '/../assets/css/student-portal.css') ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    .tab-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 8px 18px;
      border-radius: 12px;
      font-size: 13px;
      font-weight: 700;
      color: #64748b;
      background: transparent;
      transition: all 0.2s ease;
      text-decoration: none;
      white-space: nowrap;
    }
    .tab-pill.active {
      background: #ffffff;
      color: #0f2a53;
      box-shadow: 0 2px 8px rgba(15, 42, 83, 0.08);
    }
    .step-connector {
      width: 2px;
      background: #e2e8f0;
      margin-left: 23px;
      height: 24px;
    }
    .step-connector.completed {
      background: #10b981;
    }
    .step-connector.adaptive {
      background: #a855f7;
      border-left: 2px dashed #a855f7;
    }
  </style>
</head>
<body class="student-portal bg-[#f4f7fb] text-navy-950 font-sans antialiased">
<div class="min-h-screen flex">
  <?php include 'includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col ml-[240px] max-[1024px]:ml-0 min-w-0 transition-all duration-300">
    <?php include 'includes/topbar.php'; ?>

    <main class="p-6 sm:p-8 max-w-7xl w-full mx-auto space-y-6">

      <!-- ========================================== -->
      <!-- HEADER & COURSE SELECTOR (Sections 3, 9, 35) -->
      <!-- ========================================== -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/80 pb-6">
        <div>
          <p class="student-kicker text-pink-500 mb-1">LEARNING PATH</p>
          <h1 class="text-2xl sm:text-3xl font-black text-navy-950">เส้นทางการเรียนของฉัน</h1>
          <p class="text-xs sm:text-sm text-slate-500 mt-1">วางแผนการเรียนเฉพาะบุคคล และดูสิ่งที่ควรทำต่อเพื่อไปถึงเป้าหมาย</p>
        </div>

        <?php if (!empty($enrolledCourses)): ?>
          <!-- Course Selector -->
          <div class="flex items-center gap-2.5 bg-white p-2 rounded-2xl border border-slate-200 shadow-2xs">
            <span class="text-xs font-bold text-slate-500 pl-2">คอร์สที่กำลังดู:</span>
            <select aria-label="เลือกคอร์สเรียน" onchange="location.href='learning-path.php?tab=<?= urlencode($activeTab) ?>&course_id='+this.value"
                    class="h-9 px-3 rounded-xl bg-slate-50 border border-slate-200 text-xs font-bold text-navy-950 focus:border-pink-500 focus:outline-none cursor-pointer">
              <?php foreach ($enrolledCourses as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $selectedCourseId === (int)$c['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['title']) ?> (<?= htmlspecialchars($c['subject']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
      </div>

      <?php if (empty($enrolledCourses)): ?>
        <!-- ========================================== -->
        <!-- EMPTY STATE: NO LEARNING PATH (Section 25, Acceptance Test #8) -->
        <!-- ========================================== -->
        <div class="bg-white rounded-3xl border border-slate-200 p-10 sm:p-14 text-center max-w-2xl mx-auto shadow-sm space-y-5">
          <div class="w-16 h-16 rounded-2xl bg-pink-50 text-pink-500 flex items-center justify-center mx-auto text-2xl">
            ✦
          </div>
          <div class="space-y-2">
            <h2 class="text-2xl font-black text-navy-950">ยังไม่มีเส้นทางการเรียน</h2>
            <p class="text-sm text-slate-500 max-w-md mx-auto leading-relaxed">
              เริ่มจากทำแบบประเมินระดับ หรือเลือกคอร์สเพื่อสร้างเส้นทางการเรียนของคุณ
            </p>
          </div>
          <div class="flex items-center justify-center gap-3 pt-2 flex-wrap">
            <a href="tests.php" class="h-11 px-6 rounded-xl bg-pink-500 hover:bg-pink-600 text-white text-xs font-bold inline-flex items-center gap-2 shadow-sm transition">
              <span>ทำแบบประเมิน</span> &rarr;
            </a>
            <a href="../courses" class="h-11 px-6 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-navy-950 text-xs font-bold inline-flex items-center gap-2 transition">
              <span>ดูคอร์ส</span>
            </a>
          </div>
        </div>

      <?php else: ?>

        <!-- ========================================== -->
        <!-- COURSE SUMMARY STRIP (Section 10) -->
        <!-- Separate Completion % and Mastery % -->
        <!-- ========================================== -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-white p-4 sm:p-5 rounded-3xl border border-slate-200 shadow-2xs">
          <div class="border-r border-slate-100 pr-3">
            <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">เป้าหมาย (Goal)</div>
            <div class="text-sm sm:text-base font-black text-navy-950 mt-0.5 truncate">
              <?= htmlspecialchars($activeGoal['goal_name'] ?? 'เป้าหมายประจำคอร์ส') ?>
              <?php if ($activeGoal && $activeGoal['target_score'] !== null): ?>
                <span class="text-pink-500 font-bold">(<?= (float)$activeGoal['target_score'] ?>)</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="border-r border-slate-100 pr-3">
            <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">ความคืบหน้าคอร์ส</div>
            <div class="text-sm sm:text-base font-black text-navy-950 mt-0.5 flex items-center gap-1.5">
              <span><?= $courseProgressPercent ?>%</span>
              <span class="text-[10px] font-semibold text-slate-400">(Completion)</span>
            </div>
          </div>
          <div class="border-r border-slate-100 pr-3">
            <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">ความเชี่ยวชาญปัจจุบัน</div>
            <div class="text-sm sm:text-base font-black text-emerald-600 mt-0.5 flex items-center gap-1.5">
              <span><?= $courseMasteryScore ?>%</span>
              <span class="text-[10px] font-semibold text-slate-400">(Mastery)</span>
            </div>
          </div>
          <div>
            <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">หัวข้อปัจจุบัน</div>
            <div class="text-sm sm:text-base font-black text-pink-600 mt-0.5 truncate">
              <?= htmlspecialchars($currentTopicName) ?>
            </div>
          </div>
        </div>

        <!-- ========================================== -->
        <!-- MAIN TABS (Section 4) -->
        <!-- ========================================== -->
        <div class="flex items-center justify-between gap-4 flex-wrap">
          <div class="p-1.5 rounded-2xl bg-slate-200/70 inline-flex gap-1 overflow-x-auto max-w-full">
            <a href="learning-path.php?course_id=<?= (int)$selectedCourseId ?>&tab=path"
               class="tab-pill <?= $activeTab === 'path' ? 'active' : '' ?>">
              เส้นทางของฉัน
            </a>
            <a href="learning-path.php?course_id=<?= (int)$selectedCourseId ?>&tab=weekly"
               class="tab-pill <?= $activeTab === 'weekly' ? 'active' : '' ?>">
              แผนสัปดาห์นี้
            </a>
            <a href="learning-path.php?course_id=<?= (int)$selectedCourseId ?>&tab=calendar"
               class="tab-pill <?= $activeTab === 'calendar' ? 'active' : '' ?>">
              ปฏิทิน
            </a>
          </div>

          <div class="text-xs text-slate-400 font-medium">
            <?= match($activeTab) {
              'path' => '✦ แสดงหัวข้อที่ต้องเรียนตามลำดับและแบบฝึกเสริม',
              'weekly' => '✦ ภารกิจและกิจกรรมที่ต้องทำในสัปดาห์นี้',
              'calendar' => '✦ กำหนดการ คาบเรียนสด และวันส่งงาน',
              default => ''
            } ?>
          </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB CONTENT GRID (Desktop: Left 2 cols, Right 1 col) -->
        <!-- ========================================== -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

          <!-- LEFT COLUMN: TAB CONTENT (2/3 width) -->
          <div class="lg:col-span-2 space-y-6">

            <?php if ($activeTab === 'path'): ?>
              <!-- ========================================== -->
              <!-- TAB 1: เส้นทางของฉัน (LEARNING PATH) -->
              <!-- ========================================== -->

              <!-- Next Action Card (Section 7, 8) -->
              <?php if ($nextAction): ?>
                <div class="p-6 rounded-3xl bg-gradient-to-br from-navy-950 via-slate-900 to-navy-950 text-white shadow-sm border border-slate-800 space-y-4 relative overflow-hidden">
                  <div class="flex items-start justify-between gap-4 relative z-10">
                    <div class="space-y-1">
                      <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-pink-500/20 text-pink-300 border border-pink-500/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-pink-400 animate-pulse"></span>
                        เรียนอะไรต่อ? (Next Action)
                      </span>
                      <h2 class="text-lg sm:text-xl font-black text-white pt-1">
                        <?= htmlspecialchars($nextAction['title']) ?>
                      </h2>
                      <p class="text-xs text-slate-300">
                        หัวข้อ: <strong class="text-pink-300"><?= htmlspecialchars($nextAction['topic'] ?: $selectedCourse['subject']) ?></strong> · ประมาณ 12–15 นาที
                      </p>
                    </div>

                    <a href="<?= htmlspecialchars(resolveStudentActionUrl($nextAction['action_url'])) ?>"
                       class="h-10 px-5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white text-xs font-bold inline-flex items-center gap-1.5 shadow-sm transition shrink-0">
                      <span><?= htmlspecialchars($nextAction['action_label'] ?: 'เริ่มเรียนต่อ') ?></span>
                      <span>&rarr;</span>
                    </a>
                  </div>
                </div>
              <?php endif; ?>

              <!-- Learning Path Nodes (Section 5, 6, 22) -->
              <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                  <div>
                    <h2 class="text-base font-black text-navy-950">ลำดับเส้นทางการเรียน (Learning Path)</h2>
                    <p class="text-xs text-slate-400 mt-0.5">เรียงลำดับหัวข้อและกิจกรรมเฉพาะบุคคล</p>
                  </div>
                  <span class="text-xs font-bold text-slate-400"><?= count($pathNodes) ?> ขั้นตอน</span>
                </div>

                <div class="space-y-0 pt-2">
                  <?php foreach ($pathNodes as $idx => $node): ?>
                    <?php
                      $isLast = $idx === count($pathNodes) - 1;
                      $isAdaptive = in_array($node['type'], ['adaptive_remediation', 'mastery_check'], true);
                      $cardBorder = match($node['status']) {
                        'COMPLETED' => 'border-emerald-100 bg-emerald-50/30',
                        'CURRENT' => 'border-pink-300 ring-2 ring-pink-500/10 bg-white',
                        'REVIEW_REQUIRED' => 'border-amber-200 bg-amber-50/30',
                        'REMEDIATION' => 'border-purple-200 bg-purple-50/30',
                        'AVAILABLE' => 'border-blue-100 bg-white',
                        default => 'border-slate-100 bg-slate-50/50'
                      };
                      if ($node['is_focused']) {
                        $cardBorder = 'border-pink-500 ring-4 ring-pink-500/20 bg-pink-50/30';
                      }
                    ?>
                    <!-- Node Item -->
                    <div class="flex items-start gap-4">
                      <!-- Node Indicator -->
                      <div class="flex flex-col items-center shrink-0">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold <?= match($node['status']) {
                          'COMPLETED' => 'bg-emerald-500 text-white',
                          'CURRENT' => 'bg-pink-500 text-white ring-4 ring-pink-200',
                          'REVIEW_REQUIRED' => 'bg-amber-500 text-white',
                          'REMEDIATION' => 'bg-purple-600 text-white',
                          'AVAILABLE' => 'bg-blue-500 text-white',
                          default => 'bg-slate-200 text-slate-500'
                        } ?>">
                          <?= match($node['status']) {
                            'COMPLETED' => '✓',
                            'CURRENT' => '▶',
                            'REVIEW_REQUIRED' => '!',
                            'REMEDIATION' => '✦',
                            default => $idx + 1
                          } ?>
                        </div>
                        <?php if (!$isLast): ?>
                          <div class="step-connector <?= $node['status'] === 'COMPLETED' ? 'completed' : ($isAdaptive ? 'adaptive' : '') ?>"></div>
                        <?php endif; ?>
                      </div>

                      <!-- Node Card -->
                      <div class="flex-1 p-4 rounded-2xl border <?= $cardBorder ?> space-y-2 mb-3">
                        <div class="flex items-start justify-between gap-3 flex-wrap">
                          <div class="space-y-0.5 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                              <h3 class="text-sm font-black text-navy-950 <?= $node['status'] === 'COMPLETED' ? 'text-slate-600' : '' ?>">
                                <?= htmlspecialchars($node['title']) ?>
                              </h3>
                              <?= renderPathStatusBadge($node['status']) ?>
                            </div>
                            <?php if (!empty($node['desc'])): ?>
                              <p class="text-xs text-slate-500"><?= htmlspecialchars($node['desc']) ?></p>
                            <?php endif; ?>
                          </div>

                          <div class="flex items-center gap-2 shrink-0">
                            <?php if ($node['mastery_score'] !== null): ?>
                              <span class="text-xs font-black <?= $node['mastery_score'] >= 80 ? 'text-emerald-600' : ($node['mastery_score'] < 65 ? 'text-rose-600' : 'text-amber-600') ?>">
                                ความเข้าใจ <?= $node['mastery_score'] ?>%
                              </span>
                            <?php endif; ?>

                            <?php if (!empty($node['action_url'])): ?>
                              <a href="<?= htmlspecialchars($node['action_url']) ?>"
                                 class="h-8 px-3 rounded-lg bg-pink-500 hover:bg-pink-600 text-white text-[11px] font-bold inline-flex items-center gap-1 shadow-2xs">
                                <span>เรียนต่อ</span> &rarr;
                              </a>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>

            <?php elseif ($activeTab === 'weekly'): ?>
              <!-- ========================================== -->
              <!-- TAB 2: แผนสัปดาห์นี้ (WEEKLY PLAN / STUDY ROADMAP DATA) -->
              <!-- ========================================== -->
              <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                  <div>
                    <h2 class="text-base font-black text-navy-950">แผนสัปดาห์นี้ (Weekly Study Plan)</h2>
                    <p class="text-xs text-slate-400 mt-0.5">กิจกรรมและภารกิจที่ต้องทำให้สำเร็จตามลำดับเวลา</p>
                  </div>
                  <span class="text-xs font-bold text-slate-400"><?= count($weeklyActivities) ?> ภารกิจ</span>
                </div>

                <?php if (empty($weeklyActivities)): ?>
                  <div class="py-12 text-center text-slate-400 space-y-2">
                    <span class="text-3xl">🎉</span>
                    <p class="text-sm font-bold text-navy-950">สัปดาห์นี้ยังไม่มีกิจกรรมที่ต้องทำ 🎉</p>
                    <p class="text-xs text-slate-400">คุณทำภารกิจครบถ้วนแล้ว หรือยังไม่มีภารกิจใหม่ในสัปดาห์นี้</p>
                  </div>
                <?php else: ?>
                  <!-- Group activities by day_group -->
                  <?php
                    $grouped = [];
                    foreach ($weeklyActivities as $act) {
                        $grouped[$act['day_group']][] = $act;
                    }
                  ?>

                  <div class="space-y-6">
                    <?php foreach ($grouped as $dayLabel => $items): ?>
                      <div class="space-y-3">
                        <div class="flex items-center gap-2">
                          <span class="w-2 h-2 rounded-full bg-pink-500"></span>
                          <h3 class="text-xs font-black uppercase tracking-wider text-slate-600"><?= htmlspecialchars($dayLabel) ?></h3>
                        </div>

                        <div class="space-y-2.5">
                          <?php foreach ($items as $item): ?>
                            <div class="p-4 rounded-2xl border <?= $item['is_done'] ? 'border-slate-100 bg-slate-50/50' : 'border-slate-200 hover:border-slate-300 bg-white' ?> transition flex items-center justify-between gap-4"
                                 id="task-row-<?= (int)$item['id'] ?>">
                              <div class="flex items-center gap-3.5 min-w-0">
                                <?php if ($item['is_manual']): ?>
                                  <!-- Interactive Checkbox calling roadmap-api.php -->
                                  <input type="checkbox"
                                         aria-label="ทำภารกิจสำเร็จ"
                                         class="w-5 h-5 rounded-md text-pink-500 border-slate-300 focus:ring-pink-500 cursor-pointer shrink-0"
                                         <?= $item['is_done'] ? 'checked' : '' ?>
                                         onchange="toggleWeeklyTask(<?= (int)$item['id'] ?>, this.checked)">
                                <?php else: ?>
                                  <span class="w-5 h-5 rounded-md flex items-center justify-center text-xs font-bold <?= $item['is_done'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' ?> shrink-0">
                                    <?= $item['is_done'] ? '✓' : '•' ?>
                                  </span>
                                <?php endif; ?>

                                <div class="min-w-0 space-y-1">
                                  <div class="flex items-center gap-2 flex-wrap">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700">
                                      <?= htmlspecialchars($item['type_label']) ?>
                                    </span>
                                    <span class="text-xs font-bold <?= $item['is_done'] ? 'line-through text-slate-400' : 'text-navy-950' ?> truncate">
                                      <?= htmlspecialchars($item['title']) ?>
                                    </span>
                                  </div>
                                  <div class="text-[11px] text-slate-400 flex items-center gap-3">
                                    <span><?= htmlspecialchars($item['category']) ?></span>
                                    <?php if ($item['points'] > 0): ?>
                                      <span class="text-amber-600 font-bold">+<?= $item['points'] ?> NC Points</span>
                                    <?php endif; ?>
                                  </div>
                                </div>
                              </div>

                              <div class="shrink-0 flex items-center gap-2">
                                <?php if ($item['is_done']): ?>
                                  <span class="text-xs font-bold text-emerald-600">เสร็จแล้ว</span>
                                <?php else: ?>
                                  <a href="<?= htmlspecialchars(resolveStudentActionUrl($item['action_url'])) ?>"
                                     class="h-8 px-3.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-navy-950 text-xs font-bold inline-flex items-center gap-1 transition">
                                    <span>เริ่มทำ</span> &rarr;
                                  </a>
                                <?php endif; ?>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

            <?php elseif ($activeTab === 'calendar'): ?>
              <!-- ========================================== -->
              <!-- TAB 3: ปฏิทิน (CALENDAR — WHEN TO DO IT) -->
              <!-- ========================================== -->
              <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 flex-wrap gap-2">
                  <div>
                    <h2 class="text-base font-black text-navy-950">ปฏิทินการเรียน (Learning Calendar)</h2>
                    <p class="text-xs text-slate-400 mt-0.5">ตารางเรียนสด วันสอบ และกำหนดส่งงาน</p>
                  </div>

                  <!-- Calendar View Mode Buttons (Section 18) -->
                  <div class="inline-flex rounded-xl bg-slate-100 p-1 text-xs font-bold text-slate-600">
                    <button type="button" class="px-3 py-1 rounded-lg bg-white shadow-2xs text-navy-950">กำหนดการ (Agenda)</button>
                    <button type="button" class="px-3 py-1 rounded-lg hover:text-navy-950" onclick="alert('แสดงกำหนดการทั้งหมดตามลำดับเวลา')">สัปดาห์นี้</button>
                    <button type="button" class="px-3 py-1 rounded-lg hover:text-navy-950" onclick="alert('แสดงกำหนดการทั้งหมดตามลำดับเวลา')">เดือนนี้</button>
                  </div>
                </div>

                <?php if (empty($calendarEvents)): ?>
                  <div class="py-12 text-center text-slate-400 space-y-2">
                    <span class="text-3xl">📅</span>
                    <p class="text-sm font-bold text-navy-950">ไม่มีกิจกรรมตามกำหนดการในช่วงนี้</p>
                    <p class="text-xs text-slate-400">เมื่อมีคาบเรียนสดหรือการนัดหมาย ระบบจะแจ้งเตือนในปฏิทินนี้</p>
                  </div>
                <?php else: ?>
                  <div class="space-y-3">
                    <?php foreach ($calendarEvents as $ev): ?>
                      <?php
                        $evDate = date('d/m/Y', strtotime($ev['event_date']));
                        $evTime = date('H:i', strtotime($ev['start_time'])) . ' - ' . date('H:i', strtotime($ev['end_time']));
                        $isToday = $ev['event_date'] === date('Y-m-d');
                      ?>
                      <div class="p-4 rounded-2xl border <?= $isToday ? 'border-pink-300 bg-pink-50/30' : 'border-slate-100 bg-slate-50/40' ?> flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3.5 min-w-0">
                          <div class="w-12 h-12 rounded-xl bg-white border border-slate-200 flex flex-col items-center justify-center shrink-0">
                            <span class="text-[9px] font-black uppercase text-slate-400"><?= date('M', strtotime($ev['event_date'])) ?></span>
                            <span class="text-sm font-black text-navy-950"><?= date('d', strtotime($ev['event_date'])) ?></span>
                          </div>
                          <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                              <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-pink-100 text-pink-700">
                                <?= match($ev['event_type']) {
                                  'lesson' => 'คาบเรียน',
                                  'exam' => 'การสอบ',
                                  'meeting' => 'ครูดูแลพิเศษ',
                                  default => 'กิจกรรม'
                                } ?>
                              </span>
                              <strong class="text-xs text-navy-950 truncate"><?= htmlspecialchars($ev['title']) ?></strong>
                            </div>
                            <div class="text-[11px] text-slate-400 mt-1">
                              เวลา <?= $evTime ?> น. · <?= htmlspecialchars($ev['location'] ?: 'ห้องเรียนสดออนไลน์') ?>
                            </div>
                          </div>
                        </div>

                        <div class="shrink-0">
                          <a href="live-session.php" class="h-8 px-3.5 rounded-lg bg-pink-500 hover:bg-pink-600 text-white text-xs font-bold inline-flex items-center gap-1 transition">
                            <span>เข้าห้องเรียน</span> &rarr;
                          </a>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

            <?php endif; ?>

          </div>

          <!-- ========================================== -->
          <!-- RIGHT COLUMN: DESKTOP SIDEBAR (Sections 20, 27, 28) -->
          <!-- (Stacked on Mobile naturally via CSS grid) -->
          <!-- ========================================== -->
          <div class="space-y-6">

            <!-- 1. เป้าหมายของฉัน (Personal Goal Card — Section 27) -->
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-3">
              <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <span class="text-xs font-black text-navy-950 flex items-center gap-1.5">
                  <span>🎯</span>
                  <span>เป้าหมายของฉัน</span>
                </span>
                <span class="px-2 py-0.5 rounded-full bg-pink-50 text-pink-700 text-[10px] font-black border border-pink-200">
                  Phase 1 Goal
                </span>
              </div>

              <div class="space-y-2">
                <h3 class="text-base font-black text-navy-950">
                  <?= htmlspecialchars($activeGoal['goal_name'] ?? 'สอบเข้ามหาวิทยาลัยในฝัน') ?>
                </h3>
                <?php if ($activeGoal && $activeGoal['target_score'] !== null): ?>
                  <div class="flex items-baseline gap-1 text-xs text-slate-500">
                    <span>คะแนนเป้าหมาย:</span>
                    <strong class="text-pink-600 text-sm"><?= (float)$activeGoal['target_score'] ?></strong>
                    <span>คะแนน</span>
                  </div>
                <?php endif; ?>
                <div class="text-[11px] text-slate-400 pt-1 border-t border-slate-100">
                  ภายใน: <strong class="text-slate-600"><?= htmlspecialchars($activeGoal['target_date'] ? date('d/m/Y', strtotime($activeGoal['target_date'])) : 'ปีการศึกษา 2570') ?></strong>
                </div>
              </div>
            </div>

            <!-- 2. ภาพรวมทักษะปัจจุบัน (Compact Skill Summary — Section 20) -->
            <!-- RULE: Do NOT place the full Skill Map inside Learning Path -->
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
              <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                  <h3 class="text-xs font-black text-navy-950 flex items-center gap-1.5">
                    <span>📊</span>
                    <span>ภาพรวมทักษะปัจจุบัน</span>
                  </h3>
                  <p class="text-[11px] text-slate-400 mt-0.5">ระดับความเข้าใจเฉลี่ย</p>
                </div>
                <span class="text-xs font-black text-pink-600"><?= $courseMasteryScore ?>%</span>
              </div>

              <?php if (empty($skillSummary)): ?>
                <div class="py-6 text-center text-slate-400 text-xs">
                  ยังไม่มีข้อมูลทักษะที่บันทึก
                </div>
              <?php else: ?>
                <div class="space-y-3">
                  <?php foreach ($skillSummary as $sk): ?>
                    <?php $sc = round((float)$sk['mastery_score'], 1); ?>
                    <div class="space-y-1">
                      <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-navy-950 truncate"><?= htmlspecialchars($sk['topic_name']) ?></span>
                        <span class="font-black <?= $sc >= 80 ? 'text-emerald-600' : ($sc < 65 ? 'text-rose-600' : 'text-amber-600') ?>"><?= $sc ?>%</span>
                      </div>
                      <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full <?= $sc >= 80 ? 'bg-emerald-500' : ($sc < 65 ? 'bg-rose-500' : 'bg-amber-500') ?>"
                             style="width: <?= min(100, $sc) ?>%"></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <!-- Direct Link to Full Skill Map (Section 20, 46) -->
              <div class="pt-2 border-t border-slate-100 text-center">
                <a href="skill-map.php?course_id=<?= (int)$selectedCourseId ?>"
                   class="inline-flex items-center gap-1.5 text-xs font-bold text-pink-600 hover:text-pink-700">
                  <span>ดูแผนที่ทักษะแบบเต็ม</span>
                  <span>&rarr;</span>
                </a>
              </div>
            </div>

            <!-- 3. กิจกรรมสำคัญถัดไป (Next Important Event) -->
            <div class="bg-gradient-to-br from-indigo-950 to-navy-950 rounded-3xl p-6 text-white shadow-sm space-y-3">
              <span class="text-[10px] font-black uppercase tracking-wider text-pink-400">UPCOMING EVENT</span>
              <h3 class="text-sm font-black text-white">ห้องเรียนสด & การวัดผล</h3>
              <p class="text-xs text-slate-300 leading-relaxed">
                เข้าเรียนสดและทำแบบฝึกหัดตรงเวลา เพื่อสะสมหลักฐานความเชี่ยวชาญเข้าสู่ Skill Map ของคุณ
              </p>
              <a href="live-session.php" class="h-9 px-4 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold inline-flex items-center gap-1 transition">
                <span>ตรวจสอบห้องเรียนสด</span> &rarr;
              </a>
            </div>

          </div>

        </div>

      <?php endif; ?>

    </main>
    <?php include 'includes/bottom-nav.php'; ?>
  </div>
</div>

<script>
// Toggle task completion in Weekly Plan tab calling student/roadmap-api.php
async function toggleWeeklyTask(taskId, isCompleted) {
  try {
    const res = await fetch('roadmap-api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'toggle',
        task_id: taskId,
        is_completed: isCompleted
      })
    });
    const data = await res.json();
    if (data.success) {
      const row = document.getElementById('task-row-' + taskId);
      if (row) {
        if (isCompleted) {
          row.classList.add('bg-slate-50/50');
        } else {
          row.classList.remove('bg-slate-50/50');
        }
      }
    } else {
      alert(data.message || 'ไม่สามารถอัปเดตภารกิจได้');
      location.reload();
    }
  } catch (err) {
    console.error('Error toggling task:', err);
    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง');
  }
}
</script>
</body>
</html>
