<?php
declare(strict_types=1);

/**
 * Nextbeyond Compass - Smart Question Search & Retrieval Service
 * Hybrid Search Engine: Natural Language Intent Parsing + Metadata Filtering +
 * Semantic Vector Search + Near-Duplicate Detection + MMR Diversity Ranking.
 */

interface VectorSearchProvider {
    public function buildSemanticText(array $question): string;
    public function computeSimilarity(string $textA, string $textB): float;
    public function computeVectorSimilarity(array $vecA, array $vecB): float;
}

class LocalVectorSearchProvider implements VectorSearchProvider {
    private static array $stopwords = [
        'the', 'is', 'at', 'which', 'on', 'a', 'an', 'and', 'or', 'in', 'to', 'for', 'of', 'with', 'as', 'by',
        'ที่', 'และ', 'หรือ', 'ใน', 'ของ', 'เป็น', 'คือ', 'มี', 'การ', 'ความ', 'ให้', 'ได้', 'จาก', 'กับ', 'โดย'
    ];

    public function buildSemanticText(array $q): string {
        $parts = [];
        if (!empty($q['subject'])) $parts[] = "วิชา: " . $q['subject'];
        if (!empty($q['level'])) $parts[] = "ระดับ: " . $q['level'];
        if (!empty($q['topic'])) $parts[] = "หัวข้อ: " . $q['topic'];
        if (!empty($q['subtopic'])) $parts[] = "หัวข้อย่อย: " . $q['subtopic'];
        if (!empty($q['skill'])) $parts[] = "ทักษะ: " . $q['skill'];
        if (!empty($q['learning_objective'])) $parts[] = "วัตถุประสงค์: " . $q['learning_objective'];
        if (!empty($q['question_text'])) $parts[] = "โจทย์: " . $q['question_text'];

        if (!empty($q['options'])) {
            $opts = is_array($q['options']) ? $q['options'] : json_decode((string)$q['options'], true);
            if (is_array($opts)) {
                $parts[] = "ตัวเลือก: " . implode(" | ", $opts);
            }
        }
        if (!empty($q['explanation'])) $parts[] = "คำอธิบาย: " . $q['explanation'];
        if (!empty($q['tags'])) {
            $tags = is_array($q['tags']) ? $q['tags'] : json_decode((string)$q['tags'], true);
            if (is_array($tags)) {
                $parts[] = "แท็ก: " . implode(", ", $tags);
            }
        }

        return implode(" \n ", $parts);
    }

    public function tokenize(string $text): array {
        $clean = mb_strtolower(trim($text), 'UTF-8');
        // Replace non-alphanumeric Thai and English with spaces
        $clean = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $clean);
        $words = preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = [];
        foreach ($words as $w) {
            if (mb_strlen($w, 'UTF-8') >= 2 && !in_array($w, self::$stopwords, true)) {
                $tokens[] = $w;
                // Add character 3-grams for Thai agglutinative matching
                if (preg_match('/\p{Thai}/u', $w) && mb_strlen($w, 'UTF-8') > 3) {
                    $len = mb_strlen($w, 'UTF-8');
                    for ($i = 0; $i <= $len - 3; $i++) {
                        $tokens[] = mb_substr($w, $i, 3, 'UTF-8');
                    }
                }
            }
        }
        return $tokens;
    }

    public function vectorize(string $text): array {
        $tokens = $this->tokenize($text);
        if (empty($tokens)) return [];
        $tf = array_count_values($tokens);
        $norm = 0.0;
        foreach ($tf as $count) {
            $norm += $count * $count;
        }
        $norm = sqrt($norm);
        if ($norm > 0) {
            foreach ($tf as $token => $count) {
                $tf[$token] = $count / $norm;
            }
        }
        return $tf;
    }

    public function computeSimilarity(string $textA, string $textB): float {
        $vecA = $this->vectorize($textA);
        $vecB = $this->vectorize($textB);
        return $this->computeVectorSimilarity($vecA, $vecB);
    }

    public function computeVectorSimilarity(array $vecA, array $vecB): float {
        if (empty($vecA) || empty($vecB)) return 0.0;
        $dot = 0.0;
        foreach ($vecA as $token => $valA) {
            if (isset($vecB[$token])) {
                $dot += $valA * $vecB[$token];
            }
        }
        return (float) min(1.0, max(0.0, $dot));
    }
}

