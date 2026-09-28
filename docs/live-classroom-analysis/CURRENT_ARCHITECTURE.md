# Live Classroom — Current Architecture & System Analysis

> เอกสารสรุปการทำงานของระบบห้องเรียนสด (Live Classroom / Live Session) ใน Nextbeyond  
> วิเคราะห์จากซอร์สโค้ดปัจจุบันที่มีอยู่จริง (As-Is Architecture) ห้ามมีข้อสมมติฐานที่ไม่ได้เขียนโค้ดไว้

---

## 1. Live Classroom ปัจจุบันทำอะไรได้บ้าง
ระบบ Live Classroom ใน Nextbeyond คือระบบบริหารจัดการคาบเรียนสดแบบเรียลไทม์ระหว่างคุณครูและนักเรียน โดยผสมผสาน **การสอบวัดผลสด (Live Quiz/Exam)**, **การควบคุมหน้าจอผู้เรียน (Eyes On Me)**, **การเช็กความเข้าใจระหว่างคาบ (In-Class Understanding Check)**, **การควบคุมความสนใจแบบ Gamification (Boss Battle Arena)**, **การเตรียมตัวก่อนเรียน (Pre-Class Readiness)**, และ **การมอบหมายการบ้านหลังเรียน (Post-Class Assignment)** ทำงานประสานกันผ่านฐานข้อมูล MySQL และการส่งสัญญาณ Polling ระหว่างหน้าเว็บครูและนักเรียน

---

