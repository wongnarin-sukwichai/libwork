<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ตรวจสอบและปิดงวดรายเดือน
 *
 * รายการผิดปกติที่ต้องจัดการก่อนปิด (แก้ข้อมูลแล้วหายเอง หรือ HR กด "รับทราบ" พร้อมหมายเหตุ):
 *   no_out ไม่ได้ลงเวลาออก · no_in ไม่ได้ลงเวลาเข้า · absent ไม่มาโดยไม่มีใบลา · clash มีใบลาเต็มวันแต่ลงเวลาด้วย · offline บันทึกขณะออฟไลน์
 * รายการเพื่อทราบ (ไม่บังคับ): late สาย · early ออกก่อนเวลา
 */
class MonthClose
{
    public const BLOCKING = ['no_out', 'no_in', 'absent', 'clash', 'offline'];

    public const LABELS = [
        'no_out' => 'ไม่ได้ลงเวลาออก',
        'no_in' => 'ไม่ได้ลงเวลาเข้า',
        'absent' => 'ไม่มาโดยไม่มีใบลา',
        'clash' => 'ข้อมูลขัดกัน',
        'offline' => 'บันทึกขณะออฟไลน์',
        'late' => 'สาย',
        'early' => 'ออกก่อนเวลา',
    ];

    public static function isClosed(string $date): bool
    {
        return DB::table('month_closures')->where('month', substr($date, 0, 7))->exists();
    }

    /** ใช้ก่อนแก้ข้อมูลเวลา/วันลา ของวันที่อยู่ในงวดที่ปิดแล้ว */
    public static function assertOpen(string $date, string $field = 'date'): void
    {
        if (self::isClosed($date)) {
            $m = CarbonImmutable::parse($date);
            throw ValidationException::withMessages([$field => 'งวด '.thai_month($m).' ปิดแล้ว ต้องให้ admin ปลดล็อกก่อนแก้ไข']);
        }
    }

    /** @return array<int, array> รายการผิดปกติของเดือน (เฉพาะวันที่ผ่านมาแล้ว) */
    public static function anomalies(string $month): array
    {
        $start = CarbonImmutable::parse("{$month}-01");
        $end = $start->endOfMonth()->min(CarbonImmutable::yesterday());
        if ($end->lessThan($start)) {
            return [];
        }

        $users = User::query()->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $start))
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $end))
            ->get(['id', 'prefix', 'first_name', 'last_name', 'is_active', 'start_date', 'end_date']);
        $attendances = Attendance::query()->where('type', 'normal')->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()->groupBy(fn ($a) => $a->work_date->toDateString().'|'.$a->user_id);
        $offline = Attendance::query()->where('recorded_offline', true)->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])->get();
        $leaves = LeaveService::byDate($start->toDateString(), $end->toDateString());
        $acks = DB::table('anomaly_acks')->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()
            ->keyBy(fn ($a) => $a->date.'|'.$a->user_id.'|'.$a->kind);

        $items = [];
        $push = function (User $u, string $date, string $kind, string $detail, ?int $attendanceId = null) use (&$items, $acks) {
            $ack = $acks->get("{$date}|{$u->id}|{$kind}");
            $items[] = [
                'user_id' => $u->id, 'name' => $u->full_name, 'date' => $date, 'kind' => $kind, 'label' => self::LABELS[$kind],
                'detail' => $detail, 'blocking' => in_array($kind, self::BLOCKING, true), 'attendance_id' => $attendanceId,
                'resolved' => (bool) $ack, 'ack_note' => $ack?->note,
            ];
        };

        foreach (CarbonPeriod::create($start, $end) as $d) {
            $date = $d->toDateString();
            if (! ScheduleResolver::isWorkday($d)) {
                continue;
            }
            foreach ($users as $u) {
                if (($u->start_date && $u->start_date->toDateString() > $date) || ($u->end_date && $u->end_date->toDateString() < $date)) {
                    continue;
                }
                $a = $attendances->get("{$date}|{$u->id}")?->first();
                $leave = $leaves[$date][$u->id] ?? null;
                if ($leave && ! $leave->half_day) {
                    if ($a?->check_in_at) {
                        $push($u, $date, 'clash', 'มีใบ'.$leave->leaveType->name.' แต่ลงเวลาเข้า '.$a->check_in_at->format('H:i'), $a->id);
                    }

                    continue;
                }
                if (! $a || (! $a->check_in_at && ! $a->check_out_at)) {
                    if ($u->is_active) {
                        // ลาครึ่งวันแต่ไม่มาอีกครึ่ง: ตกลงกันว่า HR แก้เป็นลาเต็มวันเอง (ระบบแค่เตือน)
                        $push($u, $date, 'absent', $leave
                            ? 'มีใบ'.LeaveService::label($leave).' แต่ไม่ได้ลงเวลาอีกครึ่งวัน · ถ้าไม่ได้มาทั้งวันให้แก้เป็นลาเต็มวัน'
                            : 'ไม่มีการลงเวลาและไม่มีใบลา');
                    }

                    continue;
                }
                if (! $a->check_in_at) {
                    $push($u, $date, 'no_in', 'ออก '.$a->check_out_at->format('H:i').' · ไม่มีเวลาเข้า', $a->id);
                } elseif (! $a->check_out_at) {
                    $push($u, $date, 'no_out', 'เข้า '.$a->check_in_at->format('H:i').' · ไม่มีเวลาออก', $a->id);
                }
                if ($a->late_minutes) {
                    $push($u, $date, 'late', 'เข้า '.$a->check_in_at->format('H:i')." (สาย {$a->late_minutes} นาที)".($a->note ? " · {$a->note}" : ''), $a->id);
                }
                if ($a->early_leave_minutes && $a->check_out_at) {
                    $push($u, $date, 'early', 'ออก '.$a->check_out_at->format('H:i')." (ก่อนเวลา {$a->early_leave_minutes} นาที)", $a->id);
                }
            }
        }
        foreach ($offline as $a) {
            $u = $users->firstWhere('id', $a->user_id);
            if ($u) {
                $push($u, $a->work_date->toDateString(), 'offline', ($a->type === 'ot' ? 'OT · ' : '').'บันทึกจากคิวออฟไลน์ของจอ kiosk', $a->id);
            }
        }

        // late/early ถือว่าจัดการแล้ว (เพื่อทราบ) · เรียงวันที่ใหม่ก่อน
        return collect($items)->map(fn ($i) => $i['blocking'] ? $i : [...$i, 'resolved' => true])
            ->sortBy([['resolved', 'asc'], ['date', 'desc']])->values()->all();
    }
}
