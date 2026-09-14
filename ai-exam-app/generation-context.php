<?php
declare(strict_types=1);

function examGenerationContext(array $input): array
{
    $mode = $input['sourceMode'] ?? 'document';
    if (!in_array($mode, ['document', 'brief'], true)) throw new InvalidArgumentException('รูปแบบแหล่งข้อมูลไม่ถูกต้อง');
    $context = ['sourceMode' => $mode];
    foreach (['topic' => 300, 'grade' => 50, 'details' => 6000] as $field => $limit) {
        $value = $input[$field] ?? '';
        if (!is_string($value) || mb_strlen($value) > $limit) throw new InvalidArgumentException("ข้อมูล {$field} ยาวเกินกำหนดหรือไม่ใช่ข้อความ");
        $context[$field] = trim($value);
    }
    $type = $input['type'] ?? 'copy';
    if (!in_array($type, ['copy', 'similar', 'levels'], true)) throw new InvalidArgumentException('ประเภทการสร้างไม่ถูกต้อง');
    if ($mode === 'brief') {
        if ($type === 'copy') throw new InvalidArgumentException('โหมดคัดลอกต้องมีเอกสารต้นฉบับ');
        if ($context['topic'] === '' || $context['grade'] === '') throw new InvalidArgumentException('กรุณาระบุหัวข้อและระดับชั้นสำหรับการสร้างโดยไม่มีเอกสาร');
    }
    $difficulty = $input['difficulty'] ?? '';
    if (!in_array($difficulty, ['', 'easy', 'medium', 'hard', 'expert'], true)) throw new InvalidArgumentException('ระดับความยากไม่ถูกต้อง');
    if ($type === 'levels') {
        $counts = $input['counts'] ?? null;
        if (!is_array($counts) || !$counts) throw new InvalidArgumentException('กรุณากำหนดจำนวนข้อแยกระดับ');
        $total = 0;
        foreach ($counts as $level => $count) {
            if (!in_array($level, ['easy', 'medium', 'hard', 'expert'], true) || !is_int($count) || $count < 0) throw new InvalidArgumentException('จำนวนข้อในแต่ละระดับต้องเป็นจำนวนเต็มตั้งแต่ 0');
            $total += $count;
        }
        if ($total < 1 || $total > 100) throw new InvalidArgumentException('จำนวนข้อรวมต้องอยู่ระหว่าง 1 ถึง 100 ข้อ');
    }
    return $context;
}

function examStyleExamples(string $subject, string $grade): string
{
    $examples = json_decode((string) file_get_contents(__DIR__ . '/prompts/examples.json'), true, 512, JSON_THROW_ON_ERROR);
    $band = preg_match('/ม\s*\.?\s*[1-6]|มัธยม/u', $grade) ? 'secondary' : (preg_match('/ป\s*\.?\s*[1-6]|ประถม/u', $grade) ? 'primary' : null);
    $name = explode(' (', $subject)[0];
    $selected = array_values(array_filter($examples, static fn(array $example): bool => $example['subject'] === $name && ($band === null || $example['band'] === $band)));
    if (!$selected) return '';
    return "\nตัวอย่างประกอบรูปแบบและคุณภาพ ไม่ใช่เนื้อหาที่ต้องออกสอบหรือหลักสูตร ห้ามคัดลอกคำถาม ตัวเลข หรือบทอ่านของตัวอย่าง หากหัวข้อไม่ตรงให้ใช้เฉพาะวิธีเขียนและอธิบาย ยึดหัวข้อและชั้นเรียนของผู้ใช้ก่อนเสมอ:\n"
        . json_encode(array_slice($selected, 0, 2), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function examBriefPrompt(int $count, string $difficulty, string $brief, string $details): string
{
    $levels = ['easy' => 'ง่าย: แนวคิดพื้นฐานโดยตรง', 'medium' => 'ปานกลาง: เชื่อมโยงและประยุกต์', 'hard' => 'ยาก: วิเคราะห์หลายเงื่อนไข', 'expert' => 'ยากมาก: สังเคราะห์และประเมินเหตุผลภายในขอบเขตชั้นเรียน'];
    return "สร้างข้อสอบใหม่ {$count} ข้อจากโจทย์งานของครูด้านล่าง โดยไม่มีเอกสารหรือแนวข้อสอบต้นฉบับ\n"
        . $brief . "\nคำอธิบายเพิ่มเติม: " . ($details ?: 'ไม่มี')
        . "\nความยาก: " . ($levels[$difficulty] ?? $levels['medium'])
        . "\nวางการกระจายทักษะให้ครอบคลุมหัวข้อและจำนวนที่ขอ ใช้ความรู้พื้นฐานที่มั่นใจได้ เหมาะกับชั้นเรียน ห้ามอ้างว่าได้อ่านเอกสาร ห้ามอ้างตัวชี้วัดหรือแหล่งข้อมูลที่ไม่ได้รับมา"
        . "\nถ้าต้องมีบทอ่าน บทสนทนา ตาราง หรือข้อมูลทดลอง ให้แต่งขึ้นให้ครบในแต่ละข้อและระบุข้อมูลสมมติตามความเหมาะสม ไม่อ้างบทประพันธ์หรือเหตุการณ์สมมติเป็นข้อเท็จจริง หลีกเลี่ยงกฎหมาย สถิติ หรือข่าวล่าสุดที่ไม่มีแหล่งยืนยัน";
}
