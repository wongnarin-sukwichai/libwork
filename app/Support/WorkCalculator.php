<?php

namespace App\Support;

use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * คำนวณชั่วโมงจากเวลาเข้า-ออกตามรูปแบบเวลา
 *
 * กฎ (ตกลงกับผู้ใช้ 2026-11-20):
 * - มาก่อน count_from ไม่นับ · มาไม่เกิน start + grace นับเหมือนมาตรงเวลา และไม่นับว่าสาย
 * - สาย (เฉพาะเวลาปกติ) นับนาทีตั้งแต่ start_time เช่น มา 08:41 = สาย 11 นาที
 * - นับไม่เกิน end_time · หักช่วงพักที่ซ้อนทับ
 * - OT มี special_from: ส่วนหลังเวลานี้คือ "OT ช่วงพิเศษ" เช่น 17:00–20:00 = 3 ชม. + 20:00–22:00 = 2 ชม.
 * - ปัดลงแยกแต่ละส่วน แล้วจำกัดด้วยเพดานต่อวัน (ตัดส่วนพิเศษก่อน)
 */
class WorkCalculator
{
    /**
     * @return array{worked_minutes:int, special_minutes:int, late_minutes:int, early_leave_minutes:int}
     */
    /**
     * @param  ?string  $half  ลาครึ่งวันในวันนั้น: am = ลาครึ่งเช้า (ทำงานบ่าย เริ่มนับสายจากเวลาเลิกพัก), pm = ลาครึ่งบ่าย (ครบเวลาที่เวลาเริ่มพัก)
     * @param  ?array{latest_start:string, min_minutes:int}  $flex  เวลายืดหยุ่นรายคน (เฉพาะเวลาปกติ)
     */
    public static function calculate(Schedule $schedule, CarbonInterface $date, ?CarbonInterface $in, ?CarbonInterface $out, ?string $half = null, ?array $flex = null): array
    {
        $day = CarbonImmutable::parse($date->toDateString());
        $at = fn (?string $time) => $time ? $day->setTimeFromTimeString($time) : null;

        $start = $at($schedule->start_time);
        $end = $at($schedule->end_time);
        $countFrom = $at($schedule->count_from) ?? $start;
        $halfDay = $schedule->type === 'normal' && in_array($half, ['am', 'pm'], true) && $schedule->break_start && $schedule->break_end ? $half : null;

        if ($schedule->type === 'normal' && $flex) {
            return self::flex($schedule, $at, $countFrom, $in, $out, $halfDay, $flex);
        }

        // ลาครึ่งเช้า: ครึ่งที่ทำงานเริ่มหลังพัก · ลาครึ่งบ่าย: ครึ่งที่ทำงานจบตอนเริ่มพัก
        if ($halfDay === 'am') {
            $start = $at($schedule->break_end);
            $countFrom = $countFrom->max($start);
        } elseif ($halfDay === 'pm') {
            $end = $at($schedule->break_start);
        }
        $graceEnd = $start->addMinutes((int) $schedule->grace_minutes);
        $in = $in ? CarbonImmutable::parse($in) : null;
        $out = $out ? CarbonImmutable::parse($out) : null;

        $late = 0;
        if ($schedule->type === 'normal' && $in && $in->greaterThan($graceEnd)) {
            $late = (int) $start->diffInMinutes($in);
        }

        $early = 0;
        if ($schedule->type === 'normal' && $out && $out->lessThan($end)) {
            $early = (int) $out->diffInMinutes($end);
        }

        $result = ['worked_minutes' => 0, 'special_minutes' => 0, 'late_minutes' => $late, 'early_leave_minutes' => $early];
        if (! $in || ! $out) {
            return $result;
        }

        $effStart = $in->lessThanOrEqualTo($graceEnd) ? $start : $in;
        $effStart = $effStart->max($countFrom);
        $effEnd = $out->min($end);
        if ($effEnd->lessThanOrEqualTo($effStart)) {
            return $result;
        }

        $break = [$at($schedule->break_start), $at($schedule->break_end)];
        $minutes = fn ($a, $b) => self::overlap($a, $b, $a, $b) - ($break[0] ? self::overlap($a, $b, $break[0], $break[1]) : 0);

        $special = 0;
        $specialFrom = $at($schedule->special_from);
        if ($specialFrom && $effEnd->greaterThan($specialFrom)) {
            $special = $minutes($effStart->max($specialFrom), $effEnd);
            $normal = $effStart->lessThan($specialFrom) ? $minutes($effStart, $specialFrom) : 0;
        } else {
            $normal = $minutes($effStart, $effEnd);
        }

        $normal = self::round($normal, $schedule->rounding);
        $special = self::round($special, $schedule->rounding);

        if ($schedule->daily_cap_minutes && $normal + $special > $schedule->daily_cap_minutes) {
            $over = $normal + $special - $schedule->daily_cap_minutes;
            $cut = min($special, $over);
            $special -= $cut;
            $normal -= $over - $cut;
        }

        return ['worked_minutes' => $normal, 'special_minutes' => $special] + $result;
    }

