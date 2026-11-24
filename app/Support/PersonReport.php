<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * รายงานรายบุคคลช่วงวันที่ใดก็ได้ (หน้า member, รายบุคคลของ HR, PDF/Excel)
 * หนึ่งแถวต่อวัน: วันทำการทุกวัน + วันหยุดที่มีการลงเวลา OT
 */
class PersonReport
{
    public static function build(User $user, CarbonImmutable $from, CarbonImmutable $to, bool $includeHolidays = false): array
    {
        $today = CarbonImmutable::today();
        $last = $to->min($today);
        $items = Attendance::query()->with('schedule')->where('user_id', $user->id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->get()->groupBy(fn ($a) => $a->work_date->toDateString());
        $leaves = LeaveService::byDate($from->toDateString(), $to->toDateString(), [$user->id]);
        $holidays = \App\Models\Holiday::map();

        $rows = [];
        $workdays = 0;
        $leaveDays = 0.0;
        if ($last->greaterThanOrEqualTo($from)) {
            foreach (CarbonPeriod::create($from, $last) as $d) {
                $d = CarbonImmutable::parse($d);
                $key = $d->toDateString();
                $workday = ScheduleResolver::isWorkday($d);
                $n = $items->get($key)?->firstWhere('type', 'normal');
                $o = $items->get($key)?->firstWhere('type', 'ot');
                if (! $workday && ! $n && ! $o) {
                    if ($includeHolidays) {
                        $rows[] = ['date' => $key, 'holiday' => true, 'status' => $holidays[$key] ?? 'วันหยุด', 'tone' => 'neutral'] + self::empty();
                    }

                    continue;
                }
                $workdays += $workday ? 1 : 0;
                [$status, $tone] = $o && ! $n && ! $workday
                    ? DailyReport::status($o, 'ot', $d, $today)
                    : DailyReport::status($n, 'normal', $d, $today, $user->flex_time);
                if ($leave = $leaves[$key][$user->id] ?? null) {
                    $leaveDays += $leave->half_day ? 0.5 : 1;
                    if (! $n?->check_in_at || $leave->half_day) {
                        // เหมือนหน้ารายวัน: ลาครึ่งวันแล้วสาย/ออกก่อนในครึ่งที่ทำงาน ต่อท้ายสถานะ
                        $extra = $leave->half_day && in_array($tone, ['late', 'bad'], true) ? " · {$status}" : '';
                        $status = LeaveService::label($leave).$extra;
                        $tone = $extra ? $tone : ($leave->leaveType->category === 'duty' ? 'duty' : 'leave');
                    }
                }
                $main = $n ?? $o;
                $rows[] = [
                    'date' => $key,
                    'holiday' => ! $workday,
                    'attendance_id' => $main?->id,
                    'check_in' => $main?->check_in_at?->format('H:i'),
                    'check_out' => $main?->check_out_at?->format('H:i'),
                    'in_photo' => $main?->check_in_photo,
                    'out_photo' => $main?->check_out_photo,
                    'hours' => $n && $n->check_out_at ? WorkCalculator::hm($n->worked_minutes) : null,
                    'ot' => $o?->worked_minutes ? WorkCalculator::hm($o->worked_minutes) : null,
                    'ot_special' => $o?->special_minutes ? '+'.WorkCalculator::hm($o->special_minutes) : null,
                    'status' => $status,
                    'tone' => $tone,
                    'note' => $main?->note,
                ];
            }
        }

        $all = $items->flatten();
        $normal = $all->where('type', 'normal');
        $ot = $all->where('type', 'ot');

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'stats' => [
                'present' => $normal->whereNotNull('check_in_at')->count(),
                'workdays' => $workdays,
                'hours' => WorkCalculator::hm((int) $normal->sum('worked_minutes')),
                'ot' => WorkCalculator::hm((int) ($ot->sum('worked_minutes') + $ot->sum('special_minutes'))),
                'ot_normal' => WorkCalculator::hm((int) $ot->sum('worked_minutes')),
                'ot_special' => WorkCalculator::hm((int) $ot->sum('special_minutes')),
                'late_count' => $normal->where('late_minutes', '>', 0)->count(),
                'late_minutes' => (int) $normal->sum('late_minutes'),
                'leave_days' => $leaveDays,
            ],
            'rows' => $rows,
        ];
    }

    private static function empty(): array
    {
        return ['attendance_id' => null, 'check_in' => null, 'check_out' => null, 'in_photo' => null, 'out_photo' => null, 'hours' => null, 'ot' => null, 'ot_special' => null, 'note' => null];
    }
}
