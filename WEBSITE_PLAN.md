# แผนสร้างเว็บไซต์ Nextbeyond Compass

## 1. เป้าหมายของเว็บไซต์

สร้างแพลตฟอร์มการเรียนที่ช่วยให้ผู้เรียน “รู้ระดับ → เลือกเป้าหมาย → ได้แผน → ฝึกต่อ → วัดผลซ้ำ” โดยให้ภาพลักษณ์ของสถาบันมีความน่าเชื่อถือ ทันสมัย และใช้งานง่ายบนมือถือ

เป้าหมายทางธุรกิจ:

1. ช่วยผู้เรียนค้นหาคอร์สที่เหมาะสมได้เร็วขึ้น
2. เปลี่ยนผู้เข้าชมทั่วไปให้เริ่มทำแบบวัดระดับหรือสร้าง Learning Path
3. สร้างช่องทางทดลองเรียนฟรีก่อนตัดสินใจสมัครคอร์ส
4. เก็บข้อมูลพัฒนาการเพื่อใช้แนะนำบทเรียนและติดตามผล

## 2. กลุ่มผู้ใช้หลัก

| กลุ่ม | ความต้องการ | จุดเริ่มต้นที่แนะนำ |
|---|---|---|
| นักเรียนที่ยังไม่รู้ระดับ | อยากรู้ว่าควรเริ่มจากตรงไหน | Placement Test |
| นักเรียนที่มีเป้าหมายชัด | ต้องการแผนเรียนเป็นลำดับ | Learning Path |
| นักเรียนเตรียมสอบ | ต้องการแนวข้อสอบและกลยุทธ์ | Exam Guides |
| ผู้ปกครอง | ต้องการเห็นโครงสร้างและผลลัพธ์ | Courses + Progress Report |
| ผู้ทดลองเรียน | อยากสัมผัสรูปแบบการสอนก่อน | Free Learning |

## 3. Design Direction

### บุคลิกแบรนด์

- Academic yet innovative — มีความเป็นสถาบันแต่ไม่เก่า
- Clear and supportive — ข้อมูลชัด เข้าใจง่าย และชี้ทางต่อเสมอ
- Ambitious — สื่อถึงการเติบโตและไปได้ไกลกว่าเดิม

### สีหลัก

| บทบาท | สี | ตัวอย่างการใช้งาน |
|---|---|---|
| Deep Navy | `#061633` / `#09234D` | Hero, Footer, Heading, โครงสร้างหลัก |
| Progress Blue | `#2369DD` / `#3981F5` | Link, Tab, Progress, Active state |
| Action Pink | `#E72D82` / `#F54696` | CTA หลัก, Highlight, Hot status |
| Surface | `#FFFFFF` / `#F6F8FC` | พื้นหลังและ Card |
| Text Muted | `#65738A` | Metadata และคำอธิบายรอง |

### Typography และ Layout

- Font หลัก: IBM Plex Sans Thai; Fallback เป็น Tahoma/System Sans
- Desktop Container: 1,180–1,200 px
- Grid: 12 คอลัมน์บน Desktop, 2 คอลัมน์บน Tablet, 1 คอลัมน์บน Mobile
- Vertical spacing: Base unit 8 px; Section 80–90 px
- Card: พื้นขาว ขอบบาง เงานุ่ม Radius 16–28 px
- CTA สีชมพูใช้เฉพาะ Action สำคัญเพื่อรักษาลำดับสายตา

## 4. Sitemap

```text
Home
├── Courses
├── Placement Test
│   └── Result + Skill Breakdown
├── Learning Path
│   └── Progress Tracking
├── Exam Guides
└── Free Learning
```

## 5. ขอบเขตฟังก์ชัน

### 5.1 Home

- อธิบายคุณค่าของ Academy ภายในหน้าจอแรก
- CTA ไปยัง Courses และ Placement Test
- แสดงวิชา จุดเด่น คอร์สแนะนำ และวิธีเรียน 3 ขั้น
- รองรับภาพ Hero และแสดงผลแบบ Responsive

### 5.2 Courses

- แสดงรายการคอร์สตามวิชาและระดับ
- Search จากชื่อ วิชา ระดับ และทักษะ
- Filter ภาษาอังกฤษ ชีววิทยา และเคมี
- เชื่อมไป Placement Test, Learning Path และ Exam Guides

### 5.3 Placement Test

