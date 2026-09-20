<?php
/** Teacher/Admin API for gaps, smart retrieval and reviewed remediation creation. */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/../includes/phase4-adaptive-service.php';
require_once __DIR__ . '/../includes/phase5-mastery-service.php';

use NextBeyond\Adaptive\GapAnalysisService;
use NextBeyond\Adaptive\QuestionSearchService;
use NextBeyond\Adaptive\RemediationService;
use NextBeyond\Mastery\AdaptiveConfigService;
use NextBeyond\Mastery\InterventionService;
use NextBeyond\Mastery\LearningDecisionService;
use NextBeyond\Mastery\MasteryCheckService;

function adaptiveRespond(array $payload,int $status=200): never
{ http_response_code($status);echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit; }
function adaptiveInput(): array
{ $data=json_decode((string)file_get_contents('php://input'),true);return is_array($data)?array_merge($_POST,$data):$_POST; }

$method=$_SERVER['REQUEST_METHOD']??'GET';$input=$method==='GET'?$_GET:adaptiveInput();$action=(string)($input['action']??'gaps');
$gaps=new GapAnalysisService($pdo);$search=new QuestionSearchService($pdo);$remediation=new RemediationService($pdo);
$mastery=new MasteryCheckService($pdo,$search);$decisions=new LearningDecisionService($pdo);$interventions=new InterventionService($pdo);$config=new AdaptiveConfigService($pdo);