## 2. Teacher ทำอะไรได้ (ความสามารถของครู)
อ้างอิงจาก [`admin/live-sessions.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-sessions.php), [`admin/live-session-room.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-session-room.php), [`admin/live-sessions-api.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-sessions-api.php), และ [`assets/js/live-session-teacher.js`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/assets/js/live-session-teacher.js):
1. **สร้างและเปิดห้องเรียนสด:** ตั้งชื่อห้อง, สุ่ม PIN 6 หลัก, เลือกว่าจะผูกกับข้อสอบชุดใด, กำหนดเวลาทำข้อสอบ (Time Limit), เลือกระดับชั้น และเลือกว่าจะผูกกับ Calendar Event ใด
2. **ควบคุมหน้าจอนักเรียน (Eyes On Me):** 
   - สวิตช์หลัก (Global Switch) เพื่อล็อกหน้าจอนักเรียนทุกคนพร้อมกันด้วยม่านสีดำ ไม่ให้กดทำข้อสอบต่อเพื่อหันมาฟังครูอธิบาย
   - แผงเลือกล็อกเฉพาะรายบุคคล (Per-Student Selective Lock)
3. **ส่งประกาศสดด่วน (Urgent Announcement):** พิมพ์ข้อความให้เด้งขึ้นบนแถบด้านบนของหน้าจอนักเรียนทุกคนทันที
4. **ส่งสัญญาณเช็กความเข้าใจ (Trigger Understanding Check):** กดปุ่ม `📢 เช็กความเข้าใจ` เพื่อส่งสัญญาณให้นักเรียนทุกคนประเมินตนเอง และดูสถิติแท่งเปอร์เซ็นต์ (เข้าใจ / บางส่วน / ไม่เข้าใจ) แบบเรียลไทม์
5. **ดูภาพรวมข้อสอบ (Exam Overview):** ดูข้อสอบทุกข้อพร้อมเฉลยและทักษะที่เกี่ยวข้อง
6. **ตรวจสอบความคืบหน้านักเรียนรายคน (Student Details):** ดูว่าใครกำลังทำข้อไหน, เปอร์เซ็นต์ความคืบหน้า, คะแนนสอบ, และปุ่มกดสร้างรายการติดตามหลังคาบ (Follow-up Intervention)
7. **เช็คชื่อและส่งออกข้อมูล (Attendance):** ดูเวลาที่นักเรียนแต่ละคนกดเข้าร่วมห้อง และดาวน์โหลดไฟล์ `.csv` (รองรับภาษาไทยสำหรับ Excel)
8. **วิเคราะห์ข้อที่นักเรียนตอบผิดมากที่สุด (Missed Questions):** จัดอันดับข้อที่นักเรียนทำผิดมากที่สุด พร้อมจำนวนคนและเปอร์เซ็นต์ความแม่นยำ
9. **ดูแผนที่ทักษะของห้อง (Class Skill Map):** สรุปเปอร์เซ็นต์ความแม่นยำแยกตามทักษะย่อยของข้อสอบชุดนั้น
10. **ดูศูนย์บทเรียนเสริม (Remediation Hub):** สรุปหัวข้อที่มีอัตราตอบผิดสูงสุด เพื่อให้ครูเน้นย้ำหรือจัดกิจกรรมเสริม
11. **รีเซ็ตสิทธิ์การสอบนักเรียน (Reset Student Attempt):** กดรีเซ็ตคำตอบให้นักเรียนรายคน เพื่อล้างคำตอบเดิมและให้นักเรียนเริ่มสอบใหม่ได้ทันที
12. **มอบหมายงานหลังเรียน (Post-Class Quick Assign):** เลือกใบงานจากคลัง กำหนดประเภท (การบ้าน/ใบงาน/ฝึกฝน/หลังเรียน) และตั้งกำหนดส่ง เชื่อมโยงกับเซสชันนี้อัตโนมัติ
13. **บอสไฟท์บนจอใหญ่ (Boss Battle Arena):** เปิดมอนสเตอร์บอสขึ้นจอโปรเจกเตอร์หน้าห้อง (เช่น มังกรเพลิง, โกเล็ม, ฟีนิกซ์), ครูสั่งโจมตีลดเลือดบอสได้ (`-25 HP`), เลือดบอสจะลดอัตโนมัติเมื่อนักเรียนตอบถูกในแต่ละข้อ (`-10 HP`), และแจกแต้มพอยต์อัตโนมัติเมื่อพิชิตบอสได้
14. **ปิดห้องเรียนสด (Close Session):** สั่งสิ้นสุดเซสชัน ส่งผลให้หน้านักเรียน Auto-submit คำตอบทันที และระบบจะซิงก์ผลความเข้าใจผู้เรียนเข้าสู่ Topic Mastery

---

## 3. Student ทำอะไรได้ (ความสามารถของนักเรียน)
อ้างอิงจาก [`student/live-session.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/live-session.php), [`student/live-session-api.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/live-session-api.php), และ [`student/take-test.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/take-test.php):
1. **เตรียมตัวก่อนเรียน (Pre-class):** ตรวจสอบหัวข้อย่อยและทำเครื่องหมายว่าทบทวนแล้วที่หน้า `get-ready.php`
2. **เข้าร่วมห้องเรียนผ่าน PIN 6 หลัก:** กรอกรหัส PIN บนช่องกรอก 6 หลักที่มี Auto-focus
3. **ใช้งานห้องล็อบบี้สด (Lobby):**
   - ดูข้อมูลครูผู้สอน, เวลาเรียน, และสถานที่เรียน
   - กดลิงก์เข้าห้องประชุมออนไลน์ (Google Meet / Zoom) หากครูระบุไว้
   - ดูหัวข้อประจำคาบเรียน
   - ดูประกาศด่วนจากครู
   - ออกจากห้องเรียน (Leave Session)
4. **ทำข้อสอบสดประจำคาบ (Live Quiz):**
   - กดเข้าสู่หน้า `take-test.php` ทำข้อสอบแบบเลือกตอบ (Multiple Choice)
   - หน้าจอจะล็อกอัตโนมัติเมื่อครูเปิด Eyes On Me
   - ตอบคำถามและส่งความคืบหน้า (ข้อปัจจุบัน + จำนวนข้อที่ทำแล้ว) ขึ้นเซิร์ฟเวอร์แบบเรียลไทม์
   - ได้รับการแจ้งเตือนหากครูสั่งรีเซ็ตข้อสอบ และหน้าจอจะโหลดใหม่ทันที
   - หากครูปิดเซสชัน ระบบจะบังคับส่งคำตอบอัตโนมัติ
