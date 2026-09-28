# Live Classroom File Manifest

เอกสารแจกแจงรายการไฟล์ทั้งหมดที่เกี่ยวข้องกับระบบ **Live Classroom / Live Session / ห้องเรียนสด** ของ Nextbeyond แบ่งตามระดับความสำคัญ 3 ระดับ พร้อมระบุเหตุผลความจำเป็นในการวิเคราะห์เชิงระบบ

---

## สรุปภาพรวมจำนวนไฟล์

- **CORE Files:** 8 ไฟล์ (แกนหลักของระบบห้องเรียนสด)
- **DEPENDENCY Files:** 13 ไฟล์ (ระบบที่ห้องเรียนสดเรียกใช้ หรือต่อยอดจากห้องเรียนสด)
- **SHARED Files:** 10 ไฟล์ (การเชื่อมต่อฐานข้อมูล การอนุญาต สิทธิ์ และโครงร่างส่วนกลาง)
- **รวมทั้งหมด:** 31 ไฟล์

---

## 1. CORE FILES (ความสำคัญสูงสุด: ขาดไม่ได้ในการทำความเข้าใจ Live Classroom)

| File Path | Type | Why Required |
|---|---|---|
| `admin/live-sessions.php` | Page | **หน้าหลักฝั่งครูสำหรับบริหารเซสชัน:** รายการเซสชันที่ Active อยู่, ประวัติย้อนหลัง, โมดอลสร้างเซสชันใหม่, รหัส PIN, ลิงก์เข้าห้องเรียน, และ Projector View |
| `admin/live-session-room.php` | Page | **Teacher Command Center:** ห้องควบคุมการสอนสด รวบรวมสวิตช์ Eyes On Me (Global & Per-student), การเช็กความเข้าใจผู้เรียน, 9 ปุ่มควบคุม, และสถานะสดของนักเรียน |
| `admin/live-sessions-api.php` | Backend API | **API ฝั่งครูทั้งหมด:** ดึงรายละเอียดเซสชัน, สถิตินักเรียนรายคน, คำถามที่ตอบผิดมากสุด, สถิติความเข้าใจ, บันทึกการรีเซ็ตคำตอบ, ควบคุมบอสไฟท์, ล็อกจอ Eyes On Me, และปิดเซสชัน |
| `assets/js/live-session-teacher.js` | Client Script | **Frontend Controller ฝั่งครู:** ควบคุม Realtime Polling (ทุก 3 วินาที), สังเคราะห์เสียง Web Audio FX, จัดการโมดอลทั้ง 9 แบบ, ส่งข้อความประกาศ/เช็กความเข้าใจ, และส่งออกไฟล์ CSV เช็คชื่อ |
| `student/live-session.php` | Page | **หน้าหลักฝั่งนักเรียน (PIN Entry & Lobby):** ช่องกรอก PIN 6 หลัก, ล็อบบี้ห้องเรียนสด, ลิงก์เข้าห้องวิดีโอคอล (Meet/Zoom), หัวข้อประจำคาบ, แบนเนอร์ประกาศสด, และม่านล็อกจอ Eyes On Me |
| `student/live-session-api.php` | Backend API | **API ฝั่งนักเรียนทั้งหมด:** เข้าร่วมผ่าน PIN (Join), ออกจากห้อง (Leave), Polling สถานะห้องสด (Status, Eyes On Me, Reset), ส่งผลเช็กความเข้าใจ, และโจมตีบอส |
| `includes/live-sessions-helper.php` | Service Helper | **Database Schema & Business Logic Helper:** สร้างตาราง `classroom_sessions` และ `session_participants`, สุ่ม PIN 6 หลัก, ปิดเซสชันอื่นที่เปิดค้าง, ข้อมูลมอนสเตอร์บอส, และแจกรางวัลแต้ม |
| `includes/phase2-session-service.php` | Integration Service | **บริการเชื่อมโยง Phase 2 (Prepare & Learn):** จัดการตาราง `session_topics`, `session_readiness`, `session_understanding_checks`, ซิงก์ผลความเข้าใจผู้เรียนเข้าสู่ตาราง `topic_mastery` |

---

## 2. DEPENDENCY FILES (ไฟล์ที่ Live Classroom เรียกใช้ หรือเชื่อมต่อด้วย)

