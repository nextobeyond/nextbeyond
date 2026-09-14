<?php
require __DIR__ . '/../admin/ai-exam-app/generation-context.php';
require __DIR__ . '/../admin/ai-exam-app/question-validation.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$valid = ['sourceMode'=>'brief','type'=>'similar','topic'=>'เศษส่วน','grade'=>'ป.4'];
check(examGenerationContext($valid)['topic']==='เศษส่วน','Brief context');
check(examGenerationContext([])['sourceMode']==='document','Legacy requests use documents');
foreach ([['topic'=>''],['grade'=>''],['type'=>'copy'],['sourceMode'=>'bad'],['difficulty'=>'bad'],['topic'=>[]],['type'=>'levels','counts'=>['easy'=>-1]],['type'=>'levels','counts'=>['easy'=>0]],['type'=>'levels','counts'=>['easy'=>101]]] as $override) {
    try { examGenerationContext(array_replace($valid,$override)); } catch (InvalidArgumentException $e) { continue; }
    throw new RuntimeException('Invalid brief accepted');
}
examGenerationContext(array_replace($valid,['type'=>'levels','counts'=>['easy'=>1,'hard'=>2]]));
$examples=json_decode(file_get_contents(__DIR__.'/../admin/ai-exam-app/prompts/examples.json'),true);
check(count($examples)===10,'Ten examples');
foreach ($examples as $q) validateExamQuestions([$q],true,true);
$primary=examStyleExamples('คณิตศาสตร์ (Math)','ป.4');
check(str_contains($primary,'การบวกเศษส่วน')&&!str_contains($primary,'สมการเชิงเส้น'),'Grade band selection');
check(examStyleExamples('วิชาใหม่','ม.1')==='','Custom subjects do not receive unrelated examples');
check(str_contains(examBriefPrompt(2,'hard','หัวข้อ: เศษส่วน',''),'ไม่มีเอกสาร'),'Brief does not pretend to have a source');
echo "PASS: brief validation, legacy source mode, level counts and ten example structures\n";
