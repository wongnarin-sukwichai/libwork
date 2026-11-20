<?php

namespace Database\Seeders;

use App\Models\EmploymentType;
use App\Models\Holiday;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;

/**
 * ประเภทลา นโยบายตัวอย่าง (อิงระเบียบการลาของข้าราชการ) และวันหยุดราชการแบบวันที่ตายตัว
 * HR ต้องตรวจและแก้ในหน้าตั้งค่าให้ตรงกับข้อบังคับของหน่วยงาน · รันซ้ำได้ ไม่ทับค่าที่แก้แล้ว
 */
class LeaveSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'vacation', 'name' => 'ลาพักผ่อน', 'category' => 'leave', 'counts_quota' => true, 'carry_over' => true, 'allow_half_day' => true],
            ['code' => 'sick', 'name' => 'ลาป่วย', 'category' => 'leave', 'counts_quota' => true, 'carry_over' => false, 'allow_half_day' => true],
            ['code' => 'personal', 'name' => 'ลากิจ', 'category' => 'leave', 'counts_quota' => true, 'carry_over' => false, 'allow_half_day' => true],
            ['code' => 'official', 'name' => 'ไปราชการ', 'category' => 'duty', 'counts_quota' => false, 'carry_over' => false, 'allow_half_day' => true],
            ['code' => 'maternity', 'name' => 'ลาคลอดบุตร', 'category' => 'leave', 'counts_quota' => true, 'carry_over' => false, 'allow_half_day' => false],
            ['code' => 'ordination', 'name' => 'ลาอุปสมบท', 'category' => 'leave', 'counts_quota' => true, 'carry_over' => false, 'allow_half_day' => false],
            ['code' => 'other', 'name' => 'อื่นๆ', 'category' => 'leave', 'counts_quota' => false, 'carry_over' => false, 'allow_half_day' => true],
        ];
        foreach ($types as $i => $t) {
            LeaveType::query()->firstOrCreate(['code' => $t['code']], $t + ['sort_order' => $i + 1]);
        }

        // [วันต่อปี, เดือนขั้นต่ำ, เพดานรวม <10 ปี, เพดานรวม ≥10 ปี]
        $defaults = [
            'vacation' => [10, 6, 20, 30],
            'sick' => [60, 0, null, null],
            'personal' => [45, 0, null, null],
            'maternity' => [90, 0, null, null],
            'ordination' => [120, 12, null, null],
        ];
        $short = ['vacation' => [10, 6, 10, 10]]; // ลูกจ้างชั่วคราว/แม่บ้าน: ไม่สะสม (ตัวอย่าง)

        $leaveTypes = LeaveType::query()->pluck('id', 'code');
        foreach (EmploymentType::query()->where('has_leave_quota', true)->get() as $emp) {
            foreach ($defaults as $code => $row) {
                $row = in_array($emp->code, ['part_time', 'housekeeper'], true) ? ($short[$code] ?? $row) : $row;
                LeavePolicy::query()->firstOrCreate(
                    ['employment_type_id' => $emp->id, 'leave_type_id' => $leaveTypes[$code]],
                    ['days_per_year' => $row[0], 'min_service_months' => $row[1], 'max_total_days' => $row[2], 'max_total_days_10y' => $row[3]],
                );
            }
        }

        // วันหยุดราชการที่วันที่ตายตัว ปีงบ 2570 · วันหยุดตามจันทรคติและวันหยุดชดเชยให้ HR เพิ่มเอง
        $holidays = [
            '2026-10-13' => 'วันนวมินทรมหาราช',
            '2026-10-23' => 'วันปิยมหาราช',
            '2026-12-05' => 'วันพ่อแห่งชาติ',
            '2026-12-10' => 'วันรัฐธรรมนูญ',
            '2026-12-31' => 'วันสิ้นปี',
            '2027-01-01' => 'วันขึ้นปีใหม่',
            '2027-04-06' => 'วันจักรี',
            '2027-04-13' => 'วันสงกรานต์',
            '2027-04-14' => 'วันสงกรานต์',
            '2027-04-15' => 'วันสงกรานต์',
            '2027-05-04' => 'วันฉัตรมงคล',
            '2027-06-03' => 'วันเฉลิมพระชนมพรรษาพระราชินี',
            '2027-07-28' => 'วันเฉลิมพระชนมพรรษา ร.10',
            '2027-08-12' => 'วันแม่แห่งชาติ',
        ];
        foreach ($holidays as $date => $name) {
            Holiday::query()->firstOrCreate(['date' => $date], ['name' => $name]);
        }
    }
}
