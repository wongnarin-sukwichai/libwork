<?php

/*
| เมนู sidebar ตาม role · จัดเป็นโมดูล (หัวข้อใน sidebar) แต่ละโมดูลมีเมนูของตัวเอง
| module = ชื่อหัวข้อ (null = ไม่แสดงหัวข้อ) · admin_only ใช้ได้ทั้งระดับโมดูลและระดับเมนู
| icon = ชื่อไอคอนของ lucide (resources/js/Components/Icon.vue)
| ตรวจสิทธิ์ของ route อยู่ใน routes/web.php (middleware role:...)
*/

return [
    'member' => [
        ['module' => null, 'items' => [
            ['label' => 'หน้าหลัก', 'href' => '/me', 'icon' => 'house'],
            ['label' => 'เวลาปฏิบัติงานของฉัน', 'href' => '/me/attendance', 'icon' => 'clock'],
            ['label' => 'สิทธิ์วันลา', 'href' => '/me/leave', 'icon' => 'calendar-check'],
            ['label' => 'ปฏิทิน', 'href' => '/calendar', 'icon' => 'calendar-days'],
        ]],
    ],

    'staff' => [
        ['module' => 'หน้าหลัก', 'items' => [
            ['label' => 'รายวัน', 'href' => '/staff', 'icon' => 'house'],
            ['label' => 'รายบุคคล', 'href' => '/staff/person', 'icon' => 'user-search'],
            ['label' => 'ประวัติวันลา', 'href' => '/staff/leaves', 'icon' => 'history'],
            ['label' => 'ปฏิทิน', 'href' => '/calendar', 'icon' => 'calendar-days'],
            ['label' => 'สรุปภาพรวม', 'href' => '/staff/summary', 'icon' => 'chart-column'],
            ['label' => 'ตรวจสอบรายการ', 'href' => '/staff/close', 'icon' => 'list-checks'],
            ['label' => 'สะสมวันลา', 'href' => '/staff/carry-over', 'icon' => 'calculator'],
        ]],
        ['module' => 'ระบบ', 'admin_only' => true, 'items' => [
            ['label' => 'สมาชิก', 'href' => '/staff/members', 'icon' => 'users'],
            ['label' => 'ตั้งค่า', 'href' => '/admin/settings', 'icon' => 'settings'],
        ]],
    ],
];