5. **ตอบรับการเช็กความเข้าใจ (Interactive Understanding Feedback):** เมื่อครูกดเช็กความเข้าใจ จะมีหน้าต่างเด้งขึ้นมาให้นักเรียนกดเลือกระดับความเข้าใจ 3 ระดับ: 👍 เข้าใจแล้ว / 😐 บางส่วน / 🤷 ยังไม่เข้าใจ
6. **ดูผลสอบย้อนหลัง:** ตรวจดูคะแนนและเฉลยหลังส่งข้อสอบที่หน้า `test-result.php`

---

## 4. Session ถูกสร้างอย่างไร
อ้างอิงจาก [`admin/live-sessions-api.php:407-468`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-sessions-api.php#L407-L468):
1. ครูส่งคำขอ `POST live-sessions-api.php` พร้อมข้อมูล: `title`, `examId`, `educationStage`, `allowLateJoin`, `hasTimeLimit`, `timeLimitMinutes`, `customPin` (ถ้ามี), และ `calendarEventId` (ถ้ามี)
2. ฟังก์ชัน `closeOtherActiveSessions($pdo, $currentUserId)` จะปิดเซสชันสถานะ `active` เดิมทั้งหมดของครูท่านนั้น เพื่อบังคับกฎ **1 ครู = 1 Active Session**
3. ระบบสร้าง Primary Key รูปแบบ: `'ses-' . time() . '-' . random_int(1000, 9999)`
4. สุ่ม PIN 6 หลักผ่าน `generateSessionPin($pdo)` (ตรวจสอบไม่ให้ซ้ำกับห้องอื่นที่ยังเปิดอยู่)
5. บันทึกเรคคอร์ดลงในตาราง `classroom_sessions` โดยค่าเริ่มต้นกำหนด `status = 'active'`, `boss_current_hp = 100`, `boss_max_hp = 100`, `boss_reward_points = 50`
6. หากส่ง `calendarEventId` มาด้วย: จะเรียก `Phase2SessionService->linkSessionToEvent($sessionId, $calEventId)` เพื่อเขียน `session_id` ลงในตาราง `calendar_events` และเชื่อมหัวข้อใน `session_topics`

นอกจากนี้ ในฝั่งระบบซ่อมเสริม (Phase 5 Remediation) ใน [`includes/phase5-mastery-service.php:558`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/includes/phase5-mastery-service.php#L558): เมื่อครูกดนัดหมายสอนเสริมรายบุคคล (Intervention Schedule) ระบบจะสร้าง `classroom_sessions` อัตโนมัติด้วย ID ขึ้นต้น `intv_...` พร้อมผูก `intervention_id` และ `gap_id`

---

## 5. Session เริ่มและจบอย่างไร
- **การเริ่ม (Start):**
  - เซสชันจะเริ่มทันทีที่มีการบันทึกสถานะเป็น `'active'` ในตาราง `classroom_sessions`
  - ฟิลด์ `started_at` จะถูกบันทึกเป็นเวลาปัจจุบัน (`NOW()`)
- **การจบ (End / Close):**
  - ครูสามารถกดปิดเซสชันได้จากหน้าห้องควบคุม หรือผ่านคำขอ `PATCH live-sessions-api.php` โดยส่ง `{ status: 'closed' }`
  - ฝั่ง Backend จะอัปเดตฟิลด์ `ended_at = NOW()` และ `status = 'closed'`
  - **Post-Session Hook 1:** เรียก `$_p2->syncUnderstandingToMastery($sessionId)` เพื่อนำข้อมูลการกดเช็กความเข้าใจของนักเรียนในคาบนี้ไปคำนวณและอัปเดตคะแนนลงในตาราง `topic_mastery`
  - **Post-Session Hook 2:** ในฝั่งนักเรียน เมื่อ Polling ตรวจพบ `sessionClosed: true` หน้าทำข้อสอบ (`take-test.php`) จะแจ้งเตือนและเรียกคำสั่ง `submitExam(true)` เพื่อส่งคำตอบที่ทำค้างไว้ทันที แล้วพานักเรียนกลับสู่หน้าแรก

---

## 6. Student เข้าห้องอย่างไร
อ้างอิงจาก [`student/live-session.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/live-session.php) และ [`student/live-session-api.php:163-250`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/live-session-api.php#L163-L250):
1. นักเรียนใส่รหัส PIN 6 หลักที่หน้า `student/live-session.php` หรือกดจากลิงก์ที่มีพารามิเตอร์ `?pin=XXXXXX`
2. ส่งคำขอ `POST live-session-api.php?action=join` พร้อม payload `{ sessionPin: pin }`
3. Backend ตรวจสอบ:
   - ตรวจหาเซสชันใน `classroom_sessions` ที่ตรงกับ PIN และมี `status = 'active'`
   - หากพบเซสชัน จะตรวจสิทธิ์การเข้าสาย (`allow_late_join`): หากห้องนี้ไม่อนุญาตให้เข้าสาย และเวลาเริ่มเกิน 5 นาที (300 วินาที) แล้ว ระบบจะปฏิเสธ (403 Forbidden)
4. การสร้างตัวตนในเซสชัน:
   - หากนักเรียนยังไม่เคยเข้าร่วม จะเพิ่มเรคคอร์ดลงใน `session_participants` (ID: `sp-{timestamp}-{rand}`) กำหนด `status = 'joined'`
   - หากเซสชันมี `exam_id` ระบบจะค้นหาว่านักเรียนมี `test_attempts` ที่ยังทำไม่เสร็จของข้อสอบชุดนั้นอยู่หรือไม่ หากมีจะผูก `attempt_id` เข้าด้วยกัน
5. Backend ส่งกลับ `lobbyUrl` (`live-session.php?sessionId=...`) เพื่อให้นักเรียนเข้าสู่หน้าล็อบบี้

---

## 7. ระบบคำถามทำงานอย่างไร
- คำถามในเซสชันจะดึงมาจากตาราง `exams` และ `exam_questions` ตามค่า `exam_id` ที่ผูกไว้กับเซสชัน
- ฝั่งครู: API `live-sessions-api.php` จะอ่านคำถามทั้งหมด แปลงตัวเลือก JSON และส่งออกในอาเรย์ `questions` เพื่อแสดงในหน้าต่าง "ภาพรวมข้อสอบ (Exam Overview)"
- ฝั่งนักเรียน: เมื่อนักเรียนกดเริ่มทำแบบทดสอบจากหน้าล็อบบี้ จะเปิดหน้า `student/take-test.php?id={examId}&sessionId={sessionId}` ซึ่งจะดึงข้อสอบจากฐานข้อมูลมาแสดงทีละข้อพร้อมตัวเลือก A, B, C, D และจับเวลาถอยหลังตามที่กำหนดในเซสชัน

---

## 8. ระบบเก็บคำตอบอย่างไร
อ้างอิงจาก [`student/take-test.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/take-test.php) และ [`student/submit-test.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/submit-test.php):
1. **ฝั่ง Client ระหว่างทำข้อสอบ:**
   - คำตอบที่นักเรียนเลือกจะถูกเก็บไว้ในตัวแปร JavaScript `answers[questionId] = selectedOption`
   - มีการสำรองข้อมูลลง `localStorage` ตามคีย์ `nb_exam_answers_{attemptId}` ทุกครั้งที่มีการเลือกคำตอบ
2. **การส่งความคืบหน้าระหว่างทำ:**
   - ทุกครั้งที่ Polling สถานะห้องสด (ทุก 3 วินาที) Client จะส่ง `currentQ` (ข้อปัจจุบัน) และ `answered` (จำนวนข้อที่ตอบแล้ว) ไปยัง `live-session-api.php?action=status`
   - Backend จะอัปเดตฟิลด์ `current_question` และ `answered_count` ในตาราง `session_participants` ทำให้คุณครูเห็นแท่งความคืบหน้าของนักเรียนขยับแบบเรียลไทม์
3. **การส่งข้อสอบสมบูรณ์ (Submit):**
   - เมื่อนักเรียนกดส่ง หรือหมดเวลา หรือครูสั่งปิดห้อง ฟอร์มใน `take-test.php` จะยิงคำขอ `POST submit-test.php`
   - Backend จะบันทึกคำตอบแต่ละข้อลงในตาราง `test_answers` พร้อมคำนวณคะแนนและบันทึกคะแนนรวมลงในตาราง `test_attempts`
   - ปรับสถานะใน `session_participants`: `status = 'submitted'`, `answered_count = totalQuestions`

---

## 9. มี Realtime หรือไม่
- **ไม่มี WebSocket / Socket.io / SSE ในปัจจุบัน**
- ระบบใช้ **HTTP Short Polling** ผ่าน `fetch()`:
  - หน้าครู (`live-session-teacher.js`): Polling ทุก **3 วินาที** (3000 ms) ไปยัง `live-sessions-api.php?sessionId=...`
  - หน้าล็อบบี้นักเรียน (`live-session.php`): Polling ทุก **2.5 วินาที** (2500 ms) ไปยัง `live-session-api.php?action=status&sessionId=...`
  - หน้านักเรียนทำข้อสอบ (`take-test.php`): Polling ทุก **3 วินาที** (3000 ms)
- แม้จะเป็น Polling แต่ถูกออกแบบให้ตอบสนองเหมือน Real-time ผ่านการเปรียบเทียบ State (State diffing) เช่น เมื่อพบการเปลี่ยนแปลงของฟิลด์ `eyes_on_me_enabled` จอจะล็อกทันที หรือเมื่อพบข้อความประกาศใหม่ก็จะแสดงผลทันที

---

## 10. มี Autosave หรือไม่
- **มี Autosave ระดับ Client ในหน้าทำข้อสอบ:** ใน `student/take-test.php` มีการบันทึกคำตอบลงในเบราว์เซอร์ `localStorage` อัตโนมัติทุกครั้งที่นักเรียนกดเลือกคำตอบ
- **มี Heartbeat Progress Save:** มีการบันทึกความคืบหน้าข้อปัจจุบัน (`current_question`) และจำนวนข้อที่ตอบแล้ว (`answered_count`) ลงฐานข้อมูลตาราง `session_participants` ทุกรอบของ Polling (ทุก 3 วินาที)
- **การบันทึกตัวเลือกคำตอบลงตาราง `test_answers`:** จะเกิดขึ้นในขั้นตอนการส่งข้อสอบ (`submit-test.php`) โดยส่งคำตอบทั้งหมดในก้อนเดียว

---

## 11. มี Reconnect / Recovery หรือไม่
- **มี Recovery ผ่าน PIN และ Session ID:**
  - หากนักเรียนอินเทอร์เน็ตหลุด ปิดแท็บ หรือเบราว์เซอร์แครช เมื่อกลับมาเข้า `student/live-session.php` แล้วกรอก PIN เดิม ระบบจะตรวจพบว่านักเรียนมีเรคคอร์ดใน `session_participants` อยู่แล้ว และจะดึง `attempt_id` เดิมขึ้นมาทำงานต่อทันทีโดยไม่สร้างผู้เข้าร่วมซ้ำ
  - ในหน้า `take-test.php` เมื่อหน้าโหลดใหม่ ระบบจะอ่านคำตอบเดิมที่ค้างอยู่ใน `localStorage` กลับคืนมาให้ผู้เรียนทำต่อได้ทันที
- **ไม่มี Offline Engine แท้จริง:** หากขาดการเชื่อมต่ออินเทอร์เน็ตจะไม่สามารถ Polling หรือส่งข้อสอบได้จนกว่าจะต่อเน็ตคืน

---

## 12. มี Pre / Post Test หรือไม่
- **Pre-Test (การเตรียมตัวและทดสอบก่อนเรียน):**
  - มีระบบ **Pre-Class Get Ready** ที่ [`student/get-ready.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/get-ready.php): นักเรียนสามารถตรวจเช็กหัวข้อย่อยและทำ Checklist ความพร้อมก่อนเข้าเรียนสด
  - แต่ **ยังไม่มีโมเดล Pre-test ที่เป็นชุดข้อสอบแยกเฉพาะ (Separate Pre-test Exam Instance)** ก่อนเข้าห้องเรียนสด
- **In-Session Quiz:** เซสชันเชื่อมโยงกับชุดข้อสอบ 1 ชุดในฐานข้อมูล (`exam_id`) สำหรับทำระหว่างคาบเรียนสด
- **Post-Test (การวัดผลหลังเรียน):**
  - ในโมดอล `post_class_assignment` ของครู มีตัวเลือกประเภทกิจกรรม `posttest` (แบบทดสอบหลังเรียน) ที่สามารถดึงใบงาน/ข้อสอบจากคลังมามอบหมายให้นักเรียนทำหลังสิ้นสุดคาบเรียนสดได้

---

## 13. มี Remediation หรือไม่ (การสอนเสริม/ซ่อมเสริม)
- **มี ในระดับการวิเคราะห์และเชื่อมโยงคิวช่วยเหลือ (Intervention):**
  1. ในหน้าห้องควบคุมสด มีเมนู **ศูนย์บทเรียนเสริม (Remediation Hub)**: รวบรวมหัวข้อที่นักเรียนในห้องตอบผิดมากที่สุด คำนวณอัตราความแม่นยำเฉลี่ย เพื่อให้ครูทราบว่าต้องเน้นย้ำจุดใด
  2. มีปุ่ม **"ติดตามหลังคาบ"** ในรายชื่อนักเรียนแต่ละคน (ฟังก์ชัน `flagStudentForFollowup` ใน `assets/js/live-session-teacher.js:1052`): เมื่อครูกด จะเรียก API `action=follow_up_intervention` เพื่อส่งนักเรียนเข้าสู่ระบบ **Intervention Queue** ใน [`includes/phase5-mastery-service.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/includes/phase5-mastery-service.php)
  3. เมื่อมีการจัดตารางสอนซ่อมเสริมในระบบ Intervention ระบบสามารถสร้างห้องเรียนสดใหม่เพื่อสอนซ่อมเสริมเรื่องนั้นโดยเฉพาะได้
- **ยังไม่มี:** ระบบสร้างโจทย์ซ่อมเสริมอัตโนมัติเฉพาะบุคคลแบบ Real-time ภายในตัวห้องเรียนสดทันทีที่ตอบผิด

---

## 14. มี Student Monitoring หรือไม่ (การติดตามผู้เรียน)
- **มี ครบถ้วนทั้งภาพรวมและรายบุคคล:**
  1. **Live Student Progress Pills:** แถบสถานะนักเรียนสดหน้าห้องควบคุม แสดงชื่อ, ข้อที่กำลังทำ, เปอร์เซ็นต์ความคืบหน้า, และสถานะว่าส่งข้อสอบแล้วหรือไม่
  2. **Student Details Modal:** ดูรายชื่อนักเรียนทุกคน, อีเมล, คะแนนสอบ (หากส่งแล้ว), หรือข้อที่กำลังทำ (หากยังทำอยู่)
  3. **Real-time Question Stats:** ครูเห็นสถิติทันทีว่าข้อใดมีคนตอบผิดกี่คน (เช่น ข้อ 3 ตอบผิด 5 คน ถูก 20%)
  4. **Class Understanding Stats:** แถบเปอร์เซ็นต์แบบสด ๆ แสดงว่านักเรียนเข้าใจบทเรียนกี่เปอร์เซ็นต์

---

## 15. เชื่อม Calendar อย่างไร
- ตาราง `classroom_sessions` มีคอลัมน์ `calendar_event_id INT NULL`
- ตาราง `calendar_events` มีคอลัมน์ `session_id VARCHAR(64) NULL`
- ใน `admin/calendar-api.php`:
  - เมื่อดึงรายการ Event จะทำการ `LEFT JOIN classroom_sessions cs ON cs.id = ce.session_id` เพื่อส่ง `linked_session_id`, `linked_session_pin`, และ `linked_session_status`
  - มี Endpoint `POST calendar-api.php?action=link_session` สำหรับผูกเซสชันเข้ากับ Event
- ใน `assets/js/admin-calendar.js`: เมื่อครูเปิดดูคาบเรียนในปฏิทิน หากคาบนั้นยังไม่มีเซสชันจะมีปุ่ม `🔴 เริ่ม Live Session` ซึ่งจะส่งครูไปที่ `live-sessions.php?calendarEventId=...` และเมื่อสร้างเซสชันเสร็จ ทั้งสองจะถูกเชื่อมโยงกัน
- ใน `student/get-ready.php`: นักเรียนจะเห็นนับถอยหลังสู่วันที่และเวลาของ Event ในปฏิทิน พร้อมปุ่มเข้าห้องเรียนสดทันทีที่เซสชันของ Event นั้นถูกเปิด

---

## 16. เชื่อม Learning Path หรือไม่
- **เชื่อมโยงผ่าน 2 ช่องทาง:**
  1. **Topic Mastery Synchronization:** เมื่อครูสั่งปิดห้องเรียนสด (`status = 'closed'`) ฟังก์ชัน `syncUnderstandingToMastery` ใน `Phase2SessionService` จะนำผลการเช็กความเข้าใจ (`session_understanding_checks`) คำนวณเป็นคะแนนเฉลี่ย แล้วทำการ Upsert ลงในตาราง `topic_mastery` ซึ่งเป็นตารางฐานรากของ Skill Map และ Learning Path
  2. **Adaptive Roadmap Steps:** ใน Phase 5 Intervention เมื่อครูนัดหมายคาบสอนสดพิเศษเพื่อแก้ Learning Gap ระบบจะอัปเดตตาราง `adaptive_roadmap_steps` ให้มี `action_url = 'live-session.php?pin=' . $pin` ในแผนการเรียนของนักเรียนคนนั้น

---

## 17. เชื่อม IA Points หรือไม่ (Reward Points)
- **เชื่อมโยงผ่านระบบพิชิตบอส (Boss Fight):**
  - อ้างอิงจาก [`includes/live-sessions-helper.php:184-199`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/includes/live-sessions-helper.php#L184-L199) ในฟังก์ชัน `awardBossDefeatPoints()`:
  ```php
  $stmtTx = $pdo->prepare("INSERT INTO nc_point_transactions (user_id, task_id, roadmap_id, points, transaction_type, reason, created_at) VALUES (?, NULL, NULL, ?, 'earn', ?, CURRENT_TIMESTAMP)");
  foreach ($students as $uid) {
      $stmtTx->execute([(int) $uid, $rewardPoints, 'พิชิตบอสห้องเรียนสด #' . $sessionId]);
  }
  ```
  - เมื่อเลือดบอสลดลงเหลือ 0 (`boss_defeated = 1`) ระบบจะแจกแต้มรางวัล (ค่าเริ่มต้น 50 พอยต์ หรือตามที่ครูกำหนด) เข้าสู่ตาราง `nc_point_transactions` ให้กับนักเรียนทุกคนที่อยู่ใน `session_participants` ของเซสชันนั้นทันที

---

## 18. มี AI อยู่ตรงไหนบ้าง
- **"ไม่พบ AI integration ใน Live Classroom ปัจจุบัน"**
- จากการตรวจสอบโค้ดทั้งหมดใน:
  - `admin/live-sessions.php`
  - `admin/live-session-room.php`
  - `admin/live-sessions-api.php`
  - `assets/js/live-session-teacher.js`
  - `student/live-session.php`
  - `student/live-session-api.php`
  - `includes/live-sessions-helper.php`
  - `includes/phase2-session-service.php`
- ไม่มีการเรียกใช้ LLM API (เช่น OpenAI, Anthropic, Gemini, Groq), ไม่มีการทำ AI Answer Analysis แบบสด, ไม่มีการทำ AI Copilot ระหว่างคาบ, และไม่มีการสร้างคำถามแบบ Adaptive AI ภายใน Live Session
- การคำนวณสถิติทั้งหมดใน Live Session (ความแม่นยำ, การจัดอันดับข้อที่ผิด, อัตราความเข้าใจ, ดาเมจบอส) เป็น **Deterministic SQL Aggregation & Arithmetic Calculations** ทั้งหมด
- (หมายเหตุ: ระบบ AI ใน Nextbeyond มีอยู่เฉพาะในส่วนสร้างใบงาน `ai-worksheet.php` และสร้างข้อสอบ `ai-exam-app/` ภายนอก Live Session เท่านั้น)
