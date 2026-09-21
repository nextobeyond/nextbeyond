<?php
/** Backward-compatible admin facade over the shared Phase 4 question service. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/phase4-adaptive-service.php';

use NextBeyond\Adaptive\QuestionSearchService;
use NextBeyond\Adaptive\QueryIntentParser;
use NextBeyond\Adaptive\VectorSearchProvider;

final class SemanticQueryParser
{
    public static function parse(string $query): array
    {
        $p = QueryIntentParser::parse($query);
        return ['rawQuery'=>$p['raw_query'],'subject'=>$p['subject']??'','level'=>$p['level']??'',
            'topic'=>$p['topic']??'','difficulty'=>$p['difficulty'][0]??'','questionType'=>$p['question_type']??'',
            'count'=>$p['count'],'contexts'=>$p['context'],'keywords'=>$p['keywords'],'skill'=>$p['skill']??''];
    }
}

final class HybridSearchEngine
{
    private QuestionSearchService $shared;

    public function __construct(private PDO $pdo, ?VectorSearchProvider $provider = null, array $weights = [], float $dedupThreshold = 0.90)
    { $this->shared = new QuestionSearchService($pdo, $provider); }

    public function search(array $criteria): array
    {
        $result=$this->shared->searchQuestions([
            'query'=>$criteria['query']??'','subject'=>$criteria['subject']??'',
            'grade'=>$criteria['level']??$criteria['grade']??'','topic'=>$criteria['topic']??'',
            'skill'=>$criteria['skill']??'','difficulty'=>$criteria['difficulty']??[],
            'question_type'=>$criteria['type']??'','count'=>$criteria['count']??10,
            'student_id'=>$criteria['studentId']??$criteria['student_id']??0,
            'actor_id'=>$criteria['actorId']??$criteria['actor_id']??0,
            'seen_policy'=>$criteria['seenPolicy']??$criteria['seen_policy']??'allow_repeat',
            'exclude_ids'=>$criteria['excludeIds']??$criteria['exclude_ids']??[],
            'purpose'=>$criteria['purpose']??'worksheet',
        ]);
        return ['parsedIntent'=>SemanticQueryParser::parse((string)($criteria['query']??'')),
            'totalCandidates'=>$result['candidate_count'],'recommended'=>array_map([$this,'toLegacyResult'],$result['results']),
            'candidates'=>array_map([$this,'toLegacyResult'],$result['candidates']),
            'searchCapabilities'=>$result['search_capabilities'],'shortfall'=>$result['shortfall']];
    }

    private function toLegacyResult(array $q): array
    {
        $choices=json_decode((string)($q['choices_json']??''),true);
        $answer=json_decode((string)($q['correct_answer_json']??''),true);
        return array_merge($q,['id'=>(int)$q['id'],'options'=>is_array($choices)?$choices:[],
            'correct_answer'=>$answer??$q['correct_answer_json']??'',
            'correct_answer_text'=>is_scalar($answer)?(string)$answer:'','skill'=>$q['skill_name']??'',
            'topic'=>$q['topic_name']??'','subtopic'=>$q['subtopic_name']??'','grade'=>$q['grade_level']??'',
            'source'=>$q['source_type']??'','hybrid_score'=>$q['ranking_score']??0,
            'semantic_sim'=>$q['semantic_score']??null,'match_reasons'=>$q['match_reasons']??[]]);
    }

    public function reindexAll(): array { return $this->shared->rebuildIndex('all'); }
    public function seedAcceptanceTestData(): void {}

    public function insertQuestionsToWorksheet(int $worksheetId,array $questionIds,string $position='end'): array
    {
        $questionIds=array_values(array_unique(array_filter(array_map('intval',$questionIds))));
        if($worksheetId<1||!$questionIds)throw new InvalidArgumentException('ข้อมูลไม่ถูกต้อง');
        $check=$this->pdo->prepare('SELECT id FROM worksheets WHERE id=?');$check->execute([$worksheetId]);
        if(!$check->fetchColumn())throw new RuntimeException('ไม่พบข้อมูลใบงานนี้');
        $max=$this->pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM worksheet_questions WHERE worksheet_id=?');
        $max->execute([$worksheetId]);$order=$position==='start'?1:(int)$max->fetchColumn()+1;
        if($position==='start')$this->pdo->prepare('UPDATE worksheet_questions SET sort_order=sort_order+? WHERE worksheet_id=?')->execute([count($questionIds),$worksheetId]);
        $get=$this->pdo->prepare("SELECT * FROM question_bank_items WHERE id=? AND status='active'");
        $insert=$this->pdo->prepare('INSERT INTO worksheet_questions
          (worksheet_id,canonical_question_id,source_question_id,sort_order,question_type,question_text,options,correct_answer,explanation,hint,skill,difficulty,learning_objective,points)
          VALUES(?,?,NULL,?,?,?,?,?,?,?,?,?,?,1)');
        $inserted=0;$total=0;$this->pdo->beginTransaction();
        try{
            foreach($questionIds as $qid){$get->execute([$qid]);$q=$get->fetch(PDO::FETCH_ASSOC);if(!$q)continue;
                $answer=json_decode((string)$q['correct_answer_json'],true);
                if(is_array($answer))$answer=json_encode($answer,JSON_UNESCAPED_UNICODE);elseif($answer===null)$answer=(string)$q['correct_answer_json'];
                $insert->execute([$worksheetId,$qid,$order++,$q['question_type'],$q['question_text'],$q['choices_json'],$answer,
                    $q['explanation'],$q['hint'],$q['skill_name'],$q['difficulty'],$q['learning_objective']]);$inserted++;}
            $count=$this->pdo->prepare('SELECT COUNT(*) FROM worksheet_questions WHERE worksheet_id=?');$count->execute([$worksheetId]);$total=(int)$count->fetchColumn();
            $this->pdo->prepare('UPDATE worksheets SET question_count=?,updated_at=NOW() WHERE id=?')->execute([$total,$worksheetId]);$this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        $dist=$this->calculateWorksheetDistribution($worksheetId);
        return ['success'=>true,'insertedCount'=>$inserted,'totalQuestions'=>$total,'topicBreakdown'=>$dist['topicBreakdown'],'difficultyBreakdown'=>$dist['difficultyBreakdown']];
    }

    public function calculateWorksheetDistribution(int $worksheetId): array
    {
        $stmt=$this->pdo->prepare("SELECT COALESCE(NULLIF(q.skill,''),w.topic,'ทั่วไป') topic,COALESCE(q.difficulty,'medium') difficulty
          FROM worksheet_questions q JOIN worksheets w ON w.id=q.worksheet_id WHERE q.worksheet_id=?");
        $stmt->execute([$worksheetId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);$topics=[];$difficulty=['easy'=>0,'medium'=>0,'hard'=>0,'expert'=>0];
        foreach($rows as $row){$topics[$row['topic']]=($topics[$row['topic']]??0)+1;$d=strtolower($row['difficulty']);$difficulty[$d]=($difficulty[$d]??0)+1;}
        $breakdown=[];$total=count($rows);foreach($topics as $topic=>$n)$breakdown[]=['topic'=>$topic,'count'=>$n,'percent'=>$total?round($n*100/$total,1):0];
        return ['total'=>$total,'topicBreakdown'=>$breakdown,'difficultyBreakdown'=>$difficulty];
    }
}