try {
    if($action==='recalculate'&&$method==='POST'){
        $studentId=(int)($input['student_id']??0);if(!$studentId)adaptiveRespond(['error'=>'student_id required'],422);
        adaptiveRespond(['success'=>true,'gaps'=>$gaps->recalculateForStudent($studentId,(int)($input['course_id']??0)?:null,$input['topic_name']??null)]);
    }
    if($action==='gaps'){
        $studentId=(int)($input['student_id']??0);if(!$studentId)adaptiveRespond(['error'=>'student_id required'],422);
        adaptiveRespond(['success'=>true,'gaps'=>$gaps->getStudentGaps($studentId,(int)($input['course_id']??0)?:null,!isset($input['include_resolved'])||filter_var($input['include_resolved'],FILTER_VALIDATE_BOOLEAN))]);
    }
    if($action==='gap_evidence'){
        $gapId=(int)($input['gap_id']??0);if(!$gapId)adaptiveRespond(['error'=>'gap_id required'],422);
        adaptiveRespond(['success'=>true,'gap'=>$gaps->getGap($gapId),'evidence'=>$gaps->getGapEvidence($gapId)]);
    }
    if($action==='class_summary')adaptiveRespond(['success'=>true,'gaps'=>$gaps->getClassGapSummary((int)($input['course_id']??0)?:null)]);
    if($action==='recommend'){
        $studentId=(int)($input['student_id']??0);$courseId=(int)($input['course_id']??0);$gapId=(int)($input['gap_id']??0);
        if(!$studentId||!$courseId||!$gapId)adaptiveRespond(['error'=>'student_id, course_id and gap_id required'],422);
        $result=$search->getRecommendedQuestionsForGap($studentId,$courseId,$gapId,max(1,min(50,(int)($input['count']??10))),[
            'actor_id'=>(int)$consoleUser['id'],'seen_policy'=>$input['seen_policy']??'prefer_unseen',
            'difficulty'=>$input['difficulty']??null,'exclude_ids'=>$input['exclude_ids']??[],'query'=>$input['query']??'',
        ]);
        adaptiveRespond(['success'=>true]+$result);
    }
    if($action==='create_remediation'&&$method==='POST'){
        $input['created_by']=(int)$consoleUser['id'];$input['created_by_name']=trim($consoleUser['first_name'].' '.$consoleUser['last_name']);
        adaptiveRespond(['success'=>true]+$remediation->createApprovedAssignment($input),201);
    }
    if($action==='mastery_evaluate'){
        $studentId=(int)($input['student_id']??0);$courseId=(int)($input['course_id']??0);$topic=trim((string)($input['topic_name']??''));
        if(!$studentId||!$courseId||$topic==='')adaptiveRespond(['error'=>'student_id, course_id and topic_name required'],422);
        adaptiveRespond(['success'=>true,'mastery_check'=>$mastery->evaluate($studentId,$courseId,$topic,(string)($input['skill_name']??''))]);
    }
    if($action==='recommend_mastery_check'){
        $studentId=(int)($input['student_id']??0);$courseId=(int)($input['course_id']??0);$gapId=(int)($input['gap_id']??0);
        if(!$studentId||!$courseId||!$gapId)adaptiveRespond(['error'=>'student_id, course_id and gap_id required'],422);
        adaptiveRespond(['success'=>true]+$mastery->recommendQuestions($studentId,$courseId,$gapId,max(1,min(20,(int)($input['count']??0))),['exclude_ids'=>$input['exclude_ids']??[]]));
    }
    if($action==='create_mastery_check'&&$method==='POST'){
        $input['created_by']=(int)$consoleUser['id'];$input['created_by_name']=trim($consoleUser['first_name'].' '.$consoleUser['last_name']);
        adaptiveRespond(['success'=>true]+$mastery->createAssignment($input),201);
    }
    if($action==='override_decision'&&$method==='POST')adaptiveRespond(['success'=>true]+$decisions->overrideDecision((int)($input['decision_id']??0),(string)($input['teacher_decision']??''),(string)($input['reason']??''),(int)$consoleUser['id']));
    if($action==='resolve_gap'&&$method==='POST')adaptiveRespond(['success'=>true]+$decisions->manualResolve((int)($input['gap_id']??0),(int)$consoleUser['id'],(string)($input['reason']??'')));
    if($action==='reopen_gap'&&$method==='POST')adaptiveRespond(['success'=>true]+$decisions->manualReopen((int)($input['gap_id']??0),(int)$consoleUser['id'],(string)($input['reason']??'')));
    if($action==='set_gap_blocking'&&$method==='POST')adaptiveRespond(['success'=>true]+$decisions->setBlockingMode((int)($input['gap_id']??0),(string)($input['blocking_mode']??''),(int)$consoleUser['id'],(string)($input['reason']??'')));
    if($action==='intervention_queue')adaptiveRespond(['success'=>true,'interventions'=>$interventions->queue($input)]);
    if($action==='intervention')adaptiveRespond(['success'=>true,'intervention'=>$interventions->get((int)($input['intervention_id']??0))]);
    if($action==='create_intervention'&&$method==='POST')adaptiveRespond(['success'=>true]+$interventions->createManual($input,(int)$consoleUser['id']),201);
    if($action==='schedule_intervention'&&$method==='POST')adaptiveRespond(['success'=>true]+$interventions->schedule((int)($input['intervention_id']??0),$input,(int)$consoleUser['id']));
    if($action==='update_intervention'&&$method==='POST')adaptiveRespond(['success'=>true]+$interventions->updateOutcome((int)($input['intervention_id']??0),$input,(int)$consoleUser['id']));
    if($action==='adaptive_config'){
        if($method==='POST'){
            if(($consoleUser['role']??'')!=='admin')adaptiveRespond(['error'=>'Only administrators can update adaptive thresholds'],403);
            adaptiveRespond(['success'=>true,'config'=>$config->update($input,(int)$consoleUser['id'])]);
        }
        adaptiveRespond(['success'=>true,'config'=>$config->get()]);
    }
    if($action==='learning_timeline'){
        $studentId=(int)($input['student_id']??0);if(!$studentId)adaptiveRespond(['error'=>'student_id required'],422);
        $stmt=$pdo->prepare('SELECT * FROM learning_audit_log WHERE student_id=? ORDER BY created_at DESC,id DESC LIMIT 100');$stmt->execute([$studentId]);
        adaptiveRespond(['success'=>true,'timeline'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    if($action==='index_status'){
        $stats=$pdo->query("SELECT COUNT(*) total,SUM(index_status='indexed') indexed,SUM(index_status='pending') pending,SUM(index_status='failed') failed,SUM(index_status='not_configured') not_configured,MAX(updated_at) last_updated FROM question_bank_items")->fetch(PDO::FETCH_ASSOC);
        adaptiveRespond(['success'=>true,'stats'=>$stats,'provider'=>(new NextBeyond\Adaptive\NullVectorSearchProvider())->healthCheck()]);
    }
    if($action==='reindex'&&$method==='POST')adaptiveRespond(['success'=>true,'stats'=>$search->rebuildIndex((string)($input['mode']??'missing'))]);
    adaptiveRespond(['error'=>'Invalid action'],400);
} catch(InvalidArgumentException $e){adaptiveRespond(['error'=>$e->getMessage()],422);
} catch(Throwable $e){adaptiveRespond(['error'=>'Adaptive learning operation failed','detail'=>$e->getMessage()],500);}