- เลือกแบบวัดระดับตามวิชา
- จับเวลาและแสดงเวลาคงเหลือ
- เลือกคำตอบ เปลี่ยนข้อ และดูสถานะตอบแล้ว
- คำนวณคะแนนอัตโนมัติ
- สรุปคะแนนรวม ระดับแนะนำ และคะแนนแยกทักษะ
- แสดงเฉลยพร้อมคำอธิบาย
- บันทึกผลล่าสุดเพื่อนำไปใช้ใน Learning Path

### 5.4 Learning Path

- รับค่า: วิชา ระดับปัจจุบัน เป้าหมาย และจังหวะการเรียน
- อ่านผล Placement Test ล่าสุดและแนะนำค่าตั้งต้น
- สร้างลำดับบทเรียนที่ต่างกันตามระดับ
- ปลดล็อกขั้นถัดไปเมื่อทำขั้นก่อนหน้าเสร็จ
- แสดง Progress เป็นเปอร์เซ็นต์
- บันทึกแผนและความคืบหน้าใน Browser

### 5.5 Exam Guides

- แสดงโครงสร้างข้อสอบ ระยะเวลา ระดับ และหัวข้อควรฝึก
- Search/Filter ตามวิชา
- เปิดดู Checklist ของแต่ละชุด
- บันทึกแนวข้อสอบที่สนใจใน Browser

### 5.6 Free Learning

- แสดงบทเรียนสั้นแยกตามวิชา
- Search/Filter และเชื่อมไปแบบฝึกหรือแนวข้อสอบ
- ใช้เป็น Funnel ไปยัง Learning Path และ Courses

## 6. User Flow หลัก

```text
ผู้ใช้เข้า Home
  → ทำ Placement Test
  → ดูคะแนนและจุดที่ควรพัฒนา
  → สร้าง Learning Path จากผลทดสอบ
  → เรียนตามลำดับ / ติ๊กความคืบหน้า
  → ทำ Post-test
  → ปรับเป้าหมายและแผนรอบถัดไป
```

อีกเส้นทางหนึ่ง:

```text
ผู้ใช้เข้า Courses หรือ Exam Guides
  → ค้นหาและกรองเนื้อหา
  → ยังไม่แน่ใจเรื่องระดับ
  → ทำ Placement Test
  → กลับมารับคำแนะนำที่เหมาะกับระดับ
```

## 7. โครงสร้างข้อมูลที่ควรมีเมื่อเพิ่ม Backend

| Entity | ข้อมูลสำคัญ |
|---|---|
| User | id, role, name, email, grade, goals |
| Course | id, subject, level, title, status, duration, lessons |
| Lesson | id, courseId, type, content, duration, order |
| Exam | id, subject, level, duration, version |
| Question | id, examId, skill, prompt, options, answer, explanation |
| Attempt | id, userId, examId, score, answers, completedAt |
| LearningPlan | id, userId, subject, level, goal, pace, status |
| PlanStep | id, planId, resourceId, order, completedAt |
| SavedGuide | userId, guideId, savedAt |

## 8. แผนดำเนินงาน

### Phase 1 — Static MVP (เสร็จในชุดไฟล์นี้)

- สร้าง Design System สีน้ำเงิน–ชมพู
- สร้าง 6 หน้าและ Responsive Navigation
- ทำ Search/Filter
- ทำ Placement Test, Result, Explanation
- ทำ Learning Path และ Progress
- ทำ Exam Guide Save
- เก็บสถานะผ่าน Local Storage

### Phase 2 — Content และ CMS

- ตรวจเนื้อหาและเฉลยโดยครูประจำวิชา
- เพิ่มคลังข้อสอบและสุ่มชุดข้อสอบ
- เพิ่ม CMS สำหรับคอร์ส บทเรียน แนวข้อสอบ และ Banner
- เพิ่มสถานะ Draft/Review/Published พร้อมประวัติการแก้ไข

### Phase 3 — Account และ Database

- เพิ่ม Sign up/Login และบทบาท Student/Parent/Teacher/Admin
- ย้าย Placement Result, Plan และ Progress จาก Local Storage ไป Database
- Sync ข้ามอุปกรณ์
- เพิ่ม Dashboard รายบุคคลและ Progress Report สำหรับผู้ปกครอง

### Phase 4 — Enrollment และการชำระเงิน