    /**
     * เวลายืดหยุ่น (ตกลงกับผู้ใช้ 2026-11-24): นับชั่วโมงจริงตั้งแต่ 08:30 (count_from ของรูปแบบเวลาปกติ ตามกฎหมาย)
     * - สาย = เข้าหลัง latest_start (+ ผ่อนผันของรูปแบบเวลา) นับนาทีจาก latest_start
     * - ชั่วโมงสุทธิหักพัก ต้องไม่น้อยกว่า min_minutes ไม่ครบ = early_leave_minutes (จำนวนนาทีที่ขาด)
     * - นับได้ถึง latest_start + min_minutes + เวลาพัก (เช่น 09:00 + 7 ชม. + 1 ชม. = 17:00) ต่อจากนั้นเป็นเวลา OT ซึ่งไม่ยืดหยุ่น
     * - ลาครึ่งวัน: ต้องการครึ่งหนึ่งของ min_minutes · ครึ่งเช้าเริ่มนับ/นับสายจากเวลาเลิกพัก · ครึ่งบ่ายนับถึงเวลาเริ่มพัก
     */
    private static function flex(Schedule $schedule, \Closure $at, CarbonImmutable $countFrom, ?CarbonInterface $in, ?CarbonInterface $out, ?string $half, array $flex): array
    {
        $breakStart = $at($schedule->break_start);
        $breakEnd = $at($schedule->break_end);
        $breakMinutes = $breakStart && $breakEnd ? (int) $breakStart->diffInMinutes($breakEnd) : 0;
        $required = (int) $flex['min_minutes'];
        $lateRef = $at($flex['latest_start']);
        $cap = $lateRef->addMinutes($required + $breakMinutes);

        if ($half === 'am') {
            $required = intdiv($required, 2);
            $lateRef = $breakEnd;
            $countFrom = $countFrom->max($breakEnd);
        } elseif ($half === 'pm') {
            $required = intdiv($required, 2);
            $cap = $breakStart;
        }

        $in = $in ? CarbonImmutable::parse($in) : null;
        $out = $out ? CarbonImmutable::parse($out) : null;
        $late = $in && $in->greaterThan($lateRef->addMinutes((int) $schedule->grace_minutes)) ? (int) $lateRef->diffInMinutes($in) : 0;
        if (! $in || ! $out) {
            return ['worked_minutes' => 0, 'special_minutes' => 0, 'late_minutes' => $late, 'early_leave_minutes' => 0];
        }

        $effStart = $in->max($countFrom);
        $effEnd = $out->min($cap);
        $worked = 0;
        if ($effEnd->greaterThan($effStart)) {
            $worked = self::overlap($effStart, $effEnd, $effStart, $effEnd) - ($breakStart ? self::overlap($effStart, $effEnd, $breakStart, $breakEnd) : 0);
        }
        $worked = self::round($worked, $schedule->rounding);

        return ['worked_minutes' => $worked, 'special_minutes' => 0, 'late_minutes' => $late, 'early_leave_minutes' => max(0, $required - $worked)];
    }

    /** นาทีที่ช่วง [a1,a2] ซ้อนกับ [b1,b2] */
    private static function overlap(CarbonImmutable $a1, CarbonImmutable $a2, CarbonImmutable $b1, CarbonImmutable $b2): int
    {
        $s = $a1->max($b1);
        $e = $a2->min($b2);

        return $e->greaterThan($s) ? (int) $s->diffInMinutes($e) : 0;
    }

    private static function round(int $minutes, ?string $mode): int
    {
        return match ($mode) {
            'hour' => intdiv($minutes, 60) * 60,
            'half_hour' => intdiv($minutes, 30) * 30,
            default => $minutes,
        };
    }

    /** 409 → "6:49" */
    public static function hm(int $minutes): string
    {
        return intdiv($minutes, 60).':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
}
