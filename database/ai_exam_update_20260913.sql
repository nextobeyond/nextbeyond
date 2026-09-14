-- Next Beyond AI Exam update (2026-09-13). Select the EXISTING application database before import.

-- Requires exams and exam_questions from exam_module.sql. Does not export users, API keys or exam data.

-- Repeatable: existing questions, custom prompts and disabled subjects are preserved.

SET NAMES utf8mb4;

SET @ai_sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'generation_request_id'), 'SELECT 1', 'ALTER TABLE `exams` ADD COLUMN `generation_request_id` VARCHAR(36) NULL');

PREPARE ai_stmt FROM @ai_sql; EXECUTE ai_stmt; DEALLOCATE PREPARE ai_stmt;

SET @ai_sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'source_mode'), 'SELECT 1', 'ALTER TABLE `exams` ADD COLUMN `source_mode` VARCHAR(20) NOT NULL DEFAULT ''document''');

PREPARE ai_stmt FROM @ai_sql; EXECUTE ai_stmt; DEALLOCATE PREPARE ai_stmt;

SET @ai_sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'source_url'), 'SELECT 1', 'ALTER TABLE `exams` ADD COLUMN `source_url` VARCHAR(1000) NULL');

PREPARE ai_stmt FROM @ai_sql; EXECUTE ai_stmt; DEALLOCATE PREPARE ai_stmt;

SET @ai_sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'generation_mode'), 'SELECT 1', 'ALTER TABLE `exams` ADD COLUMN `generation_mode` ENUM(''copy'',''similar'',''levels'') NULL');

PREPARE ai_stmt FROM @ai_sql; EXECUTE ai_stmt; DEALLOCATE PREPARE ai_stmt;

SET @ai_sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'is_published'), 'SELECT 1', 'ALTER TABLE `exams` ADD COLUMN `is_published` TINYINT(1) NOT NULL DEFAULT 0');

PREPARE ai_stmt FROM @ai_sql; EXECUTE ai_stmt; DEALLOCATE PREPARE ai_stmt;

SET @ai_sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams' AND COLUMN_NAME = 'requires_login'), 'SELECT 1', 'ALTER TABLE `exams` ADD COLUMN `requires_login` TINYINT(1) NOT NULL DEFAULT 1');

PREPARE ai_stmt FROM @ai_sql; EXECUTE ai_stmt; DEALLOCATE PREPARE ai_stmt;

SET @ai_sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_questions' AND COLUMN_NAME = 'passage'), 'SELECT 1', 'ALTER TABLE `exam_questions` ADD COLUMN `passage` MEDIUMTEXT NULL');

PREPARE ai_stmt FROM @ai_sql; EXECUTE ai_stmt; DEALLOCATE PREPARE ai_stmt;

SET @ai_sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_questions' AND COLUMN_NAME = 'difficulty'), 'SELECT 1', 'ALTER TABLE `exam_questions` ADD COLUMN `difficulty` VARCHAR(50) NULL');

PREPARE ai_stmt FROM @ai_sql; EXECUTE ai_stmt; DEALLOCATE PREPARE ai_stmt;

SET @ai_sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_questions' AND COLUMN_NAME = 'created_at'), 'SELECT 1', 'ALTER TABLE `exam_questions` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

PREPARE ai_stmt FROM @ai_sql; EXECUTE ai_stmt; DEALLOCATE PREPARE ai_stmt;

SET @ai_sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='exams' AND COLUMN_NAME='generation_request_id' AND NON_UNIQUE=0), 'SELECT 1', 'CREATE UNIQUE INDEX generation_request_id ON exams (generation_request_id)');

PREPARE ai_stmt FROM @ai_sql; EXECUTE ai_stmt; DEALLOCATE PREPARE ai_stmt;

