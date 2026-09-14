<?php
declare(strict_types=1);

function examSubjectDefaults(): array
{
    return [
        'คณิตศาสตร์ (Math)' => 'math',
        'วิทยาศาสตร์ (Science)' => 'science',
        'ภาษาอังกฤษ (English)' => 'english',
        'ภาษาไทย (Thai)' => 'thai',
        'สังคมศึกษา (Social Studies)' => 'social',
    ];
}

// One-time migration: preserve custom prompts, disabled subjects and later edits.
function ensureSubjectPrompts(PDO $pdo): void
{
    // Production uses utf8mb4_unicode_ci for ai_subjects while some older
    // connections default to utf8mb4_general_ci. Keep bound Thai text and
    // the subject_name column in the same collation before comparing them.
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Hosted database users may update existing tables without CREATE permission.
    // Do not issue CREATE TABLE on every API request when the tables are present.
    if (!$pdo->query("SHOW TABLES LIKE 'ai_subjects'")->fetchColumn()) {
        $pdo->exec("CREATE TABLE ai_subjects (
            id INT AUTO_INCREMENT PRIMARY KEY, subject_name VARCHAR(255) NOT NULL,
            prompt_md TEXT NULL, is_active TINYINT(1) DEFAULT 1, sort_order INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    if (!$pdo->query("SHOW TABLES LIKE 'ai_exam_migrations'")->fetchColumn()) {
        $pdo->exec("CREATE TABLE ai_exam_migrations (
            version VARCHAR(100) PRIMARY KEY, applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $version = 'subject-prompts-v1';
    $check = $pdo->prepare('SELECT version FROM ai_exam_migrations WHERE version = ?');
    $check->execute([$version]);
    if (!$check->fetchColumn()) {
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('INSERT IGNORE INTO ai_exam_migrations (version) VALUES (?)');
            $lock->execute([$version]);
            if ($lock->rowCount() === 1) {
            $find = $pdo->prepare('SELECT id, prompt_md FROM ai_subjects WHERE subject_name IN (?, ?)');
            $insert = $pdo->prepare('INSERT INTO ai_subjects (subject_name, prompt_md, sort_order) VALUES (?, ?, ?)');
            $update = $pdo->prepare('UPDATE ai_subjects SET prompt_md = ? WHERE id = ?');
            $order = 0;
            foreach (examSubjectDefaults() as $name => $file) {
                ++$order;
                $prompt = file_get_contents(__DIR__ . '/prompts/' . $file . '.md');
                if ($prompt === false) throw new RuntimeException('ไม่พบ Prompt เริ่มต้น');
                $find->execute([$name, explode(' (', $name)[0]]);
                $rows = $find->fetchAll(PDO::FETCH_ASSOC);
                if (!$rows) $insert->execute([$name, $prompt, $order]);
                foreach ($rows as $row) {
                    if (trim((string) $row['prompt_md']) === '') $update->execute([$prompt, $row['id']]);
                }
            }
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    // Upgrade only the original Math prompt; keep any prompt an administrator has edited.
    $upgradeVersion = 'subject-prompts-v2-math-examples';
    $check->execute([$upgradeVersion]);
    if (!$check->fetchColumn()) {
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('INSERT IGNORE INTO ai_exam_migrations (version) VALUES (?)');
            $lock->execute([$upgradeVersion]);
            if ($lock->rowCount() === 1) {
                $newPrompt = file_get_contents(__DIR__ . '/prompts/math.md');
                if ($newPrompt === false) throw new RuntimeException('ไม่พบ Prompt คณิตศาสตร์');
                $oldHash = 'e9da771a8fcd4fbd047a7442907acbcdb7e93c839e6cf95fe07fccaca1114172';
                $update = $pdo->prepare('UPDATE ai_subjects SET prompt_md = ? WHERE subject_name IN (?, ?) AND (TRIM(COALESCE(prompt_md, \'\')) = \'\' OR SHA2(prompt_md, 256) = ?)');
                $update->execute([$newPrompt, 'คณิตศาสตร์ (Math)', 'คณิตศาสตร์', $oldHash]);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    // Upgrade only the original Science prompt; keep any prompt an administrator has edited.
    // Keep this identifier within the 32-character limit used by older installations.
    $upgradeVersion = 'subject-prompts-v3-science';
    $check->execute([$upgradeVersion]);
    if (!$check->fetchColumn()) {
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('INSERT IGNORE INTO ai_exam_migrations (version) VALUES (?)');
            $lock->execute([$upgradeVersion]);
            if ($lock->rowCount() === 1) {
                $newPrompt = file_get_contents(__DIR__ . '/prompts/science.md');
                if ($newPrompt === false) throw new RuntimeException('ไม่พบ Prompt วิทยาศาสตร์');
                $oldHash = 'b76a5e543ba7dba896aff47b88e3bba764ba8b932eeb2670bc8b96dbcb437193';
                $update = $pdo->prepare('UPDATE ai_subjects SET prompt_md = ? WHERE subject_name IN (?, ?) AND (TRIM(COALESCE(prompt_md, \'\')) = \'\' OR SHA2(prompt_md, 256) = ?)');
                $update->execute([$newPrompt, 'วิทยาศาสตร์ (Science)', 'วิทยาศาสตร์', $oldHash]);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    // Apply the output-format rule to every saved subject prompt, including
    // prompts that administrators have customized.
    $upgradeVersion = 'subject-prompts-v4-options';
    $check->execute([$upgradeVersion]);
    if ($check->fetchColumn()) return;
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('INSERT IGNORE INTO ai_exam_migrations (version) VALUES (?)');
        $lock->execute([$upgradeVersion]);
        if ($lock->rowCount() === 1) {
            $rule = "\n\nรูปแบบคำตอบปรนัย: questionText ต้องมีเฉพาะโจทย์หรือบริบท ห้ามใส่หมายเลขข้อ ตัวเลือก A, B, C, D หรือเฉลยซ้ำใน questionText ให้ส่งตัวเลือกแต่ละข้อแยกใน options เท่านั้น และข้อความใน options ไม่ต้องขึ้นต้นด้วย A., B., C., D.";
            $subjects = $pdo->query('SELECT id, prompt_md FROM ai_subjects')->fetchAll(PDO::FETCH_ASSOC);
            $update = $pdo->prepare('UPDATE ai_subjects SET prompt_md = ? WHERE id = ?');
            foreach ($subjects as $subject) {
                $prompt = rtrim((string) ($subject['prompt_md'] ?? ''));
                if (str_contains($prompt, 'รูปแบบคำตอบปรนัย:')) continue;
                $update->execute([$prompt . $rule, (int) $subject['id']]);
            }
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