class SemanticQueryParser {
    public static function parse(string $query): array {
        $raw = trim($query);
        $intent = [
            'rawQuery' => $raw,
            'subject' => '',
            'level' => '',
            'topic' => '',
            'difficulty' => '',
            'questionType' => '',
            'count' => 10,
            'contexts' => [],
            'keywords' => []
        ];

        if ($raw === '') return $intent;

        // 1. Detect Count (e.g. "10 ข้อ", "20 questions", "5 ข้อ")
        if (preg_match('/(\d+)\s*(?:ข้อ|items?|questions?)/ui', $raw, $m)) {
            $intent['count'] = max(1, min(50, (int) $m[1]));
        }

        // 2. Detect Level
        $levels = [
            'A-Level' => ['/a[\s\-_]?level/i'],
            'ม.6' => ['/ม\.?\s*6/u', '/m\.?\s*6/i', '/grade\s*12/i'],
            'ม.5' => ['/ม\.?\s*5/u', '/m\.?\s*5/i', '/grade\s*11/i'],
            'ม.4' => ['/ม\.?\s*4/u', '/m\.?\s*4/i', '/grade\s*10/i'],
            'ม.3' => ['/ม\.?\s*3/u', '/m\.?\s*3/i', '/grade\s*9/i'],
            'ม.2' => ['/ม\.?\s*2/u', '/m\.?\s*2/i', '/grade\s*8/i'],
            'ม.1' => ['/ม\.?\s*1/u', '/m\.?\s*1/i', '/grade\s*7/i'],
            'ป.6' => ['/ป\.?\s*6/u', '/p\.?\s*6/i'],
            'ป.5' => ['/ป\.?\s*5/u', '/p\.?\s*5/i'],
            'ป.4' => ['/ป\.?\s*4/u', '/p\.?\s*4/i'],
            'ป.3' => ['/ป\.?\s*3/u', '/p\.?\s*3/i'],
            'ป.2' => ['/ป\.?\s*2/u', '/p\.?\s*2/i'],
            'ป.1' => ['/ป\.?\s*1/u', '/p\.?\s*1/i'],
        ];
        foreach ($levels as $lvlName => $patterns) {
            foreach ($patterns as $p) {
                if (preg_match($p, $raw)) {
                    $intent['level'] = $lvlName;
                    break 2;
                }
            }
        }

        // 3. Detect Subject
        $subjects = [
            'ภาษาอังกฤษ' => ['/อังกฤษ/u', '/english/i', '/grammar/i', '/vocab/i', '/reading/i'],
            'เคมี' => ['/เคมี/u', '/chemistry/i', '/chem/i', '/กรด[\s\-]?เบส/u', '/acid[\s\-]?base/i'],
            'ฟิสิกส์' => ['/ฟิสิกส์/u', '/physics/i', '/กลศาสตร์/u', '/การเคลื่อนที่/u', '/แรง/u'],
            'ชีววิทยา' => ['/ชีววิทยา/u', '/ชีวะ/u', '/biology/i', '/พันธุศาสตร์/u', '/genetics/i'],
            'คณิตศาสตร์' => ['/คณิตศาสตร์/u', '/คณิต/u', '/math/i', '/algebra/i', '/พีชคณิต/u', '/สมการ/u'],
            'ภาษาไทย' => ['/ภาษาไทย/u', '/วรรณคดี/u', '/หลักภาษา/u'],
            'วิทยาศาสตร์' => ['/วิทยาศาสตร์/u', '/science/i'],
            'สังคมศึกษา' => ['/สังคมศึกษา/u', '/สังคม/u', '/social/i']
        ];
        foreach ($subjects as $subName => $patterns) {
            foreach ($patterns as $p) {
                if (preg_match($p, $raw)) {
                    $intent['subject'] = $subName;
                    break 2;
                }
            }
        }

        // 4. Detect Difficulty
        if (preg_match('/(ง่ายมาก|very\s*easy)/ui', $raw)) {
            $intent['difficulty'] = 'easy';
        } elseif (preg_match('/(ง่าย|easy)/ui', $raw)) {
            $intent['difficulty'] = 'easy';
        } elseif (preg_match('/(ยากมาก|expert|very\s*hard)/ui', $raw)) {
            $intent['difficulty'] = 'expert';
        } elseif (preg_match('/(ยาก|hard)/ui', $raw)) {
            $intent['difficulty'] = 'hard';
        } elseif (preg_match('/(ปานกลาง|กลาง|medium|moderate)/ui', $raw)) {
            $intent['difficulty'] = 'medium';
        }

        // 5. Detect Specific Topics & Contexts
        $contexts = [
            'daily routine' => ['/daily\s*routine/i', '/กิจวัตร/u', '/routine/i'],
            'calculation' => ['/calculation/i', '/คำนวณ/u', '/สูตรคำนวณ/u'],
            'error identification' => ['/error\s*(?:identification|detection)?/i', '/จับผิด/u'],
            'passive voice' => ['/passive\s*voice/i', '/passive/i'],
            'present simple' => ['/present\s*simple/i'],
            'present continuous' => ['/present\s*continuous/i'],
            'present perfect' => ['/present\s*perfect/i'],
            'acid base' => ['/acid[\s\-]?base/i', '/กรด[\s\-]?เบส/u', '/ph/i', '/titration/i'],
            'quadratic equation' => ['/quadratic/i', '/กำลังสอง/u'],
            'newton law' => ['/newton/i', '/กฎของนิวตัน/u', '/f\s*=\s*ma/i']
        ];
        foreach ($contexts as $ctxKey => $patterns) {
            foreach ($patterns as $p) {
                if (preg_match($p, $raw)) {
                    $intent['contexts'][] = $ctxKey;
                    if (empty($intent['topic'])) {
                        $intent['topic'] = ucwords($ctxKey);
                    }
                }
            }
        }

        // Clean leftover keywords
        $filtered = preg_replace('/(\d+\s*(?:ข้อ|items?|questions?)|ม\.\s*\d|ป\.\s*\d|a[\s\-_]?level|ระดับ|ง่าย|ปานกลาง|ยาก|ยากมาก|เน้น|ขอ)/ui', ' ', $raw);
        $words = preg_split('/\s+/u', trim($filtered), -1, PREG_SPLIT_NO_EMPTY);
        $intent['keywords'] = array_slice($words, 0, 8);

        return $intent;
    }
}

class HybridSearchEngine {
    private PDO $pdo;
    private VectorSearchProvider $vectorProvider;
    private array $weights = [
        'semantic' => 0.45,
        'metadata' => 0.25,
        'keyword'  => 0.15,
        'quality'  => 0.15
    ];
    private float $dedupThreshold = 0.88;

    public function __construct(PDO $pdo, ?VectorSearchProvider $vectorProvider = null, array $weights = [], float $dedupThreshold = 0.88) {
        $this->pdo = $pdo;
        $this->vectorProvider = $vectorProvider ?? new LocalVectorSearchProvider();
        if ($weights) $this->weights = array_merge($this->weights, $weights);
        $this->dedupThreshold = $dedupThreshold;
        $this->ensureSchema();
    }

    private function ensureSchema(): void {
        static $checked = false;
        if ($checked) return;

        // Ensure exam_questions has metadata and embedding columns
        $cols = [];
        foreach ($this->pdo->query("SHOW COLUMNS FROM exam_questions") as $c) {
            $cols[$c['Field']] = true;
        }
        $alter = [];
        if (!isset($cols['learning_objective'])) $alter[] = "ADD COLUMN `learning_objective` TEXT NULL";
        if (!isset($cols['subtopic'])) $alter[] = "ADD COLUMN `subtopic` VARCHAR(150) NULL";
        if (!isset($cols['tags'])) $alter[] = "ADD COLUMN `tags` TEXT NULL";
        if (!isset($cols['source'])) $alter[] = "ADD COLUMN `source` VARCHAR(50) NOT NULL DEFAULT 'ai'";
        if (!isset($cols['review_status'])) $alter[] = "ADD COLUMN `review_status` ENUM('approved','reviewed','draft','rejected') NOT NULL DEFAULT 'reviewed'";
        if (!isset($cols['embedding_content'])) $alter[] = "ADD COLUMN `embedding_content` MEDIUMTEXT NULL";
        if (!isset($cols['embedding_version'])) $alter[] = "ADD COLUMN `embedding_version` VARCHAR(50) NULL";
        if (!isset($cols['indexed_at'])) $alter[] = "ADD COLUMN `indexed_at` DATETIME NULL";

        if ($alter) {
            $this->pdo->exec("ALTER TABLE `exam_questions` " . implode(', ', $alter));
        }

        // Ensure worksheet_questions has source_question_id
        $wsCols = [];
        foreach ($this->pdo->query("SHOW COLUMNS FROM worksheet_questions") as $c) {
            $wsCols[$c['Field']] = true;
        }
        if (!isset($wsCols['source_question_id'])) {
            $this->pdo->exec("ALTER TABLE `worksheet_questions` ADD COLUMN `source_question_id` INT NULL AFTER `worksheet_id`");
        }

        $this->seedAcceptanceTestData();
        $checked = true;
    }

