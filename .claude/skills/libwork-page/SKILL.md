---
name: libwork-page
description: เพิ่มหน้าใหม่หรือเมนูใหม่ในระบบ LibWork (หน้า member, staff/HR หรือ admin, หรือแท็บในหน้าตั้งค่า) รวมถึงเปลี่ยนหน้า Placeholder ให้เป็นหน้าจริง ใช้เมื่อผู้ใช้ขอเพิ่มหน้า เพิ่มเมนู หรือทำหน้าที่ยังเป็น "กำลังพัฒนา"
---

# เพิ่มหน้าใหม่ใน LibWork

อ่าน `CLAUDE.md` ก่อน (ข้อตกลงกับผู้ใช้ + ธีม A3) แล้วดูหน้าที่ตรงกันใน mockup https://claude.ai/artifact/DQE7YqBaqsAAzCDCUggPvP
ถ้ายังไม่มีแบบใน mockup หรือมีเรื่องธุรกิจที่ยังไม่ได้ตกลง **ถามผู้ใช้ก่อน** อย่าเดากติกาเอง

## 1. Route — `routes/web.php`
- member (ทุก role เปิดได้): ใน `Route::prefix('me')`
- staff/HR: ใน `Route::prefix('staff')->middleware('role:admin,staff')`
- admin: ใน `Route::prefix('admin')->middleware('role:admin')`
- หน้าที่เคยเป็น `Inertia::render('Placeholder', ...)` ให้เปลี่ยนเป็น controller จริง
- route ที่รับไฟล์แนบใช้ `POST` (ไม่ใช่ PUT)

## 2. Controller — `app/Http/Controllers/{Staff|Admin}/...`
- ตรรกะธุรกิจใส่ใน `app/Support/*` (ใช้ซ้ำกับ PDF/Excel/เทสต์ได้) controller แค่ validate + render
- ข้อความ validation เป็นภาษาไทย
- แก้ข้อมูลเวลา/วันลาของวันที่ผ่านมา ต้องเรียก `MonthClose::assertOpen($date)` ก่อนเสมอ
- กลุ่มงานย้อนหลังใช้ `User::departmentIdOn($date)` · ค้น attendances ด้วย `whereDate('work_date', …)`
- redirect กลับพร้อม `->with('success'|'error', 'ข้อความไทย')` (แสดงเป็น toast อัตโนมัติ)

## 3. หน้า — `resources/js/Pages/{Me|Staff|Admin}/Name.vue`
- ไม่ต้องประกาศ layout (`app.js` ใส่ `AppLayout` ให้) · เริ่มด้วย `<Head title="ชื่อหน้า" />`
- หัวหน้า: `<h1 class="text-[26px] font-bold">` + คำอธิบาย `text-[15px] text-muted` หรือป้ายน้ำเงิน `rounded-[20px] bg-navy-700 px-6 py-5 text-white shadow-card`
- การ์ด `card p-5` · ตาราง: หัวตาราง `bg-navy-700 text-white` ครอบด้วย `card overflow-hidden` + `overflow-x-auto`
- ปุ่ม: หลัก `btn-primary` (เหลือง ตัวดำ), รอง `btn-navy` / `btn-outline`, ไอคอน `btn-ghost h-9 px-2` + `aria-label`
- ฟอร์ม: `label` + `input` + `<p class="field-error">` · ใช้ `useForm` ของ Inertia · หน้าต่าง: `Components/Modal.vue`
- สถานะ: `Components/StatusPill.vue` (tone ok/late/bad/leave/duty/ot/neutral) · วันที่ไทย: `thaiDate()` จาก `@/utils/thaiDate`
- ลิงก์ดาวน์โหลด/รูป (`<a href>`, `<img src>`) ครอบ `url()` จาก `@/utils/url` · `fetch('/path')` ใช้ได้เลย
- ต้องใช้งานได้ที่ความกว้างมือถือ (ตาราง `min-w-[...]` ใน `overflow-x-auto`, grid ใช้ `grid-cols-2 lg:grid-cols-4`)
- ข้อความ UI เป็นภาษาไทย ใส่ตรงในเทมเพลต (โปรเจกต์นี้ไม่มี i18n)

## 4. เมนู — `config/menu.php`
- เพิ่ม `['label' => '...', 'href' => '/...', 'icon' => '...']` ในกลุ่ม `member` หรือ `staff` (`'admin_only' => true` = เห็นเฉพาะ admin)
- ไอคอนใหม่: import จาก `lucide-vue-next` และเพิ่มใน map ของ `resources/js/Components/Icon.vue`

## 5. แท็บในหน้าตั้งค่า (admin)
- สร้าง `Pages/Admin/Settings/XxxTab.vue` · เพิ่มข้อมูลใน `Admin\SettingController@index` · import + เพิ่ม `{ key, label }` ใน `tabs` และ `<XxxTab v-else-if="active === 'xxx'">` ใน `Pages/Admin/Settings/Index.vue` (ลบ `soon` ออก)

## 6. ตรวจงาน
- เขียน feature test ใน `tests/Feature/` (factory: `User::factory()->staff()` / `->admin()`, seed ด้วย `DatabaseSeeder` ถ้าต้องใช้รูปแบบเวลา/ประเภทลา, ใช้ `travelTo()` กับเรื่องเวลา)
- `php artisan test` และ `npm run build` ต้องผ่าน
- ลองเปิดผ่าน Apache `http://localhost/libwork/public/...` (login ด้วย curl + XSRF ได้) ให้ได้ 200
- อัปเดตตาราง "สถานะล่าสุด" และหมวดที่เกี่ยวข้องใน `CLAUDE.md`
