<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\LeaveRecord;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * บันทึกการลงเวลา: จากจอ kiosk / มือถือ (punch) และการแก้ไขโดย HR (manual + ประวัติ)
 */
class AttendanceService
{
    /**
     * @return array{ok:bool, message:string, detail:?string, attendance:?Attendance}
     */
    public static function punch(User $user, string $action, CarbonInterface $at, string $source, ?string $photo = null, bool $offline = false): array
    {
        $type = str_starts_with($action, 'ot_') ? 'ot' : 'normal';
        $isIn = in_array($action, ['in', 'ot_in'], true);
        $at = CarbonImmutable::parse($at);
        $date = $at->startOfDay();

        if (MonthClose::isClosed($date->toDateString())) {
            return self::fail('งวดของวันนี้ปิดแล้ว', 'กรุณาติดต่อ HR');
        }

        if ($type === 'ot' && ! $user->ot_eligible) {
            return self::fail('ไม่มีสิทธิ์ลงเวลา OT', 'กรุณาติดต่อ HR');
        }

        $schedule = ScheduleResolver::for($type, $date);
        if (! $schedule) {
            return $type === 'normal'
                ? self::fail('วันนี้เป็นวันหยุด', 'ถ้าปฏิบัติงานล่วงเวลา กรุณาใช้ปุ่ม OT')
                : self::fail('ยังไม่ได้ตั้งรูปแบบเวลา OT ของวันนี้', 'กรุณาติดต่อ HR');
        }

        $attendance = self::find($user->id, $date->toDateString(), $type);
        $attendance->schedule_id ??= $schedule->id;
        $attendance->department_id ??= $user->departmentIdOn($date);

        if ($isIn) {
            if ($attendance->check_in_at && $attendance->check_in_at->lessThanOrEqualTo($at)) {
                return self::fail('ลงเวลาเข้าไว้แล้ว', 'เมื่อ '.$attendance->check_in_at->format('H:i').' น.', $attendance);
            }
            $attendance->fill(['check_in_at' => $at, 'check_in_source' => $source, 'check_in_photo' => $photo ?? $attendance->check_in_photo]);
        } else {
            if ($attendance->check_in_at && $at->lessThan($attendance->check_in_at)) {
                return self::fail('เวลาออกก่อนเวลาเข้า', 'กรุณาติดต่อ HR', $attendance);
            }
            // กดออกหลายครั้ง ใช้ครั้งล่าสุด
            if (! $attendance->check_out_at || $at->greaterThan($attendance->check_out_at)) {
                $attendance->fill(['check_out_at' => $at, 'check_out_source' => $source, 'check_out_photo' => $photo ?? $attendance->check_out_photo]);
            }
        }

        $attendance->recorded_offline = $attendance->recorded_offline || $offline;
        self::recalculate($attendance);
        $attendance->save();

        return ['ok' => true, 'message' => self::punchMessage($attendance, $isIn), 'detail' => null, 'attendance' => $attendance];
    }

    public static function recalculate(Attendance $attendance): void
    {
        $schedule = $attendance->schedule ?? ScheduleResolver::for($attendance->type, $attendance->work_date);
        if (! $schedule) {
            $attendance->fill(['worked_minutes' => 0, 'special_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0]);

            return;
        }
        $attendance->schedule_id = $schedule->id;
        $attendance->fill(WorkCalculator::calculate(
            $schedule, $attendance->work_date, $attendance->check_in_at, $attendance->check_out_at,
            half: $attendance->type === 'normal' ? self::halfDayLeave($attendance->user_id, $attendance->work_date->toDateString()) : null,
            flex: $attendance->type === 'normal' ? self::flexRule($attendance->user ?? User::query()->find($attendance->user_id)) : null,
        ));
    }

    /** ลาครึ่งวันของวันนั้น (am|pm) · ลาครึ่งเช้า+ครึ่งบ่ายวันเดียวกัน = ลาเต็มวัน (คืน null) */
    public static function halfDayLeave(int $userId, string $date): ?string
    {
        $halves = LeaveRecord::query()->where('user_id', $userId)->whereNotNull('half_day')
            ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)
            ->pluck('half_day')->unique();

        return $halves->count() === 1 ? $halves->first() : null;
    }

    /** กติกาเวลายืดหยุ่นของคนนี้ (null = เวลาปกติ) */
    public static function flexRule(?User $user): ?array
    {
        return $user?->flex_time
            ? ['latest_start' => (string) Setting::get('flex.latest_start'), 'min_minutes' => (int) Setting::get('flex.min_minutes')]
            : null;
    }

    /**
     * คำนวณชั่วโมงใหม่ของคนหนึ่งในช่วงวันที่ (หลังบันทึก/แก้/ลบวันลาครึ่งวัน หรือเปลี่ยนเวลายืดหยุ่น)
     * ข้ามวันที่อยู่ในงวดที่ปิดแล้ว (ข้อมูลถูกล็อก)
     */
    public static function recalculateRange(int $userId, string $from, string $to): void
    {
        Attendance::query()->with(['schedule', 'user'])->where('user_id', $userId)->where('type', 'normal')
            ->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)
            ->get()
            ->reject(fn (Attendance $a) => MonthClose::isClosed($a->work_date->toDateString()))
            ->each(function (Attendance $a) {
                self::recalculate($a);
                $a->save();
            });
    }

