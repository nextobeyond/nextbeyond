<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/ai-settings.php';
require_once __DIR__ . '/../includes/phase4-adaptive-service.php';

function jsonRespond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function parseInput(): array {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

// Ensure database tables exist
function ensureWorksheetTables(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) return;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS worksheet_folders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            creator_id INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS worksheets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            subject VARCHAR(100) NOT NULL,
            level VARCHAR(50) NOT NULL,
            chapter VARCHAR(150) NULL,
            topic VARCHAR(150) NULL,
            subtopic VARCHAR(150) NULL,
            worksheet_type VARCHAR(50) NOT NULL DEFAULT 'Worksheet',
            difficulty VARCHAR(50) NOT NULL DEFAULT 'medium',
            question_count INT NOT NULL DEFAULT 0,
            generation_source ENUM('ai', 'manual') NOT NULL DEFAULT 'ai',
            creator_id INT NULL,
            creator_name VARCHAR(150) NOT NULL DEFAULT 'Admin',
            tags TEXT NULL,
            folder_id INT NULL,
            linked_course_ids TEXT NULL,
            linked_ep_ids TEXT NULL,
            usage_count INT NOT NULL DEFAULT 0,
            status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'published',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_subject (subject),
            INDEX idx_level (level),
            INDEX idx_status (status),
            INDEX idx_folder (folder_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS worksheet_questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            worksheet_id INT NOT NULL,
            sort_order INT NOT NULL DEFAULT 1,
            question_type VARCHAR(50) NOT NULL DEFAULT 'multipleChoice',
            question_text MEDIUMTEXT NOT NULL,
            options MEDIUMTEXT NULL,
            correct_answer MEDIUMTEXT NULL,
            explanation MEDIUMTEXT NULL,
            hint TEXT NULL,
            skill VARCHAR(100) NULL,
            difficulty VARCHAR(50) NULL,
            learning_objective TEXT NULL,
            image_url VARCHAR(500) NULL,
            points INT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_worksheet (worksheet_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS worksheet_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            worksheet_id INT NOT NULL,
            course_id INT NULL,
            ep_id INT NULL,
            class_name VARCHAR(150) NULL,
            target_type ENUM('all', 'selected') NOT NULL DEFAULT 'all',
            student_ids TEXT NULL,
            due_date DATETIME NULL,
            assigned_by INT NULL,
            assigned_by_name VARCHAR(150) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ws (worksheet_id),
            INDEX idx_course (course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Seed default folders and mock worksheets if empty
    $count = (int) $pdo->query("SELECT COUNT(*) FROM worksheets")->fetchColumn();
    if ($count === 0) {
        seedWorksheetData($pdo);
    }

    $ensured = true;
}

function seedWorksheetData(PDO $pdo): void {
    // Seed initial folders
    $folders = ['A-Level Chemistry', 'M.1 Entrance', 'English Grammar', 'Junior Math', 'My Worksheets'];
    $folderMap = [];
    foreach ($folders as $f) {
        $stmt = $pdo->prepare("INSERT INTO worksheet_folders (name) VALUES (?)");
        $stmt->execute([$f]);
        $folderMap[$f] = (int) $pdo->lastInsertId();
    }

    // Seed realistic sample worksheets
    $samples = [
        [
            'title' => 'Passive Voice Practice #01',
            'description' => 'แบบฝึกหัดทบทวนการเปลี่ยนประโยค Active เป็น Passive Voice พร้อมข้อยกเว้นสำคัญ',
            'subject' => 'ภาษาอังกฤษ',
            'level' => 'ม.4',
            'chapter' => 'English Grammar Mastery',
            'topic' => 'Passive Voice',
            'subtopic' => 'Present & Past Passive with by-agent',
            'worksheet_type' => 'Practice',
            'difficulty' => 'medium',
            'question_count' => 5,
            'generation_source' => 'ai',
            'creator_name' => 'Kru Base',
            'tags' => json_encode(['Grammar', 'Passive Voice', 'M.4', 'Foundation'], JSON_UNESCAPED_UNICODE),
            'folder_id' => $folderMap['English Grammar'] ?? null,
            'usage_count' => 3,
            'status' => 'published',
            'questions' => [
                [
                    'sort_order' => 1,
                    'question_type' => 'multipleChoice',
                    'question_text' => 'Choose the correct passive form: "The chef prepares the special meal every Sunday evening."',
                    'options' => json_encode([
                        'The special meal is prepared by the chef every Sunday evening.',
                        'The special meal was prepared by the chef every Sunday evening.',
                        'The special meal has been prepared by the chef every Sunday evening.',
                        'The special meal will be prepared by the chef every Sunday evening.'
                    ], JSON_UNESCAPED_UNICODE),
                    'correct_answer' => 'The special meal is prepared by the chef every Sunday evening.',
                    'explanation' => 'โจทย์เป็น Present Simple Tense ("prepares") ดังนั้นรูป Passive Voice คือ is/am/are + V.3 -> "is prepared"',
                    'skill' => 'Grammar Identification',
                    'difficulty' => 'medium',
                    'learning_objective' => 'เข้าใจโครงสร้าง Present Simple Passive Voice'
                ],
                [
                    'sort_order' => 2,
                    'question_type' => 'multipleChoice',
                    'question_text' => 'Identify the sentence with INCORRECT passive construction:',
                    'options' => json_encode([
                        'The building was designed by a famous architect.',
                        'The homework must be submit by 5 PM.',
                        'English is spoken in many countries worldwide.',
                        'The parcels have been delivered to the front desk.'
                    ], JSON_UNESCAPED_UNICODE),
                    'correct_answer' => 'The homework must be submit by 5 PM.',
                    'explanation' => 'หลัง modal auxiliary verb "must be" จะต้องตามด้วย Past Participle (V.3) คือ "submitted" ไม่ใช่ "submit"',
                    'skill' => 'Error Detection',
                    'difficulty' => 'medium',
                    'learning_objective' => 'ตรวจจับโครงสร้าง Modal Passive ที่ผิดรูป'
                ],
                [
                    'sort_order' => 3,
                    'question_type' => 'shortAnswer',
                    'question_text' => 'Rewrite into passive voice: "They will announce the test results tomorrow morning."',
                    'options' => null,
                    'correct_answer' => 'The test results will be announced tomorrow morning.',
                    'explanation' => 'Future Simple "will announce" เปลี่ยนเป็น "will be announced" โดยนำกรรม ("The test results") ขึ้นต้นเป็นประธาน',
                    'skill' => 'Sentence Transformation',
                    'difficulty' => 'easy',
                    'learning_objective' => 'ฝึกเขียนประโยค Passive Voice รูป Future Simple'
                ],
                [
                    'sort_order' => 4,
                    'question_type' => 'trueFalse',
                    'question_text' => 'True or False: Intransitive verbs (such as "happen", "arrive", "sleep") can be converted into passive voice.',
                    'options' => json_encode(['True', 'False'], JSON_UNESCAPED_UNICODE),
                    'correct_answer' => 'False',
                    'explanation' => 'อกรรมกริยา (Intransitive Verbs) ที่ไม่มีกรรมมารับ ไม่สามารถทำเป็น Passive Voice ได้',
                    'skill' => 'Grammar Rules',
                    'difficulty' => 'hard',
                    'learning_objective' => 'เข้าใจข้อจำกัดของ Intransitive Verbs ในโครงสร้าง Passive'
                ],
                [
                    'sort_order' => 5,
                    'question_type' => 'multipleChoice',
                    'question_text' => '"The Mona Lisa ________ by Leonardo da Vinci in the early 16th century."',
                    'options' => json_encode([
                        'was painted',
                        'is painted',
                        'had painted',
                        'painted'
                    ], JSON_UNESCAPED_UNICODE),
                    'correct_answer' => 'was painted',
                    'explanation' => 'มีบริบทเวลาในอดีตชัดเจน ("in the early 16th century") และประธานถูกกระทำ จึงใช้ Past Simple Passive: was + V.3',
                    'skill' => 'Verb Conjugation',
                    'difficulty' => 'easy',
                    'learning_objective' => 'เลือกใช้ Past Simple Passive ตาม Time Marker'
                ]
            ]
        ],
        [
            'title' => 'Acid-Base Calculation Drill',
            'description' => 'เจาะลึกการคำนวณค่า pH, pOH, Ka, Kb และปฏิกิริยาสะเทินของกรด-เบส',
            'subject' => 'เคมี',
            'level' => 'ม.5',
            'chapter' => 'กรด-เบส',
            'topic' => 'การคำนวณ pH และปฏิกิริยาสะเทิน',
            'subtopic' => 'Buffer & Titration',
            'worksheet_type' => 'Practice',
            'difficulty' => 'hard',
            'question_count' => 4,
            'generation_source' => 'ai',
            'creator_name' => 'AI System',
            'tags' => json_encode(['Chemistry', 'Acid-Base', 'pH', 'ม.5', 'A-Level'], JSON_UNESCAPED_UNICODE),
            'folder_id' => $folderMap['A-Level Chemistry'] ?? null,
            'usage_count' => 1,
            'status' => 'published',
            'questions' => [
                [
                    'sort_order' => 1,
                    'question_type' => 'multipleChoice',
                    'question_text' => 'สารละลายกรดแก่ HCl เข้มข้น 0.001 mol/dm³ มีค่า pH เท่ากับเท่าใด?',
                    'options' => json_encode(['1', '2', '3', '4'], JSON_UNESCAPED_UNICODE),
                    'correct_answer' => '3',
                    'explanation' => 'HCl เป็นกรดแก่แตกตัว 100% ได้ [H+] = 0.001 = 10^-3 M -> pH = -log[H+] = 3',
                    'skill' => 'pH Calculation',
                    'difficulty' => 'easy',
                    'learning_objective' => 'คำนวณ pH จากความเข้มข้นกรดแก่'
                ],
                [
                    'sort_order' => 2,
                    'question_type' => 'multipleChoice',
                    'question_text' => 'สารละลายเบสอ่อน BOH เข้มข้น 0.1 M มีค่า Kb = 1.0 x 10^-5 จงหาความเข้มข้นของ [OH-] และค่า pOH ตามลำดับ',
                    'options' => json_encode([
                        '[OH-] = 1.0 x 10^-3 M, pOH = 3',
                        '[OH-] = 1.0 x 10^-2 M, pOH = 2',
                        '[OH-] = 1.0 x 10^-4 M, pOH = 4',
                        '[OH-] = 1.0 x 10^-5 M, pOH = 5'
                    ], JSON_UNESCAPED_UNICODE),
                    'correct_answer' => '[OH-] = 1.0 x 10^-3 M, pOH = 3',
                    'explanation' => '[OH-] = sqrt(Kb * C) = sqrt(10^-5 * 0.1) = sqrt(10^-6) = 1.0 x 10^-3 M -> pOH = -log(10^-3) = 3',
                    'skill' => 'Weak Base Equilibrium',
                    'difficulty' => 'medium',
                    'learning_objective' => 'คำนวณสมดุลเบสอ่อนและค่า pOH'
                ],
                [
                    'sort_order' => 3,
                    'question_type' => 'shortAnswer',
                    'question_text' => 'สารละลายบัฟเฟอร์เตรียมจากกรด CH3COOH 0.2 M ปริมาตร 500 cm³ ผสมกับ CH3COONa 0.2 M ปริมาตร 500 cm³ (Ka = 1.8 x 10^-5) จะมีค่า pH ประมาณเท่าใด?',
                    'options' => null,
                    'correct_answer' => '4.74',
                    'explanation' => 'เมื่อ [กรด] = [เกลือ] ตามสมการ Henderson-Hasselbalch: pH = pKa + log([เกลือ]/[กรด]) = pKa = -log(1.8 x 10^-5) = 4.74',
                    'skill' => 'Buffer Solutions',
                    'difficulty' => 'hard',
                    'learning_objective' => 'ประยุกต์ใช้สมการ Henderson-Hasselbalch ในระบบบัฟเฟอร์'
                ],
                [
                    'sort_order' => 4,
                    'question_type' => 'multipleChoice',
                    'question_text' => 'หากหยดอินดิเคเตอร์ฟีนอล์ฟทาลีน (ช่วงเปลี่ยนสี 8.3 - 10.0 ไม่มีสี -> ชมพู) ลงในการไทเทรตระหว่าง HCl กับ NaOH จุดยุติจะมีลักษณะสีอย่างไร?',
                    'options' => json_encode([
                        'เปลี่ยนจากไม่มีสีเป็นสีชมพูระเรื่อ',
                        'เปลี่ยนจากสีชมพูเป็นไม่มีสี',
                        'เปลี่ยนเป็นสีเหลืองเข้ม',
                        'ไม่มีการเปลี่ยนสีตลอดการทดลอง'
                    ], JSON_UNESCAPED_UNICODE),
                    'correct_answer' => 'เปลี่ยนจากไม่มีสีเป็นสีชมพูระเรื่อ',
                    'explanation' => 'เมื่อไทเทรตด้วย NaOH ในบิวเรตต์ถึงจุดสมมูล pH จะเข้าสู่ช่วง 7-9 ซึ่งฟีนอล์ฟทาลีนจะเริ่มปรากฏสีชมพูจางๆ',
                    'skill' => 'Titration & Indicators',
                    'difficulty' => 'medium',
                    'learning_objective' => 'เลือกและสังเกตอินดิเคเตอร์ในปฏิกิริยาไทเทรต'
                ]
            ]
        ],
        [
            'title' => 'Quadratic Equation Practice',
            'description' => 'ชุดแบบฝึกหัดการแก้สมการกำลังสองตัวแปรเดียว การแยกตัวประกอบ และการใช้สูตร -b ± √(b² - 4ac) / 2a',
            'subject' => 'คณิตศาสตร์',
            'level' => 'ม.3',
            'chapter' => 'พีชคณิตพื้นฐาน',
            'topic' => 'สมการกำลังสองตัวแปรเดียว',
            'subtopic' => 'การหาคำตอบด้วยการแยกตัวประกอบและสูตร',
            'worksheet_type' => 'Homework',
            'difficulty' => 'medium',
            'question_count' => 4,
            'generation_source' => 'manual',
            'creator_name' => 'Kru Somchai',
            'tags' => json_encode(['Math', 'Algebra', 'Quadratic', 'ม.3', 'ONET'], JSON_UNESCAPED_UNICODE),
            'folder_id' => $folderMap['Junior Math'] ?? null,
            'usage_count' => 2,
            'status' => 'published',
            'questions' => [
                [
                    'sort_order' => 1,
                    'question_type' => 'multipleChoice',
                    'question_text' => 'คำตอบของสมการ x² - 7x + 12 = 0 คือค่าใด?',
                    'options' => json_encode(['x = 3 หรือ x = 4', 'x = -3 หรือ x = -4', 'x = 2 หรือ x = 6', 'x = -2 หรือ x = -6'], JSON_UNESCAPED_UNICODE),
                    'correct_answer' => 'x = 3 หรือ x = 4',
                    'explanation' => 'แยกตัวประกอบได้ (x - 3)(x - 4) = 0 ดังนั้น x = 3 หรือ x = 4',
                    'skill' => 'Factoring',
                    'difficulty' => 'easy',
                    'learning_objective' => 'แยกตัวประกอบพหุนามดีกรีสอง'
                ],
                [
                    'sort_order' => 2,
                    'question_type' => 'multipleChoice',
                    'question_text' => 'ค่า discriminant (b² - 4ac) ของสมการ 2x² + 4x + 2 = 0 มีค่าเท่าใด และบอกลักษณะคำตอบได้อย่างไร?',
                    'options' => json_encode([
                        '0 (มีคำตอบเดียวที่เป็นจำนวนจริง)',
                        '16 (มีสองคำตอบที่แตกต่างกัน)',
                        '-8 (ไม่มีคำตอบที่เป็นจำนวนจริง)',
                        '4 (มีคำตอบเป็นตรรกยะสองค่า)'
                    ], JSON_UNESCAPED_UNICODE),
                    'correct_answer' => '0 (มีคำตอบเดียวที่เป็นจำนวนจริง)',
                    'explanation' => 'b² - 4ac = 4² - 4(2)(2) = 16 - 16 = 0 หมายความว่าสมการมีคำตอบที่เป็นจำนวนจริงเพียง 1 คำตอบ (รากซ้ำ)',
                    'skill' => 'Discriminant Analysis',
                    'difficulty' => 'medium',
                    'learning_objective' => 'วิเคราะห์จำนวนคำตอบของสมการกำลังสอง'
                ],
                [
                    'sort_order' => 3,
                    'question_type' => 'shortAnswer',
                    'question_text' => 'ผลบวกและผลคูณของคำตอบของสมการ 3x² - 9x + 6 = 0 มีค่าเท่ากับเท่าใดตามลำดับ?',
                    'options' => null,
                    'correct_answer' => 'ผลบวก = 3, ผลคูณ = 2',
                    'explanation' => 'ผลบวกคำตอบ = -b/a = -(-9)/3 = 3, ผลคูณคำตอบ = c/a = 6/3 = 2',
                    'skill' => 'Vieta Formula',
                    'difficulty' => 'medium',
                    'learning_objective' => 'ใช้คุณสมบัติผลบวกและผลคูณคำตอบ'
                ],
                [
                    'sort_order' => 4,
                    'question_type' => 'trueFalse',
                    'question_text' => 'True or False: สมการ x² + 25 = 0 มีคำตอบเป็นจำนวนจริง',
                    'options' => json_encode(['True', 'False'], JSON_UNESCAPED_UNICODE),
                    'correct_answer' => 'False',
                    'explanation' => 'x² = -25 ไม่มีจำนวนจริงใดที่ยกกำลังสองแล้วได้ค่าลบ คำตอบจึงเป็นจำนวนเชิงซ้อน (±5i)',
                    'skill' => 'Number System',
                    'difficulty' => 'easy',
                    'learning_objective' => 'จำแนกระบบจำนวนของคำตอบสมการ'
                ]
            ]
        ]
    ];

    foreach ($samples as $s) {
        $stmt = $pdo->prepare("
            INSERT INTO worksheets (
                title, description, subject, level, chapter, topic, subtopic,
                worksheet_type, difficulty, question_count, generation_source,
                creator_name, tags, folder_id, usage_count, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $s['title'], $s['description'], $s['subject'], $s['level'],
            $s['chapter'], $s['topic'], $s['subtopic'], $s['worksheet_type'],
            $s['difficulty'], count($s['questions']), $s['generation_source'],
            $s['creator_name'], $s['tags'], $s['folder_id'], $s['usage_count'],
            $s['status']
        ]);
        $wsId = (int) $pdo->lastInsertId();

        foreach ($s['questions'] as $q) {
            $qStmt = $pdo->prepare("
                INSERT INTO worksheet_questions (
                    worksheet_id, sort_order, question_type, question_text,
                    options, correct_answer, explanation, skill, difficulty, learning_objective
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $qStmt->execute([
                $wsId, $q['sort_order'], $q['question_type'], $q['question_text'],
                $q['options'], $q['correct_answer'], $q['explanation'],
                $q['skill'], $q['difficulty'], $q['learning_objective']
            ]);
        }

        // Seed an assignment for history demo
        if ($wsId === 1) {
            $pdo->prepare("
                INSERT INTO worksheet_assignments (worksheet_id, course_id, class_name, target_type, due_date, assigned_by_name)
                VALUES (?, 1, 'English M.4 ห้อง 401', 'all', DATE_ADD(NOW(), INTERVAL 7 DAY), 'Kru Base')
            ")->execute([$wsId]);
        }
    }
}

try {
    ensureWorksheetTables($pdo);

    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // ─────────────────────────────────────────────────────────────
    // 1. LIST WORKSHEETS
    // ─────────────────────────────────────────────────────────────
    if ($method === 'GET' && ($action === 'list' || $action === '')) {
        $search = trim((string) ($_GET['q'] ?? ''));
        $subject = trim((string) ($_GET['subject'] ?? ''));
        $level = trim((string) ($_GET['level'] ?? ''));
        $type = trim((string) ($_GET['type'] ?? ''));
        $difficulty = trim((string) ($_GET['difficulty'] ?? ''));
        $source = trim((string) ($_GET['source'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $folderId = isset($_GET['folder_id']) && $_GET['folder_id'] !== '' ? (int) $_GET['folder_id'] : null;

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = "(w.title LIKE :s OR w.subject LIKE :s OR w.chapter LIKE :s OR w.topic LIKE :s OR w.subtopic LIKE :s OR w.description LIKE :s OR w.creator_name LIKE :s OR w.tags LIKE :s)";
            $params[':s'] = "%{$search}%";
        }
        if ($subject !== '') {
            $where[] = "w.subject = :subject";
            $params[':subject'] = $subject;
        }
        if ($level !== '') {
            $where[] = "w.level = :level";
            $params[':level'] = $level;
        }
        if ($type !== '') {
            $where[] = "w.worksheet_type = :type";
            $params[':type'] = $type;
        }
        if ($difficulty !== '') {
            $where[] = "w.difficulty = :difficulty";
            $params[':difficulty'] = $difficulty;
        }
        if ($source !== '' && in_array($source, ['ai', 'manual'], true)) {
            $where[] = "w.generation_source = :source";
            $params[':source'] = $source;
        }
        if ($status !== '') {
            $where[] = "w.status = :status";
            $params[':status'] = $status;
        } else {
            // By default don't hide archived unless specifically asked
        }
        if ($folderId !== null) {
            $where[] = "w.folder_id = :folder_id";
            $params[':folder_id'] = $folderId;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT w.*, f.name AS folder_name
            FROM worksheets w
            LEFT JOIN worksheet_folders f ON f.id = w.folder_id
            {$whereSql}
            ORDER BY w.updated_at DESC, w.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $worksheets = $stmt->fetchAll();

        foreach ($worksheets as &$ws) {
            $ws['tags'] = $ws['tags'] ? json_decode($ws['tags'], true) : [];
            $ws['linked_course_ids'] = $ws['linked_course_ids'] ? json_decode($ws['linked_course_ids'], true) : [];
            $ws['linked_ep_ids'] = $ws['linked_ep_ids'] ? json_decode($ws['linked_ep_ids'], true) : [];
        }
        unset($ws);

        // Also fetch folders list for fast client filtering
        $folders = $pdo->query("SELECT id, name FROM worksheet_folders ORDER BY name ASC")->fetchAll();

        jsonRespond([
            'worksheets' => $worksheets,
            'folders' => $folders,
            'total' => count($worksheets)
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 2. GET SINGLE WORKSHEET DETAIL
    // ─────────────────────────────────────────────────────────────
    if ($method === 'GET' && $action === 'get') {
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) jsonRespond(['error' => 'ไม่พบรหัสใบงาน'], 400);

        $stmt = $pdo->prepare("
            SELECT w.*, f.name AS folder_name
            FROM worksheets w
            LEFT JOIN worksheet_folders f ON f.id = w.folder_id
            WHERE w.id = ?
        ");
        $stmt->execute([$id]);
        $worksheet = $stmt->fetch();
        if (!$worksheet) jsonRespond(['error' => 'ไม่พบข้อมูลใบงานนี้'], 404);

        $worksheet['tags'] = $worksheet['tags'] ? json_decode($worksheet['tags'], true) : [];

        // Fetch questions
        $qStmt = $pdo->prepare("
            SELECT * FROM worksheet_questions
            WHERE worksheet_id = ?
            ORDER BY sort_order ASC, id ASC
        ");
        $qStmt->execute([$id]);
        $questions = $qStmt->fetchAll();

        foreach ($questions as &$q) {
            if ($q['options']) {
                $q['options'] = json_decode($q['options'], true);
            }
        }
        unset($q);

        $worksheet['questions'] = $questions;

        // Fetch usage history & assignments
        $assignStmt = $pdo->prepare("
            SELECT a.*, c.title AS course_title
            FROM worksheet_assignments a
            LEFT JOIN courses c ON c.id = a.course_id
            WHERE a.worksheet_id = ?
            ORDER BY a.created_at DESC
        ");
        $assignStmt->execute([$id]);
        $worksheet['assignments'] = $assignStmt->fetchAll();

        jsonRespond(['worksheet' => $worksheet]);
    }

    // ─────────────────────────────────────────────────────────────
    // 3. CREATE WORKSHEET (MANUAL OR AFTER AI REVIEW)
    // ─────────────────────────────────────────────────────────────
    if ($method === 'POST' && ($action === 'create' || $action === '')) {
        $data = parseInput();
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') jsonRespond(['error' => 'กรุณาระบุชื่อใบงาน'], 422);

        $subject = trim((string) ($data['subject'] ?? 'คณิตศาสตร์'));
        $level = trim((string) ($data['level'] ?? 'ม.1'));
        $chapter = trim((string) ($data['chapter'] ?? ''));
        $topic = trim((string) ($data['topic'] ?? ''));
        $subtopic = trim((string) ($data['subtopic'] ?? ''));
        $worksheetType = trim((string) ($data['worksheet_type'] ?? 'Worksheet'));
        $difficulty = trim((string) ($data['difficulty'] ?? 'medium'));
        $description = trim((string) ($data['description'] ?? ''));
        $source = in_array($data['generation_source'] ?? '', ['ai', 'manual'], true) ? $data['generation_source'] : 'manual';
        $status = in_array($data['status'] ?? '', ['draft', 'published', 'archived'], true) ? $data['status'] : 'published';
        $folderId = !empty($data['folder_id']) ? (int) $data['folder_id'] : null;
        $tags = isset($data['tags']) && is_array($data['tags']) ? json_encode(array_values($data['tags']), JSON_UNESCAPED_UNICODE) : null;
        $questions = isset($data['questions']) && is_array($data['questions']) ? $data['questions'] : [];

        $creatorName = trim(($consoleUser['first_name'] ?? 'Admin') . ' ' . ($consoleUser['last_name'] ?? ''));
        if ($creatorName === '') $creatorName = 'Admin';
        $creatorId = (int) ($consoleUser['id'] ?? 1);

        $stmt = $pdo->prepare("
            INSERT INTO worksheets (
                title, description, subject, level, chapter, topic, subtopic,
                worksheet_type, difficulty, question_count, generation_source,
                creator_id, creator_name, tags, folder_id, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $title, $description, $subject, $level, $chapter, $topic, $subtopic,
            $worksheetType, $difficulty, count($questions), $source,
            $creatorId, $creatorName, $tags, $folderId, $status
        ]);
        $wsId = (int) $pdo->lastInsertId();

        $order = 1;
        foreach ($questions as $q) {
            $qText = trim((string) ($q['question_text'] ?? $q['questionText'] ?? ''));
            if ($qText === '') continue;

            $qType = trim((string) ($q['question_type'] ?? $q['questionType'] ?? 'multipleChoice'));
            $options = $q['options'] ?? null;
            if (is_array($options)) {
                $options = json_encode(array_values($options), JSON_UNESCAPED_UNICODE);
            }
            $ans = trim((string) ($q['correct_answer'] ?? $q['correctAnswer'] ?? ''));
            $exp = trim((string) ($q['explanation'] ?? ''));
            $hint = trim((string) ($q['hint'] ?? ''));
            $skill = trim((string) ($q['skill'] ?? ''));
            $diff = trim((string) ($q['difficulty'] ?? $difficulty));
            $lo = trim((string) ($q['learning_objective'] ?? $q['learningObjective'] ?? ''));

            $insQ = $pdo->prepare("
                INSERT INTO worksheet_questions (
                    worksheet_id, sort_order, question_type, question_text,
                    options, correct_answer, explanation, hint, skill, difficulty, learning_objective
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insQ->execute([$wsId, $order++, $qType, $qText, $options, $ans, $exp, $hint, $skill, $diff, $lo]);
        }

        // Update exact count
        $pdo->prepare("UPDATE worksheets SET question_count = ? WHERE id = ?")->execute([$order - 1, $wsId]);
        (new \NextBeyond\Adaptive\CanonicalQuestionRepository($pdo))->syncWorksheet($wsId);

        jsonRespond([
            'success' => true,
            'id' => $wsId,
            'message' => 'บันทึกใบงานเข้าคลังแล้ว'
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 4. UPDATE WORKSHEET
    // ─────────────────────────────────────────────────────────────
    if ($method === 'PUT' || ($method === 'POST' && $action === 'update')) {
        $data = parseInput();
        $id = (int) ($data['id'] ?? $_GET['id'] ?? 0);
        if (!$id) jsonRespond(['error' => 'ไม่พบรหัสใบงาน'], 400);

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') jsonRespond(['error' => 'กรุณาระบุชื่อใบงาน'], 422);

        $subject = trim((string) ($data['subject'] ?? ''));
        $level = trim((string) ($data['level'] ?? ''));
        $chapter = trim((string) ($data['chapter'] ?? ''));
        $topic = trim((string) ($data['topic'] ?? ''));
        $subtopic = trim((string) ($data['subtopic'] ?? ''));
        $worksheetType = trim((string) ($data['worksheet_type'] ?? 'Worksheet'));
        $difficulty = trim((string) ($data['difficulty'] ?? 'medium'));
        $description = trim((string) ($data['description'] ?? ''));
        $status = in_array($data['status'] ?? '', ['draft', 'published', 'archived'], true) ? $data['status'] : 'published';
        $folderId = !empty($data['folder_id']) ? (int) $data['folder_id'] : null;
        $tags = isset($data['tags']) && is_array($data['tags']) ? json_encode(array_values($data['tags']), JSON_UNESCAPED_UNICODE) : null;

        $stmt = $pdo->prepare("
            UPDATE worksheets SET
                title = ?, description = ?, subject = ?, level = ?, chapter = ?, topic = ?, subtopic = ?,
                worksheet_type = ?, difficulty = ?, status = ?, folder_id = ?, tags = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $title, $description, $subject, $level, $chapter, $topic, $subtopic,
            $worksheetType, $difficulty, $status, $folderId, $tags, $id
        ]);

        if (isset($data['questions']) && is_array($data['questions'])) {
            // Replace questions
            $pdo->prepare("DELETE FROM worksheet_questions WHERE worksheet_id = ?")->execute([$id]);
            $order = 1;
            foreach ($data['questions'] as $q) {
                $qText = trim((string) ($q['question_text'] ?? $q['questionText'] ?? ''));
                if ($qText === '') continue;

                $qType = trim((string) ($q['question_type'] ?? $q['questionType'] ?? 'multipleChoice'));
                $options = $q['options'] ?? null;
                if (is_array($options)) {
                    $options = json_encode(array_values($options), JSON_UNESCAPED_UNICODE);
                }
                $ans = trim((string) ($q['correct_answer'] ?? $q['correctAnswer'] ?? ''));
                $exp = trim((string) ($q['explanation'] ?? ''));
                $hint = trim((string) ($q['hint'] ?? ''));
                $skill = trim((string) ($q['skill'] ?? ''));
                $diff = trim((string) ($q['difficulty'] ?? $difficulty));
                $lo = trim((string) ($q['learning_objective'] ?? $q['learningObjective'] ?? ''));

                $sourceQId = !empty($q['source_question_id']) ? (int) $q['source_question_id'] : (!empty($q['sourceQuestionId']) ? (int) $q['sourceQuestionId'] : null);
                $canonicalQId = !empty($q['canonical_question_id']) ? (int)$q['canonical_question_id'] : (!empty($q['canonicalQuestionId']) ? (int)$q['canonicalQuestionId'] : null);
                $insQ = $pdo->prepare("
                    INSERT INTO worksheet_questions (
                        worksheet_id, sort_order, question_type, question_text,
                        options, correct_answer, explanation, hint, skill, difficulty, learning_objective, source_question_id, canonical_question_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insQ->execute([$id, $order++, $qType, $qText, $options, $ans, $exp, $hint, $skill, $diff, $lo, $sourceQId, $canonicalQId]);
            }
            $pdo->prepare("UPDATE worksheets SET question_count = ? WHERE id = ?")->execute([$order - 1, $id]);
        }

        (new \NextBeyond\Adaptive\CanonicalQuestionRepository($pdo))->syncWorksheet($id);

        jsonRespond(['success' => true, 'message' => 'บันทึกการแก้ไขเรียบร้อยแล้ว']);
    }

    // ─────────────────────────────────────────────────────────────
    // 5. DUPLICATE WORKSHEET ("สร้างสำเนาแล้วแก้ไข")
    // ─────────────────────────────────────────────────────────────
    if ($method === 'POST' && $action === 'duplicate') {
        $data = parseInput();
        $id = (int) ($data['id'] ?? 0);
        if (!$id) jsonRespond(['error' => 'ไม่พบรหัสใบงาน'], 400);

        $stmt = $pdo->prepare("SELECT * FROM worksheets WHERE id = ?");
        $stmt->execute([$id]);
        $orig = $stmt->fetch();
        if (!$orig) jsonRespond(['error' => 'ไม่พบข้อมูลใบงานต้นฉบับ'], 404);

        $newTitle = 'สำเนา ' . $orig['title'];
        $creatorName = trim(($consoleUser['first_name'] ?? 'Admin') . ' ' . ($consoleUser['last_name'] ?? ''));
        $creatorId = (int) ($consoleUser['id'] ?? 1);

        $ins = $pdo->prepare("
            INSERT INTO worksheets (
                title, description, subject, level, chapter, topic, subtopic,
                worksheet_type, difficulty, question_count, generation_source,
                creator_id, creator_name, tags, folder_id, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')
        ");
        $ins->execute([
            $newTitle, $orig['description'], $orig['subject'], $orig['level'],
            $orig['chapter'], $orig['topic'], $orig['subtopic'], $orig['worksheet_type'],
            $orig['difficulty'], $orig['question_count'], $orig['generation_source'],
            $creatorId, $creatorName, $orig['tags'], $orig['folder_id']
        ]);
        $newId = (int) $pdo->lastInsertId();

        // Copy questions
        $qRows = $pdo->prepare("SELECT * FROM worksheet_questions WHERE worksheet_id = ? ORDER BY sort_order ASC");
        $qRows->execute([$id]);
        $questions = $qRows->fetchAll();

        foreach ($questions as $q) {
            $qIns = $pdo->prepare("
                INSERT INTO worksheet_questions (
                    worksheet_id, sort_order, question_type, question_text,
                    options, correct_answer, explanation, hint, skill, difficulty, learning_objective
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $qIns->execute([
                $newId, $q['sort_order'], $q['question_type'], $q['question_text'],
                $q['options'], $q['correct_answer'], $q['explanation'],
                $q['hint'], $q['skill'], $q['difficulty'], $q['learning_objective']
            ]);
        }

        (new \NextBeyond\Adaptive\CanonicalQuestionRepository($pdo))->syncWorksheet($newId);

        jsonRespond([
            'success' => true,
            'id' => $newId,
            'message' => 'สร้างสำเนาใบงานสำเร็จแล้ว'
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 6. ARCHIVE WORKSHEET
    // ─────────────────────────────────────────────────────────────
    if ($method === 'POST' && $action === 'archive') {
        $data = parseInput();
        $id = (int) ($data['id'] ?? 0);
        if (!$id) jsonRespond(['error' => 'ไม่พบรหัสใบงาน'], 400);

        $pdo->prepare("UPDATE worksheets SET status = 'archived' WHERE id = ?")->execute([$id]);
        jsonRespond(['success' => true, 'message' => 'จัดเก็บใบงานเข้าคลังประวัติ (Archive) เรียบร้อยแล้ว']);
    }

    // ─────────────────────────────────────────────────────────────
    // 7. DELETE WORKSHEET (WITH SAFETY CHECKS)
    // ─────────────────────────────────────────────────────────────
    if ($method === 'POST' && $action === 'delete') {
        $data = parseInput();
        $id = (int) ($data['id'] ?? 0);
        $force = !empty($data['force']);
        if (!$id) jsonRespond(['error' => 'ไม่พบรหัสใบงาน'], 400);

        // Check assignments
        $assignCount = (int) $pdo->prepare("SELECT COUNT(*) FROM worksheet_assignments WHERE worksheet_id = ?")->execute([$id]) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
        $assignStmt = $pdo->prepare("SELECT COUNT(*) FROM worksheet_assignments WHERE worksheet_id = ?");
        $assignStmt->execute([$id]);
        $usage = (int) $assignStmt->fetchColumn();

        if ($usage > 0 && !$force) {
            jsonRespond([
                'error' => "ใบงานนี้กำลังถูกใช้งานใน {$usage} คลาส/การมอบหมาย แนะนำให้กดจัดเก็บ (Archive) แทนการลบถาวร",
                'isAssigned' => true,
                'usageCount' => $usage
            ], 409);
        }

        $pdo->prepare("DELETE FROM worksheet_questions WHERE worksheet_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM worksheet_assignments WHERE worksheet_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM worksheets WHERE id = ?")->execute([$id]);

        jsonRespond(['success' => true, 'message' => 'ลบใบงานเรียบร้อยแล้ว']);
    }

    // ─────────────────────────────────────────────────────────────
    // 8. FOLDER OPERATIONS
    // ─────────────────────────────────────────────────────────────
    if ($action === 'create_folder') {
        $data = parseInput();
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') jsonRespond(['error' => 'กรุณาระบุชื่อโฟลเดอร์'], 422);

        $stmt = $pdo->prepare("INSERT INTO worksheet_folders (name) VALUES (?)");
        $stmt->execute([$name]);
        jsonRespond(['success' => true, 'id' => (int) $pdo->lastInsertId(), 'name' => $name]);
    }

    if ($action === 'rename_folder') {
        $data = parseInput();
        $id = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        if (!$id || $name === '') jsonRespond(['error' => 'ข้อมูลไม่ถูกต้อง'], 422);

        $pdo->prepare("UPDATE worksheet_folders SET name = ? WHERE id = ?")->execute([$name, $id]);
        jsonRespond(['success' => true, 'message' => 'เปลี่ยนชื่อโฟลเดอร์สำเร็จ']);
    }

    if ($action === 'move_folder') {
        $data = parseInput();
        $worksheetId = (int) ($data['worksheet_id'] ?? 0);
        $folderId = isset($data['folder_id']) && $data['folder_id'] !== '' ? (int) $data['folder_id'] : null;

        $pdo->prepare("UPDATE worksheets SET folder_id = ? WHERE id = ?")->execute([$folderId, $worksheetId]);
        jsonRespond(['success' => true, 'message' => 'ย้ายโฟลเดอร์สำเร็จ']);
    }

    // ─────────────────────────────────────────────────────────────
    // 9. ASSIGN WORKSHEET TO CLASS / COURSE / STUDENT (Phase 3 Extended)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'assign') {
        require_once __DIR__ . '/../includes/phase3-mastery-service.php';
        $p3 = new Phase3MasteryService($pdo);

        $data = parseInput();
        $worksheetId = (int) ($data['worksheet_id'] ?? 0);
        $examId = !empty($data['exam_id']) ? (int)$data['exam_id'] : null;
        if (!$worksheetId && !$examId) jsonRespond(['error' => 'ไม่พบรหัสใบงานหรือข้อสอบ'], 400);

        $courseId = !empty($data['course_id']) ? (int) $data['course_id'] : null;
        $classGroupId = !empty($data['class_group_id']) ? (int)$data['class_group_id'] : null;
        $sessionId = !empty($data['session_id']) ? (string)$data['session_id'] : null;
        $epId = !empty($data['ep_id']) ? (int) $data['ep_id'] : null;
        $className = trim((string) ($data['class_name'] ?? ''));
        $targetType = ($data['target_type'] ?? '') === 'selected' ? 'selected' : 'all';
        $studentIds = isset($data['student_ids']) && is_array($data['student_ids']) ? $data['student_ids'] : null;
        $dueDate = !empty($data['due_date']) ? date('Y-m-d H:i:s', strtotime($data['due_date'])) : null;
        $activityType = (string)($data['activity_type'] ?? 'worksheet');
        $title = trim((string)($data['title'] ?? ''));
        $topicName = trim((string)($data['topic_name'] ?? ''));
        $maxAttempts = isset($data['max_attempts']) ? (int)$data['max_attempts'] : 1;
        $passScore = isset($data['pass_score']) ? (float)$data['pass_score'] : 60.0;

        $assignerName = trim(($consoleUser['first_name'] ?? 'Admin') . ' ' . ($consoleUser['last_name'] ?? ''));
        $assignerId = (int) ($consoleUser['id'] ?? 1);

        $assignId = $p3->createAssignment([
            'worksheet_id'     => $worksheetId ?: null,
            'exam_id'          => $examId,
            'course_id'        => $courseId,
            'class_group_id'   => $classGroupId,
            'session_id'       => $sessionId,
            'activity_type'    => $activityType,
            'title'            => $title,
            'topic_name'       => $topicName,
            'target_type'      => $targetType,
            'student_ids'      => $studentIds,
            'due_date'         => $dueDate,
            'max_attempts'     => $maxAttempts,
            'pass_score'       => $passScore,
            'assigned_by'      => $assignerId,
            'assigned_by_name' => $assignerName,
        ]);

        if ($worksheetId) {
            $pdo->prepare("UPDATE worksheets SET usage_count = usage_count + 1 WHERE id = ?")->execute([$worksheetId]);
        }

        jsonRespond([
            'success' => true,
            'assignment_id' => $assignId,
            'message' => 'มอบหมายกิจกรรมการเรียนรู้เรียบร้อยแล้ว'
        ]);
    }

    // 9.1 QUICK ASSIGN FROM LIVE SESSION (Section 42-43)
    if ($action === 'quick_assign_session') {
        require_once __DIR__ . '/../includes/phase3-mastery-service.php';
        $p3 = new Phase3MasteryService($pdo);

        $data = parseInput();
        $sessionId = trim((string)($data['session_id'] ?? ''));
        $worksheetId = (int)($data['worksheet_id'] ?? 0);
        $dueDate = trim((string)($data['due_date'] ?? date('Y-m-d 23:59:59', strtotime('+3 days'))));
        $activityType = (string)($data['activity_type'] ?? 'homework');

        if (empty($sessionId) || $worksheetId < 1) {
            jsonRespond(['error' => 'session_id and worksheet_id required'], 422);
        }

        $assignId = $p3->quickAssignFromSession(
            $sessionId,
            $worksheetId,
            $dueDate,
            $activityType,
            (int)($consoleUser['id'] ?? 1)
        );

        jsonRespond([
            'success' => true,
            'assignment_id' => $assignId,
            'message' => 'มอบหมายงานหลังเรียนเรียบร้อยแล้ว'
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 10. AI WORKSHEET GENERATOR (CONNECTED TO GEMINI)
    // ─────────────────────────────────────────────────────────────
    if ($action === 'generate_ai') {
        $data = parseInput();
        $subject = trim((string) ($data['subject'] ?? 'เคมี'));
        $level = trim((string) ($data['level'] ?? 'ม.5'));
        $chapter = trim((string) ($data['chapter'] ?? ''));
        $topic = trim((string) ($data['topic'] ?? 'Acid-Base'));
        $subtopic = trim((string) ($data['subtopic'] ?? ''));
        $worksheetType = trim((string) ($data['worksheet_type'] ?? 'Worksheet'));
        $difficulty = trim((string) ($data['difficulty'] ?? 'medium'));
        $count = max(1, min(30, (int) ($data['count'] ?? 10)));
        $questionTypes = isset($data['question_types']) && is_array($data['question_types']) ? $data['question_types'] : ['multipleChoice'];
        $learningObjective = trim((string) ($data['learning_objective'] ?? ''));
        $customPrompt = trim((string) ($data['instructions'] ?? ''));

        // Retrieve server Gemini API key
        $apiKey = aiSettingsGetKey($pdo);
        if (empty($apiKey)) {
            // Check fallback in legacy or request
            $apiKey = trim((string) ($data['apiKey'] ?? ''));
        }

        if (empty($apiKey)) {
            jsonRespond([
                'error' => 'ยังไม่ได้ตั้งค่า Gemini API Key กรุณาตรวจสอบการตั้งค่าในหน้า AI Exam หรือป้อน API Key'
            ], 400);
        }

        // Build structured prompt for Gemini
        $typeNames = implode(', ', $questionTypes);
        $prompt = "คุณเป็นผู้เชี่ยวชาญด้านการออกแบบใบงานและสื่อการเรียนรู้วิชาการระดับสูง (Senior EdTech Learning Content Architect)
จงสร้างชุดใบงานการเรียนรู้ตามข้อกำหนดด้านล่างนี้ โดยให้ผลลัพธ์เป็น JSON Array ของคำถามเท่านั้น

[ข้อกำหนดใบงาน]
- วิชา: {$subject}
- ระดับชั้น: {$level}
- บทเรียน/หัวข้อใหญ่: {$chapter}
- หัวข้อย่อย/Concept สำคัญ: {$topic} {$subtopic}
- ประเภทใบงาน: {$worksheetType}
- ระดับความยาก: {$difficulty}
- จำนวนข้อ: {$count} ข้อ
- รูปแบบคำถามที่ต้องการ: {$typeNames} (เช่น multipleChoice, shortAnswer, trueFalse, essay)
- วัตถุประสงค์การเรียนรู้ (Learning Objective): " . ($learningObjective ?: 'เพื่อวัดความเข้าใจแนวคิดหลักและทักษะการแก้ปัญหา') . "
- คำสั่งพิเศษเพิ่มเติม: " . ($customPrompt ?: 'เน้นโจทย์ที่กระตุ้นการคิดวิเคราะห์ มีตัวเลือกที่สมจริง และมีคำอธิบายเฉลยที่ชัดเจนละเอียด') . "

[กติกาสร้างคำถาม]
1. โจทย์และคำอธิบายเฉลยต้องมีความถูกต้องตามหลักวิชาการ 100%
2. หากเป็น multipleChoice ต้องมี options 4 ตัวเลือก และ correctAnswer ต้องตรงกับ 1 ในตัวเลือกนั้น
3. หากเป็น trueFalse ต้องมี options ['True', 'False'] และ correctAnswer เป็น 'True' หรือ 'False'
4. หากเป็น shortAnswer ต้องมี correctAnswer เป็นข้อความคำตอบสั้นๆ หรือตัวเลข
5. ทุกข้อต้องมี explanation (คำอธิบายเฉลยละเอียดและเหตุผล) และ skill หรือ learningObjective
6. ห้ามมีข้อความเกริ่นนำหรือ markdown formatting นอกเหนือจาก JSON array

จงส่งออกโครงสร้าง JSON Array ในรูปแบบดังนี้:
[
  {
    \"questionText\": \"ข้อความโจทย์คำถาม...\",
    \"questionType\": \"multipleChoice\",
    \"options\": [\"ตัวเลือก 1\", \"ตัวเลือก 2\", \"ตัวเลือก 3\", \"ตัวเลือก 4\"],
    \"correctAnswer\": \"ตัวเลือก 1\",
    \"explanation\": \"คำอธิบายเฉลยอย่างละเอียด...\",
    \"skill\": \"ทักษะที่เกี่ยวข้อง\",
    \"difficulty\": \"{$difficulty}\",
    \"learningObjective\": \"วัตถุประสงค์ย่อยของข้อนี้\"
  }
]";

        // Call Gemini
        $candidateModels = ["gemini-2.5-flash", "gemini-2.0-flash", "gemini-1.5-flash", "gemini-1.5-pro"];
        $resultText = null;
        $lastError = null;

        foreach ($candidateModels as $model) {
            $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.4,
                    'responseMimeType' => 'application/json'
                ]
            ];

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 90);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            $rawRes = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $rawRes) {
                $resData = json_decode($rawRes, true);
                $parts = $resData['candidates'][0]['content']['parts'] ?? [];
                $txt = '';
                foreach ($parts as $p) {
                    if (isset($p['text']) && empty($p['thought'])) $txt .= $p['text'];
                }
                if ($txt !== '') {
                    $resultText = $txt;
                    break;
                }
            } else {
                if (str_contains((string)$rawRes, 'API key not valid') || str_contains((string)$rawRes, 'API_KEY_INVALID')) {
                    jsonRespond([
                        'error' => 'Gemini API Key ในระบบไม่ถูกต้อง หรือหมดอายุ (API key not valid) กรุณาไปที่เมนู "ตั้งค่าระบบ" > แท็บ "AI API Key" เพื่อกรอกและบันทึก API Key ใหม่จาก Google AI Studio'
                    ], 401);
                }
                $lastError = "Model {$model} returned HTTP {$httpCode}: " . substr((string)$rawRes, 0, 150);
            }
        }

        if (!$resultText) {
            jsonRespond(['error' => 'การเรียกใช้งาน AI ไม่สำเร็จ: ' . ($lastError ?: 'โปรดลองใหม่อีกครั้ง')], 500);
        }

        // Parse extracted JSON
        $start = strpos($resultText, '[');
        $end = strrpos($resultText, ']');
        if ($start !== false && $end !== false) {
            $jsonStr = substr($resultText, $start, $end - $start + 1);
            $decodedQuestions = json_decode($jsonStr, true);
        } else {
            $decodedQuestions = json_decode($resultText, true);
        }

        if (!is_array($decodedQuestions) || empty($decodedQuestions)) {
            jsonRespond(['error' => 'AI ตอบกลับมาในรูปแบบที่ไม่สามารถแปลงเป็นคำถามได้'], 502);
        }

        // Clean and normalize questions
        $cleanQuestions = [];
        $order = 1;
        foreach ($decodedQuestions as $q) {
            $cleanQuestions[] = [
                'sort_order' => $order++,
                'question_type' => $q['questionType'] ?? $q['question_type'] ?? 'multipleChoice',
                'question_text' => $q['questionText'] ?? $q['question_text'] ?? '',
                'options' => isset($q['options']) && is_array($q['options']) ? $q['options'] : [],
                'correct_answer' => $q['correctAnswer'] ?? $q['correct_answer'] ?? '',
                'explanation' => $q['explanation'] ?? '',
                'skill' => $q['skill'] ?? $topic,
                'difficulty' => $q['difficulty'] ?? $difficulty,
                'learning_objective' => $q['learningObjective'] ?? $q['learning_objective'] ?? $learningObjective
            ];
        }

        // Suggested title
        $suggestedTitle = "{$topic} ({$worksheetType}) - {$level}";

        jsonRespond([
            'success' => true,
            'suggested_title' => $suggestedTitle,
            'subject' => $subject,
            'level' => $level,
            'chapter' => $chapter,
            'topic' => $topic,
            'subtopic' => $subtopic,
            'worksheet_type' => $worksheetType,
            'difficulty' => $difficulty,
            'learning_objective' => $learningObjective,
            'questions' => $cleanQuestions,
            'question_count' => count($cleanQuestions)
        ]);
    }

    jsonRespond(['error' => 'Action ไม่ถูกต้อง'], 400);
} catch (Throwable $e) {
    jsonRespond([
        'error' => 'เกิดข้อผิดพลาดภายในระบบ: ' . $e->getMessage()
    ], 500);
}