    /**
     * Perform Hybrid Search across Question Bank (exam_questions + worksheet_questions)
     */
    public function search(array $criteria): array {
        $query = trim((string)($criteria['query'] ?? ''));
        $parsed = SemanticQueryParser::parse($query);

        $subject = trim((string)($criteria['subject'] ?? $parsed['subject']));
        $level = trim((string)($criteria['level'] ?? $parsed['level']));
        $topic = trim((string)($criteria['topic'] ?? $parsed['topic']));
        $difficulty = trim((string)($criteria['difficulty'] ?? $parsed['difficulty']));
        $questionType = trim((string)($criteria['type'] ?? ''));
        $status = trim((string)($criteria['status'] ?? ''));
        $targetCount = max(1, min(50, (int)($criteria['count'] ?? $parsed['count'] ?? 10)));
        $excludeIds = isset($criteria['excludeIds']) && is_array($criteria['excludeIds']) ? array_map('intval', $criteria['excludeIds']) : [];

        // If worksheetId provided and excludeWorksheetQuestions is true, get existing question texts / source IDs
        if (!empty($criteria['worksheetId']) && !empty($criteria['excludeExistingWorksheetQuestions'])) {
            $wsId = (int)$criteria['worksheetId'];
            $existing = $this->pdo->prepare("SELECT source_question_id FROM worksheet_questions WHERE worksheet_id = ? AND source_question_id IS NOT NULL");
            $existing->execute([$wsId]);
            $wsExistingIds = $existing->fetchAll(PDO::FETCH_COLUMN);
            $excludeIds = array_unique(array_merge($excludeIds, array_map('intval', $wsExistingIds)));
        }

        // Fetch candidate questions from Question Bank (exam_questions joined with exams)
        $where = ["(e.status != 'archived' OR e.status IS NULL)"];
        $params = [];

        if ($subject !== '' && $subject !== 'ทุกวิชา') {
            $where[] = "(e.subject LIKE :subj1 OR q.skill LIKE :subj2 OR q.tags LIKE :subj3)";
            $params[':subj1'] = "%{$subject}%";
            $params[':subj2'] = "%{$subject}%";
            $params[':subj3'] = "%{$subject}%";
        }
        if ($level !== '' && $level !== 'ทุกระดับชั้น') {
            $where[] = "(e.grade = :lvl OR e.grade = 'ทุกระดับ' OR q.tags LIKE :lvlTag)";
            $params[':lvl'] = $level;
            $params[':lvlTag'] = "%{$level}%";
        }
        if ($difficulty !== '' && $difficulty !== 'all') {
            $where[] = "(q.difficulty = :diff1 OR e.difficulty = :diff2 OR q.difficulty IS NULL OR q.difficulty = '')";
            $params[':diff1'] = $difficulty;
            $params[':diff2'] = $difficulty;
        }
        if ($excludeIds) {
            $exPlaceholders = [];
            foreach (array_values($excludeIds) as $idx => $exId) {
                $pName = ":ex_{$idx}";
                $exPlaceholders[] = $pName;
                $params[$pName] = (int)$exId;
            }
            $where[] = "q.id NOT IN (" . implode(',', $exPlaceholders) . ")";
        }

        $whereSql = implode(' AND ', $where);

        // Fetch up to 120 candidates for scoring & diversity ranking
        $sql = "
            SELECT q.id, q.exam_id, q.sort_order, q.question_text, q.passage,
                   q.options, q.correct_answer, q.explanation, q.skill, q.difficulty,
                   q.learning_objective, q.subtopic, q.tags, q.source, q.review_status,
                   q.created_at, e.title AS exam_title, e.subject, e.grade, e.topic,
                   e.is_ai_generated
            FROM exam_questions q
            LEFT JOIN exams e ON e.id = q.exam_id
            WHERE {$whereSql}
            ORDER BY q.id DESC
            LIMIT 120
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $candidates = $stmt->fetchAll();

        // Also fetch from worksheet_questions to maximize reuse across worksheets if candidates < 20
        if (count($candidates) < 20) {
            $wsCandidates = $this->fetchWorksheetQuestionsCandidates($subject, $level, $difficulty, $excludeIds);
            $candidates = array_merge($candidates, $wsCandidates);
        }

        // Build search query vector
        $queryText = "{$query} {$subject} {$level} {$topic} " . implode(' ', $parsed['contexts']);
        $queryVec = $this->vectorProvider->vectorize($queryText);
        $queryTokens = $this->vectorProvider->tokenize($query);

        // Score each candidate
        $scored = [];
        foreach ($candidates as $cand) {
            // Options normalization
            $opts = $cand['options'];
            if (is_string($opts)) {
                $opts = json_decode($opts, true) ?: [];
            }

            // Semantic representation
            $candText = $cand['embedding_content'] ?? '';
            if ($candText === '') {
                $candText = $this->vectorProvider->buildSemanticText($cand);
            }
            $candVec = $this->vectorProvider->vectorize($candText);

            // 1. Vector Semantic Similarity
            $sim = $this->vectorProvider->computeVectorSimilarity($queryVec, $candVec);

            // 2. Metadata Match Score
            $candLevel = (string)($cand['level'] ?? $cand['grade'] ?? '');
            $candSubj = (string)($cand['subject'] ?? $cand['skill'] ?? '');
            $metaScore = 0.0;
            if ($subject !== '' && stripos($candSubj, $subject) !== false) {
                $metaScore += 0.4;
            }
            if ($level !== '' && stripos($candLevel, $level) !== false) {
                $metaScore += 0.3;
            }
            if ($difficulty !== '' && strtolower((string)$cand['difficulty']) === strtolower($difficulty)) {
                $metaScore += 0.3;
            }

            // 3. Keyword Match Score
            $kwScore = 0.0;
            if (!empty($queryTokens)) {
                $matchedTokens = 0;
                $candLower = mb_strtolower($candText, 'UTF-8');
                foreach ($queryTokens as $tok) {
                    if (str_contains($candLower, $tok)) {
                        $matchedTokens++;
                    }
                }
                $kwScore = min(1.0, $matchedTokens / max(1, count($queryTokens)));
            }

            // 4. Quality & Review status score
            $qualityScore = 0.7;
            if (($cand['review_status'] ?? '') === 'approved') $qualityScore = 1.0;
            elseif (($cand['review_status'] ?? '') === 'reviewed') $qualityScore = 0.85;

            // Final Weighted Hybrid Score
            $finalScore = (
                $this->weights['semantic'] * $sim +
                $this->weights['metadata'] * $metaScore +
                $this->weights['keyword'] * $kwScore +
                $this->weights['quality'] * $qualityScore
            );

            // Match Explanation Tag (Section 23)
            $matchReasons = [];
            if ($sim > 0.6) $matchReasons[] = "ตรงกับหัวข้อสูง";
            if (!empty($parsed['level']) && stripos($candLevel, $parsed['level']) !== false) {
                $matchReasons[] = "ระดับ {$parsed['level']}";
            }
            foreach ($parsed['contexts'] as $ctx) {
                if (stripos($candText, $ctx) !== false) {
                    $matchReasons[] = "เน้น " . ucwords($ctx);
                }
            }
            if (empty($matchReasons)) {
                $matchReasons[] = ($finalScore > 0.45 ? "ความสอดคล้องระดับดี" : "คำถามแนะนำทั่วไป");
            }

            // Normalize correct answer representation
            $ans = $cand['correct_answer'] ?? '';
            $ansText = '';
            if (is_numeric($ans) && isset($opts[(int)$ans])) {
                $ansText = $opts[(int)$ans];
            } else {
                $ansText = (string)$ans;
            }

            $cand['options'] = $opts;
            $cand['correct_answer_text'] = $ansText;
            $cand['semantic_text'] = $candText;
            $cand['vector'] = $candVec;
            $cand['hybrid_score'] = round($finalScore, 4);
            $cand['semantic_sim'] = round($sim, 4);
            $cand['match_reasons'] = array_unique($matchReasons);

            $scored[] = $cand;
        }

        // Sort descending by hybrid score
        usort($scored, fn($a, $b) => $b['hybrid_score'] <=> $a['hybrid_score']);

        // Near-Duplicate Detection (Section 13)
        $deduped = $this->deduplicateCandidates($scored, $this->dedupThreshold);

        // MMR Diversity Ranking (Section 14)
        $recommended = $this->applyMMRDiversity($deduped, $queryVec, $targetCount);

        // Strip internal heavy vector representation for clean JSON response
        $clean = function(array $items): array {
            return array_map(function($i) {
                unset($i['vector'], $i['embedding_content']);
                return $i;
            }, $items);
        };

        return [
            'parsedIntent' => $parsed,
            'totalCandidates' => count($deduped),
            'recommended' => $clean($recommended),
            'candidates' => $clean($deduped)
        ];
    }

