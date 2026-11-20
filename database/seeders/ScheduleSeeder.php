<?php

namespace Database\Seeders;

use App\Models\Schedule;
use Illuminate\Database\Seeder;

/**
 * รูปแบบเวลาเริ่มต้นตามที่ตกลงกับผู้ใช้ (แก้ได้ในหน้าตั้งค่า) · รันซ้ำได้ ไม่ทับค่าที่แก้แล้ว
 */
class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'เวลาปกติ', 'type' => 'normal', 'applies_to' => 'weekday', 'start_time' => '08:30', 'end_time' => '16:30',
                'grace_minutes' => 5, 'count_from' => '08:30', 'break_start' => '12:00', 'break_end' => '13:00',
                'special_from' => null, 'rounding' => 'minute', 'daily_cap_minutes' => 420],
            ['name' => 'OT วันทำการ', 'type' => 'ot', 'applies_to' => 'weekday', 'start_time' => '16:30', 'end_time' => '20:00',
                'grace_minutes' => 0, 'count_from' => '17:00', 'break_start' => null, 'break_end' => null,
                'special_from' => null, 'rounding' => 'hour', 'daily_cap_minutes' => 240],
            ['name' => 'OT วันหยุด', 'type' => 'ot', 'applies_to' => 'holiday', 'start_time' => '10:00', 'end_time' => '18:00',
                'grace_minutes' => 5, 'count_from' => '10:00', 'break_start' => '12:00', 'break_end' => '13:00',
                'special_from' => null, 'rounding' => 'hour', 'daily_cap_minutes' => 420],
            ['name' => 'ล่วงเวลาพิเศษ (ถึง 22:00)', 'type' => 'ot', 'applies_to' => 'special', 'start_time' => '16:30', 'end_time' => '22:00',
                'grace_minutes' => 0, 'count_from' => '17:00', 'break_start' => null, 'break_end' => null,
                'special_from' => '20:00', 'rounding' => 'hour', 'daily_cap_minutes' => 300],
        ];

        foreach ($rows as $i => $row) {
            Schedule::query()->firstOrCreate(['name' => $row['name']], $row + ['sort_order' => $i + 1, 'is_active' => true]);
        }
    }
}
