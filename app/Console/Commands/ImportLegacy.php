<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * นำเข้าข้อมูลหลักจากระบบเดิม (worktime_db): กลุ่มงาน และสมาชิก
 * ไม่นำเข้าข้อมูลลงเวลาเก่า (ตามที่ตกลงไว้) · รันซ้ำได้ ข้อมูลจะอัปเดตตาม legacy_id
 */
class ImportLegacy extends Command
{
    protected $signature = 'libwork:import-legacy {--dry-run : แสดงผลโดยไม่บันทึก}';

    protected $description = 'นำเข้ากลุ่มงานและสมาชิกจากฐานข้อมูลระบบลงเวลาเดิม';

    /** members.type เดิม → employment_types.code */
    private const TYPE_MAP = [
        'official' => 'civil_servant',
        'staff' => 'university_staff',
        'employee' => 'employee',
        'parttime' => 'part_time',
        'maid' => 'housekeeper',
    ];

    public function handle(): int
    {
        $legacy = DB::connection('legacy');
        $dry = (bool) $this->option('dry-run');

        $deps = $legacy->table('deps')->orderBy('dep_group')->get();
        $members = $legacy->table('members')->orderBy('id')->get();
        $this->info("พบกลุ่มงาน {$deps->count()} กลุ่ม, สมาชิก {$members->count()} คน");

        if ($dry) {
            $this->table(['id', 'กลุ่มงาน', 'ลำดับ'], $deps->map(fn ($d) => [$d->id, $d->dep_title, $d->dep_group])->all());

            return self::SUCCESS;
        }

        $types = EmploymentType::query()->pluck('id', 'code');
        $internTypeId = $types['intern'] ?? null;

        DB::transaction(function () use ($deps, $members, $types, $internTypeId) {
            $depMap = [];
            foreach ($deps as $dep) {
                $name = trim($dep->dep_title);
                // ใช้กลุ่มเดิมถ้าเคยนำเข้าแล้ว หรือมีกลุ่มชื่อเดียวกันที่สร้างไว้ก่อน (เช่น จาก seeder)
                $model = Department::query()->where('legacy_id', $dep->id)->first()
                    ?? Department::query()->whereNull('legacy_id')->where('name', $name)->first()
                    ?? new Department;
                // กลุ่มที่นำเข้าแล้วไม่ทับชื่อ (HR อาจเปลี่ยนชื่อในระบบใหม่ เช่น แม่บ้าน → เจ้าหน้าที่ทำความสะอาด)
                $model->fill(['name' => $model->exists ? $model->name : $name, 'sort_order' => (int) $dep->dep_group, 'is_active' => true, 'legacy_id' => $dep->id])->save();
                $depMap[$dep->id] = $model;
            }

            foreach ($members as $m) {
                $dep = $depMap[$m->dep] ?? null;
                $typeId = $types[self::TYPE_MAP[$m->type] ?? ''] ?? null;
                if ($dep && str_contains($dep->name, 'ฝึกงาน')) {
                    $typeId = $internTypeId;
                }
                $email = filled($m->email) ? strtolower(trim($m->email)) : null;
                if ($email && User::query()->where('email', $email)->where('legacy_id', '!=', $m->id)->exists()) {
                    $this->warn("อีเมลซ้ำ ข้าม: {$email} ({$m->name})");
                    $email = null;
                }

                // ระบบเดิมมีวันที่ 0000-00-00 ปนอยู่ และบางรายการเก็บเป็นปี พ.ศ. (เช่น 2536-02-24)
                $year = (int) substr((string) $m->work_day, 0, 4);
                $workDay = match (true) {
                    $year > 2400 => ($year - 543).substr($m->work_day, 4, 6),
                    $year >= 1900 => substr($m->work_day, 0, 10),
                    default => null,
                };

                $user = User::query()->firstOrNew(['legacy_id' => $m->id]);
                $isNew = ! $user->exists;
                $user->fill([
                    'employee_code' => trim((string) $m->uid) ?: null,
                    'first_name' => trim($m->name),
                    'last_name' => trim($m->surname),
                    'email' => $email ?? $user->email,
                    'employment_type_id' => $typeId,
                    'department_id' => $dep?->id ?? $user->department_id, // ระบบเดิมไม่มีกลุ่ม = คงกลุ่มที่ตั้งในระบบใหม่
                    'start_date' => $workDay,
                    'is_active' => $m->stat === null || (int) $m->stat === 1,
                ]);
                if ($isNew) {
                    $user->role = 'member';
                }
                $user->save();

                if ($dep && $user->departmentAssignments()->doesntExist()) {
                    $user->departmentAssignments()->create([
                        'department_id' => $dep->id,
                        'effective_from' => $workDay ?? '2000-01-01',
                        'note' => 'นำเข้าจากระบบเดิม',
                    ]);
                }
            }
        });

        $this->info('นำเข้าเรียบร้อย');

        return self::SUCCESS;
    }
}
