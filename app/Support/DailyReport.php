<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\DepartmentAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * รายงานประจำวัน จัดกลุ่มตามกลุ่มงาน ณ วันนั้น (ใช้ทั้งหน้าแรก HR และ PDF/Excel ในเฟส 4)
 */
class DailyReport
{
    public static function build(string $date, string $type = 'normal', bool $withPhotoPaths = false): array
    {
        $day = CarbonImmutable::parse($date)->startOfDay();
        $today = CarbonImmutable::today();
        $workday = ScheduleResolver::isWorkday($day);
        $schedule = ScheduleResolver::for($type, $day);

        $attendances = Attendance::query()
            ->with('schedule')
            ->whereDate('work_date', $day->toDateString())
            ->where('type', $type)
            ->get()
            ->keyBy('user_id');

        // เวลาปกติ: ทุกคนที่ยังทำงานอยู่ในวันนั้น · OT: เฉพาะคนที่มีรายการ OT
        $users = User::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $attendances->keys()))
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $day))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $day))
            ->when($type === 'ot' || ! $workday, fn ($q) => $q->whereIn('id', $attendances->keys()))
            ->orderBy('first_name')
            ->get();

        $deptOn = self::departmentsOn($users, $day);
        $departments = Department::query()->ordered()->get()->keyBy('id');
        $leaves = $type === 'normal' ? (LeaveService::byDate($day->toDateString(), $day->toDateString())[$day->toDateString()] ?? []) : [];

        $rows = $users->map(function (User $u) use ($attendances, $deptOn, $day, $today, $type, $leaves, $withPhotoPaths) {
            /** @var Attendance|null $a */
            $a = $attendances->get($u->id);
            [$status, $tone] = self::status($a, $type, $day, $today);
            $leave = $leaves[$u->id] ?? null;
            if ($leave && (! $a?->check_in_at || $leave->half_day)) {
                // ลาเต็มวัน หรือลาครึ่งวัน: แสดงสถานะการลา (ลาครึ่งวันแต่มาทำงาน ยังเห็นเวลาเข้า-ออก)
                $status = LeaveService::label($leave);
                $tone = $leave->leaveType->category === 'duty' ? 'duty' : 'leave';
            }

            return [
                'user_id' => $u->id,
                'attendance_id' => $a?->id,
                'name' => $u->full_name,
                'department_id' => $a?->department_id ?? $deptOn[$u->id] ?? null,
                'check_in' => $a?->check_in_at?->format('H:i'),
                'check_out' => $a?->check_out_at?->format('H:i'),
                'check_in_seconds' => $a?->check_in_at?->format('H:i:s'),
                'check_out_seconds' => $a?->check_out_at?->format('H:i:s'),
                'has_in_photo' => (bool) $a?->check_in_photo,
                'has_out_photo' => (bool) $a?->check_out_photo,
                'worked_minutes' => $a?->worked_minutes ?? 0,
                'special_minutes' => $a?->special_minutes ?? 0,
                'hours' => $a && $a->check_in_at && $a->check_out_at ? WorkCalculator::hm($a->worked_minutes) : null,
                'special_hours' => $a?->special_minutes ? WorkCalculator::hm($a->special_minutes) : null,
                'status' => $status,
                'tone' => $tone,
                'note' => $a?->note,
                'manual' => $a && ($a->check_in_source === 'manual' || $a->check_out_source === 'manual'),
                'offline' => (bool) $a?->recorded_offline,
                // path จริงของรูปสำหรับ PDF (ตัดออกก่อนส่งไปหน้าเว็บ)
                'in_photo_path' => $withPhotoPaths ? Pdf::photoPath($a?->check_in_photo) : null,
                'out_photo_path' => $withPhotoPaths ? Pdf::photoPath($a?->check_out_photo) : null,
            ];
        });

        $groups = $rows->groupBy(fn ($r) => $r['department_id'] ?? 0)
            ->map(function (Collection $items, $deptId) use ($departments) {
                $dept = $departments->get($deptId);
                $present = $items->whereNotNull('check_in')->count();

                return [
                    'id' => (int) $deptId,
                    'name' => $dept?->name ?? 'ไม่ระบุกลุ่มงาน',
                    'sort' => $dept?->sort_order ?? PHP_INT_MAX,
                    'head_name' => $dept?->head_name,
                    'summary' => $items->count().' คน · มา '.$present,
                    'rows' => $items->values()->all(),
                ];
            })
            ->sortBy('sort')
            ->values();

        return [
            'date' => $day->toDateString(),
            'type' => $type,
            'workday' => $workday,
            'schedule' => $schedule ? [
                'name' => $schedule->name,
                'start' => $schedule->hm('start_time'),
                'end' => $schedule->hm('end_time'),
                'special' => $schedule->applies_to === 'special',
            ] : null,
            'stats' => [
                'total' => $rows->count(),
                'present' => $rows->whereNotNull('check_in')->whereNotIn('tone', ['leave', 'duty'])->count(),
                'late' => $rows->where('tone', 'late')->count(),
                'leave' => $rows->whereIn('tone', ['leave', 'duty'])->count(),
                'no_out' => $rows->where('status', 'ไม่ได้ลงเวลาออก')->count(),
                'absent' => $rows->whereIn('status', ['ไม่ได้ลงเวลา'])->count(),
            ],
            'groups' => $groups->all(),
        ];
    }

    /** กลุ่มงานของแต่ละคน ณ วันที่ (query เดียว) */
    private static function departmentsOn(Collection $users, CarbonImmutable $day): array
    {
        $map = $users->pluck('department_id', 'id')->all();
        DepartmentAssignment::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->whereDate('effective_from', '<=', $day)
            ->orderBy('effective_from')
            ->orderBy('id')
            ->get(['user_id', 'department_id'])
            ->each(function ($a) use (&$map) {
                $map[$a->user_id] = $a->department_id;
            });

        return $map;
    }

    /** @return array{0:string,1:string} [ข้อความ, tone: ok|late|bad|leave|neutral|ot] */
    public static function status(?Attendance $a, string $type, CarbonImmutable $day, CarbonImmutable $today): array
    {
        $past = $day->lessThan($today);

        if (! $a || (! $a->check_in_at && ! $a->check_out_at)) {
            if ($day->greaterThan($today)) {
                return ['—', 'neutral'];
            }

            return $past ? ['ไม่ได้ลงเวลา', 'bad'] : ['ยังไม่ลงเวลา', 'neutral'];
        }
        if (! $a->check_in_at) {
            return ['ไม่ได้ลงเวลาเข้า', 'bad'];
        }
        if (! $a->check_out_at) {
            return $past ? ['ไม่ได้ลงเวลาออก', 'bad'] : ['กำลังปฏิบัติงาน', 'neutral'];
        }
        if ($type === 'ot') {
            return [$a->special_minutes ? 'ล่วงเวลาพิเศษ' : 'OT', 'ot'];
        }
        if ($a->late_minutes) {
            return ["สาย {$a->late_minutes} นาที", 'late'];
        }
        if ($a->early_leave_minutes) {
            return ["ออกก่อน {$a->early_leave_minutes} นาที", 'late'];
        }
        $start = $day->setTimeFromTimeString($a->schedule?->start_time ?? '08:30');

        return $a->check_in_at->greaterThan($start) ? ['ปกติ (ผ่อนผัน)', 'ok'] : ['ปกติ', 'ok'];
    }
}
