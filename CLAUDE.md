# LibWork — ระบบลงเวลาปฏิบัติงาน

ระบบลงเวลาปฏิบัติงาน (ปกติ + OT), วันลา, ปฏิทิน และรายงาน ของสำนักวิทยบริการ มหาวิทยาลัยมหาสารคาม
เขียนใหม่แทนระบบเดิม `c:\xampp\htdocs\worktime-server` (Laravel 10 + Vue SPA, ฐานข้อมูล `worktime_db`)
ผู้ใช้สื่อสารภาษาไทย · ทุกการตัดสินใจคุยกันก่อนลงมือ (ผู้ใช้ไม่ชอบให้ทำไปก่อนแล้วค่อยบอก)

## สถานะล่าสุด (อัปเดต 2026-11-20)

| เฟส | สถานะ | ขอบเขต |
|---|---|---|
| 1 พื้นฐาน | ✅ | login Google + รหัสผ่านสำรอง, role, สมาชิก, กลุ่มงาน + ย้ายพร้อมวันที่มีผล, ประเภทบุคลากร, ตั้งค่า, นำเข้าข้อมูลเดิม |
| 2 ลงเวลา | ✅ | kiosk C3 (ลงทะเบียนเครื่อง, ออฟไลน์, แถบสถานะ), รูปแบบเวลา + วันพิเศษ, คำนวณชั่วโมง, หน้าแรก HR รายวัน, แก้เวลาพร้อมประวัติ, หน้า member |
| 2b ลงเวลาผ่านมือถือ | ⬜ ยังไม่ทำ | ปิดเป็นค่าเริ่มต้น, จำกัด IP และ/หรือพิกัด (geofence), บังคับเซลฟี่, อนุญาตรายคน (`users.mobile_checkin` มีแล้ว), แท็บตั้งค่า "ลงเวลาผ่านมือถือ" ยังเป็น soon |
| 3 วันลา | ✅ | ประเภทลา, นโยบายตามประเภทบุคลากร, วันหยุด, ยอดคงเหลือ, ยอดยกมาปีงบ (ร่าง → ตรวจ → ยืนยัน), บันทึกวันลา, ปฏิทิน, หน้าแรกสาธารณะ |
| 4 รายงาน | ✅ | PDF รายวัน (A4 แนวตั้ง), Excel, รายบุคคล + PDF/Excel, ตรวจสอบและปิดงวดรายเดือน (ล็อกข้อมูล) |
| 5 API + ติดตั้ง | ⬜ ยังไม่ทำ | API สถิติ (Sanctum token, read-only, JSON สำหรับกราฟ, ไม่ส่งรูป/ข้อมูลส่วนบุคคล) + แท็บ "API token" + คู่มือติดตั้งบน golden (nginx, cron, Google OAuth, Chrome kiosk mode) |

- เทสต์: 50 ข้อผ่านทั้งหมด (`php artisan test`) · `npm run build` ผ่าน
- ยังไม่ได้ `git init` (ผู้ใช้ยังไม่สั่ง) · commit/push เมื่อผู้ใช้สั่งเท่านั้น
- **นัดทำต่อ จันทร์ 23 พ.ย. 2569:** เริ่มจากถามผู้ใช้ว่าจะทำเฟส 2b (มือถือ) หรือเฟส 5 (API + คู่มือติดตั้ง) ก่อน และเสนอ `git init` + commit เฟส 1–4 อีกครั้ง
- Mockup ที่อนุมัติแล้ว (ธีม A3 + Kiosk C3, 15 หน้า): https://claude.ai/artifact/DQE7YqBaqsAAzCDCUggPvP — ใช้เป็นแบบเมื่อสร้างหน้าใหม่

### งานที่ค้าง (ฝั่งผู้ใช้/HR ก่อนใช้จริง)
- กรอกยอดลาพักผ่อนสะสมเริ่มต้นของทุกคน (เมนู ยอดยกมาปีงบ, ปีงบ 2570 ร่างไว้แล้ว ยกมา = 0 ทุกคน) แล้วกดยืนยัน
- ตรวจนโยบายการลา (ค่าตั้งต้นเป็นตัวอย่างระเบียบข้าราชการ) และชื่อประเภทบุคลากร (เดาจากรหัสระบบเดิม)
- เพิ่มวันหยุดตามจันทรคติและวันหยุดชดเชย (seeder ใส่เฉพาะวันที่ตายตัวของปีงบ 2570)
- กำหนด admin/staff ตัวจริง (ทุกคนที่นำเข้าเป็น member) แล้วระงับบัญชีตั้งต้น `admin@libwork.test` (ตอนนี้อยู่ในกลุ่มสำนักงานเลขานุการ จึงขึ้นในรายงาน)
- สมาชิก 5 คนยังไม่มีกลุ่มงาน · มีอีเมลแค่ 22/56 คน (คนไม่มีอีเมลลงเวลาที่ kiosk ได้แต่ login ไม่ได้)
- ใส่ `GOOGLE_CLIENT_ID/SECRET` ใน .env (callback `{APP_URL}/auth/google/callback`)

