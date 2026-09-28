# Live Classroom — Gap Analysis & Missing Capabilities

> เอกสารเปรียบเทียบขีดความสามารถของระบบห้องเรียนสด (Live Classroom / Live Session) ใน Nextbeyond  
> ประเมินระหว่างสถาปัตยกรรมปัจจุบัน (As-Is) กับชุดฟีเจอร์มาตรฐานของระบบห้องเรียนเสมือนจริงขั้นสูง

---

## ตารางสรุปภาพรวมขีดความสามารถ (Capability Matrix)

| สถานะ | ความหมาย | จำนวน |
|---|---|:---:|
| ✅ **Existing** | มีการพัฒนาและใช้งานได้จริงในโค้ดปัจจุบัน | **9** |
| 🟡 **Partial** | มีบางส่วน หรือมีโครงสร้างรองรับแต่ยังไม่ครบวงจร | **7** |
| ❌ **Missing** | ยังไม่มีโค้ดหรือการพัฒนาฟีเจอร์นี้ในระบบปัจจุบัน | **4** |
| ❓ **Cannot determine** | ไม่สามารถระบุได้จากโค้ด | **0** |

---

## รายละเอียดการวิเคราะห์รายฟีเจอร์ (Detailed Capabilities Evaluation)

### 1. Live Session Control Center
- **สถานะ:** ✅ **Existing**
- **ไฟล์อ้างอิง:** 
  - [`admin/live-session-room.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-session-room.php)
  - [`admin/live-sessions.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-sessions.php)
  - [`assets/js/live-session-teacher.js`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/assets/js/live-session-teacher.js)
- **การทำงานปัจจุบัน:** หน้า Teacher Command Center สมบูรณ์แบบ มีแผงควบคุม PIN, สวิตช์ Eyes On Me, บัตรเช็กความเข้าใจ, แถบสถานะนักเรียนสด, และ 9 ปุ่มการทำงานหลัก

---