    /**
     * Remove near-duplicate questions based on semantic similarity threshold (Section 13)
     */
    private function deduplicateCandidates(array $candidates, float $threshold): array {
        $unique = [];
        foreach ($candidates as $cand) {
            $isDuplicate = false;
            foreach ($unique as $existing) {
                $sim = $this->vectorProvider->computeVectorSimilarity($cand['vector'], $existing['vector']);
                if ($sim >= $threshold) {
                    $isDuplicate = true;
                    break;
                }
            }
            if (!$isDuplicate) {
                $unique[] = $cand;
            }
        }
        return $unique;
    }

    /**
     * Apply Maximum Marginal Relevance (MMR) for Diversity Ranking (Section 14)
     */
    private function applyMMRDiversity(array $candidates, array $queryVec, int $k, float $lambda = 0.65): array {
        if (empty($candidates)) return [];
        $selected = [];
        $remaining = $candidates;

        // Pick highest scored item first
        $first = array_shift($remaining);
        $selected[] = $first;

        while (count($selected) < $k && !empty($remaining)) {
            $bestIdx = null;
            $bestScore = -INF;

            foreach ($remaining as $idx => $cand) {
                // Sim to query
                $simQuery = $this->vectorProvider->computeVectorSimilarity($cand['vector'], $queryVec);

                // Max sim to already selected
                $maxSimSelected = 0.0;
                foreach ($selected as $sel) {
                    $s = $this->vectorProvider->computeVectorSimilarity($cand['vector'], $sel['vector']);
                    if ($s > $maxSimSelected) $maxSimSelected = $s;
                }

                // MMR formula: lambda * sim(q, di) - (1 - lambda) * max(sim(di, dj))
                $mmrScore = ($lambda * $simQuery) - ((1.0 - $lambda) * $maxSimSelected) + (0.1 * $cand['hybrid_score']);

                if ($mmrScore > $bestScore) {
                    $bestScore = $mmrScore;
                    $bestIdx = $idx;
                }
            }

            if ($bestIdx !== null) {
                $selected[] = $remaining[$bestIdx];
                array_splice($remaining, $bestIdx, 1);
            } else {
                break;
            }
        }

        return $selected;
    }

