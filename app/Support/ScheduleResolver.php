<?php

namespace App\Support;

use App\Models\Holiday;
use App\Models\Schedule;
use App\Models\SpecialDay;
use Carbon\CarbonInterface;

/**
 * เลือกรูปแบบเวลาของวันนั้น
 * - เวลาปกติ: เฉพาะวันทำการ (วันหยุดคืน null)
 * - OT: วันที่กำหนดพิเศษในปฏิทิน > วันหยุด (OT วันหยุด) > วันทำการ (OT วันทำการ)
 */
class ScheduleResolver
{
    public static function isWorkday(CarbonInterface $date): bool
    {
        return ! $date->isWeekend() && ! isset(Holiday::map()[$date->toDateString()]);
    }

    public static function for(string $type, CarbonInterface $date): ?Schedule
    {
        $active = Schedule::query()->where('is_active', true)->where('type', $type)->ordered();

        if ($type === 'normal') {
            return self::isWorkday($date) ? $active->where('applies_to', 'weekday')->first() : null;
        }

        $special = SpecialDay::query()->whereDate('date', $date->toDateString())->with('schedule')->first();
        if ($special?->schedule?->is_active) {
            return $special->schedule;
        }

        return $active->where('applies_to', self::isWorkday($date) ? 'weekday' : 'holiday')->first();
    }
}
