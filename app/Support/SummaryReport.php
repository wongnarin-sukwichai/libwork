<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * สรุปภาพรวมทุกคน ช่วงวันที่ใดก็ได้ (เช่น ทั้งปีงบประมาณ) · หน้าเว็บ + PDF + Excel ใช้ข้อมูลชุดเดียวกัน
 *
 * - วันทำการ / ไม่ลงเวลา นับถึงวันนี้เท่านั้น (วันข้างหน้ายังไม่เกิด) และเฉพาะช่วงที่คนนั้นทำงานอยู่ (start_date–end_date)
 * - วันลานับทั้งช่วงที่เลือก รวมวันลาล่วงหน้า (ครึ่งวัน = 0.5) · นับเฉพาะวันทำการ (เหมือน LeaveService::byDate)
 * - ไม่ลงเวลา = วันทำการที่ผ่านไปแล้ว ไม่มีเวลาเข้า และไม่มีวันลา/ไปราชการ
 * - กลุ่มงานใช้กลุ่ม ณ วันสุดท้ายของช่วง (ไม่เกินวันนี้)
 */
class SummaryReport
{
    /** ประเภทลาที่มีคอลัมน์ของตัวเอง ที่เหลือรวมใน "อื่นๆ" */
    public const LEAVE_COLUMNS = ['sick' => 'ป่วย', 'personal' => 'กิจ', 'vacation' => 'พักผ่อน'];

    public static function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $today = CarbonImmutable::today();
        $last = $to->min($today->subDay()); // วันที่ "ผ่านไปแล้ว" ล่าสุด สำหรับนับไม่ลงเวลา
        $lastCounted = $to->min($today);     // วันทำการนับถึงวันนี้

        $attendances = Attendance::query()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->get(['user_id', 'work_date', 'type', 'check_in_at', 'worked_minutes', 'special_minutes', 'late_minutes', 'early_leave_minutes'])
            ->groupBy('user_id');

        $users = User::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $attendances->keys()))
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $to))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $from))
            ->orderBy('first_name')
            ->get();

        $leaves = LeaveService::byDate($from->toDateString(), $to->toDateString(), $users->pluck('id')->all());
        $leaveTypes = LeaveType::query()->get(['id', 'code', 'category'])->keyBy('id');

        $workdays = [];
        if ($lastCounted->greaterThanOrEqualTo($from)) {
            foreach (CarbonPeriod::create($from, $lastCounted) as $d) {
                if (ScheduleResolver::isWorkday($d)) {
                    $workdays[] = $d->toDateString();
                }
            }
        }

        $deptOn = DailyReport::departmentsOn($users, $to->min($today));
        $rows = $users->map(function (User $u) use ($attendances, $leaves, $leaveTypes, $workdays, $last, $deptOn) {
            $mine = $attendances->get($u->id, collect());
            $normal = $mine->where('type', 'normal');
            $ot = $mine->where('type', 'ot');
            $checkedIn = $normal->filter(fn ($a) => $a->check_in_at)->map(fn ($a) => $a->work_date->toDateString())->flip();

            $leave = array_fill_keys(array_keys(self::LEAVE_COLUMNS), 0.0) + ['other' => 0.0, 'duty' => 0.0];
            foreach ($leaves as $date => $byUser) {
                if ($r = $byUser[$u->id] ?? null) {
                    $type = $leaveTypes->get($r->leave_type_id);
                    $key = $type?->category === 'duty' ? 'duty' : (isset(self::LEAVE_COLUMNS[$type?->code]) ? $type->code : 'other');
                    $leave[$key] += $r->half_day ? 0.5 : 1;
                }
            }

            $start = $u->start_date?->toDateString();
            $end = $u->end_date?->toDateString();
            $own = array_filter($workdays, fn ($d) => (! $start || $d >= $start) && (! $end || $d <= $end));
            $absent = count(array_filter($own, fn ($d) => $d <= $last->toDateString() && ! isset($checkedIn[$d]) && ! isset($leaves[$d][$u->id])));

            $late = $normal->where('late_minutes', '>', 0);
            $early = $normal->where('early_leave_minutes', '>', 0);

            return [
                'user_id' => $u->id,
                'name' => $u->full_name,
                'department_id' => $deptOn[$u->id] ?? null,
                'workdays' => count($own),
                'present' => $checkedIn->count(),
                'late_count' => $late->count(),
                'late_minutes' => (int) $late->sum('late_minutes'),
                'early_count' => $early->count(),
                'early_minutes' => (int) $early->sum('early_leave_minutes'),
                'absent' => $absent,
                'leave' => $leave,
                'leave_total' => array_sum($leave) - $leave['duty'],
                'ot_minutes' => (int) $ot->sum('worked_minutes'),
                'ot_special_minutes' => (int) $ot->sum('special_minutes'),
            ];
        });

        $departments = Department::query()->get()->keyBy('id');
        $groups = $rows->groupBy(fn ($r) => $r['department_id'] ?? 0)
            ->map(fn (Collection $items, $id) => [
                'id' => (int) $id,
                'name' => $departments->get($id)?->name ?? 'ไม่ระบุกลุ่มงาน',
                'sort' => $departments->get($id)?->sort_order ?? PHP_INT_MAX,
                'rows' => $items->map(fn ($r) => self::format($r))->values()->all(),
                'total' => self::format(self::sum($items)),
            ])
            ->sortBy('sort')
            ->values();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'counted_to' => $lastCounted->toDateString(),
            'workdays' => count($workdays),
            'leave_columns' => self::LEAVE_COLUMNS,
            'groups' => $groups->all(),
            'total' => self::format(self::sum($rows)),
            'people' => $rows->count(),
        ];
    }

    /** รวมตัวเลขของหลายแถว (ยอดรวมกลุ่ม / ทั้งหมด) */
    private static function sum(Collection $rows): array
    {
        $out = ['name' => 'รวม', 'user_id' => null];
        // วันทำการของแต่ละคนรวมกันไม่มีความหมาย แถวรวมเว้นว่าง
        foreach (['present', 'late_count', 'late_minutes', 'early_count', 'early_minutes', 'absent', 'leave_total', 'ot_minutes', 'ot_special_minutes'] as $k) {
            $out[$k] = $rows->sum($k);
        }
        $out['workdays'] = null;
        foreach ([...array_keys(self::LEAVE_COLUMNS), 'other', 'duty'] as $k) {
            $out['leave'][$k] = $rows->sum(fn ($r) => $r['leave'][$k]);
        }

        return $out;
    }

    /** เพิ่มข้อความแสดงผล (ชั่วโมง h:mm) */
    private static function format(array $r): array
    {
        return $r + [
            'ot' => $r['ot_minutes'] ? WorkCalculator::hm($r['ot_minutes']) : null,
            'ot_special' => $r['ot_special_minutes'] ? WorkCalculator::hm($r['ot_special_minutes']) : null,
        ];
    }
}