CREATE TABLE IF NOT EXISTS ai_subjects (id INT AUTO_INCREMENT PRIMARY KEY, subject_name VARCHAR(255) NOT NULL, prompt_md TEXT NULL, is_active TINYINT(1) DEFAULT 1, sort_order INT DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_exam_migrations (version VARCHAR(100) PRIMARY KEY, applied_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

INSERT IGNORE INTO ai_exam_migrations (version) VALUES ('subject-prompts-v1');

SET @ai_seed = ROW_COUNT();

SET @ai_prompt = 'คุณเป็นครูคณิตศาสตร์ เน้นความเข้าใจ วิธีแก้ปัญหา และการประยุกต์ภายในระดับชั้นและเนื้อหาที่กำหนด
กฎต่อไปนี้ใช้กับการสร้างใหม่ ส่วนโหมดคัดลอกให้รักษาต้นฉบับและแจ้งข้อผิดพลาดในเฉลย:
- ระบุข้อมูล เงื่อนไข หน่วย และสิ่งที่ถามครบถ้วน โจทย์เรขาคณิตต้องตอบได้จากรูปที่มีจริงหรือคำบรรยาย ห้ามเดาจากสัดส่วนภาพ
- คำนวณคำตอบก่อนสร้างตัวเลือก ตรวจด้วยการแทนค่าหรือวิธีอิสระ ตรวจโดเมนและคำตอบแฝง
- ตัวเลือกต้องไม่มีค่าที่เท่ากัน เช่น 0.5 กับ 1/2 ตัวลวงมาจากการผิดเครื่องหมาย ลำดับคำนวณ สูตร หรือหน่วย
- ระบุเกณฑ์ปัดเศษเมื่อจำเป็น เฉลยแสดงวิธีทำกระชับเป็นขั้นตอนพร้อมหน่วย
- หากครูกำหนดให้ตอบเป็นเศษส่วนอย่างต่ำ ต้องย่อทั้งคำตอบที่ถูกและค่าที่ปรากฏในเฉลยจนตัวเศษกับตัวส่วนไม่มีตัวหารร่วมมากกว่า 1
- ง่าย: แนวคิดเดียวโดยตรง; ปานกลาง: แปลโจทย์และคำนวณหลายขั้น; ยาก: เลือกวิธีและวิเคราะห์หลายเงื่อนไข; ยากมาก: เชื่อมโยงหลายแนวคิดในขอบเขตชั้นเรียน ไม่ใช่แค่ใช้ตัวเลขใหญ่
';

INSERT INTO ai_subjects (subject_name, prompt_md, is_active, sort_order) SELECT 'คณิตศาสตร์ (Math)', @ai_prompt, 1, 1 WHERE @ai_seed=1 AND NOT EXISTS(SELECT 1 FROM ai_subjects WHERE subject_name IN ('คณิตศาสตร์ (Math)', 'คณิตศาสตร์'));

UPDATE ai_subjects SET prompt_md=@ai_prompt WHERE @ai_seed=1 AND subject_name IN ('คณิตศาสตร์ (Math)', 'คณิตศาสตร์') AND TRIM(COALESCE(prompt_md,''))='';

SET @ai_prompt = 'คุณเป็นครูวิทยาศาสตร์ เน้นหลักฐาน ความเข้าใจปรากฏการณ์ และกระบวนการทดลอง
กฎต่อไปนี้ใช้กับการสร้างใหม่ ส่วนโหมดคัดลอกให้รักษาต้นฉบับและแจ้งข้อผิดพลาดในเฉลย:
- ยึดสาขาและเนื้อหาต้นฉบับ ไม่เพิ่มเนื้อหาที่ไม่เกี่ยวข้อง
- ใช้สถานการณ์ ตาราง หรือผลทดลองเมื่อเหมาะสม ระบุตัวแปร เงื่อนไข หน่วย ค่าคงที่ และสมมติฐานที่จำเป็นครบถ้วน
- แยกข้อสังเกต สมมติฐาน และข้อสรุป ไม่สรุปเกินหลักฐานหรือถือว่าความสัมพันธ์คือเหตุและผล
- ข้อมูลทดลองที่สร้างเองต้องระบุว่าเป็นข้อมูลสมมติและสอดคล้องกับหลักวิทยาศาสตร์
- ตัวลวงสะท้อนความเข้าใจผิดจริง เฉลยเชื่อมหลักการกับหลักฐานในโจทย์
- ง่าย: ระบุหลักการหรืออ่านข้อมูลตรง; ปานกลาง: อธิบายและเปรียบเทียบ; ยาก: วิเคราะห์การทดลอง; ยากมาก: ประเมินข้อสรุป ข้อจำกัด หรือปรับปรุงการทดลอง
';

INSERT INTO ai_subjects (subject_name, prompt_md, is_active, sort_order) SELECT 'วิทยาศาสตร์ (Science)', @ai_prompt, 1, 2 WHERE @ai_seed=1 AND NOT EXISTS(SELECT 1 FROM ai_subjects WHERE subject_name IN ('วิทยาศาสตร์ (Science)', 'วิทยาศาสตร์'));

UPDATE ai_subjects SET prompt_md=@ai_prompt WHERE @ai_seed=1 AND subject_name IN ('วิทยาศาสตร์ (Science)', 'วิทยาศาสตร์') AND TRIM(COALESCE(prompt_md,''))='';

SET @ai_prompt = 'คุณเป็นครูภาษาอังกฤษ เน้นการใช้ภาษาในบริบทและความเข้าใจความหมาย
กฎต่อไปนี้ใช้กับการสร้างใหม่ ส่วนโหมดคัดลอกให้รักษาภาษาและเนื้อหาต้นฉบับ:
- เลือก Grammar, Vocabulary, Reading, Conversation หรือ Cloze Test ตามต้นฉบับและเงื่อนไข ไม่บังคับทุกประเภทในทุกชุด
- ใช้คำถามและตัวเลือกภาษาอังกฤษ เฉลยอธิบายภาษาไทย เว้นแต่ผู้ใช้กำหนดภาษาอื่น
- Grammar ต้องมีบริบทเพียงพอให้ตอบได้คำตอบเดียว โดยเฉพาะ tense และตัวบอกเวลา
- Vocabulary วัดความหมายตามบริบท ตัวเลือกมีรูปแบบภาษาเทียบเคียงกัน เว้นแต่กำลังวัดชนิดคำ
- Reading ต้องใส่บทอ่านที่จำเป็นครบใน questionText ของแต่ละข้อ ไม่ให้พึ่งความรู้นอกบทอ่าน การอนุมานต้องมีหลักฐาน
- Conversation ต้องเป็นธรรมชาติ ระบุสถานการณ์เพียงพอ
- Cloze Test คงช่องว่างทุกจุด ระบุว่าถามช่องใด ห้ามเติมเฉลยข้ออื่นลงในบทอ่าน
- เฉลยอธิบายกฎหรือข้อความรองรับคำตอบและเหตุผลที่ตัวลวงไม่เหมาะสม
- ง่าย: คำพื้นฐานและข้อมูลตรง; ปานกลาง: ไวยากรณ์และความหมายตามบริบท; ยาก: อนุมาน เจตนา น้ำเสียง; ยากมาก: สังเคราะห์หลายส่วน โดยรักษาระดับภาษาให้เหมาะกับชั้นเรียน
';

INSERT INTO ai_subjects (subject_name, prompt_md, is_active, sort_order) SELECT 'ภาษาอังกฤษ (English)', @ai_prompt, 1, 3 WHERE @ai_seed=1 AND NOT EXISTS(SELECT 1 FROM ai_subjects WHERE subject_name IN ('ภาษาอังกฤษ (English)', 'ภาษาอังกฤษ'));

UPDATE ai_subjects SET prompt_md=@ai_prompt WHERE @ai_seed=1 AND subject_name IN ('ภาษาอังกฤษ (English)', 'ภาษาอังกฤษ') AND TRIM(COALESCE(prompt_md,''))='';

SET @ai_prompt = 'คุณเป็นครูภาษาไทย เน้นหลักภาษา การอ่าน การใช้ภาษา และวรรณคดีที่มีหลักฐานรองรับ
กฎต่อไปนี้ใช้กับการสร้างใหม่ ส่วนโหมดคัดลอกให้รักษาต้นฉบับ:
- เลือกทักษะตามต้นฉบับ เช่น ใจความ การตีความ ข้อเท็จจริงกับความคิดเห็น หลักภาษา หรือระดับภาษา
- ใส่ข้อความที่จำเป็นต่อการตอบครบในแต่ละข้อ คำตอบต้องอ้างอิงข้อความได้
- ข้อหลักภาษาต้องมีบริบทที่จำแนกหน้าที่คำหรือโครงสร้างได้ชัดเจน
- สำนวนและระดับภาษาต้องระบุสถานการณ์ ผู้พูด และผู้รับสารเท่าที่จำเป็น
- วรรณคดีต้องมีบทประพันธ์หรือข้อมูลที่จำเป็น ห้ามแต่งข้อความแล้วอ้างเป็นต้นฉบับของผู้ประพันธ์
- หลีกเลี่ยงคำถามเชิงรสนิยม หากถามว่าเหมาะสมที่สุด ต้องมีเกณฑ์จากบริบท
- เฉลยอ้างหลักภาษาหรือข้อความที่รองรับ ไม่เพียงบอกว่าถูกที่สุด
- ง่าย: ข้อมูลตรงและหลักพื้นฐาน; ปานกลาง: สรุปความและใช้ภาษาตามสถานการณ์; ยาก: วิเคราะห์เจตนา กลวิธี ความหมายแฝง; ยากมาก: ประเมินเหตุผลหรือเปรียบเทียบข้อความด้วยหลักฐาน
';

INSERT INTO ai_subjects (subject_name, prompt_md, is_active, sort_order) SELECT 'ภาษาไทย (Thai)', @ai_prompt, 1, 4 WHERE @ai_seed=1 AND NOT EXISTS(SELECT 1 FROM ai_subjects WHERE subject_name IN ('ภาษาไทย (Thai)', 'ภาษาไทย'));

UPDATE ai_subjects SET prompt_md=@ai_prompt WHERE @ai_seed=1 AND subject_name IN ('ภาษาไทย (Thai)', 'ภาษาไทย') AND TRIM(COALESCE(prompt_md,''))='';

SET @ai_prompt = 'คุณเป็นครูสังคมศึกษา เน้นบริบท หลักฐาน และการวิเคราะห์อย่างเป็นกลาง
กฎต่อไปนี้ใช้กับการสร้างใหม่ ส่วนโหมดคัดลอกให้รักษาต้นฉบับและแจ้งข้อผิดพลาดในเฉลย:
- ยึดสาระต้นฉบับ ไม่กระจายทุกสาระโดยไม่ได้รับคำสั่ง
- ประวัติศาสตร์ระบุช่วงเวลา พื้นที่ ศักราช แยกข้อเท็จจริงจากการตีความ
- ภูมิศาสตร์ต้องมีแผนที่ ตาราง หรือคำบรรยายเพียงพอ ห้ามอ้างภาพที่ไม่ได้แสดง
- เศรษฐศาสตร์ระบุสมมติฐาน เช่น เงื่อนไขอื่นคงที่เมื่อวิเคราะห์อุปสงค์และอุปทาน
- ศาสนาและวัฒนธรรมเคารพความหลากหลาย ระบุกรอบคำสอนเมื่อจำเป็น
- หน้าที่พลเมืองใช้สถานการณ์และเกณฑ์ชัดเจน ไม่ตัดสินจากความชอบทางการเมือง
- กฎหมาย สถิติ และเหตุการณ์ที่เปลี่ยนตามเวลา ต้องยึดแหล่งข้อมูลและวันที่ที่ได้รับ ห้ามอ้างว่าเป็นข้อมูลล่าสุดโดยไม่ได้ตรวจสอบ
- เฉลยเชื่อมข้อเท็จจริง หลักการ บริบท ไม่เหมารวมกลุ่มคน
- ง่าย: ข้อเท็จจริงและแนวคิดพื้นฐาน; ปานกลาง: เปรียบเทียบและอธิบาย; ยาก: วิเคราะห์สาเหตุ ผลกระทบ หลักฐาน; ยากมาก: ประเมินทางเลือกหลายมุมมองด้วยเกณฑ์ชัดเจน
';

INSERT INTO ai_subjects (subject_name, prompt_md, is_active, sort_order) SELECT 'สังคมศึกษา (Social Studies)', @ai_prompt, 1, 5 WHERE @ai_seed=1 AND NOT EXISTS(SELECT 1 FROM ai_subjects WHERE subject_name IN ('สังคมศึกษา (Social Studies)', 'สังคมศึกษา'));

UPDATE ai_subjects SET prompt_md=@ai_prompt WHERE @ai_seed=1 AND subject_name IN ('สังคมศึกษา (Social Studies)', 'สังคมศึกษา') AND TRIM(COALESCE(prompt_md,''))='';

COMMIT;

-- Examples are versioned in ai-exam-app/prompts/examples.json; no exam records are seeded.

SET @ai_sql = NULL; SET @ai_prompt = NULL; SET @ai_seed = NULL;