### 2. Attendance (การเช็คชื่อผู้เข้าเรียน)
- **สถานะ:** ✅ **Existing**
- **ไฟล์อ้างอิง:** 
  - [`admin/live-session-room.php:237-244`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-session-room.php#L237-L244)
  - [`admin/live-sessions-api.php:83-133`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-sessions-api.php#L83-L133)
  - [`assets/js/live-session-teacher.js:448-478`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/assets/js/live-session-teacher.js#L448-L478)
- **การทำงานปัจจุบัน:** บันทึกเวลาเข้าร่วมห้องของนักเรียนแต่ละคนลงใน `session_participants.joined_at` โดยอัตโนมัติ มีหน้าต่างดูรายชื่อผู้เข้าเรียน และปุ่มดาวน์โหลดไฟล์รายงาน `.csv` ภาษาไทยสำหรับ Excel

---

### 3. Student Live Status (การติดตามสถานะสดของผู้เรียน)
- **สถานะ:** ✅ **Existing**
- **ไฟล์อ้างอิง:** 
  - [`admin/live-session-room.php:310-318`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-session-room.php#L310-L318)
  - [`assets/js/live-session-teacher.js:145-200`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/assets/js/live-session-teacher.js#L145-L200)
  - [`admin/live-sessions-api.php:100-133`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-sessions-api.php#L100-L133)
- **การทำงานปัจจุบัน:** Live Student Progress Pills อัปเดตทุก 3 วินาที แสดงชื่อนักเรียน, ข้อปัจจุบันที่กำลังทำ (`current_question`), เปอร์เซ็นต์ความคืบหน้า (`progressPct`), คะแนนสอบ (เมื่อส่งแล้ว), และสถานะ `joined` / `in_progress` / `submitted`

---

### 4. Live Question Push (การยิงคำถามสดทีละข้อ)
- **สถานะ:** 🟡 **Partial**
- **ไฟล์อ้างอิง:** 
  - [`admin/live-sessions-api.php:49-80`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-sessions-api.php#L49-L80)
  - [`student/take-test.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/take-test.php)
- **สิ่งที่ระบบมี:** เซสชันสามารถผูกกับชุดข้อสอบ 1 ชุด (`exam_id`) ให้นักเรียนเปิดทำข้อสอบทั้งชุดในคาบเรียนได้
- **สิ่งที่ยังขาด:** **ยังไม่มีระบบ "ยิงโจทย์ทีละข้อ (Push Single Question On-demand)"** ที่ครูกดส่งโจทย์ข้อ 1 ให้นักเรียนทุกคนเห็นพร้อมกันบนหน้าจอ รอให้นักเรียนตอบหมดเวลา แล้วค่อยเฉลยและกดส่งข้อถัดไป (แบบ Kahoot หรือ Quizizz)

---

### 5. Live Answer Monitor (การมอนิเตอร์คำตอบสด)
- **สถานะ:** ✅ **Existing**
- **ไฟล์อ้างอิง:** 
  - [`admin/live-sessions-api.php:135-177`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-sessions-api.php#L135-L177)
  - [`assets/js/live-session-teacher.js:699-720`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/assets/js/live-session-teacher.js#L699-L720)
- **การทำงานปัจจุบัน:** ครูสามารถเปิดดูสถิติคำตอบสดผ่านเมนู "ข้อที่ตอบผิด (Missed Questions)" ซึ่งจะคำนวณจากตาราง `test_answers` แบบสด จัดอันดับว่าข้อใดมีผู้ตอบผิดมากที่สุด มีจำนวนคนผิดกี่คน และเปอร์เซ็นต์ความแม่นยำรายข้อ

---

### 6. Individual Student Control (การควบคุมผู้เรียนเฉพาะรายคน)
- **สถานะ:** ✅ **Existing**
- **ไฟล์อ้างอิง:** 
  - [`admin/live-session-room.php:141-154`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-session-room.php#L141-L154)
  - [`admin/live-sessions-api.php:285-304`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-sessions-api.php#L285-L304)
  - [`assets/js/live-session-teacher.js:1010-1040`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/assets/js/live-session-teacher.js#L1010-L1040)
- **การทำงานปัจจุบัน:** 
  1. ล็อกหน้าจอเฉพาะรายบุคคล (Per-Student Selective Eyes On Me Lock ผ่าน `locked_student_ids`)
  2. สั่งรีเซ็ตสิทธิ์การสอบเฉพาะรายบุคคล (Reset Student Attempt) ลบคำตอบเดิมเพื่อให้นักเรียนคนนั้นเริ่มสอบใหม่ได้ทันที
  3. ปักธงส่งนักเรียนรายคนเข้าคิวช่วยเหลือ (Follow-up Intervention)

---

### 7. Pre-test (การทดสอบก่อนเรียน)
- **สถานะ:** 🟡 **Partial**
- **ไฟล์อ้างอิง:** 
  - [`student/get-ready.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/get-ready.php)
  - [`student/get-ready-api.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/get-ready-api.php)
- **สิ่งที่ระบบมี:** ระบบ Pre-Class Get Ready ให้นักเรียนทบทวน Checklist หัวข้อย่อยก่อนเข้าเรียน
- **สิ่งที่ยังขาด:** **ยังไม่มีชุดข้อสอบ Pre-test แยกเฉพาะ** สำหรับทดสอบวัดระดับก่อนเริ่มเนื้อหาในเซสชัน เพื่อนำคะแนนมาเปรียบเทียบกับ Post-test

---

### 8. Post-test (การทดสอบหลังเรียน)
- **สถานะ:** 🟡 **Partial**
- **ไฟล์อ้างอิง:** 
  - [`assets/js/live-session-teacher.js:520-630`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/assets/js/live-session-teacher.js#L520-L630)
  - [`includes/phase3-mastery-service.php:1286-1320`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/includes/phase3-mastery-service.php#L1286-L1320)
- **สิ่งที่ระบบมี:** ในโมดอลมอบหมายงานหลังเรียน (`post_class_assignment`) มีประเภทกิจกรรม `posttest` เพื่อดึงใบงานจากคลังมาสั่งเป็นการบ้านหลังเรียนได้
- **สิ่งที่ยังขาด:** ยังไม่ใช่ Post-test แบบ Real-time ที่รันต่อเนื่องทันทีภายใน Live Session เดียวกันหลังจากครูสอนเสร็จ

---

### 9. Remediation (การสอนเสริม/ซ่อมเสริม)
- **สถานะ:** 🟡 **Partial**
- **ไฟล์อ้างอิง:** 
  - [`admin/live-session-room.php:254-262`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-session-room.php#L254-L262)
  - [`assets/js/live-session-teacher.js:632-653`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/assets/js/live-session-teacher.js#L632-L653)
  - [`includes/phase5-mastery-service.php:558`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/includes/phase5-mastery-service.php#L558)
- **สิ่งที่ระบบมี:** มีศูนย์บทเรียนเสริม (Remediation Hub) สรุปหัวข้อที่มีอัตราตอบผิดสูง และมีปุ่มส่งนักเรียนเข้าคิว Intervention เพื่อจัดตารางสอนสดซ่อมเสริมพิเศษ
- **สิ่งที่ยังขาด:** **ยังไม่มีระบบสร้างแบบฝึกหัดซ่อมเสริมอัตโนมัติเฉพาะบุคคลแบบ Real-time** ภายในตัวห้องเรียนสดทันทีที่ตรวจพบว่านักเรียนตอบข้อใดผิด

---

### 10. Session Notes (บันทึกประจำคาบเรียน)
- **สถานะ:** 🟡 **Partial**
- **ไฟล์อ้างอิง:** 
  - [`includes/phase2-session-service.php:107`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/includes/phase2-session-service.php#L107)
  - [`student/live-session.php:48`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/live-session.php#L48)
- **สิ่งที่ระบบมี:** มีฟิลด์ `notes` จาก `calendar_events` แสดงรายละเอียดคาบเรียน และฟิลด์ `announcement_message` สำหรับส่งข้อความประกาศ
- **สิ่งที่ยังขาด:** **ยังไม่มีแผงบันทึกโน้ตการสอนของครูระหว่างคาบ (Dedicated Teacher Session Notes / Teaching Log)** เพื่อบันทึกพฤติกรรมหรือข้อสังเกตของผู้เรียนในคาบนั้นเก็บไว้

---

### 11. Student Notebook (สมุดจดบันทึกสดของผู้เรียน)
- **สถานะ:** ❌ **Missing**
- **ไฟล์อ้างอิง:** ไม่พบในซอร์สโค้ด
- **รายละเอียด:** ไม่มีเครื่องมือสมุดบันทึก (Live Notebook / Scratchpad) ให้นักเรียนจดสรุปหรือคำอธิบายของครูระหว่างอยู่ในห้องเรียนสด

---

### 12. AI Classroom Copilot (ผู้ช่วย AI ในห้องเรียน)
- **สถานะ:** ❌ **Missing**
- **ไฟล์อ้างอิง:** ไม่พบในซอร์สโค้ด
- **รายละเอียด:** ไม่มีการเชื่อมต่อ LLM หรือ AI Copilot ในขณะเรียนสด เช่น ไม่มี AI คอยสรุปเนื้อหา, ไม่มี AI ช่วยตอบคำถามนักเรียนเมื่อยกมือถาม, และไม่มี AI แนะนำแนวทางการสอนแก่ครูระหว่างคาบ

---

### 13. Live Gap Detection (การตรวจจับจุดติดขัดสด)
- **สถานะ:** 🟡 **Partial**
- **ไฟล์อ้างอิง:** 
  - [`admin/live-sessions-api.php:135-177`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-sessions-api.php#L135-L177)
  - [`includes/phase5-mastery-service.php:321-329`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/includes/phase5-mastery-service.php#L321-L329)
- **สิ่งที่ระบบมี:** แสดงหัวข้อที่มีคนตอบผิดมากที่สุด (Top Missed Topic) และมีปุ่มให้ครูกดสร้าง Learning Gap เชื่อมโยงเข้าตาราง `student_learning_gaps`
- **สิ่งที่ยังขาด:** เป็นการตรวจจับด้วยเกณฑ์ตัวเลขสถิติธรรมดา (Rule-based) ยังไม่มีอัลกอริทึมวิเคราะห์แพทเทิร์นความเข้าใจคลาดเคลื่อนเชิงลึก (Deep Misconception Analysis) แบบอัตโนมัติ

---

### 14. Adaptive Questions (คำถามปรับตามระดับผู้เรียน)
- **สถานะ:** ❌ **Missing**
- **ไฟล์อ้างอิง:** ไม่พบในซอร์สโค้ดของ Live Session
- **รายละเอียด:** ชุดคำถามใน Live Quiz เป็นแบบ Fixed Static Questions ตามที่กำหนดใน `exams` ผู้เรียนทุกคนได้ข้อสอบเหมือนกันทั้งหมด ไม่มีการปรับระดับความยาก (Difficulty Adjustment) ตามผลการตอบแบบเรียลไทม์

---

### 15. Learning Path Sync (การซิงก์ผลสู่แผนการเรียนรู้)
- **สถานะ:** ✅ **Existing**
- **ไฟล์อ้างอิง:** 
  - [`includes/phase2-session-service.php:401-459`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/includes/phase2-session-service.php#L401-L459)
  - [`includes/phase5-mastery-service.php:558`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/includes/phase5-mastery-service.php#L558)
  - [`student/learning-path.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/learning-path.php)
- **การทำงานปัจจุบัน:** ผลการประเมินความเข้าใจในห้องเรียนสดถูกนำไปคำนวณและอัปเดตลงตาราง `topic_mastery` เมื่อปิดเซสชัน และตารางสอนเสริมถูกเพิ่มเป็นก้าวใน `adaptive_roadmap_steps` โดยตรง

---

### 16. Homework Push (การมอบหมายการบ้านจากในเซสชัน)
- **สถานะ:** ✅ **Existing**
- **ไฟล์อ้างอิง:** 
  - [`assets/js/live-session-teacher.js:520-630`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/assets/js/live-session-teacher.js#L520-L630)
  - [`includes/phase3-mastery-service.php:1286-1320`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/includes/phase3-mastery-service.php#L1286-L1320)
  - [`admin/worksheets-api.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/worksheets-api.php)
- **การทำงานปัจจุบัน:** มีฟีเจอร์ Quick Assign ที่ครูสามารถเลือกใบงาน/การบ้านจากคลัง ตั้งกำหนดส่ง และกดมอบหมายให้นักเรียนทุกคนในคาบได้ทันที โดยจะผูก `session_id` และ `topic_name` ให้เสร็จสรรพ

---

### 17. IA Points Integration (การสะสมพอยต์รางวัล)
- **สถานะ:** ✅ **Existing**
- **ไฟล์อ้างอิง:** 
  - [`includes/live-sessions-helper.php:184-199`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/includes/live-sessions-helper.php#L184-L199)
  - [`admin/live-sessions-api.php:359-361`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-sessions-api.php#L359-L361)
  - [`student/live-session-api.php:307-309`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/live-session-api.php#L307-L309)
- **การทำงานปัจจุบัน:** ผูกกับระบบ Boss Battle Arena เมื่อเลือดบอสลดลงเหลือ 0 ระบบจะเรียก `awardBossDefeatPoints()` บันทึกธุรกรรมแต้มเข้าตาราง `nc_point_transactions` ให้นักเรียนทุกคนในห้องอัตโนมัติ

---

### 18. Session Report (รายงานสรุปผลหลังจบคลาส)
- **สถานะ:** 🟡 **Partial**
- **ไฟล์อ้างอิง:** 
  - [`admin/live-session-room.php`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/admin/live-session-room.php)
  - [`assets/js/live-session-teacher.js:475-477`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/assets/js/live-session-teacher.js#L475-L477)
- **สิ่งที่ระบบมี:** สรุปผลคะแนนเฉลี่ย, สรุปข้อที่ผิด, แผนที่ทักษะ, และดาวน์โหลดไฟล์ CSV รายชื่อผู้เข้าเรียนได้
- **สิ่งที่ยังขาด:** **ยังไม่มีหน้ารายงานสรุปผลภาพรวมแบบรวมศูนย์หลังจบคลาส (Dedicated Session Summary Report)** ที่รวมคะแนน, การเข้าเรียน, จุดแข็ง-จุดอ่อนของทั้งห้อง, และการส่งออกเป็น PDF Report

---

### 19. Reconnect / Recovery (การกู้คืนเซสชันเมื่อหลุด)
- **สถานะ:** ✅ **Existing**
- **ไฟล์อ้างอิง:** 
  - [`student/live-session-api.php:196-231`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/live-session-api.php#L196-L231)
  - [`student/take-test.php:258-320`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/take-test.php#L258-L320)
- **การทำงานปัจจุบัน:** หากเน็ตหลุดหรือปิดแท็บ นักเรียนสามารถกรอก PIN เดิมเพื่อกลับเข้าสู่เซสชันได้ทันที ระบบจะค้นหา `session_participants` เดิมและดึง `attempt_id` เดิมขึ้นมาทำข้อสอบต่อโดยไม่สูญเสียข้อมูล

---

### 20. Offline Answer Protection (การป้องกันคำตอบสูญหายเมื่อออฟไลน์)
- **สถานะ:** 🟡 **Partial**
- **ไฟล์อ้างอิง:** 
  - [`student/take-test.php:180-210`](file:///Applications/XAMPP/xamppfiles/htdocs/Nextbeyond/student/take-test.php#L180-L210)
- **สิ่งที่ระบบมี:** มีการบันทึกคำตอบลง `localStorage` ในเบราว์เซอร์อัตโนมัติทุกครั้งที่เลือกข้อ ป้องกันคำตอบหายเมื่อรีเฟรชหน้าหรือเน็ตสะดุดชั่วขณะ
- **สิ่งที่ยังขาด:** ยังไม่มีระบบ Offline Sync Engine แท้จริง (เช่น Service Worker + IndexedDB + Background Sync) หากนักเรียนออฟไลน์ต่อเนื่องยาวนานจะไม่สามารถรับคำสั่ง Eyes On Me หรือส่งข้อสอบเมื่อหมดเวลาได้
