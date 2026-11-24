<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\LeaveRecord;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\AttendanceService;
use App\Support\FiscalYear;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * [DEV ONLY] ข้อมูลตัวอย่างสำหรับทดสอบหน้า HR: ผู้ใช้ตัวอย่าง 4 คน (ปกติ / สาย / ลา / ไปราชการ)
 * ใช้บัญชี mock1–4@libwork.test เท่านั้น ไม่แตะข้อมูลคนจริง · --clean ลบออกทั้งหมด
 */
class MockToday extends Command
{
    protected $signature = 'libwork:mock {date? : วันที่ (ค่าเริ่มต้น วันนี้)} {--clean : ลบผู้ใช้ตัวอย่างและข้อมูลของเขาทั้งหมด}';

    protected $description = '[ทดสอบ] สร้างข้อมูลลงเวลาตัวอย่าง 4 รายการ: ปกติ, สาย, ลา, ไปราชการ';

    private const PEOPLE = [
        1 => ['ตัวอย่าง', 'มาปกติ'],
        2 => ['ตัวอย่าง', 'มาสาย'],
        3 => ['ตัวอย่าง', 'ลาป่วย'],
        4 => ['ตัวอย่าง', 'ไปราชการ'],
    ];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('ห้ามใช้บนเซิร์ฟเวอร์จริง');

            return self::FAILURE;
        }

        if ($this->option('clean')) {
            return $this->clean();
        }

        $day = CarbonImmutable::parse($this->argument('date') ?? today())->startOfDay();
        $deptId = Department::query()->ordered()->value('id');
        $users = collect(self::PEOPLE)->map(fn ($name, $i) => User::query()->updateOrCreate(
            ['email' => "mock{$i}@libwork.test"],
            ['first_name' => $name[0], 'last_name' => $name[1], 'role' => 'member', 'department_id' => $deptId, 'is_active' => true],
        ));

        // ล้างของวันนั้นก่อน (รันซ้ำได้)
        $this->deleteAttendances(Attendance::query()->whereIn('user_id', $users->pluck('id'))->whereDate('work_date', $day));
        LeaveRecord::withTrashed()->whereIn('user_id', $users->pluck('id'))->whereDate('start_date', $day)->forceDelete();

        $results = [
            $this->punchPair($users[1], $day->setTime(8, 24, 12), $day->setTime(16, 34, 50)),
            $this->punchPair($users[2], $day->setTime(8, 47, 3), $day->setTime(16, 31, 20)),
        ];
        foreach ($results as $r) {
            if (! $r['ok']) {
                $this->error("ลงเวลาไม่สำเร็จ: {$r['message']} {$r['detail']}");

                return self::FAILURE;
            }
        }

        $this->leave($users[3], 'sick', $day, 'ข้อมูลตัวอย่าง');
        $this->leave($users[4], 'official', $day, 'ข้อมูลตัวอย่าง · ประชุมที่ส่วนกลาง');

        $this->info('สร้างข้อมูลตัวอย่างวันที่ '.thai_date($day).' แล้ว 4 รายการ (ปกติ / สาย / ลาป่วย / ไปราชการ)');
        $this->line('ลบออก: php artisan libwork:mock --clean');

        return self::SUCCESS;
    }

    private function punchPair(User $user, CarbonImmutable $in, CarbonImmutable $out): array
    {
        $r = AttendanceService::punch($user, 'in', $in, 'kiosk', $this->fakePhoto($in));

        return $r['ok'] ? AttendanceService::punch($user, 'out', $out, 'kiosk', $this->fakePhoto($out)) : $r;
    }

    /** รูปสมมุติแทนรูปถ่ายจากกล้อง: โลโก้ห้องสมุดกลางพื้นขาว 480×480 (เก็บที่เดียวกับรูปจริง) */
    private function fakePhoto(CarbonImmutable $at): string
    {
        $size = 480;
        $canvas = imagecreatetruecolor($size, $size);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        $logo = imagecreatefrompng(public_path('img/logos/library-dark.png'));
        $w = 300;
        $h = (int) round(imagesy($logo) * $w / imagesx($logo));
        imagecopyresampled($canvas, $logo, ($size - $w) / 2, ($size - $h) / 2, 0, 0, $w, $h, imagesx($logo), imagesy($logo));

        ob_start();
        imagejpeg($canvas, null, 80);
        $jpeg = ob_get_clean();

        $path = 'attendance/'.$at->format('Y/m/d').'/mock-'.Str::uuid().'.jpg';
        Storage::disk('local')->put($path, $jpeg);

        return $path;
    }

    private function leave(User $user, string $code, CarbonImmutable $day, string $reason): void
    {
        LeaveRecord::query()->create([
            'user_id' => $user->id,
            'leave_type_id' => LeaveType::query()->where('code', $code)->value('id'),
            'start_date' => $day->toDateString(),
            'end_date' => $day->toDateString(),
            'days' => 1,
            'fiscal_year' => FiscalYear::of($day),
            'reason' => $reason,
        ]);
    }

    /** ลบการลงเวลาพร้อมไฟล์รูปสมมุติ */
    private function deleteAttendances($query): void
    {
        $query->get()->each(function (Attendance $a) {
            Storage::disk('local')->delete(array_filter([$a->check_in_photo, $a->check_out_photo]));
            $a->delete();
        });
    }

    private function clean(): int
    {
        $ids = User::query()->where('email', 'like', 'mock_@libwork.test')->pluck('id');
        $this->deleteAttendances(Attendance::query()->whereIn('user_id', $ids));
        LeaveRecord::withTrashed()->whereIn('user_id', $ids)->forceDelete();
        User::query()->whereIn('id', $ids)->delete();
        $this->info("ลบผู้ใช้ตัวอย่าง {$ids->count()} คนและข้อมูลของเขาแล้ว");

        return self::SUCCESS;
    }
}