    /**
     * HR แก้ไข/เพิ่มเวลา (ต้องระบุเหตุผล เก็บประวัติก่อน-หลัง)
     */
    public static function manualSave(User $subject, string $date, string $type, ?string $in, ?string $out, ?string $note, User $actor, string $reason): Attendance
    {
        $day = CarbonImmutable::parse($date);
        MonthClose::assertOpen($day->toDateString(), 'reason');
        $attendance = self::find($subject->id, $day->toDateString(), $type);
        $before = $attendance->exists ? $attendance->auditSnapshot() : null;

        $attendance->department_id ??= $subject->departmentIdOn($day);
        $attendance->schedule_id ??= ScheduleResolver::for($type, $day)?->id;
        $newIn = $in ? $day->setTimeFromTimeString($in) : null;
        $newOut = $out ? $day->setTimeFromTimeString($out) : null;
        if ($attendance->check_in_at?->format('H:i') !== $in) {
            $attendance->fill(['check_in_at' => $newIn, 'check_in_source' => $newIn ? 'manual' : null]);
        }
        if ($attendance->check_out_at?->format('H:i') !== $out) {
            $attendance->fill(['check_out_at' => $newOut, 'check_out_source' => $newOut ? 'manual' : null]);
        }
        $attendance->note = $note;
        self::recalculate($attendance);
        $attendance->save();

        AttendanceLog::query()->create([
            'attendance_id' => $attendance->id,
            'subject_user_id' => $subject->id,
            'work_date' => $day->toDateString(),
            'actor_id' => $actor->id,
            'action' => $before ? 'update' : 'create',
            'before' => $before,
            'after' => $attendance->auditSnapshot(),
            'reason' => $reason,
        ]);

        return $attendance;
    }

    /** ข้อความบนจอ kiosk หลังลงเวลาสำเร็จ */
    public static function punchMessage(Attendance $a, bool $isIn): string
    {
        $time = ($isIn ? $a->check_in_at : $a->check_out_at)->format('H:i');
        if ($isIn) {
            $label = $a->type === 'ot' ? 'เข้า OT' : 'ลงเวลาเข้า';
            $status = $a->type === 'ot' ? '' : ($a->late_minutes ? " · สาย {$a->late_minutes} นาที" : ' · ตรงเวลา');

            return "{$label} {$time} น.{$status}";
        }
        $hours = WorkCalculator::hm($a->worked_minutes);
        if ($a->type === 'ot') {
            $extra = $a->special_minutes ? ' + พิเศษ '.WorkCalculator::hm($a->special_minutes) : '';

            return "ออก OT {$time} น. · OT {$hours}{$extra} ชม.";
        }

        return "ลงเวลาออก {$time} น. · รวม {$hours} ชม.";
    }

    /** หา/สร้างรายการของวัน (ใช้ whereDate เพราะคอลัมน์ date เก็บรูปแบบต่างกันในแต่ละฐานข้อมูล) */
    private static function find(int $userId, string $date, string $type): Attendance
    {
        return Attendance::query()->where('user_id', $userId)->whereDate('work_date', $date)->where('type', $type)->first()
            ?? new Attendance(['user_id' => $userId, 'work_date' => $date, 'type' => $type]);
    }

    private static function fail(string $message, ?string $detail = null, ?Attendance $attendance = null): array
    {
        return ['ok' => false, 'message' => $message, 'detail' => $detail, 'attendance' => $attendance];
    }
}