- ระบบสมัครคอร์ส ตารางเรียน และที่นั่งคงเหลือ
- Payment Gateway, Receipt และสถานะการชำระเงิน
- Notification ก่อนเรียนและเมื่อมี Feedback
- เพิ่ม Consent, Privacy Policy และ Data Retention

### Phase 5 — Analytics และ Personalization

- วัด Conversion: Home → Test → Plan → Enrollment
- วิเคราะห์ข้อที่ผิดบ่อย แยกตาม Skill และระดับ
- แนะนำบทเรียน/ข้อสอบถัดไปจากผลจริง
- A/B Test CTA, Hero และขั้นตอนสมัคร

## 9. Acceptance Criteria

### Functional

- ทุกเมนูและลิงก์ภายในต้องเปิดหน้าที่ถูกต้อง
- Search/Filter ต้องแสดงจำนวนผลและ Empty State ถูกต้อง
- Placement Test ต้องบันทึกคำตอบ เปลี่ยนข้อ จับเวลา และคำนวณคะแนนได้
- Result ต้องแสดงคะแนนรวม คะแนนแยกทักษะ และเฉลย
- Learning Path ต้องอ่านผลทดสอบล่าสุด สร้างแผน และบันทึก Progress ได้
- การ Refresh หน้าไม่ทำให้ผลหรือแผนที่บันทึกไว้หาย

### Responsive & Accessibility

- ใช้งานได้ที่ 360 px, 768 px, 1,024 px และ 1,440 px
- ปุ่มและ Input ต้องใช้งานด้วย Keyboard ได้
- มี `alt`, `label`, Focus state และโครง Heading ตามลำดับ
- Contrast ของข้อความและ CTA ผ่าน WCAG AA
- รองรับ `prefers-reduced-motion` ใน Production

### Performance & SEO

- ภาพ Hero ใช้ WebP/AVIF เพิ่มเติมเมื่อ Production
- ตั้ง Title/Description เฉพาะแต่ละหน้า
- เพิ่ม Open Graph, Sitemap, robots.txt และ Structured Data (Course/EducationalOrganization)
- เป้าหมาย Lighthouse: Performance ≥ 90, Accessibility ≥ 95, SEO ≥ 95

## 10. Security และ Privacy

- ไม่เก็บข้อมูลอ่อนไหวใน Local Storage เมื่อมีระบบสมาชิก
- ตรวจ Validation ทั้ง Client และ Server
- ใช้ CSRF/Session protection ตามวิธี Authentication ที่เลือก
- จำกัดสิทธิ์ Admin/CMS ด้วย Role-based Access Control
- บันทึก Consent และอธิบายการใช้ข้อมูลผู้เรียนอย่างชัดเจน
- สำรองฐานข้อมูลและกำหนดระยะเวลาการเก็บ Attempts/Reports

## 11. QA Checklist ก่อนเผยแพร่

- [ ] ครูตรวจข้อสอบ คำตอบ และคำอธิบายครบทุกข้อ
- [ ] ทดสอบ Chrome, Safari, Firefox และ Mobile Browser
- [ ] ทดสอบ Navigation, Search, Filter และ Empty State
- [ ] ทดสอบ Timer หมดเวลาและคำถามที่ไม่ได้ตอบ
- [ ] ทดสอบการส่งผล Placement ไป Learning Path
- [ ] ทดสอบ Lock/Unlock และ Reset Progress
- [ ] ตรวจข้อความภาษาไทย สระ/วรรณยุกต์ไม่ถูกตัด
- [ ] Optimize รูปและตรวจ Broken Link
- [ ] เพิ่ม Privacy Policy, Terms และช่องทางติดต่อ
- [ ] ตั้ง Analytics Event และตรวจ Consent Banner
- [ ] สร้าง Backup/Rollback Plan ก่อน Deployment

## 12. แนวทาง Deployment

สำหรับ Static MVP สามารถ Deploy ได้โดยตรงบน GitHub Pages, Cloudflare Pages, Netlify หรือ Vercel โดยกำหนดโฟลเดอร์นี้เป็น Static Root และไม่ต้องใช้ Build Command

เมื่อเพิ่ม Account, Database และ CMS แนะนำย้ายไปสถาปัตยกรรม Full-stack เช่น Next.js + API + PostgreSQL พร้อม Environment Variables และ CI/CD แยก Preview/Production
