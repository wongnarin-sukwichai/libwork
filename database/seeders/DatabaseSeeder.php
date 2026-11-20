<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * ข้อมูลตั้งต้น: ประเภทบุคลากร และผู้ดูแลระบบคนแรก
     * กลุ่มงานและสมาชิกจริงนำเข้าจากระบบเดิมด้วย `php artisan libwork:import-legacy`
     */
    public function run(): void
    {
        $types = [
            ['code' => 'civil_servant', 'name' => 'ข้าราชการ', 'has_leave_quota' => true],
            ['code' => 'university_staff', 'name' => 'พนักงานมหาวิทยาลัย', 'has_leave_quota' => true],
            ['code' => 'employee', 'name' => 'ลูกจ้าง', 'has_leave_quota' => true],
            ['code' => 'part_time', 'name' => 'ลูกจ้างชั่วคราว', 'has_leave_quota' => true],
            ['code' => 'housekeeper', 'name' => 'แม่บ้าน', 'has_leave_quota' => true],
            ['code' => 'intern', 'name' => 'นิสิตฝึกงาน', 'has_leave_quota' => false],
        ];
        foreach ($types as $i => $type) {
            EmploymentType::query()->updateOrCreate(['code' => $type['code']], $type + ['sort_order' => $i + 1]);
        }

        $this->call([ScheduleSeeder::class, LeaveSeeder::class]);

        if (Department::query()->doesntExist()) {
            Department::query()->create(['name' => 'สำนักงานเลขานุการ', 'sort_order' => 1]);
        }

        $email = strtolower((string) env('ADMIN_EMAIL', 'admin@libwork.test'));
        User::query()->firstOrCreate(['email' => $email], [
            'first_name' => 'ผู้ดูแล',
            'last_name' => 'ระบบ',
            'role' => 'admin',
            'password' => env('ADMIN_PASSWORD', 'password'),
            'department_id' => Department::query()->ordered()->value('id'),
            'is_active' => true,
        ]);
    }
}