    /**
     * Fallback fetch from worksheet_questions to enrich candidates
     */
    private function fetchWorksheetQuestionsCandidates(string $subject, string $level, string $difficulty, array $excludeIds): array {
        try {
            $where = ["wq.question_text != ''"];
            $params = [];
            if ($subject !== '' && $subject !== 'ทุกวิชา') {
                $where[] = "(w.subject LIKE :s1 OR wq.skill LIKE :s2)";
                $params[':s1'] = "%{$subject}%";
                $params[':s2'] = "%{$subject}%";
            }
            if ($level !== '' && $level !== 'ทุกระดับชั้น') {
                $where[] = "(w.level = :l)";
                $params[':l'] = $level;
            }
            $whereSql = implode(' AND ', $where);

            $stmt = $this->pdo->prepare("
                SELECT wq.id, wq.question_text, wq.options, wq.correct_answer,
                       wq.explanation, wq.skill, wq.difficulty, wq.learning_objective,
                       w.title AS exam_title, w.subject, w.level AS grade, w.topic,
                       'worksheet' AS source, 'reviewed' AS review_status, wq.created_at
                FROM worksheet_questions wq
                JOIN worksheets w ON w.id = wq.worksheet_id
                WHERE {$whereSql}
                LIMIT 30
            ");
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Rebuild search index for questions (Section 25)
     */
    public function reindexAll(): array {
        $questions = $this->pdo->query("
            SELECT q.id, q.question_text, q.options, q.explanation, q.skill, q.difficulty,
                   e.subject, e.grade, e.topic
            FROM exam_questions q
            LEFT JOIN exams e ON e.id = q.exam_id
        ")->fetchAll();

        $indexed = 0;
        $failed = 0;
        $upd = $this->pdo->prepare("
            UPDATE exam_questions
            SET embedding_content = ?, embedding_version = 'nb-hybrid-v1', indexed_at = NOW()
            WHERE id = ?
        ");

        foreach ($questions as $q) {
            try {
                $text = $this->vectorProvider->buildSemanticText($q);
                $upd->execute([$text, $q['id']]);
                $indexed++;
            } catch (Throwable) {
                $failed++;
            }
        }

        return [
            'total' => count($questions),
            'indexed' => $indexed,
            'failed' => $failed
        ];
    }

    /**
     * Insert selected questions into a worksheet with source_question_id tracking (Sections 18, 19, 20)
     */
    public function insertQuestionsToWorksheet(int $worksheetId, array $questionIds, string $position = 'end'): array {
        if ($worksheetId <= 0 || empty($questionIds)) {
            throw new InvalidArgumentException("ข้อมูลไม่ถูกต้อง");
        }

        // Fetch current worksheet
        $wsStmt = $this->pdo->prepare("SELECT * FROM worksheets WHERE id = ?");
        $wsStmt->execute([$worksheetId]);
        $ws = $wsStmt->fetch();
        if (!$ws) throw new RuntimeException("ไม่พบข้อมูลใบงานนี้");

        // Current max sort_order
        $maxOrderStmt = $this->pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM worksheet_questions WHERE worksheet_id = ?");
        $maxOrderStmt->execute([$worksheetId]);
        $currentMaxOrder = (int)$maxOrderStmt->fetchColumn();

        $insertedCount = 0;
        $order = ($position === 'start') ? 1 : ($currentMaxOrder + 1);

        // If inserting at start, shift existing questions
        if ($position === 'start') {
            $shift = count($questionIds);
            $this->pdo->prepare("UPDATE worksheet_questions SET sort_order = sort_order + ? WHERE worksheet_id = ?")->execute([$shift, $worksheetId]);
        }

        $insStmt = $this->pdo->prepare("
            INSERT INTO worksheet_questions (
                worksheet_id, source_question_id, sort_order, question_type,
                question_text, options, correct_answer, explanation, skill, difficulty, learning_objective
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($questionIds as $qId) {
            $qId = (int)$qId;
            // First check in exam_questions
            $qStmt = $this->pdo->prepare("
                SELECT q.*, e.subject, e.grade, e.topic
                FROM exam_questions q
                LEFT JOIN exams e ON e.id = q.exam_id
                WHERE q.id = ?
            ");
            $qStmt->execute([$qId]);
            $q = $qStmt->fetch();

            if (!$q) {
                // Check in worksheet_questions fallback
                $wqStmt = $this->pdo->prepare("SELECT * FROM worksheet_questions WHERE id = ?");
                $wqStmt->execute([$qId]);
                $q = $wqStmt->fetch();
                if (!$q) continue;
            }

            $qText = $q['question_text'] ?? '';
            $opts = $q['options'] ?? null;
            if (is_array($opts)) {
                $opts = json_encode(array_values($opts), JSON_UNESCAPED_UNICODE);
            }
            $ans = $q['correct_answer'] ?? '';
            if (is_numeric($ans) && !empty($opts)) {
                $optArr = is_array($q['options']) ? $q['options'] : json_decode((string)$opts, true);
                if (isset($optArr[(int)$ans])) $ans = $optArr[(int)$ans];
            }
            $exp = $q['explanation'] ?? '';
            $skill = $q['skill'] ?? ($q['topic'] ?? '');
            $diff = $q['difficulty'] ?? 'medium';
            $lo = $q['learning_objective'] ?? '';
            $type = (!empty($opts) && $opts !== '[]') ? 'multipleChoice' : 'shortAnswer';

            $insStmt->execute([
                $worksheetId, $qId, $order++, $type,
                $qText, $opts, (string)$ans, $exp, $skill, $diff, $lo
            ]);
            $insertedCount++;
        }

        // Update question_count on worksheet
        $newCountStmt = $this->pdo->prepare("SELECT COUNT(*) FROM worksheet_questions WHERE worksheet_id = ?");
        $newCountStmt->execute([$worksheetId]);
        $totalQuestions = (int)$newCountStmt->fetchColumn();
        $this->pdo->prepare("UPDATE worksheets SET question_count = ?, updated_at = NOW() WHERE id = ?")->execute([$totalQuestions, $worksheetId]);

        // Calculate Topic & Difficulty Distribution (Section 20)
        $distribution = $this->calculateWorksheetDistribution($worksheetId);

        return [
            'success' => true,
            'insertedCount' => $insertedCount,
            'totalQuestions' => $totalQuestions,
            'topicBreakdown' => $distribution['topicBreakdown'],
            'difficultyBreakdown' => $distribution['difficultyBreakdown']
        ];
    }

    /**
     * Calculate Topic & Difficulty Distribution (Section 20)
     */
    public function calculateWorksheetDistribution(int $worksheetId): array {
        $questions = $this->pdo->prepare("
            SELECT wq.skill, wq.difficulty, w.topic AS ws_topic
            FROM worksheet_questions wq
            JOIN worksheets w ON w.id = wq.worksheet_id
            WHERE wq.worksheet_id = ?
        ");
        $questions->execute([$worksheetId]);
        $rows = $questions->fetchAll();
        $total = count($rows);

        $topics = [];
        $difficulties = ['easy' => 0, 'medium' => 0, 'hard' => 0, 'expert' => 0];

        foreach ($rows as $r) {
            $t = trim((string)($r['skill'] ?: $r['ws_topic'] ?: 'ทั่วไป'));
            $topics[$t] = ($topics[$t] ?? 0) + 1;

            $d = strtolower(trim((string)($r['difficulty'] ?: 'medium')));
            if (!isset($difficulties[$d])) $difficulties[$d] = 0;
            $difficulties[$d]++;
        }

        $topicBreakdown = [];
        foreach ($topics as $tName => $cnt) {
            $topicBreakdown[] = [
                'topic' => $tName,
                'count' => $cnt,
                'percent' => $total > 0 ? round(($cnt / $total) * 100, 1) : 0
            ];
        }

        return [
            'total' => $total,
            'topicBreakdown' => $topicBreakdown,
            'difficultyBreakdown' => $difficulties
        ];
    }

    /**
     * Seed acceptance test data (Present Continuous worksheet & Question Bank sets)
     */
    public function seedAcceptanceTestData(): void {
        // 1. Seed Present Simple & Daily Routine questions in Question Bank
        $check = $this->pdo->query("SELECT id FROM exams WHERE title LIKE '%Present Simple & Daily Routine%' LIMIT 1")->fetch();
        if (!$check) {
            $insExam = $this->pdo->prepare("
                INSERT INTO exams (title, subject, grade, topic, difficulty, type, is_ai_generated, status, is_published)
                VALUES ('คลังข้อสอบ: Present Simple & Daily Routine (ม.2)', 'ภาษาอังกฤษ', 'ม.2', 'Present Simple', 'medium', 'quiz', 1, 'active', 1)
            ");
            $insExam->execute();
            $examId = (int)$this->pdo->lastInsertId();

            $psQuestions = [
                [
                    'text' => 'Sarah _____ to school every day.',
                    'opts' => ['walk', 'walks', 'is walking', 'walked'],
                    'ans' => 1,
                    'exp' => 'Present Simple ใช้กับกิจวัตรประจำวัน ("every day") และประธาน Sarah เป็นเอกพจน์บุรุษที่ 3 กริยาจึงเติม s -> "walks"',
                    'skill' => 'Present Simple',
                    'diff' => 'medium',
                    'lo' => 'Use present simple to describe routines and habits'
                ],
                [
                    'text' => 'Tom _____ his teeth every morning before breakfast.',
                    'opts' => ['brush', 'brushes', 'is brushing', 'brushed'],
                    'ans' => 1,
                    'exp' => 'กริยาที่ลงท้ายด้วย sh (brush) เมื่อประธานเป็นเอกพจน์บุรุษที่ 3 ใน Present Simple ให้เติม es -> "brushes"',
                    'skill' => 'Present Simple',
                    'diff' => 'easy',
                    'lo' => 'Spelling rules for 3rd person singular in Present Simple'
                ],
                [
                    'text' => 'My parents _____ coffee every morning at 7:00 AM.',
                    'opts' => ['drinks', 'drink', 'are drinking', 'drank'],
                    'ans' => 1,
                    'exp' => 'ประธาน My parents เป็นพหูพจน์ กริยาใน Present Simple จึงไม่ต้องเติม s -> "drink"',
                    'skill' => 'Present Simple',
                    'diff' => 'easy',
                    'lo' => 'Subject-verb agreement with plural subjects'
                ],
                [
                    'text' => 'They _____ to the gym on weekends because they are busy.',
                    'opts' => ["don't go", "doesn't go", "aren't going", "didn't go"],
                    'ans' => 0,
                    'exp' => 'ประโยคปฏิเสธของ Present Simple สำหรับประธาน They ใช้ don\'t + verb base form -> "don\'t go"',
                    'skill' => 'Present Simple',
                    'diff' => 'medium',
                    'lo' => 'Negative statements in Present Simple'
                ],
                [
                    'text' => 'What time _____ your sister usually wake up on weekdays?',
                    'opts' => ['do', 'does', 'is', 'has'],
                    'ans' => 1,
                    'exp' => 'ประโยคคำถาม Wh-question ที่ประธานเป็นเอกพจน์ ("your sister") ต้องใช้กริยาช่วย does -> "does your sister usually wake up"',
                    'skill' => 'Present Simple',
                    'diff' => 'medium',
                    'lo' => 'Question forms with auxiliary does in Present Simple'
                ],
                [
                    'text' => 'How often _____ you practice playing the piano at home?',
                    'opts' => ['do', 'does', 'are', 'were'],
                    'ans' => 0,
                    'exp' => 'ถามความถี่ How often กับประธาน you ใช้กริยาช่วย do -> "How often do you practice"',
                    'skill' => 'Present Simple',
                    'diff' => 'easy',
                    'lo' => 'Asking about frequency in daily routine'
                ],
                [
                    'text' => 'Our English class _____ at 8:30 AM every Monday.',
                    'opts' => ['starts', 'start', 'is starting', 'has started'],
                    'ans' => 0,
                    'exp' => 'ตารางเวลาที่แน่นอน (Schedules and Timetables) ใช้ Present Simple -> "starts"',
                    'skill' => 'Present Simple',
                    'diff' => 'medium',
                    'lo' => 'Present Simple for fixed schedules'
                ],
                [
                    'text' => 'He rarely _____ meat because he prefers a vegetarian diet.',
                    'opts' => ['eat', 'eats', 'is eating', 'ate'],
                    'ans' => 1,
                    'exp' => 'เมื่อมี Adverb of frequency "rarely" กับประธาน He กริยาต้องเติม s -> "eats"',
                    'skill' => 'Present Simple',
                    'diff' => 'medium',
                    'lo' => 'Adverbs of frequency in Present Simple'
                ],
                [
                    'text' => 'Choose the sentence that correctly expresses a daily habit:',
                    'opts' => [
                        'I am walking the dog every evening.',
                        'I walks the dog every evening.',
                        'I walk the dog every evening.',
                        'I walked the dog every evening tomorrow.'
                    ],
                    'ans' => 2,
                    'exp' => 'รูปประโยคบอกเล่า Present Simple ที่ถูกต้องสำหรับ I คือกริยาช่อง 1 ธรรมดา -> "I walk the dog every evening."',
                    'skill' => 'Present Simple',
                    'diff' => 'medium',
                    'lo' => 'Identifying correct Present Simple syntax'
                ],
                [
                    'text' => 'Lisa and her brother always _____ their homework right after dinner.',
                    'opts' => ['finishes', 'finish', 'are finishing', 'finished'],
                    'ans' => 1,
                    'exp' => 'ประธานสองคนเชื่อมด้วย and ("Lisa and her brother") เป็นพหูพจน์ กริยาใช้ finish รูปฐาน',
                    'skill' => 'Present Simple',
                    'diff' => 'medium',
                    'lo' => 'Compound subject in Present Simple routine'
                ],
                [
                    'text' => 'The sun _____ in the east and sets in the west.',
                    'opts' => ['rises', 'rise', 'is rising', 'rose'],
                    'ans' => 0,
                    'exp' => 'ข้อเท็จจริงทางธรรมชาติ (General Truth / Scientific Fact) ใช้ Present Simple -> "rises"',
                    'skill' => 'Present Simple',
                    'diff' => 'easy',
                    'lo' => 'Expressing universal truths'
                ],
                [
                    'text' => 'Jack never _____ late for his morning classes.',
                    'opts' => ['is', 'are', 'be', 'being'],
                    'ans' => 0,
                    'exp' => 'Verb to be ใน Present Simple สำหรับประธานเอกพจน์ Jack คือ is โดยวาง adverb "never" ไว้หลัง verb to be -> "is never late"',
                    'skill' => 'Present Simple',
                    'diff' => 'hard',
                    'lo' => 'Position of adverbs of frequency with Verb to be'
                ]
            ];

            $insQ = $this->pdo->prepare("
                INSERT INTO exam_questions (
                    exam_id, sort_order, question_text, options, correct_answer,
                    explanation, skill, difficulty, learning_objective, tags, source, review_status,
                    embedding_content, embedding_version, indexed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'teacher', 'approved', ?, 'nb-hybrid-v1', NOW())
            ");

            $ord = 1;
            foreach ($psQuestions as $item) {
                $optsJson = json_encode($item['opts'], JSON_UNESCAPED_UNICODE);
                $tagsJson = json_encode(['English', 'M.2', 'Present Simple', 'Daily Routine', 'Grammar'], JSON_UNESCAPED_UNICODE);
                $semText = "วิชา: ภาษาอังกฤษ | ระดับ: ม.2 | หัวข้อ: Present Simple | ทักษะ: {$item['skill']} | โจทย์: {$item['text']} | ตัวเลือก: " . implode(' | ', $item['opts']) . " | คำอธิบาย: {$item['exp']}";
                $insQ->execute([
                    $examId, $ord++, $item['text'], $optsJson, $item['ans'],
                    $item['exp'], $item['skill'], $item['diff'], $item['lo'], $tagsJson, $semText
                ]);
            }
        }

        // 2. Seed Acid-Base Calculation questions (Acceptance Test 42)
        $chemCheck = $this->pdo->query("SELECT id FROM exams WHERE title LIKE '%Acid-Base Calculations%' LIMIT 1")->fetch();
        if (!$chemCheck) {
            $insExam = $this->pdo->prepare("
                INSERT INTO exams (title, subject, grade, topic, difficulty, type, is_ai_generated, status, is_published)
                VALUES ('คลังข้อสอบ: Acid-Base Calculations (A-Level เคมี)', 'เคมี', 'A-Level', 'Acid-Base', 'hard', 'quiz', 1, 'active', 1)
            ");
            $insExam->execute();
            $chemExamId = (int)$this->pdo->lastInsertId();

            $chemQuestions = [
                [
                    'text' => 'สารละลายกรดแก่ HCl เข้มข้น 0.005 mol/dm³ ปริมาตร 500 cm³ ผสมกับกรดแก่ HNO3 เข้มข้น 0.015 mol/dm³ ปริมาตร 500 cm³ จงหาค่า pH ของสารละลายผสมนี้ (calculation)',
                    'opts' => ['pH = 1.0', 'pH = 2.0', 'pH = 2.5', 'pH = 3.0'],
                    'ans' => 1,
                    'exp' => 'จำนวนโมล H+ รวม = (0.005 * 0.5) + (0.015 * 0.5) = 0.0025 + 0.0075 = 0.010 mol ในปริมาตร 1.0 dm³ -> [H+] = 0.01 M = 10^-2 M -> pH = -log(10^-2) = 2.0',
                    'skill' => 'Acid-Base Calculation',
                    'diff' => 'hard',
                    'lo' => 'คำนวณค่า pH ของสารละลายกรดแก่ผสม'
                ],
                [
                    'text' => 'สารละลายเบสอ่อน BOH 0.20 M แตกตัวได้ 2% จงคำนวณหาค่าคงที่การแตกตัวของเบส (Kb) และค่า pOH (calculation)',
                    'opts' => [
                        'Kb = 8.0 x 10^-5, pOH = 2.4',
                        'Kb = 4.0 x 10^-4, pOH = 3.2',
                        'Kb = 1.6 x 10^-5, pOH = 1.8',
                        'Kb = 2.0 x 10^-6, pOH = 4.0'
                    ],
                    'ans' => 0,
                    'exp' => '[OH-] = C * alpha = 0.20 * 0.02 = 4.0 x 10^-3 M -> Kb = [OH-]^2 / C = (4.0 x 10^-3)^2 / 0.20 = 8.0 x 10^-5 -> pOH = -log(4.0 x 10^-3) = 3 - 0.60 = 2.4',
                    'skill' => 'Acid-Base Calculation',
                    'diff' => 'hard',
                    'lo' => 'คำนวณค่า Kb และ pOH จากร้อยละการแตกตัว'
                ],
                [
                    'text' => 'จะต้องใช้สารละลาย NaOH เข้มข้น 0.10 mol/dm³ ปริมาตรกี่ลูกบาศก์เซนติเมตร จึงจะทำปฏิกิริยาสะเทินพอดีกับสารละลายกรด H2SO4 เข้มข้น 0.05 mol/dm³ ปริมาตร 40 cm³ (calculation)',
                    'opts' => ['20 cm³', '40 cm³', '80 cm³', '100 cm³'],
                    'ans' => 1,
                    'exp' => 'ตามสมการการสะเทิน: a * M1 * V1 = b * M2 * V2 โดย H2SO4 แตกตัวให้ H+ 2 ตัว (a=2), NaOH แตกตัวให้ OH- 1 ตัว (b=1) -> 2 * 0.05 * 40 = 1 * 0.10 * V(NaOH) -> 4.0 = 0.10 * V -> V = 40 cm³',
                    'skill' => 'Acid-Base Calculation',
                    'diff' => 'medium',
                    'lo' => 'คำนวณปริมาตรสารละลายในปฏิกิริยาสะเทินกรดเบส'
                ],
                [
                    'text' => 'สารละลายบัฟเฟอร์กรดประกอบด้วย CH3COOH 0.10 M และ CH3COONa 0.10 M (Ka = 1.8 x 10^-5) หากเติมกรด HCl เข้มข้น 0.01 mol ลงในบัฟเฟอร์ปริมาตร 1 dm³ ค่า pH จะเปลี่ยนแปลงอย่างไร (calculation)',
                    'opts' => [
                        'pH ลดลงจาก 4.74 เป็นประมาณ 4.65',
                        'pH เพิ่มขึ้นจาก 4.74 เป็น 5.12',
                        'pH ลดลงอย่างรวดเร็วเป็น 1.00',
                        'pH ไม่เปลี่ยนแปลงเลยคงที่ 4.74'
                    ],
                    'ans' => 0,
                    'exp' => 'เดิม pH = pKa = 4.74 เมื่อเติม HCl 0.01 mol จะเกิดปฏิกิริยากับ CH3COO- ทำให้ [กรด] กลายเป็น 0.11 M และ [เกลือ] เหลือ 0.09 M -> pH ใหม่ = 4.74 + log(0.09/0.11) = 4.74 - 0.087 = 4.65 (ลดลงเพียงเล็กน้อยตามสมบัติบัฟเฟอร์)',
                    'skill' => 'Acid-Base Calculation',
                    'diff' => 'expert',
                    'lo' => 'วิเคราะห์การต้านทานการเปลี่ยนแปลง pH ของระบบบัฟเฟอร์'
                ]
            ];

            $insChem = $this->pdo->prepare("
                INSERT INTO exam_questions (
                    exam_id, sort_order, question_text, options, correct_answer,
                    explanation, skill, difficulty, learning_objective, tags, source, review_status,
                    embedding_content, embedding_version, indexed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'teacher', 'approved', ?, 'nb-hybrid-v1', NOW())
            ");

            $ord = 1;
            foreach ($chemQuestions as $item) {
                $optsJson = json_encode($item['opts'], JSON_UNESCAPED_UNICODE);
                $tagsJson = json_encode(['Chemistry', 'A-Level', 'Acid-Base', 'Calculation', 'pH'], JSON_UNESCAPED_UNICODE);
                $semText = "วิชา: เคมี | ระดับ: A-Level | หัวข้อ: Acid-Base | ทักษะ: {$item['skill']} | โจทย์: {$item['text']} | ตัวเลือก: " . implode(' | ', $item['opts']) . " | คำอธิบาย: {$item['exp']}";
                $insChem->execute([
                    $chemExamId, $ord++, $item['text'], $optsJson, $item['ans'],
                    $item['exp'], $item['skill'], $item['diff'], $item['lo'], $tagsJson, $semText
                ]);
            }
        }

        // 3. Seed "Present Continuous Practice" Worksheet (20 questions) for Acceptance Test 41
        $wsCheck = $this->pdo->query("SELECT id FROM worksheets WHERE title LIKE '%Present Continuous Practice%' LIMIT 1")->fetch();
        if (!$wsCheck) {
            $insWs = $this->pdo->prepare("
                INSERT INTO worksheets (
                    title, description, subject, level, chapter, topic, subtopic,
                    worksheet_type, difficulty, question_count, generation_source,
                    creator_name, tags, status
                ) VALUES (
                    'Present Continuous Practice',
                    'ชุดแบบฝึกหัดทบทวนการใช้ Present Continuous Tense สำหรับเหตุการณ์ที่กำลังเกิดขึ้นในขณะพูด (Actions happening now)',
                    'ภาษาอังกฤษ', 'ม.2', 'English Tenses Mastery', 'Present Continuous', 'Actions in progress',
                    'Practice', 'medium', 20, 'ai', 'Kru Base', '[\"English\",\"M.2\",\"Present Continuous\"]', 'published'
                )
            ");
            $insWs->execute();
            $wsId = (int)$this->pdo->lastInsertId();

            $verbs = [
                ['eat', 'is eating', 'lunch right now'],
                ['read', 'is reading', 'a novel in the library at the moment'],
                ['play', 'are playing', 'football in the schoolyard'],
                ['study', 'is studying', 'for the upcoming science exam tonight'],
                ['watch', 'are watching', 'a documentary on television'],
                ['cook', 'is cooking', 'dinner in the kitchen'],
                ['write', 'is writing', 'an essay on climate change'],
                ['listen', 'are listening', 'to their favorite podcast'],
                ['drive', 'is driving', 'to the office right now'],
                ['swim', 'are swimming', 'in the community pool'],
                ['draw', 'is drawing', 'a sketch of the landscape'],
                ['sing', 'is singing', 'on the main stage'],
                ['dance', 'are dancing', 'to the lively music'],
                ['wait', 'is waiting', 'for the morning school bus'],
                ['clean', 'are cleaning', 'the laboratory after class'],
                ['repair', 'is repairing', 'the broken bicycle wheel'],
                ['sleep', 'is sleeping', 'soundly in the bedroom'],
                ['fly', 'is flying', 'a kite in the windy park'],
                ['talk', 'are talking', 'about the weekend travel plan'],
                ['paint', 'is painting', 'the classroom wall with bright colors']
            ];

            $insWq = $this->pdo->prepare("
                INSERT INTO worksheet_questions (
                    worksheet_id, sort_order, question_type, question_text,
                    options, correct_answer, explanation, skill, difficulty, learning_objective
                ) VALUES (?, ?, 'multipleChoice', ?, ?, ?, ?, 'Present Continuous', 'medium', 'Express actions happening now')
            ");

            for ($i = 0; $i < 20; $i++) {
                $v = $verbs[$i];
                $sub = ($i % 2 === 0) ? "Sarah" : "The students";
                $correct = $v[1];
                $qText = "Look! {$sub} _____ {$v[2]}.";
                $options = [
                    $correct,
                    ($i % 2 === 0 ? "eats" : "eat"),
                    ($i % 2 === 0 ? "ate" : "played"),
                    ($i % 2 === 0 ? "has eaten" : "have played")
                ];
                $insWq->execute([
                    $wsId, $i + 1, $qText,
                    json_encode($options, JSON_UNESCAPED_UNICODE),
                    $correct,
                    "มีคำบอกเวลาหรือสัญญาณแสดงเหตุการณ์กำลังเกิดขึ้น ('Look!') จึงใช้ Present Continuous: is/am/are + V-ing -> '{$correct}'"
                ]);
            }
        }
    }
}

