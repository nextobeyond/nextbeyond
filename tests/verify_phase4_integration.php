<?php
/** NEXTBEYOND V2 — Phase 4 integration verification. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/phase3-mastery-service.php';
require_once __DIR__ . '/../includes/phase4-adaptive-service.php';

use NextBeyond\Adaptive\GapAnalysisService;
use NextBeyond\Adaptive\QuestionSearchService;
use NextBeyond\Adaptive\RemediationService;

$passed=0;$failed=0;
function p4check(string $name,bool $ok,string $detail=''): void { global $passed,$failed;echo ($ok?' [PASS] ':' [FAIL] ').$name.($detail!==''?" ({$detail})":'')."\n";$ok?$passed++:$failed++; }

echo "NEXTBEYOND V2 — PHASE 4 VERIFICATION\n";
$pdo->exec(file_get_contents(__DIR__.'/../database/phase4_adaptive_learning.sql'));
$courseId=(int)$pdo->query('SELECT id FROM courses ORDER BY id LIMIT 1')->fetchColumn();
if(!$courseId)throw new RuntimeException('A course is required for verification');
$email='phase4.verify.'.bin2hex(random_bytes(4)).'@example.test';
$pdo->prepare("INSERT INTO users(email,password_hash,first_name,last_name,grade,role,is_active) VALUES(?,?,'Phase4','Verifier','ม.5','student',1)")->execute([$email,password_hash('test',PASSWORD_DEFAULT)]);
$studentId=(int)$pdo->lastInsertId();
$assignmentId=$worksheetId=$remediationId=$gapId=0;

try {
    $p3=new Phase3MasteryService($pdo);$gaps=new GapAnalysisService($pdo);$search=new QuestionSearchService($pdo);$rem=new RemediationService($pdo);
    foreach ([['worksheet',45],['homework',50],['posttest',42]] as $i=>$ev) {
        $p3->recordLearningEvidence(['student_id'=>$studentId,'course_id'=>$courseId,'topic_name'=>'Present Perfect','skill'=>'Present Perfect vs Past Simple','source_type'=>$ev[0],'source_id'=>'p4-gap-'.$i,'score'=>$ev[1],'max_score'=>100]);
    }
    $pdo->prepare("INSERT INTO topic_mastery(student_id,course_id,subject,topic_name,mastery_score,confidence_score,evidence_count,source,last_assessed_at)
      SELECT ?,?,COALESCE(subject,'English'),'Present Perfect',44,90,3,'phase4_test',NOW() FROM courses WHERE id=?
      ON DUPLICATE KEY UPDATE mastery_score=44,confidence_score=90,evidence_count=3,last_assessed_at=NOW()")
      ->execute([$studentId,$courseId,$courseId]);
    $calculated=$gaps->recalculateForStudent($studentId,$courseId,'Present Perfect');
    $gap=$calculated[0]??[];$gapId=(int)($gap['id']??0);
    p4check('Repeated Present Perfect evidence creates a high gap',($gap['severity']??'')==='high');
    p4check('Three independent sources produce high confidence',($gap['confidence']??'')==='high');
    p4check('Gap keeps mastery context',abs((float)($gap['mastery_score']??0)-44)<0.01);

    $acid=$search->searchQuestions(['query'=>'Acid Base A-Level calculation 10 ข้อ','actor_id'=>1,'count'=>10]);
    p4check('Natural-language intent parses Chemistry A-Level',($acid['parsed_intent']['subject']??'')==='เคมี'&&($acid['parsed_intent']['level']??'')==='A-Level');
    p4check('No vector provider reports semantic unavailable',($acid['search_capabilities']['semantic']??true)===false);
    p4check('Fallback search still returns existing questions',count($acid['results'])>0,'returned '.count($acid['results']));

    $present=$search->searchQuestions(['query'=>'Present Simple ม.2 daily routine','actor_id'=>1,'count'=>10,'student_id'=>$studentId,'seen_policy'=>'prefer_unseen']);
    p4check('Shared search returns a diverse worksheet-ready set',count($present['results'])>=2);
    $seenSeed=array_slice(array_map('intval',array_column($present['results'],'id')),0,2);
    foreach($seenSeed as $i=>$qid)$pdo->prepare("INSERT INTO question_exposures(question_id,student_id,attempt_count,correct_count,incorrect_count,last_result) VALUES(?,?,1,?,?,?)")->execute([$qid,$studentId,$i===0?0:1,$i===0?1:0,$i===0?'incorrect':'correct']);
    $unseen=$search->searchQuestions(['query'=>'Present Simple ม.2 daily routine','actor_id'=>1,'count'=>5,'student_id'=>$studentId,'seen_policy'=>'prefer_unseen']);
    p4check('Prefer-unseen ranks unexposed questions first',count(array_filter($unseen['results'],fn($q)=>!empty($q['previously_seen'])))===0);
    $retry=$search->searchQuestions(['query'=>'Present Simple ม.2 daily routine','actor_id'=>1,'count'=>5,'student_id'=>$studentId,'seen_policy'=>'retry_incorrect']);
    p4check('Retry-incorrect is distinct from normal retrieval',count($retry['results'])===1&&!empty($retry['results'][0]['previously_seen']));
    $ids=array_slice(array_map('intval',array_column($present['results'],'id')),0,2);
    $created=$rem->createApprovedAssignment(['student_id'=>$studentId,'course_id'=>$courseId,'gap_id'=>$gapId,'question_ids'=>$ids,'created_by'=>1,'created_by_name'=>'Phase 4 Test']);
    $assignmentId=(int)$created['assignment_id'];$worksheetId=(int)$created['worksheet_id'];$remediationId=(int)$created['remediation_id'];
    p4check('Teacher approval creates an existing-runner assignment',$assignmentId>0&&$worksheetId>0);
    $duplicate=$rem->createApprovedAssignment(['student_id'=>$studentId,'course_id'=>$courseId,'gap_id'=>$gapId,'question_ids'=>$ids,'created_by'=>1]);
    p4check('Duplicate active remediation is prevented',!empty($duplicate['duplicate']));
    p4check('Selected student can access remediation',$p3->canStudentAccessAssignment($studentId,$assignmentId));

    $submission=$p3->getOrCreateSubmission($assignmentId,$studentId);$details=$p3->getAssignmentDetails($assignmentId);
    foreach($details['questions'] as $q)$p3->autosaveAnswer((int)$submission['id'],$studentId,(int)$q['id'],(string)$q['correct_answer']);
    $done=$p3->submitActivity((int)$submission['id'],$studentId);
    p4check('Remediation completes through Phase 3 runner',!empty($done['success'])&&(float)$done['score_percent']===100.0);
    $status=$pdo->prepare('SELECT status FROM student_learning_gaps WHERE id=?');$status->execute([$gapId]);
    p4check('Completion moves gap to monitoring, not resolved',$status->fetchColumn()==='monitoring');
    $exposure=$pdo->prepare('SELECT COUNT(*) FROM question_exposures WHERE student_id=? AND attempt_count>0');$exposure->execute([$studentId]);
    p4check('Canonical question exposure is updated',(int)$exposure->fetchColumn()===count($ids));
    $evidence=$pdo->prepare("SELECT COUNT(*) FROM learning_evidence WHERE student_id=? AND source_type='remediation' AND canonical_question_id IS NOT NULL");$evidence->execute([$studentId]);
    p4check('Question-level remediation evidence is recorded',(int)$evidence->fetchColumn()>=count($ids));
} finally {
    // Remove only uniquely-owned verification artifacts.
    $pdo->prepare('DELETE FROM activity_answers WHERE submission_id IN (SELECT id FROM activity_submissions WHERE student_id=?)')->execute([$studentId]);
    $pdo->prepare('DELETE FROM activity_submissions WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM learning_evidence WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM question_exposures WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM remediation_questions WHERE remediation_id IN (SELECT id FROM personalized_remediations WHERE student_id=?)')->execute([$studentId]);
    $pdo->prepare('DELETE FROM personalized_remediations WHERE student_id=?')->execute([$studentId]);
    if($assignmentId)$pdo->prepare('DELETE FROM worksheet_assignments WHERE id=?')->execute([$assignmentId]);
    if($worksheetId){$pdo->prepare('DELETE FROM worksheet_questions WHERE worksheet_id=?')->execute([$worksheetId]);$pdo->prepare('DELETE FROM worksheets WHERE id=?')->execute([$worksheetId]);}
    $pdo->prepare('DELETE FROM student_learning_gaps WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM topic_mastery WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM student_learning_profiles WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM question_search_logs WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$studentId]);
}
echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed===0?0:1);