## ข้อตกลงกับผู้ใช้ (อย่ารื้อใหม่ ถ้าจะเปลี่ยนให้ถามก่อน)
- **ติดตั้ง:** เซิร์ฟเวอร์เดียวที่ `https://golden.msu.ac.th/libwork` (เครื่องเดียวกับระบบเดิม 202.28.32.99, nginx, ใบรับรอง `*.msu.ac.th` หมดอายุ 5 มี.ค. 2570) ไม่มีเวอร์ชันไคลเอนต์ · ขอโดเมนย่อยไม่ได้ (IT ต่างหน่วยงาน)
- **Kiosk:** ช่องทางหลัก, เครื่องต่อสายแลนเข้าเซิร์ฟเวอร์ได้โดยไม่ต้อง authen เน็ตมหาวิทยาลัย (ทดสอบแล้ว ไม่ต้องแก้ hosts) · แม่บ้านเปิดเครื่องตอนเช้า ต้องใช้ง่าย · หน้าลงเวลาไม่แสดงป้ายล่วงเวลาพิเศษ
- **Login:** Google เฉพาะอีเมลที่ admin เพิ่มไว้ (ไม่สมัครเอง) · role: admin, staff (= HR), member
- **เวลาปกติ:** 08:30–16:30, ผ่อนผัน 5 นาที (นับเต็มเหมือนตรงเวลา), สายนับนาทีจาก 08:30, มาก่อนไม่นับ, หักพัก 12–13 น., นับไม่เกิน 16:30
- **OT:** วันทำการ 16:30–20:00 เริ่มนับ 17:00 ปัดลงชั่วโมงเต็ม เพดาน 4 ชม. (ได้ 3) · วันหยุด 10:00–18:00 หักพัก เพดาน 7 · ล่วงเวลาพิเศษถึง 22:00 = OT ปกติ 3 + OT พิเศษ 2 (รายงานแยกคอลัมน์) กำหนดรายวันในปฏิทิน · ทุกค่าตั้งได้ในหลังบ้าน
- **วันลา:** HR บันทึกเอง ไม่มีอนุมัติ · ปีงบประมาณ · ลาพักผ่อนสะสมได้: ยกมา + สิทธิ์ปีนี้ ≤ เพดาน (20 / 30 วันเมื่ออายุงาน ≥ 10 ปี) · ยอดยกมา HR ต้องตรวจก่อน member เห็น · member แจ้ง HR ตรวจสอบยอดได้ · ไปราชการไม่หักโควต้า
- **หน้าแรกสาธารณะ:** ปฏิทินวันลา (ไม่แสดง OT) ปุ่มเข้าสู่ระบบมุมขวาบน · แสดงแค่ "ลา"/"ไปราชการ" ไม่บอกประเภท (PDPA, เปิดได้ในตั้งค่า)
- **หน้าแรก HR:** ตารางรายวันจัดกลุ่มตามกลุ่มงาน (เหมือนระบบเดิม) + ปุ่ม PDF/Excel ทันที · กลุ่มงานย้อนหลังใช้กลุ่ม ณ วันนั้น
- **PDF ส่งหัวหน้าสำนัก:** A4 แนวตั้ง, รูปย่อ, badge เฉพาะสถานะผิดปกติ (มีเส้นขอบ พิมพ์ขาวดำได้), ช่องลงนามผู้รายงาน/ผู้ตรวจ · ระบบเดิมสร้าง PDF เป็นภาพ (67 MB) ระบบใหม่เป็นตัวอักษร (~100 KB)
- **รายบุคคล:** ช่วงวันที่อิสระ ใช้ทำรายงานนิสิตฝึกงานส่งคณะ (ช่องลงนาม ผู้ฝึกงาน/ผู้ควบคุม/หัวหน้าสำนัก)
- **ปิดงวดรายเดือน** แทนการตรวจใบลาทีละใบ · ไม่ย้ายข้อมูลลงเวลาเก่า (นำเข้าเฉพาะกลุ่มงาน/สมาชิก)

