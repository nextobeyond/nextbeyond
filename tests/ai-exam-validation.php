<?php
require __DIR__ . '/../ai-exam-app/question-validation.php';
$q = ['questionText' => '2 + 3 เท่ากับเท่าใด', 'options' => ['4', '5', '6', '7'], 'correctAnswerIndex' => 1, 'explanation' => '2 + 3 = 5'];
if (validateExamQuestions([$q], true, true)[0]['questionText'] !== $q['questionText']) throw new Exception('Numeric prefix changed');
$invalid = [array_diff_key($q, ['correctAnswerIndex' => true]), array_replace($q, ['correctAnswerIndex' => 4]), array_replace($q, ['correctAnswerIndex' => '1']), array_replace($q, ['options' => ['A. 5', 'B. 5', 'C. 6', 'D. 7']]), array_replace($q, ['explanation' => '']), array_replace($q, ['options' => ['', '5', '6', '7']])];
foreach ($invalid as $question) {
    try { validateExamQuestions([$question], true, true); } catch (InvalidArgumentException $e) { continue; }
    throw new Exception('Invalid question accepted');
}
$copy = array_replace($q, ['options' => ['4', '5', '6', '7', '8']]);
validateExamQuestions([$copy], false, true);
echo "Passed: numeric prefix, invalid answers, duplicate/empty options, explanation, copy option count\n";
