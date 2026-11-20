<?php

/*
| เมนู sidebar ตาม role · ลำดับในไฟล์คือลำดับในเมนู
| icon = ชื่อไอคอนของ lucide (resources/js/Components/Icon.vue)
| ตรวจสิทธิ์ของ route อยู่ใน routes/web.php (middleware role:...)
*/

return [
    'member' => [
        ['label' => 'หน้าหลัก', 'href' => '/me', 'icon' => 'house'],
        ['label' => 'เวลาปฏิบัติงานของฉัน', 'href' => '/me/attendance', 'icon' => 'clock'],
        ['label' => 'สิทธิ์วันลา', 'href' => '/me/leave', 'icon' => 'calendar-check'],
        ['label' => 'ปฏิทิน', 'href' => '/calendar', 'icon' => 'calendar-days'],
    ],

    'staff' => [
        ['label' => 'หน้าหลัก · รายวัน', 'href' => '/staff', 'icon' => 'house'],
        ['label' => 'รายบุคคล', 'href' => '/staff/person', 'icon' => 'user-search'],
        ['label' => 'บันทึกวันลา', 'href' => '/staff/leaves', 'icon' => 'file-pen'],
        ['label' => 'ปฏิทิน', 'href' => '/calendar', 'icon' => 'calendar-days'],
        ['label' => 'ตรวจสอบ/ปิดงวด', 'href' => '/staff/close', 'icon' => 'list-checks'],
        ['label' => 'ยอดยกมาปีงบ', 'href' => '/staff/carry-over', 'icon' => 'calculator'],
        ['label' => 'สมาชิก', 'href' => '/staff/members', 'icon' => 'users'],
        ['label' => 'ตั้งค่า', 'href' => '/admin/settings', 'icon' => 'settings', 'admin_only' => true],
    ],
];
