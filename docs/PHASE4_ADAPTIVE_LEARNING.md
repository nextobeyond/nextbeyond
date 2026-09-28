# NEXTBEYOND V2 — Phase 4 Adaptive Learning

## Architecture audit

- `exam_questions` and `worksheet_questions` are activity-owned snapshots. Both historically required a parent activity and were not canonical assets.
- `worksheet_questions.source_question_id` existed but referred specifically to an exam question. Phase 4 leaves it intact and adds the unambiguous `canonical_question_id`.
- Topic and skill metadata is presently text-based (`topic_name`, `skill`). Nullable taxonomy IDs are reserved on canonical questions and gaps for a later normalized curriculum hierarchy.
- AI exam generation writes `exam_questions`; AI worksheet generation writes `worksheet_questions`. Both now synchronize their new/edited questions to the canonical bank with `index_status=pending`.
- Phase 3 already supplies assignments, targeting, autosave, submissions, scoring, question answers, learning evidence, mastery, profile refresh, roadmap evaluation, and the student activity runner. Remediation reuses all of these.
- The former admin search used local token vectors over a fixed 120-row window, changed schemas and seeded sample content from its constructor, and had no ownership or exposure policy. It has been replaced by a compatibility facade over the shared service.

## Canonical question architecture

`question_bank_items` is the academic source for reusable questions. Exam and worksheet rows remain immutable-compatible snapshots through `canonical_question_id`; editing an activity copy does not modify its canonical item. Canonical records include academic metadata, review/quality state, visibility/ownership, provenance, and index state.

Existing questions are backfilled repeatably by `(source_type, source_record_id)`. Historical exams, worksheets, attempts, and answers are not rewritten or deleted.

## Gap architecture

`GapAnalysisService` interprets `learning_evidence` together with `topic_mastery`. It stores current and historical state in `student_learning_gaps`, including mastery, recent accuracy, evidence count, confidence, severity, trend, and lifecycle status.

- One item can only produce a low-confidence potential gap.
- High severity requires at least three pieces of evidence.
- Critical severity requires at least six pieces of evidence plus persistently very low performance.
- A completed remediation moves a gap to `monitoring`; Phase 4 never resolves it automatically.

Mastery refresh automatically recalculates the relevant gap.

## Search architecture

`QuestionSearchService` is shared by Question Bank/Worksheet Builder and remediation. Its flow is:

1. Rule-based natural-language intent parsing.
2. Visibility and review-status permission scope.
3. Indexed metadata pre-filtering with a bounded candidate window.
4. Keyword scoring and optional semantic-provider scoring.
5. Quality and difficulty fit.
6. Student exposure mode (`unseen_only`, `prefer_unseen`, `allow_repeat`, `retry_incorrect`).
7. Normalized/lexical near-duplicate removal.
8. Diversity reranking.

The default `NullVectorSearchProvider` explicitly reports semantic search as unavailable. Metadata, keyword, topic, skill, quality, exposure, and diversity search continue to work. It does not generate fake semantic scores.

`VectorSearchProvider` defines `indexQuestion`, `updateQuestion`, `removeQuestion`, `search`, `bulkIndex`, and `healthCheck`, allowing Qdrant, Pinecone, Weaviate, pgvector, or another provider to be added without changing academic services.

## Remediation architecture

The teacher previews the selected canonical questions and explicitly approves them. `RemediationService` then transactionally:

1. Prevents another active remediation for the same student and gap.
2. Creates a normal worksheet snapshot.
3. Links each snapshot to its canonical question.
4. Creates `personalized_remediations` and `remediation_questions` records.
5. Creates a targeted Phase 3 `worksheet_assignment` with `activity_type=remediation`.

The existing runner handles hints, explanations, autosave, retry, submission, and scoring. Completion records canonical question exposure and question-level learning evidence, then moves the gap to monitoring.

## Database migration and indexes

Apply `database/phase4_adaptive_learning.sql`. It is repeatable and additive. New tables:

- `question_bank_items`
- `question_embeddings`
- `question_exposures`
- `student_learning_gaps`
- `personalized_remediations`
- `remediation_questions`
- `question_search_logs`

The migration adds canonical links to both question snapshot tables, remediation enum values, question-level evidence fields, composite metadata/exposure/gap indexes, and a FULLTEXT academic-text index.

## Safety and rollback

- Take a database backup before production migration.
- Deploy the migration before the PHP/UI changes.
- The migration never deletes historical academic data.
- Old exam and worksheet runners do not depend on Phase 4 tables.
- To roll back application behavior, deploy the prior PHP version and hide the Phase 4 UI/API. Leave additive tables and columns in place so canonical links, exposure history, and remediation evidence are not lost.
- Do not drop Phase 4 tables until all remediation assignments and their historical evidence have been archived/exported.

## Verification

Run:

```bash
/Applications/XAMPP/xamppfiles/bin/php tests/verify_phase4_integration.php
```

The verification covers gap severity/confidence, intent parsing, vector-unavailable fallback, exposure modes, reviewed assignment creation, duplicate protection, the existing runner, question evidence/exposure, and the monitoring lifecycle.

Phase 5 remains intentionally out of scope: mastery checks, automatic resolution, intervention escalation, and learning-path rewrites are not implemented.
