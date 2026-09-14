<?php
declare(strict_types=1);

function removeDuplicatedOptionBlock(string $questionText, array $options): string
{
    if (count($options) < 2 || count($options) > 10) return trim($questionText);

    $labels = range('A', 'J');
    $parts = [];
    foreach ($options as $index => $option) {
        $option = preg_replace('/^(?:[A-J]|[0-9]+)[.)]\s*/iu', '', trim((string) $option));
        if ($option === '') return trim($questionText);
        $quotedOption = preg_quote($option, '/');
        $quotedOption = preg_replace('/\s+/u', '\\s+', $quotedOption);
        $parts[] = $labels[$index] . '\\s*[.)]\\s*' . $quotedOption;
    }

    // Only remove a complete A–J block at the end of the question. This keeps
    // labels that legitimately occur in a passage or in the question itself.
    $pattern = '/(?:^|\\R)\\h*(?:[0-9]+\\h*[.)]\\h*)?' . implode('\\s*', $parts) . '\\s*$/isu';
    $cleaned = preg_replace($pattern, '', $questionText);

    return trim($cleaned ?? $questionText);
}

function validateExamQuestions(array $questions, bool $fourOptions = false, bool $requireExplanation = false): array
{
    if (!$questions || count($questions) > 100 || array_keys($questions) !== range(0, count($questions) - 1)) {
        throw new InvalidArgumentException('ข้อสอบต้องมีคำถาม 1–100 ข้อในรูปแบบรายการ');
    }
    foreach ($questions as $index => &$question) {
        $error = 'คำถามข้อที่ ' . ($index + 1) . ' ไม่สมบูรณ์: ';
        if (!is_array($question)) throw new InvalidArgumentException($error . 'รูปแบบไม่ถูกต้อง');
        $text = $question['questionText'] ?? $question['question'] ?? null;
        $options = $question['options'] ?? null;
        $answer = $question['correctAnswerIndex'] ?? $question['correctAnswer'] ?? null;
        if (!is_string($text) || trim($text) === '' || mb_strlen($text) > 10000) {
            throw new InvalidArgumentException($error . 'ข้อความคำถามต้องมี 1–10000 ตัวอักษร');
        }
        if (!is_array($options) || count($options) < 2 || count($options) > 10 || ($fourOptions && count($options) !== 4)) {
            throw new InvalidArgumentException($error . 'จำนวนตัวเลือกไม่ถูกต้อง');
        }
        foreach ($options as $option) {
            if (!is_string($option) || trim($option) === '' || mb_strlen($option) > 2000) {
                throw new InvalidArgumentException($error . 'ตัวเลือกว่างหรือยาวเกินไป');
            }
        }
        $options = array_map('trim', array_values($options));
        $normalized = array_map(static fn(string $option): string => mb_strtolower(preg_replace('/^(?:[A-Ja-jกขคงจฉชซฌญ]|[0-9]+)[.)]\\s*/u', '', $option)), $options);
        if (count(array_unique($normalized)) !== count($options)) throw new InvalidArgumentException($error . 'ตัวเลือกซ้ำกัน');
        if (!is_int($answer) || $answer < 0 || $answer >= count($options)) {
            throw new InvalidArgumentException($error . 'ตำแหน่งเฉลยไม่ถูกต้อง');
        }
        foreach (['explanation', 'skill', 'difficulty', 'passage'] as $field) {
            if (isset($question[$field]) && !is_string($question[$field])) throw new InvalidArgumentException($error . $field . ' ต้องเป็นข้อความ');
        }
        if ($requireExplanation && trim($question['explanation'] ?? '') === '') throw new InvalidArgumentException($error . 'ไม่มีคำอธิบายเฉลย');
        if (isset($question['skill'])) $question['skill'] = mb_substr($question['skill'], 0, 100);
        if (isset($question['difficulty'])) $question['difficulty'] = mb_substr($question['difficulty'], 0, 50);
        $question['questionText'] = removeDuplicatedOptionBlock($text, $options);
        $question['options'] = $options;
        $question['correctAnswerIndex'] = $answer;
    }
    unset($question);
    return $questions;
}
