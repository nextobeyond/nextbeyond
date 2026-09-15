# Nextbeyond Compass

เว็บไซต์ PHP สำหรับระบบเรียนออนไลน์ พร้อมหน้าเว็บสาธารณะ ระบบแอดมิน ระบบสร้างข้อสอบด้วย AI และฐานข้อมูล MySQL

## เริ่มใช้งาน

1. วางโปรเจกต์ใน Apache/XAMPP
2. ตั้งค่าฐานข้อมูลใน `includes/db.php`
3. Import `database/schema.sql` สำหรับระบบทั้งหมด หรือ `database/exam_module.sql` สำหรับระบบข้อสอบ
4. ติดตั้ง package และ build CSS

```bash
npm install
npm run build:css
```

## โครงสร้างหลัก

- `admin/` — หน้าจัดการและ API ของแอดมิน
- `AI-EXAM/` — ตัวสร้างข้อสอบและ API เชื่อม Gemini
- `assets/css/` — Tailwind source และ CSS ที่ build แล้ว
- `assets/js/` — JavaScript ของแต่ละหน้า
- `database/` — schema และ migration
- `includes/` — header, footer และการเชื่อมต่อฐานข้อมูล

หน้าเว็บไซต์เริ่มจาก `index.php` ส่วนระบบจัดการเริ่มจาก `admin/index.php`
# nexxtbeyond
# nextbeyond
