<?php

namespace App\Support;

use App\Models\Holiday;
use App\Models\LeaveRecord;
use App\Models\Setting;
use App\Models\SpecialDay;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * เหตุการณ์บนปฏิทินรายเดือน ['YYYY-MM-DD' => [['label', 'tone'], ...]]
 * mode: public (หน้าแรก ไม่ต้อง login) | member (ของตัวเอง + วันหยุด + วันพิเศษ) | staff (ทุกคน)
 */
class CalendarEvents
{
    public static function month(int $year, int $month, string $mode, ?User $viewer = null): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfWeek(CarbonImmutable::SUNDAY);
        $end = CarbonImmutable::create($year, $month, 1)->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);
        $events = [];
        $add = function (string $date, string $label, string $tone) use (&$events) {
            $events[$date][] = ['label' => $label, 'tone' => $tone];
        };

        foreach (Holiday::map() as $date => $name) {
            if ($date >= $start->toDateString() && $date <= $end->toDateString()) {
                $add($date, $name, 'holiday');
            }
        }

        if ($mode !== 'public') {
            SpecialDay::query()->with('schedule:id,name')->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()
                ->each(fn ($d) => $add($d->date->toDateString(), $d->schedule?->name ?? 'ล่วงเวลาพิเศษ', 'otx'));
        }

        $showType = $mode !== 'public' || Setting::get('public_calendar.show_leave_type');
        $byDate = LeaveService::byDate($start->toDateString(), $end->toDateString(), $mode === 'member' ? [$viewer->id] : null);
        $names = User::query()->whereIn('id', collect($byDate)->flatMap(fn ($u) => array_keys($u))->unique())->pluck('first_name', 'id');

        foreach ($byDate as $date => $records) {
            /** @var LeaveRecord $r */
            foreach ($records as $userId => $r) {
                $duty = $r->leaveType->category === 'duty';
                $type = $showType ? LeaveService::label($r) : ($duty ? 'ไปราชการ' : 'ลา');
                $add($date, $mode === 'member' ? $type : ($names[$userId] ?? '').' · '.$type, $duty ? 'duty' : 'leave');
            }
        }

        return $events;
    }

    /** คนที่ไม่อยู่วันนี้ (หน้าแรก) */
    public static function absentToday(bool $showType): array
    {
        $today = CarbonImmutable::today()->toDateString();
        $records = LeaveService::byDate($today, $today)[$today] ?? [];

        return collect($records)->map(function (LeaveRecord $r) use ($showType) {
            $r->loadMissing('user.department:id,name');
            $duty = $r->leaveType->category === 'duty';

            return [
                'id' => $r->user_id,
                'name' => $r->user?->full_name,
                'department' => $r->user?->department?->name,
                'type' => $showType ? LeaveService::label($r) : ($duty ? 'ไปราชการ' : 'ลา'),
            ];
        })->values()->all();
    }

    public static function upcomingHolidays(int $limit = 4): array
    {
        $today = CarbonImmutable::today()->toDateString();

        return collect(Holiday::map())->filter(fn ($n, $d) => $d >= $today)->take($limit)
            ->map(fn ($name, $date) => ['date' => $date, 'name' => $name])->values()->all();
    }
}
