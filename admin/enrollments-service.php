<?php
declare(strict_types=1);

/**
 * Nextbeyond Compass - Course Access & Student Enrollment Management Service ("จัดการสิทธิ์คอร์ส")
 * Core business architecture for individual course access, class group scheduling, capacity, and audit.
 */

class EnrollmentService {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->ensureSchema();
    }

    /**
     * Non-destructively ensure schema tables and columns (Sections 13, 14, 41)
     */
    public function ensureSchema(): void {
        static $ensured = false;
        if ($ensured) return;

        // 1. Table: class_groups (Section 13)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `class_groups` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(150) NOT NULL,
                `code` VARCHAR(50) NULL,
                `course_id` INT NOT NULL,
                `teacher_id` INT NULL,
                `schedule_day` VARCHAR(20) NULL,
                `schedule_time` VARCHAR(50) NULL,
                `schedule_text` VARCHAR(255) NULL,
                `capacity` INT NOT NULL DEFAULT 15,
                `learning_mode` ENUM('online', 'onsite', 'hybrid') NOT NULL DEFAULT 'online',
                `room` VARCHAR(100) NULL,
                `status` ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_course` (`course_id`),
                INDEX `idx_teacher` (`teacher_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 2. Table: enrollment_audit_logs (Section 41)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `enrollment_audit_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `enrollment_id` INT NULL,
                `student_id` INT NOT NULL,
                `course_id` INT NOT NULL,
                `action` VARCHAR(50) NOT NULL,
                `details` TEXT NULL,
                `performed_by` INT NULL,
                `performed_by_name` VARCHAR(150) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_student` (`student_id`),
                INDEX `idx_enrollment` (`enrollment_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 3. Extend enrollments columns
        try {
            $cols = [];
            foreach ($this->pdo->query("SHOW COLUMNS FROM `enrollments`")->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $cols[$c['Field']] = $c;
            }

            if (!isset($cols['class_group_id'])) {
                $this->pdo->exec("ALTER TABLE `enrollments` ADD COLUMN `class_group_id` INT NULL AFTER `course_id`, ADD INDEX `idx_cg` (`class_group_id`)");
            }
            if (!isset($cols['access_type'])) {
                $this->pdo->exec("ALTER TABLE `enrollments` ADD COLUMN `access_type` VARCHAR(30) NOT NULL DEFAULT 'manual' AFTER `class_group_id`");
            }
            if (!isset($cols['learning_mode'])) {
                $this->pdo->exec("ALTER TABLE `enrollments` ADD COLUMN `learning_mode` VARCHAR(30) NOT NULL DEFAULT 'online' AFTER `access_type`");
            }
            if (!isset($cols['start_date'])) {
                $this->pdo->exec("ALTER TABLE `enrollments` ADD COLUMN `start_date` DATE NULL AFTER `enrolled_at`");
            }
            if (!isset($cols['end_date'])) {
                $this->pdo->exec("ALTER TABLE `enrollments` ADD COLUMN `end_date` DATE NULL AFTER `expires_at`");
            }
            if (!isset($cols['payment_status'])) {
                $this->pdo->exec("ALTER TABLE `enrollments` ADD COLUMN `payment_status` VARCHAR(30) NOT NULL DEFAULT 'paid' AFTER `status`");
            }
            if (!isset($cols['order_id'])) {
                $this->pdo->exec("ALTER TABLE `enrollments` ADD COLUMN `order_id` INT NULL AFTER `payment_status`");
            }
            if (!isset($cols['bundle_id'])) {
                $this->pdo->exec("ALTER TABLE `enrollments` ADD COLUMN `bundle_id` VARCHAR(100) NULL AFTER `order_id`");
            }
            if (!isset($cols['assigned_by'])) {
                $this->pdo->exec("ALTER TABLE `enrollments` ADD COLUMN `assigned_by` INT NULL AFTER `bundle_id`");
            }
            if (!isset($cols['assigned_by_name'])) {
                $this->pdo->exec("ALTER TABLE `enrollments` ADD COLUMN `assigned_by_name` VARCHAR(150) NULL AFTER `assigned_by`");
            }
            if (!isset($cols['notes'])) {
                $this->pdo->exec("ALTER TABLE `enrollments` ADD COLUMN `notes` TEXT NULL AFTER `assigned_by_name`");
            }
            if (!isset($cols['updated_at'])) {
                $this->pdo->exec("ALTER TABLE `enrollments` ADD COLUMN `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            }

            // Alter status column from strict enum to VARCHAR(30) so we can support active, trial, pending, expired, paused, revoked, completed
            if (isset($cols['status']) && str_starts_with(strtolower((string)$cols['status']['Type']), 'enum')) {
                $this->pdo->exec("ALTER TABLE `enrollments` MODIFY COLUMN `status` VARCHAR(30) NOT NULL DEFAULT 'active'");
            }
        } catch (Throwable $e) {
            error_log("Enrollment schema alter error: " . $e->getMessage());
        }

        // 4. Extend users table for grade
        try {
            $userCols = [];
            foreach ($this->pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $userCols[$c['Field']] = true;
            }
            if (!isset($userCols['grade'])) {
                $this->pdo->exec("ALTER TABLE `users` ADD COLUMN `grade` VARCHAR(50) NULL AFTER `nickname`");
            }
        } catch (Throwable $e) {
            error_log("Users grade alter error: " . $e->getMessage());
        }

        $ensured = true;
    }

    /**
     * Automatically seeds acceptance testing scenario data (Section 49 - 53)
     */
    public function seedAcceptanceScenarioData(): array {
        // 1. Seed standard M.5 Courses if empty or missing
        $courseSpecs = [
            'M5 English' => ['subject' => 'ภาษาอังกฤษ', 'level' => 'ม.5', 'price' => 1200, 'duration' => 24],
            'M5 Chemistry' => ['subject' => 'เคมี', 'level' => 'ม.5', 'price' => 1500, 'duration' => 30],
            'M5 Biology' => ['subject' => 'ชีววิทยา', 'level' => 'ม.5', 'price' => 1500, 'duration' => 30],
            'M5 Mathematics' => ['subject' => 'คณิตศาสตร์', 'level' => 'ม.5', 'price' => 1400, 'duration' => 28],
            'M5 Physics' => ['subject' => 'ฟิสิกส์', 'level' => 'ม.5', 'price' => 1500, 'duration' => 30]
        ];

        $courseMap = []; // title => id
        foreach ($courseSpecs as $title => $spec) {
            $stmt = $this->pdo->prepare("SELECT id FROM courses WHERE title = ? LIMIT 1");
            $stmt->execute([$title]);
            $cId = $stmt->fetchColumn();
            if (!$cId) {
                $ins = $this->pdo->prepare("
                    INSERT INTO courses (title, subject, level, description, price, duration_hours, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'active')
                ");
                $ins->execute([
                    $title,
                    $spec['subject'],
                    $spec['level'],
                    "คอร์สเรียนระดับ {$spec['level']} วิชา {$spec['subject']} เน้นเนื้อหาเข้มข้นและการประยุกต์ทำข้อสอบ",
                    $spec['price'],
                    $spec['duration']
                ]);
                $cId = (int)$this->pdo->lastInsertId();
            }
            $courseMap[$title] = (int)$cId;
        }

        // 2. Seed Class Groups for each course (Section 12, 13)
        $classGroupSpecs = [
            'M5-ENG-A' => [
                'course' => 'M5 English',
                'day' => 'Monday',
                'time' => '19:00 - 20:30',
                'text' => 'วันจันทร์ 19:00 - 20:30',
                'cap' => 15,
                'mode' => 'online',
                'room' => 'Live Room 1'
            ],
            'M5-ENG-B' => [
                'course' => 'M5 English',
                'day' => 'Saturday',
                'time' => '13:00 - 14:30',
                'text' => 'วันเสาร์ 13:00 - 14:30',
                'cap' => 15,
                'mode' => 'online',
                'room' => 'Live Room 2'
            ],
            'M5-CHEM-A' => [
                'course' => 'M5 Chemistry',
                'day' => 'Tuesday',
                'time' => '19:00 - 20:30',
                'text' => 'วันอังคาร 19:00 - 20:30',
                'cap' => 15,
                'mode' => 'online',
                'room' => 'Live Room 1'
            ],
            'M5-BIO-A' => [
                'course' => 'M5 Biology',
                'day' => 'Wednesday',
                'time' => '19:00 - 20:30',
                'text' => 'วันพุธ 19:00 - 20:30',
                'cap' => 15,
                'mode' => 'online',
                'room' => 'Live Room 1'
            ],
            'M5-MATH-A' => [
                'course' => 'M5 Mathematics',
                'day' => 'Thursday',
                'time' => '19:00 - 20:30',
                'text' => 'วันพฤหัสบดี 19:00 - 20:30',
                'cap' => 15,
                'mode' => 'online',
                'room' => 'Live Room 1'
            ],
            'M5-PHY-A' => [
                'course' => 'M5 Physics',
                'day' => 'Friday',
                'time' => '19:00 - 20:30',
                'text' => 'วันศุกร์ 19:00 - 20:30',
                'cap' => 15,
                'mode' => 'online',
                'room' => 'Live Room 1'
            ]
        ];

        $classGroupMap = []; // name => id
        foreach ($classGroupSpecs as $cgName => $cg) {
            $stmt = $this->pdo->prepare("SELECT id FROM class_groups WHERE name = ? LIMIT 1");
            $stmt->execute([$cgName]);
            $cgId = $stmt->fetchColumn();
            $courseId = $courseMap[$cg['course']] ?? 0;
            if (!$cgId && $courseId) {
                $ins = $this->pdo->prepare("
                    INSERT INTO class_groups (name, code, course_id, schedule_day, schedule_time, schedule_text, capacity, learning_mode, room, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                $ins->execute([
                    $cgName,
                    $cgName,
                    $courseId,
                    $cg['day'],
                    $cg['time'],
                    $cg['text'],
                    $cg['cap'],
                    $cg['mode'],
                    $cg['room']
                ]);
                $cgId = (int)$this->pdo->lastInsertId();
            }
            $classGroupMap[$cgName] = (int)$cgId;
        }

        // 3. Seed Students for Acceptance Scenarios (ใบเตย, จีจี้, แคนดี้, เอม - All Grade: ม.5)
        $studentSpecs = [
            'baitoey@student.nextbeyond.com' => ['first_name' => 'ใบเตย', 'last_name' => 'ปิยธิดา', 'nickname' => 'ใบเตย', 'grade' => 'ม.5', 'phone' => '0812345601'],
            'gigi@student.nextbeyond.com'    => ['first_name' => 'จีจี้', 'last_name' => 'กัญญาพร', 'nickname' => 'จีจี้', 'grade' => 'ม.5', 'phone' => '0812345602'],
            'candy@student.nextbeyond.com'   => ['first_name' => 'แคนดี้', 'last_name' => 'ศิรภัสสร', 'nickname' => 'แคนดี้', 'grade' => 'ม.5', 'phone' => '0812345603'],
            'aim@student.nextbeyond.com'     => ['first_name' => 'เอม', 'last_name' => 'ชลธิชา', 'nickname' => 'เอม', 'grade' => 'ม.5', 'phone' => '0812345604'],
        ];

        $studentMap = []; // nickname => id
        foreach ($studentSpecs as $email => $st) {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? OR nickname = ? LIMIT 1");
            $stmt->execute([$email, $st['nickname']]);
            $uId = $stmt->fetchColumn();
            if (!$uId) {
                $ins = $this->pdo->prepare("
                    INSERT INTO users (email, password_hash, first_name, last_name, nickname, grade, phone, role, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'student', 1)
                ");
                $ins->execute([
                    $email,
                    password_hash('password', PASSWORD_DEFAULT),
                    $st['first_name'],
                    $st['last_name'],
                    $st['nickname'],
                    $st['grade'],
                    $st['phone']
                ]);
                $uId = (int)$this->pdo->lastInsertId();
            } else {
                // Ensure grade is updated
                $this->pdo->prepare("UPDATE users SET grade = ?, nickname = ? WHERE id = ?")->execute([$st['grade'], $st['nickname'], $uId]);
            }
            $studentMap[$st['nickname']] = (int)$uId;
        }

        // 4. Seed Initial Enrollments according to Acceptance Scenario 1:
        // ใบเตย: English, Chemistry, Biology (Class: M5-ENG-A, M5-CHEM-A, M5-BIO-A)
        // จีจี้:  English, Chemistry, Biology (Class: M5-ENG-A, M5-CHEM-A, M5-BIO-A)
        // แคนดี้: English, Mathematics (Class: M5-ENG-A, M5-MATH-A)
        // เอม:    English, Physics (Class: M5-ENG-A, M5-PHY-A)
        $enrollmentMatrix = [
            'ใบเตย' => [
                ['course' => 'M5 English', 'class' => 'M5-ENG-A', 'type' => 'paid', 'mode' => 'online'],
                ['course' => 'M5 Chemistry', 'class' => 'M5-CHEM-A', 'type' => 'paid', 'mode' => 'online'],
                ['course' => 'M5 Biology', 'class' => 'M5-BIO-A', 'type' => 'paid', 'mode' => 'online'],
            ],
            'จีจี้' => [
                ['course' => 'M5 English', 'class' => 'M5-ENG-A', 'type' => 'paid', 'mode' => 'online'],
                ['course' => 'M5 Chemistry', 'class' => 'M5-CHEM-A', 'type' => 'paid', 'mode' => 'online'],
                ['course' => 'M5 Biology', 'class' => 'M5-BIO-A', 'type' => 'paid', 'mode' => 'online'],
            ],
            'แคนดี้' => [
                ['course' => 'M5 English', 'class' => 'M5-ENG-A', 'type' => 'paid', 'mode' => 'online'],
                ['course' => 'M5 Mathematics', 'class' => 'M5-MATH-A', 'type' => 'paid', 'mode' => 'online'],
            ],
            'เอม' => [
                ['course' => 'M5 English', 'class' => 'M5-ENG-A', 'type' => 'paid', 'mode' => 'online'],
                ['course' => 'M5 Physics', 'class' => 'M5-PHY-A', 'type' => 'paid', 'mode' => 'online'],
            ],
        ];

        foreach ($enrollmentMatrix as $nickname => $courses) {
            $sId = $studentMap[$nickname] ?? 0;
            if (!$sId) continue;

            foreach ($courses as $item) {
                $cId = $courseMap[$item['course']] ?? 0;
                $cgId = $classGroupMap[$item['class']] ?? null;
                if (!$cId) continue;

                $check = $this->pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? AND status = 'active' LIMIT 1");
                $check->execute([$sId, $cId]);
                if (!$check->fetchColumn()) {
                    $this->assignCourseAccess($sId, $cId, [
                        'class_group_id' => $cgId,
                        'access_type' => $item['type'],
                        'learning_mode' => $item['mode'],
                        'status' => 'active',
                        'payment_status' => 'paid',
                        'assigned_by' => 2,
                        'assigned_by_name' => 'Admin Next Beyond',
                        'notes' => 'ระบบจัดสรรสิทธิ์เริ่มต้นตามแผนการเรียน'
                    ]);
                }
            }
        }

        return [
            'students' => $studentMap,
            'courses' => $courseMap,
            'class_groups' => $classGroupMap
        ];
    }

    /**
     * Assign Course Access to Student (Sections 4, 5, 6, 7, 8, 42)
     */
    public function assignCourseAccess(int $studentId, int $courseId, array $data = []): array {
        // 1. Check existing active enrollment (Section 42: Duplicate Protection)
        $stmt = $this->pdo->prepare("
            SELECT e.*, c.title AS course_title, cg.name AS class_group_name
            FROM enrollments e
            LEFT JOIN courses c ON c.id = e.course_id
            LEFT JOIN class_groups cg ON cg.id = e.class_group_id
            WHERE e.user_id = ? AND e.course_id = ? AND e.status IN ('active', 'trial')
            LIMIT 1
        ");
        $stmt->execute([$studentId, $courseId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing && empty($data['force_override'])) {
            return [
                'success' => false,
                'is_duplicate' => true,
                'existing_enrollment' => $existing,
                'error' => "นักเรียนมีสิทธิ์ในคอร์ส {$existing['course_title']} อยู่แล้ว (สถานะ: {$existing['status']})"
            ];
        }

        $classGroupId = !empty($data['class_group_id']) ? (int)$data['class_group_id'] : null;

        // 2. Capacity Check on Class Group (Section 33)
        if ($classGroupId) {
            $cgStmt = $this->pdo->prepare("
                SELECT cg.*, COUNT(e.id) AS current_enrolled
                FROM class_groups cg
                LEFT JOIN enrollments e ON e.class_group_id = cg.id AND e.status IN ('active', 'trial')
                WHERE cg.id = ?
                GROUP BY cg.id
            ");
            $cgStmt->execute([$classGroupId]);
            $cgInfo = $cgStmt->fetch(PDO::FETCH_ASSOC);

            if ($cgInfo && (int)$cgInfo['current_enrolled'] >= (int)$cgInfo['capacity'] && empty($data['override_capacity'])) {
                return [
                    'success' => false,
                    'is_full' => true,
                    'capacity' => (int)$cgInfo['capacity'],
                    'current' => (int)$cgInfo['current_enrolled'],
                    'error' => "กลุ่มเรียน {$cgInfo['name']} เต็มแล้ว ({$cgInfo['current_enrolled']}/{$cgInfo['capacity']} คน)"
                ];
            }
        }

        $accessType = $data['access_type'] ?? 'paid';
        $learningMode = $data['learning_mode'] ?? 'online';
        $status = $data['status'] ?? ($accessType === 'trial' ? 'trial' : 'active');
        $paymentStatus = $data['payment_status'] ?? ($accessType === 'paid' ? 'paid' : 'free');
        $startDate = !empty($data['start_date']) ? $data['start_date'] : date('Y-m-d');
        $endDate = !empty($data['end_date']) ? $data['end_date'] : null;
        $orderId = !empty($data['order_id']) ? (int)$data['order_id'] : null;
        $bundleId = !empty($data['bundle_id']) ? trim((string)$data['bundle_id']) : null;
        $assignedBy = !empty($data['assigned_by']) ? (int)$data['assigned_by'] : null;
        $assignedByName = trim((string)($data['assigned_by_name'] ?? 'Admin'));
        $notes = trim((string)($data['notes'] ?? ''));

        // If trial without endDate, set +7 days default
        if ($accessType === 'trial' && empty($endDate)) {
            $endDate = date('Y-m-d', strtotime('+7 days'));
        }

        $ins = $this->pdo->prepare("
            INSERT INTO enrollments (
                user_id, course_id, class_group_id, access_type, learning_mode,
                enrolled_at, start_date, end_date, expires_at, status,
                payment_status, order_id, bundle_id, assigned_by, assigned_by_name, notes
            ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $expiresAt = $endDate ? "{$endDate} 23:59:59" : null;
        $ins->execute([
            $studentId, $courseId, $classGroupId, $accessType, $learningMode,
            $startDate, $endDate, $expiresAt, $status,
            $paymentStatus, $orderId, $bundleId, $assignedBy, $assignedByName, $notes
        ]);
        $enrollmentId = (int)$this->pdo->lastInsertId();

        // Audit Log (Section 41)
        $this->logAudit($enrollmentId, $studentId, $courseId, 'assigned', "กำหนดสิทธิ์คอร์ส: {$accessType} (โหมด: {$learningMode})", $assignedBy, $assignedByName);

        return [
            'success' => true,
            'enrollment_id' => $enrollmentId,
            'message' => 'กำหนดสิทธิ์คอร์สให้นักเรียนเรียบร้อยแล้ว'
        ];
    }

    /**
     * Bulk Assign Course to multiple students (Section 17, 53)
     */
    public function bulkAssignCourse(array $studentIds, int $courseId, array $data = []): array {
        $assigned = [];
        $skipped = [];

        foreach ($studentIds as $sId) {
            $sId = (int)$sId;
            if (!$sId) continue;

            $res = $this->assignCourseAccess($sId, $courseId, $data);
            if ($res['success']) {
                $assigned[] = $sId;
            } else {
                $stName = $this->pdo->query("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = {$sId}")->fetchColumn();
                $skipped[] = [
                    'student_id' => $sId,
                    'name' => $stName ?: "#{$sId}",
                    'reason' => $res['error'] ?? 'ข้าม'
                ];
            }
        }

        return [
            'success' => true,
            'total_processed' => count($studentIds),
            'assigned_count' => count($assigned),
            'skipped_count' => count($skipped),
            'assigned_ids' => $assigned,
            'skipped' => $skipped
        ];
    }

    /**
     * Bulk Add Students to Class Group (Section 18)
     */
    public function bulkAssignClassGroup(array $studentIds, int $classGroupId, ?int $assignedBy = null, string $assignedByName = 'Admin'): array {
        $cg = $this->pdo->query("SELECT * FROM class_groups WHERE id = {$classGroupId}")->fetch(PDO::FETCH_ASSOC);
        if (!$cg) {
            return ['success' => false, 'error' => 'ไม่พบกลุ่มเรียนที่ระบุ'];
        }

        $courseId = (int)$cg['course_id'];
        $updated = [];

        foreach ($studentIds as $sId) {
            $sId = (int)$sId;
            if (!$sId) continue;

            // Check if student has enrollment for this course
            $stmt = $this->pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$sId, $courseId]);
            $enId = $stmt->fetchColumn();

            if ($enId) {
                $this->pdo->prepare("UPDATE enrollments SET class_group_id = ? WHERE id = ?")->execute([$classGroupId, $enId]);
                $this->logAudit((int)$enId, $sId, $courseId, 'class_changed', "เปลี่ยนกลุ่มเรียนเป็น {$cg['name']}", $assignedBy, $assignedByName);
                $updated[] = $sId;
            } else {
                // Auto-create enrollment with this class group
                $res = $this->assignCourseAccess($sId, $courseId, [
                    'class_group_id' => $classGroupId,
                    'access_type' => 'manual',
                    'learning_mode' => $cg['learning_mode'],
                    'assigned_by' => $assignedBy,
                    'assigned_by_name' => $assignedByName,
                    'notes' => "เพิ่มเข้ากลุ่มเรียน {$cg['name']} แบบกลุ่ม"
                ]);
                if ($res['success']) {
                    $updated[] = $sId;
                }
            }
        }

        return [
            'success' => true,
            'class_group_name' => $cg['name'],
            'updated_count' => count($updated)
        ];
    }

    /**
     * Assign Course Bundle (Section 19, 20)
     */
    public function assignBundle(int $studentId, string $bundleName, array $courseIds, array $data = []): array {
        $bundleId = 'BNDL-' . strtoupper(substr(md5($bundleName . microtime()), 0, 8));
        $createdEnrollments = [];

        foreach ($courseIds as $cId) {
            $cId = (int)$cId;
            $res = $this->assignCourseAccess($studentId, $cId, array_merge($data, [
                'access_type' => 'bundle',
                'bundle_id' => $bundleId,
                'notes' => "มาจากแพ็กเกจ {$bundleName} (Bundle ID: {$bundleId})"
            ]));
            if ($res['success']) {
                $createdEnrollments[] = $res['enrollment_id'];
            }
        }

        return [
            'success' => true,
            'bundle_id' => $bundleId,
            'bundle_name' => $bundleName,
            'created_enrollments' => $createdEnrollments
        ];
    }

    /**
     * Extend Course Access (+7d, +30d, +90d or specific date) (Section 23)
     */
    public function extendAccess(int $enrollmentId, string|int $daysOrDate, ?int $performedBy = null, string $performedByName = 'Admin'): array {
        $en = $this->pdo->query("SELECT * FROM enrollments WHERE id = {$enrollmentId}")->fetch(PDO::FETCH_ASSOC);
        if (!$en) return ['success' => false, 'error' => 'ไม่พบข้อมูลสิทธิ์คอร์ส'];

        $currentExpire = $en['end_date'] ?: ($en['expires_at'] ? date('Y-m-d', strtotime($en['expires_at'])) : date('Y-m-d'));
        // If already passed, extend from today
        $baseDate = (strtotime($currentExpire) < time()) ? date('Y-m-d') : $currentExpire;

        if (is_numeric($daysOrDate)) {
            $days = (int)$daysOrDate;
            $newDate = date('Y-m-d', strtotime("{$baseDate} +{$days} days"));
        } else {
            $newDate = date('Y-m-d', strtotime((string)$daysOrDate));
        }

        $newExpiresAt = "{$newDate} 23:59:59";
        $stmt = $this->pdo->prepare("
            UPDATE enrollments
            SET end_date = ?, expires_at = ?, status = 'active'
            WHERE id = ?
        ");
        $stmt->execute([$newDate, $newExpiresAt, $enrollmentId]);

        $this->logAudit($enrollmentId, (int)$en['user_id'], (int)$en['course_id'], 'extended', "ขยายเวลาเรียนถึง {$newDate}", $performedBy, $performedByName);

        return [
            'success' => true,
            'new_end_date' => $newDate,
            'message' => "ขยายเวลาสิทธิ์การเรียนถึง {$newDate} เรียบร้อยแล้ว"
        ];
    }

    /**
     * Update Status: Pause, Revoke, Complete, Active (Section 22, 24)
     */
    public function updateStatus(int $enrollmentId, string $newStatus, ?int $performedBy = null, string $performedByName = 'Admin', string $reason = ''): array {
        $validStatuses = ['active', 'trial', 'pending', 'expired', 'paused', 'revoked', 'completed'];
        if (!in_array($newStatus, $validStatuses, true)) {
            return ['success' => false, 'error' => 'สถานะไม่ถูกต้อง'];
        }

        $en = $this->pdo->query("SELECT * FROM enrollments WHERE id = {$enrollmentId}")->fetch(PDO::FETCH_ASSOC);
        if (!$en) return ['success' => false, 'error' => 'ไม่พบข้อมูลสิทธิ์คอร์ส'];

        $stmt = $this->pdo->prepare("UPDATE enrollments SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $enrollmentId]);

        $statusTextMap = [
            'active' => 'เปิดใช้งานสิทธิ์',
            'paused' => 'พักสิทธิ์ชั่วคราว',
            'revoked' => 'ระงับสิทธิ์การเรียน',
            'expired' => 'หมดอายุ',
            'completed' => 'เรียนจบแล้ว'
        ];
        $label = $statusTextMap[$newStatus] ?? $newStatus;
        $detail = $reason ? "{$label}: {$reason}" : $label;

        $this->logAudit($enrollmentId, (int)$en['user_id'], (int)$en['course_id'], $newStatus, $detail, $performedBy, $performedByName);

        return ['success' => true, 'status' => $newStatus, 'message' => "เปลี่ยนสถานะเป็น {$label} เรียบร้อยแล้ว"];
    }

    /**
     * Change Class Group (Section 25)
     */
    public function changeClassGroup(int $enrollmentId, int $newClassGroupId, ?int $performedBy = null, string $performedByName = 'Admin'): array {
        $en = $this->pdo->query("SELECT * FROM enrollments WHERE id = {$enrollmentId}")->fetch(PDO::FETCH_ASSOC);
        if (!$en) return ['success' => false, 'error' => 'ไม่พบข้อมูลสิทธิ์คอร์ส'];

        $cg = $this->pdo->query("SELECT * FROM class_groups WHERE id = {$newClassGroupId}")->fetch(PDO::FETCH_ASSOC);
        if (!$cg) return ['success' => false, 'error' => 'ไม่พบกลุ่มเรียนที่ระบุ'];

        $stmt = $this->pdo->prepare("UPDATE enrollments SET class_group_id = ? WHERE id = ?");
        $stmt->execute([$newClassGroupId, $enrollmentId]);

        $this->logAudit($enrollmentId, (int)$en['user_id'], (int)$en['course_id'], 'class_changed', "ย้ายกลุ่มเรียนเป็น {$cg['name']}", $performedBy, $performedByName);

        return ['success' => true, 'new_class_group' => $cg['name'], 'message' => "ย้ายกลุ่มเรียนเป็น {$cg['name']} เรียบร้อยแล้ว"];
    }

    /**
     * Real-time Access Guard Check (Section 11)
     */
    public function checkAccess(int $studentId, int $courseId): array {
        $stmt = $this->pdo->prepare("
            SELECT e.*, c.title AS course_title, c.subject, c.level,
                   cg.name AS class_group_name, cg.schedule_text, cg.room
            FROM enrollments e
            INNER JOIN courses c ON c.id = e.course_id
            LEFT JOIN class_groups cg ON cg.id = e.class_group_id
            WHERE e.user_id = ? AND e.course_id = ?
            ORDER BY e.id DESC
            LIMIT 1
        ");
        $stmt->execute([$studentId, $courseId]);
        $en = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$en) {
            return [
                'has_access' => false,
                'status' => 'not_enrolled',
                'reason' => 'คุณยังไม่ได้ลงทะเบียนในคอร์สนี้'
            ];
        }

        // Check expiration
        if (!empty($en['expires_at']) && strtotime($en['expires_at']) < time()) {
            if ($en['status'] === 'active') {
                $this->pdo->prepare("UPDATE enrollments SET status = 'expired' WHERE id = ?")->execute([$en['id']]);
                $en['status'] = 'expired';
            }
            return [
                'has_access' => false,
                'status' => 'expired',
                'reason' => 'สิทธิ์การเข้าเรียนในคอร์สนี้หมดอายุแล้ว'
            ];
        }

        if ($en['status'] === 'paused') {
            return [
                'has_access' => false,
                'status' => 'paused',
                'reason' => 'สิทธิ์การเข้าเรียนถูกพักการใช้งานชั่วคราว'
            ];
        }

        if ($en['status'] === 'revoked') {
            return [
                'has_access' => false,
                'status' => 'revoked',
                'reason' => 'สิทธิ์การเข้าเรียนในคอร์สนี้ถูกยกเลิกแล้ว'
            ];
        }

        if (in_array($en['status'], ['active', 'trial'], true)) {
            return [
                'has_access' => true,
                'status' => $en['status'],
                'enrollment' => $en
            ];
        }

        return [
            'has_access' => false,
            'status' => $en['status'],
            'reason' => 'สถานะการลงทะเบียนไม่พร้อมใช้งาน'
        ];
    }

    /**
     * Get Student's Detailed Course Access List (Section 3, 29)
     */
    public function getStudentCourseAccess(int $studentId): array {
        $stmt = $this->pdo->prepare("
            SELECT e.*, c.title AS course_title, c.subject, c.level, c.cover_image, c.price,
                   cg.name AS class_group_name, cg.schedule_day, cg.schedule_time, cg.schedule_text,
                   cg.capacity, cg.learning_mode AS class_mode,
                   u_t.first_name AS teacher_first_name, u_t.last_name AS teacher_last_name, u_t.nickname AS teacher_nickname
            FROM enrollments e
            INNER JOIN courses c ON c.id = e.course_id
            LEFT JOIN class_groups cg ON cg.id = e.class_group_id
            LEFT JOIN users u_t ON u_t.id = cg.teacher_id
            WHERE e.user_id = ?
            ORDER BY 
                CASE e.status 
                    WHEN 'active' THEN 1 
                    WHEN 'trial' THEN 2 
                    WHEN 'pending' THEN 3 
                    WHEN 'paused' THEN 4 
                    WHEN 'expired' THEN 5 
                    ELSE 6 
                END,
                e.enrolled_at DESC
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get Student's Combined Weekly Schedule from all active enrollments (Section 34)
     */
    public function getStudentCombinedSchedule(int $studentId): array {
        $stmt = $this->pdo->prepare("
            SELECT cg.schedule_day, cg.schedule_time, cg.schedule_text, cg.name AS class_group_name,
                   cg.room, c.title AS course_title, c.subject,
                   CONCAT_WS(' ', u.first_name, u.last_name) AS teacher_name
            FROM enrollments e
            INNER JOIN class_groups cg ON cg.id = e.class_group_id
            INNER JOIN courses c ON c.id = e.course_id
            LEFT JOIN users u ON u.id = cg.teacher_id
            WHERE e.user_id = ? AND e.status IN ('active', 'trial') AND (e.expires_at IS NULL OR e.expires_at >= NOW())
            ORDER BY 
                FIELD(cg.schedule_day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                cg.schedule_time ASC
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Course Access Matrix for Admin (Section 38)
     */
    public function getCourseAccessMatrix(?string $grade = null): array {
        // Fetch courses
        $courses = $this->pdo->query("SELECT id, title, subject, level FROM courses WHERE status = 'active' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Fetch students
        $stWhere = "role = 'student' AND is_active = 1";
        $params = [];
        if ($grade) {
            $stWhere .= " AND grade = ?";
            $params[] = $grade;
        }
        $stStmt = $this->pdo->prepare("SELECT id, first_name, last_name, nickname, grade, email, avatar_url FROM users WHERE {$stWhere} ORDER BY grade ASC, first_name ASC");
        $stStmt->execute($params);
        $students = $stStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all active enrollments
        $enMap = [];
        $ens = $this->pdo->query("
            SELECT e.user_id, e.course_id, e.status, e.access_type, e.learning_mode, cg.name AS class_group_name
            FROM enrollments e
            LEFT JOIN class_groups cg ON cg.id = e.class_group_id
            WHERE e.status IN ('active', 'trial')
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($ens as $row) {
            $enMap[$row['user_id']][$row['course_id']] = [
                'has_access' => true,
                'status' => $row['status'],
                'access_type' => $row['access_type'],
                'learning_mode' => $row['learning_mode'] ?? 'online',
                'class_group_name' => $row['class_group_name']
            ];
        }

        $formattedStudents = [];
        $matrixRows = [];
        foreach ($students as $st) {
            $accesses = [];
            foreach ($courses as $c) {
                $accesses[$c['id']] = $enMap[$st['id']][$c['id']] ?? null;
            }
            $formattedStudents[] = [
                'id' => (int)$st['id'],
                'name' => trim($st['first_name'] . ' ' . $st['last_name']),
                'nickname' => $st['nickname'],
                'grade' => $st['grade'] ?: 'ม.5',
                'email' => $st['email'],
                'avatar_url' => $st['avatar_url'] ?? '',
                'courses' => $accesses
            ];
            $matrixRows[] = [
                'student' => $st,
                'accesses' => $accesses
            ];
        }

        return [
            'courses' => $courses,
            'students' => $formattedStudents,
            'rows' => $matrixRows
        ];
    }

    /**
     * Enrollment Dashboard Metrics (Section 45, 46)
     */
    public function getMetrics(): array {
        $totalStudents = (int)$this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1")->fetchColumn();
        $activeEnrollments = (int)$this->pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'active'")->fetchColumn();
        $trialStudents = (int)$this->pdo->query("SELECT COUNT(DISTINCT user_id) FROM enrollments WHERE status = 'trial'")->fetchColumn();
        $coursesSold = (int)$this->pdo->query("SELECT COUNT(DISTINCT course_id) FROM enrollments WHERE status IN ('active', 'trial')")->fetchColumn();
        
        // Expiring in next 7 days
        $expiringSoon = (int)$this->pdo->query("
            SELECT COUNT(*) FROM enrollments 
            WHERE status = 'active' AND expires_at IS NOT NULL 
            AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        ")->fetchColumn();

        return [
            'total_students' => $totalStudents,
            'active_enrollments' => $activeEnrollments,
            'courses_sold' => $coursesSold,
            'trial_students' => $trialStudents,
            'expiring_soon' => $expiringSoon
        ];
    }

    /**
     * Audit logger (Section 41)
     */
    public function logAudit(?int $enrollmentId, int $studentId, int $courseId, string $action, string $details = '', ?int $performedBy = null, string $performedByName = 'Admin'): void {
        try {
            $ins = $this->pdo->prepare("
                INSERT INTO enrollment_audit_logs (enrollment_id, student_id, course_id, action, details, performed_by, performed_by_name)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([$enrollmentId, $studentId, $courseId, $action, $details, $performedBy, $performedByName]);
        } catch (Throwable $e) {
            error_log("Enrollment audit log failed: " . $e->getMessage());
        }
    }

    /**
     * Get Student Audit History (Section 41)
     */
    public function getStudentAuditLogs(int $studentId): array {
        $stmt = $this->pdo->prepare("
            SELECT l.*, c.title AS course_title
            FROM enrollment_audit_logs l
            LEFT JOIN courses c ON c.id = l.course_id
            WHERE l.student_id = ?
            ORDER BY l.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