| File Path | Type | Why Required |
|---|---|---|
| `student/get-ready.php` | Page | **หน้าเตรียมตัวก่อนเรียน (Pre-class):** ดึงหัวข้อจากเซสชันมาให้นักเรียนทบทวนล่วงหน้า และแสดงการแจ้งเตือนพร้อม PIN เมื่อครูเปิดห้องเรียนสด |
| `student/get-ready-api.php` | Backend API | **API บันทึกความพร้อมก่อนเรียน:** ตรวจสอบและบันทึกสถานะ `reviewed` / `not_started` ในแต่ละหัวข้อของคาบเรียน |
| `student/take-test.php` | Page | **ระบบทำข้อสอบสดในห้องเรียน:** รองรับพารามิเตอร์ `sessionId`, ทำ Polling สถานะห้องสดทุก 3 วินาที, ล็อกจอตาม Eyes On Me, รับประกาศสด, และส่งคำตอบอัตโนมัติหากครูปิดเซสชัน |
| `student/submit-test.php` | Backend Processor | **ประมวลผลการส่งข้อสอบ:** ปรับปรุงสถานะใน `session_participants` เป็น `submitted` และบันทึกจำนวนข้อที่ตอบ พร้อมส่งต่อไปยังหน้าผลสอบพร้อม `sessionId` |
| `student/test-result.php` | Page | **หน้าแสดงผลสอบห้องเรียนสด:** แสดงคะแนนและปุ่มนำทางย้อนกลับไปยังหน้าห้องเรียนสด `live-session.php?sessionId=...` |
| `admin/calendar-api.php` | Backend API | **API ปฏิทินและการเชื่อมโยงเซสชัน:** ดึงสถานะ `session_id`, `session_pin` ของ Event และมี Endpoint `action=link_session` สำหรับผูกเซสชันกับปฏิทิน |
| `assets/js/admin-calendar.js` | Client Script | **ปฏิทินฝั่งครู:** ตรวจจับสถานะเซสชันใน Event แสดงปุ่ม `🟢 เข้าห้องเรียนสด` หรือ `🔴 เริ่ม Live Session` เพื่อเปิดห้องได้จากปฏิทินทันที |
| `includes/phase3-mastery-service.php` | Integration Service | **มอบหมายงานหลังเรียน (Post-Class):** มีเมธอด `quickAssignFromSession` สำหรับสร้างการบ้าน/ใบงานผูกกับ `sessionId` และ `topic_name` |
| `admin/worksheets-api.php` | Backend API | **API เชื่อมโยงใบงาน:** รองรับแอ็กชัน `quick_assign_session` ที่ถูกเรียกจากโมดอลในห้องเรียนสดของครู |
| `includes/phase5-mastery-service.php` | Adaptive Service | **ระบบช่วยเหลือผู้เรียนและคิว Intervention:** สร้าง Live Session พิเศษ (Intervention Session) อัตโนมัติเมื่อครูนัดหมายแก้ปัญหาจุดติดขัด (Learning Gap) |
| `admin/adaptive-learning-api.php` | Backend API | **API จัดการ Intervention:** สั่งเริ่มนัดหมายเซสชันสอนสดพิเศษสำหรับผู้เรียนรายบุคคล |
| `student/learning-path.php` | Page | **แผนที่การเรียนรู้ของนักเรียน:** แสดงอีเวนต์สำคัญถัดไปและปุ่มทางลัดเข้าสู่ห้องเรียนสด |
| `student/index.php` | Page | **Dashboard นักเรียน:** แสดงแถบเตือนสีแดงสดพร้อมรหัส PIN เมื่อมีห้องเรียนสดเปิดอยู่ เพื่อให้นักเรียนกดเข้าเรียนได้ทันที |

---

## 3. SHARED FILES (ส่วนประกอบโครงสร้าง ส่วนขยาย สิทธิ์การเข้าถึง และการเชื่อมต่อ)

| File Path | Type | Why Required |
|---|---|---|
| `admin/includes/access.php` | Security Guard | **การจำกัดสิทธิ์ผู้ใช้ฝั่งแอดมิน:** ตรวจสอบสิทธิ์ `teacher_test_access` ในการเข้าถึง `live-sessions.php`, `live-session-room.php`, และ `live-sessions-api.php` |
| `admin/includes/sidebar.php` | UI Navigation | **แถบเมนูข้างฝั่งแอดมิน/ครู:** ลิงก์นำทางไปยัง `live-sessions.php` ในหมวด ASSESSMENT |
| `admin/includes/topbar.php` | UI Layout | **แถบหัวบนฝั่งแอดมิน:** แสดงโปรไฟล์ครู และการแจ้งเตือน |
| `assets/js/admin-guard.js` | Client Guard | ตรวจสอบสถานะการล็อกอินและบทบาทของแอดมิน/ครูบนเบราว์เซอร์ |
| `student/includes/guard.php` | Security Guard | ตรวจสอบ Session นักเรียน ป้องกันการเข้าถึงโดยไม่ได้รับอนุญาต และรองรับ Admin Inspection Mode |
| `student/includes/sidebar.php` | UI Navigation | **แถบเมนูข้างฝั่งนักเรียน:** ลิงก์นำทางไปยัง `live-session.php` พร้อมไอคอนสายฟ้า SVG |
| `student/includes/topbar.php` | UI Layout | แถบหัวบนฝั่งนักเรียน พร้อมแถบสีส้มแสดงโหมดตรวจระบบของแอดมิน |
| `includes/db.php` | Database Config | การเชื่อมต่อฐานข้อมูล PDO MySQL สำหรับทุกระบบ (ในชุดรีวิวได้ทำการ Redact รหัสผ่านเพื่อความปลอดภัย) |
| `includes/settings-service.php` | System Settings | ดึงชื่อสถาบัน โลโก้ และการตั้งค่ากลางของระบบ |
| `docs/live-session-BUILD-SPEC.md` | Specification | เอกสารสเปกต้นแบบและแนวคิดการพัฒนาฟีเจอร์ Live Session Teacher Command Center |