## ธีมและหน้าตา (A3 + C3)
- ฟอนต์ Anuphan (`@fontsource/anuphan` เก็บในเครื่อง) · ไอคอน `lucide-vue-next` ผ่าน `Components/Icon.vue` (เพิ่มไอคอนใหม่ที่ map ในไฟล์นั้น)
- สีใน `resources/css/app.css` (`@theme`): **navy-700 `#1E3A6E` เป็นโครง** (ป้ายหัวหน้า, เมนูที่เลือก, การ์ดรอง), **sun-500 `#F5B700` เป็นจุดเน้น** (ปุ่มหลัก, การ์ดแรก, แท่งกราฟชั่วโมงปกติ) ตัวอักษรบนเหลืองเป็นดำเสมอ, ink `#0F1E3A`, muted `#4B587C`, พื้น page `#F3F6FB`
- Sidebar พื้นขาว เมนูที่เลือกพื้นน้ำเงินตัวขาว ไม่มีโลโก้ · หน้าสาธารณะ/kiosk แถบหัวน้ำเงิน ไอคอนในกรอบเหลือง
- Kiosk C3: พื้นขาว, ปุ่มลงเวลาเข้าเหลือง, ลงเวลาออกดำ, OT กรอบน้ำเงิน, การ์ด "ล่าสุด" เทาอ่อน `#F2F4F7`
- สีสถานะ (`Components/StatusPill.vue`): ok เขียว, late ส้ม, bad แดง, leave ฟ้าอ่อน, duty เหลืองอ่อน, ot ม่วง (ม่วง `violet-700` = ล่วงเวลาพิเศษ)
- คลาสส่วนกลาง (`card`, `input`, `label`, `btn-primary`, `btn-navy`, `btn-outline`, `btn-ghost`, `pill`, `field-error`) ประกาศด้วย `@utility` เพราะ Tailwind v4 ไม่ให้ `@apply` คลาสใน `@layer components`
- การ์ด: `card` (มุม 18px + เงา) · ป้ายหัวหน้า: `rounded-[20px] bg-navy-700 text-white shadow-card`

