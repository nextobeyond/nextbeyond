<?php
/** NEXTBEYOND V2 — Phase 5 end-to-end verification. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/phase3-mastery-service.php';
require_once __DIR__ . '/../includes/phase5-mastery-service.php';

use NextBeyond\Mastery\InterventionService;
use NextBeyond\Mastery\LearningDecisionService;
use NextBeyond\Mastery\MasteryCheckService;

$passed=0;$failed=0;
function p5check(string $name,bool $ok,string $detail=''):void{global$passed,$failed;echo($ok?' [PASS] ':' [FAIL] ').$name.($detail!==''?" ({$detail})":'')."\n";$ok?$passed++:$failed++;}
function p5gap(PDO $pdo,int $sid,int $cid,string $topic,float $mastery=40.0,string $blocking='non_blocking'):int{
    $severity=$blocking==='blocking'?'critical':'high';
    $pdo->prepare("INSERT INTO student_learning_gaps(student_id,course_id,subject,topic_name,skill_name,gap_score,severity,confidence,evidence_count,recent_accuracy,mastery_score,trend,status,blocking_mode)
      VALUES(?,?,'ภาษาอังกฤษ',?,'Present Simple',70,?,'high',8,?,?, 'stable','open',?)")
      ->execute([$sid,$cid,$topic,$severity,$mastery,$mastery,$blocking]);return(int)$pdo->lastInsertId();
}
function p5evidence(Phase3MasteryService $p3,int $sid,int $cid,string $topic,array $scores,array $sources):void{
    foreach($scores as$i=>$score)$p3->recordLearningEvidence(['student_id'=>$sid,'course_id'=>$cid,'topic_name'=>$topic,'skill'=>'Present Simple','source_type'=>$sources[$i%count($sources)],'source_id'=>'p5-'.$topic.'-'.$i.'-'.bin2hex(random_bytes(2)),'score'=>$score,'max_score'=>100,'difficulty'=>'medium','is_correct'=>$score>=60]);
}

echo "NEXTBEYOND V2 — PHASE 5 VERIFICATION\n";
$pdo->exec(file_get_contents(__DIR__.'/../database/phase5_mastery_intervention.sql'));
$courseId=(int)$pdo->query("SELECT id FROM courses WHERE subject LIKE '%อังกฤษ%' ORDER BY id LIMIT 1")->fetchColumn();
$teacherId=(int)$pdo->query("SELECT id FROM users WHERE role IN ('admin','teacher') AND is_active=1 ORDER BY role='admin' DESC,id LIMIT 1")->fetchColumn();
if(!$courseId||!$teacherId)throw new RuntimeException('An English course and active teacher/admin are required');
$email='phase5.verify.'.bin2hex(random_bytes(4)).'@example.test';
$pdo->prepare("INSERT INTO users(email,password_hash,first_name,last_name,grade,role,is_active) VALUES(?,?,'Phase5','Verifier','ม.5','student',1)")->execute([$email,password_hash('test',PASSWORD_DEFAULT)]);
$studentId=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO enrollments(user_id,course_id,status,access_type,payment_status,assigned_by,assigned_by_name) VALUES(?,?,'active','manual','paid',?,'Phase 5 Test')")->execute([$studentId,$courseId,$teacherId]);

$p3=new Phase3MasteryService($pdo);$mastery=new MasteryCheckService($pdo);$decisions=new LearningDecisionService($pdo);$interventions=new InterventionService($pdo);
$ownedAssignments=[];$ownedWorksheets=[];$ownedSessions=[];$ownedEvents=[];

try{
    // 1 + 6: low post-remediation evidence continues the loop and a critical branch can block.
    $mainGap=p5gap($pdo,$studentId,$courseId,'Present Simple',40,'blocking');
    p5evidence($p3,$studentId,$courseId,'Present Simple',array_fill(0,8,40),['worksheet','remediation']);
    $low=$mastery->evaluate($studentId,$courseId,'Present Simple','Present Simple',['idempotency_key'=>'p5-low-'.$studentId]);
    $lowDecision=$decisions->applySystemDecision($low);
    p5check('1. Low remediation result does not advance',$low['recommendation']!=='advance'&&$low['mastery_status']==='developing',$low['recommendation']);
    $block=$pdo->prepare("SELECT step_type,is_blocking FROM adaptive_roadmap_steps WHERE gap_id=? AND status='available' ORDER BY id DESC LIMIT 1");$block->execute([$mainGap]);$blockRow=$block->fetch(PDO::FETCH_ASSOC);
    p5check('6. Prerequisite weakness inserts a blocking adaptive step',!empty($blockRow)&&(int)$blockRow['is_blocking']===1&&in_array($blockRow['step_type'],['remediation','additional_practice'],true));

    // 2: strong evidence alone can recommend advance but cannot resolve without explicit verification.
    $evidenceOnlyGap=p5gap($pdo,$studentId,$courseId,'Evidence Only',88);
    p5evidence($p3,$studentId,$courseId,'Evidence Only',array_fill(0,8,92),['remediation','homework']);
    $evidenceOnly=$mastery->evaluate($studentId,$courseId,'Evidence Only','',['idempotency_key'=>'p5-evidence-only-'.$studentId]);
    $decisions->applySystemDecision($evidenceOnly);
    $s=$pdo->prepare('SELECT status FROM student_learning_gaps WHERE id=?');$s->execute([$evidenceOnlyGap]);
    p5check('2. High remediation evidence waits for an independent check',$evidenceOnly['recommendation']==='advance'&&$s->fetchColumn()!=='resolved');

    // 4: a high percentage with too little evidence never unlocks mastery.
    $thinGap=p5gap($pdo,$studentId,$courseId,'Thin Evidence',95);
    p5evidence($p3,$studentId,$courseId,'Thin Evidence',[100,100,100],['worksheet','posttest','homework']);
    $thin=$mastery->evaluate($studentId,$courseId,'Thin Evidence','',['idempotency_key'=>'p5-thin-'.$studentId]);
    $decisions->applySystemDecision($thin);$s->execute([$thinGap]);
    p5check('4. Insufficient evidence cannot auto-unlock',$thin['mastery_status']==='not_enough_evidence'&&$s->fetchColumn()!=='resolved',$thin['mastery_status']);

    // 5: repeated low cycles produce exactly one active human-intervention recommendation.
    $repeatGap=p5gap($pdo,$studentId,$courseId,'Repeated Difficulty',35);
    p5evidence($p3,$studentId,$courseId,'Repeated Difficulty',array_fill(0,8,35),['worksheet','remediation']);
    for($i=1;$i<=2;$i++)$pdo->prepare("INSERT INTO personalized_remediations(student_id,course_id,gap_id,topic_name,question_count,status,completed_at) VALUES(?,?,?,?,5,'completed',NOW())")->execute([$studentId,$courseId,$repeatGap,'Repeated Difficulty']);
    $repeat=$mastery->evaluate($studentId,$courseId,'Repeated Difficulty','',['idempotency_key'=>'p5-repeat-'.$studentId]);$repeatDecision=$decisions->applySystemDecision($repeat);$decisions->applySystemDecision($repeat);
    $activeIntervention=$pdo->prepare("SELECT COUNT(*) FROM teacher_interventions WHERE gap_id=? AND status IN ('recommended','planned','in_progress','monitoring')");$activeIntervention->execute([$repeatGap]);
    p5check('5. Repeated failure escalates to one intervention',$repeat['recommendation']==='teacher_intervention'&&(int)$activeIntervention->fetchColumn()===1);

    // 3 + 10: an explicit check uses the normal runner, is independently selected and is idempotent.
    $recommended=$mastery->recommendQuestions($studentId,$courseId,$mainGap,5);
    $questionIds=array_slice(array_map('intval',array_column($recommended['results']??[],'id')),0,5);
    p5check('3a. Independent mastery questions are available',count($questionIds)>=3,'found '.count($questionIds));
    if(count($questionIds)>=3){
        $created=$mastery->createAssignment(['student_id'=>$studentId,'course_id'=>$courseId,'gap_id'=>$mainGap,'question_ids'=>$questionIds,'created_by'=>$teacherId,'created_by_name'=>'Phase 5 Test']);
        $duplicate=$mastery->createAssignment(['student_id'=>$studentId,'course_id'=>$courseId,'gap_id'=>$mainGap,'question_ids'=>$questionIds,'created_by'=>$teacherId]);
        $ownedAssignments[]=(int)$created['assignment_id'];$ownedWorksheets[]=(int)$created['worksheet_id'];
        p5check('10a. Duplicate active mastery assignment is prevented',!empty($duplicate['duplicate']));
        $submission=$p3->getOrCreateSubmission((int)$created['assignment_id'],$studentId);$details=$p3->getAssignmentDetails((int)$created['assignment_id']);
        foreach($details['questions'] as$q)$p3->autosaveAnswer((int)$submission['id'],$studentId,(int)$q['id'],(string)$q['correct_answer']);
        $done=$p3->submitActivity((int)$submission['id'],$studentId);
        $s->execute([$mainGap]);
        p5check('3b. Passing explicit mastery check resolves and advances',!empty($done['learning_decision'])&&$s->fetchColumn()==='resolved',(string)($done['learning_decision']['effective_decision']??'none'));
        $decisionCount=$pdo->prepare('SELECT COUNT(*) FROM learning_decisions WHERE mastery_check_id=?');$decisionCount->execute([(int)($done['learning_decision']['mastery_check']['id']??0)]);
        p5check('10b. Decision application is idempotent',(int)$decisionCount->fetchColumn()===1);
    }

    // 7: later low evidence reopens a mastered gap and preserves history.
    p5evidence($p3,$studentId,$courseId,'Present Simple',array_fill(0,8,20),['worksheet','homework']);
    $regression=$mastery->evaluate($studentId,$courseId,'Present Simple','Present Simple',['idempotency_key'=>'p5-regression-'.$studentId]);
    $s->execute([$mainGap]);$history=$pdo->prepare("SELECT COUNT(*) FROM gap_resolution_history WHERE gap_id=? AND event_type='reopened' AND resolution_type='regression'");$history->execute([$mainGap]);
    p5check('7. Regression reopens a mastered gap',$regression['mastery_status']==='regression_detected'&&$s->fetchColumn()==='open'&&(int)$history->fetchColumn()===1);

    // 8 + 9 + 10: live follow-up enters the queue once and scheduling creates both calendar and classroom records.
    $liveGap=p5gap($pdo,$studentId,$courseId,'Live Follow-up',55);
    $live=$interventions->createManual(['gap_id'=>$liveGap,'trigger_type'=>'live_class_followup','recommended_action'=>'teacher_feedback','teacher_notes'=>'ต้องการทบทวนหลังคาบ'],$teacherId);
    $liveDup=$interventions->createManual(['gap_id'=>$liveGap,'trigger_type'=>'live_class_followup','recommended_action'=>'teacher_feedback'],$teacherId);
    $liveId=(int)($live['intervention_id']??$liveDup['intervention']['id']??0);
    $trigger=$pdo->prepare('SELECT trigger_type FROM teacher_interventions WHERE id=?');$trigger->execute([$liveId]);
    p5check('8. Live-class follow-up creates a teacher case',$liveId>0&&$trigger->fetchColumn()==='live_class_followup');
    p5check('10c. Duplicate active intervention is prevented',!empty($liveDup['duplicate']));
    $scheduled=$interventions->schedule($liveId,['event_date'=>date('Y-m-d',strtotime('+1 day')),'start_time'=>'16:00','duration_minutes'=>30,'mode'=>'online'],$teacherId);
    $ownedSessions[]=(string)$scheduled['session_id'];$ownedEvents[]=(int)$scheduled['calendar_event_id'];
    $link=$pdo->prepare('SELECT COUNT(*) FROM classroom_sessions cs JOIN calendar_events ce ON ce.id=cs.calendar_event_id JOIN session_participants sp ON sp.session_id=cs.id WHERE cs.id=? AND cs.intervention_id=? AND sp.student_id=?');$link->execute([$scheduled['session_id'],$liveId,$studentId]);
    p5check('9. Support booking creates linked calendar, room and participant',(int)$link->fetchColumn()===1,'PIN '.$scheduled['session_pin']);

    $audit=$pdo->prepare('SELECT COUNT(*) FROM learning_audit_log WHERE student_id=?');$audit->execute([$studentId]);
    p5check('Teacher/system decisions are auditable',(int)$audit->fetchColumn()>=5,(string)$audit->fetchColumn());
}finally{
    $pdo->prepare('DELETE FROM session_topics WHERE session_id IN (SELECT id FROM classroom_sessions WHERE intervention_id IN (SELECT id FROM teacher_interventions WHERE student_id=?))')->execute([$studentId]);
    $pdo->prepare('DELETE FROM session_participants WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM classroom_sessions WHERE intervention_id IN (SELECT id FROM teacher_interventions WHERE student_id=?)')->execute([$studentId]);
    $pdo->prepare("DELETE FROM calendar_events WHERE notes LIKE 'Intervention #%'")->execute();
    $pdo->prepare('DELETE FROM adaptive_roadmap_steps WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM learning_audit_log WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM gap_resolution_history WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM adaptive_learning_cycles WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM teacher_interventions WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM learning_decisions WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM mastery_check_questions WHERE mastery_check_assignment_id IN (SELECT id FROM mastery_check_assignments WHERE student_id=?)')->execute([$studentId]);
    $pdo->prepare('DELETE FROM mastery_check_assignments WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM mastery_checks WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM activity_answers WHERE submission_id IN (SELECT id FROM activity_submissions WHERE student_id=?)')->execute([$studentId]);
    $pdo->prepare('DELETE FROM activity_submissions WHERE student_id=?')->execute([$studentId]);
    foreach($ownedAssignments as$id)$pdo->prepare('DELETE FROM worksheet_assignments WHERE id=?')->execute([$id]);
    foreach($ownedWorksheets as$id){$pdo->prepare('DELETE FROM worksheet_questions WHERE worksheet_id=?')->execute([$id]);$pdo->prepare('DELETE FROM worksheets WHERE id=?')->execute([$id]);}
    $pdo->prepare('DELETE FROM personalized_remediations WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM learning_evidence WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM question_exposures WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM question_search_logs WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM student_learning_gaps WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM topic_mastery WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM student_learning_profiles WHERE student_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM enrollments WHERE user_id=?')->execute([$studentId]);
    $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$studentId]);
}
echo "\nResult: {$passed} passed, {$failed} failed\n";exit($failed===0?0:1);