## Stack และคำสั่ง
- Laravel 12 (PHP 8.2.4 XAMPP, ไม่มี ext-intl) + MariaDB `libwork_db` + Inertia v3 + Vue 3 `<script setup>` (JavaScript ไม่ใช้ TS) + Vite 7 + Tailwind v4 · mPDF + PhpSpreadsheet + Socialite
- เปิดผ่าน Apache: http://localhost/libwork/public (admin `admin@libwork.test` / `password`, รหัสผ่านสำรองเปิดเสมอเมื่อ `APP_ENV=local`) หรือ `php artisan serve` + `npm run dev`
- `npm run build` ทุกครั้งหลังแก้หน้าเว็บ · `php artisan test` (sqlite in-memory; `APP_URL` ใน phpunit.xml ต้องเป็น http://localhost)
- ฐานใหม่: `php artisan migrate:fresh --seed` → `php artisan libwork:import-legacy` (รันซ้ำได้ อัปเดตตาม `legacy_id`) → `php artisan libwork:draft-fiscal-year`
- cron บนเซิร์ฟเวอร์: `php artisan schedule:run` ทุกนาที (ร่างยอดวันลาทุก 1 ต.ค.)

## โครงสร้าง
- หน้า: `resources/js/Pages/*` · layout กำหนดใน `app.js`: `Home`, `Auth/Login` → `PublicLayout`, `Kiosk/*` → ไม่มี layout, ที่เหลือ → `AppLayout` (sidebar)
- เมนูตาม role: `config/menu.php` (`admin_only` ซ่อนจาก staff) · สิทธิ์ route: middleware `role:admin,staff` ใน `routes/web.php` · หน้าแรกหลัง login: staff/admin → `/staff`, member → `/me`
- Sub-path: `<meta name="base-path">` + `resources/js/utils/url.js`; `app.js` เติม prefix ให้ Inertia request และ `fetch()` · ใช้ path ขึ้นต้น `/` ตามปกติ แต่ `<a href>` ธรรมดาและ `<img src>` ต้องครอบ `url()`
- ตรรกะหลักอยู่ใน `app/Support/`: `WorkCalculator` (กฎนับชั่วโมง), `ScheduleResolver` (รูปแบบเวลาของวัน, `isWorkday`), `AttendanceService` (punch / manualSave), `DailyReport`, `PersonReport`, `LeaveService`, `CalendarEvents`, `FiscalYear`, `MonthClose`, `Pdf`, `Xlsx`, `PhotoStore` · helper `thai_date()` / `thai_month()` ใน `app/helpers.php` · ฝั่ง JS `utils/thaiDate.js`
- ค่าตั้งค่า: `Setting::get()` / `put()` (cache) ค่าเริ่มต้นใน `Setting::DEFAULTS`

### ลงเวลา
- `schedules` (normal/ot × weekday/holiday/special) · `special_days` (วันที่ใช้รูปแบบพิเศษ) · `attendances` 1 แถว/คน/วัน/ประเภท (unique) · `punches` หลักฐานดิบ (`client_uuid` กันซ้ำตอนส่งคิวออฟไลน์) · `attendance_logs` ประวัติ HR แก้ (บังคับเหตุผล)
- Kiosk: `/kiosk` + `/kiosk/api/*` ยืนยันด้วย header `X-Kiosk-Token` (middleware `kiosk`, ยกเว้น CSRF) · รหัสลงทะเบียนใช้ครั้งเดียว 24 ชม. · คิวออฟไลน์ใน localStorage (`Pages/Kiosk/useKiosk.js`) รับย้อนหลัง ≤ 72 ชม. · ตรวจ authen เน็ตด้วย gstatic generate_204
- รูป private `storage/app/private/attendance/Y/m/d` ย่อ ≤ 480px (`PhotoStore`) เปิดผ่าน `/photos/attendance/{id}/{in|out}` เฉพาะ staff หรือเจ้าของ
- กดเข้าซ้ำ = ปฏิเสธ · กดออกหลายครั้ง = ใช้ครั้งล่าสุด · วันหยุดกดเวลาปกติไม่ได้ · OT ต้อง `ot_eligible`

### วันลา
- `holidays` (`Holiday::map()` cache) · `leave_types` (category leave|duty, counts_quota, carry_over) · `leave_policies` (ตามประเภทบุคลากร) · `leave_records` (soft delete) · `leave_balances` (draft|confirmed, `system_carried_over` เทียบค่าที่ HR แก้) · `leave_adjustments` (member เห็น) · `leave_inquiries`
- `LeaveService::draftYear()` ยกยอดเฉพาะเมื่อปีก่อนมี `leave_balances` จริง และไม่ทับค่าที่ HR แก้/ยืนยันแล้ว · ลาคร่อมปีงบต้องแยกรายการ · ลาเกินสิทธิ์ต้องติ๊กยืนยัน · ลาซ้อนวันไม่ได้

### รายงานและปิดงวด
- PDF: `Pdf::make()` + `resources/views/pdf/*` · **ฟอนต์ garuda** (มากับ mPDF) — Sarabun ใช้ไม่ได้: เปิด OTL มีกล่อง □ หลังจุด (mPDF แทรก U+200B), ปิด OTL วรรณยุกต์หาย · `shrink_tables_to_fit => 0` + ตารางแยกต่อกลุ่มงาน `page-break-inside: avoid` · แคชฟอนต์ `storage/app/mpdf`
- Excel: `Xlsx::download()` (ฟอนต์ Tahoma, เวลาเป็นข้อความ)
- ปิดงวด: `MonthClose::anomalies()` blocking = no_out/no_in/absent/clash/offline (แก้ข้อมูลแล้วหายเอง หรือ HR "รับทราบ" → `anomaly_acks`), เพื่อทราบ = late/early · ปิดได้หลังสิ้นเดือน · ปิดแล้ว `MonthClose::assertOpen()` ล็อกแก้เวลา/วันลา และ kiosk ปฏิเสธคิวออฟไลน์ของงวดนั้น · admin ปลดล็อก (`month_closure_logs`)

## กับดักที่เจอแล้ว
- ค้นหา `attendances` ด้วย `whereDate('work_date', …)` เสมอ (sqlite เก็บ date เป็น datetime → `firstOrNew` แบบสตริงสร้างแถวซ้ำ)
- Faker th_TH: ห้าม `safeEmail()`/`userName()` (ต้องมี intl)
- ข้อมูลเดิม: `members.work_day` บางแถวเป็นปี พ.ศ. หรือ `0000-00-00`, `members.type` = official/staff/employee/parttime/maid
- Tailwind v4: `@utility` แทน `@layer components` ถ้าจะ `@apply` ต่อกัน
- หน้าที่มีไฟล์แนบใช้ `POST` (ไม่ใช่ PUT) + `forceFormData`
- กล้องและ GPS ในเบราว์เซอร์ต้อง HTTPS หรือ localhost

## เพิ่มหน้าใหม่
ใช้ skill `libwork-page` (`.claude/skills/libwork-page/SKILL.md`)
