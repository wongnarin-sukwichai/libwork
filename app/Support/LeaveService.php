<?php

namespace App\Support;

use App\Models\Holiday;
use App\Models\LeaveAdjustment;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRecord;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * วันลา: นับวันทำการ · ยอดคงเหลือ · ร่างยอดยกมาปีงบใหม่
 *
 * ยอดคงเหลือ = สิทธิ์ปีนี้ + ยกมา − ใช้ไป (การปรับยอดของ HR บันทึกเป็นประวัติและรวมอยู่ใน "ยกมา")
 * ลาพักผ่อนยกไปปีถัดไป: ยกมา + สิทธิ์ปีใหม่ รวมไม่เกินเพดาน (20 วัน / 30 วันเมื่ออายุงาน ≥ 10 ปี ตามค่าตั้งต้น)
 */
class LeaveService
{
    /** จำนวนวันทำการในช่วง (ไม่รวมเสาร์-อาทิตย์และวันหยุด) · ครึ่งวัน = 0.5 */
    public static function countDays(string $start, string $end, ?string $half = null): float
    {
        if ($half) {
            return ScheduleResolver::isWorkday(CarbonImmutable::parse($start)) ? 0.5 : 0;
        }
        $holidays = Holiday::map();

        return (float) collect(CarbonPeriod::create($start, $end))
            ->filter(fn ($d) => ! $d->isWeekend() && ! isset($holidays[$d->toDateString()]))
            ->count();
    }

    /** ใช้ไปในปีงบแยกตามประเภทลา [leave_type_id => days] */
    public static function used(int $userId, int $fy): Collection
    {
        return LeaveRecord::query()
            ->where('user_id', $userId)
            ->where('fiscal_year', $fy)
            ->selectRaw('leave_type_id, sum(days) as total, count(*) as times')
            ->groupBy('leave_type_id')
            ->get()
            ->keyBy('leave_type_id');
    }

    /**
     * สรุปสิทธิ์ของคนหนึ่งในปีงบ (ทุกประเภทลา)
     *
     * @param  bool  $memberView  true = ถ้ายอดยังเป็นร่าง จะไม่แสดงยอดยกมาใหม่
     */
    public static function summary(User $user, int $fy, bool $memberView = false): array
    {
        $types = LeaveType::query()->where('is_active', true)->ordered()->get();
        $policies = LeavePolicy::query()->where('employment_type_id', $user->employment_type_id)->get()->keyBy('leave_type_id');
        $balances = LeaveBalance::query()->where('user_id', $user->id)->where('fiscal_year', $fy)->get()->keyBy('leave_type_id');
        $used = self::used($user->id, $fy);

        return $types->map(function (LeaveType $t) use ($policies, $balances, $used, $user, $fy, $memberView) {
            $policy = $policies->get($t->id);
            $balance = $balances->get($t->id);
            $draft = $balance?->status === 'draft';
            $entitled = $balance?->entitled ?? ($policy ? self::entitlement($user, $policy, $fy) : 0);
            $carried = ($memberView && $draft) ? 0.0 : (float) ($balance?->carried_over ?? 0);
            $u = (float) ($used->get($t->id)?->total ?? 0);
            $quota = $t->counts_quota && $policy;

            return [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'category' => $t->category,
                'counts_quota' => $quota,
                'carry_over' => $t->carry_over,
                'entitled' => $entitled,
                'carried_over' => $carried,
                'used' => $u,
                'times' => (int) ($used->get($t->id)?->times ?? 0),
                'remaining' => $quota ? $entitled + $carried - $u : null,
                'cap' => $balance?->cap ?? ($policy ? self::cap($user, $policy, $fy) : null),
                'pending' => $memberView && $draft && $t->carry_over,
            ];
        })->all();
    }

    /** สิทธิ์ปีงบ: ต้องทำงานครบ min_service_months ณ วันเริ่มปีงบ */
    public static function entitlement(User $user, LeavePolicy $policy, int $fy): float
    {
        if ($policy->min_service_months && $user->start_date) {
            $eligibleFrom = CarbonImmutable::parse($user->start_date)->addMonths($policy->min_service_months);
            if ($eligibleFrom->greaterThan(FiscalYear::start($fy))) {
                return 0;
            }
        }

        return (float) $policy->days_per_year;
    }

    public static function cap(User $user, LeavePolicy $policy, int $fy): ?float
    {
        $years = $user->start_date ? CarbonImmutable::parse($user->start_date)->diffInYears(FiscalYear::start($fy)) : 0;

        return $years >= 10 && $policy->max_total_days_10y !== null ? $policy->max_total_days_10y : $policy->max_total_days;
    }

    /**
     * สร้าง/อัปเดตร่างยอดตั้งต้นของปีงบ (สิทธิ์ + ยกมาจากปีก่อน) · ไม่ทับค่าที่ HR แก้ไว้หรือยืนยันแล้ว
     *
     * @return int จำนวนแถวที่สร้าง/อัปเดต
     */
    public static function draftYear(int $fy): int
    {
        $count = 0;
        $types = LeaveType::query()->where('is_active', true)->where('counts_quota', true)->get();
        $users = User::query()->where('is_active', true)->whereNotNull('employment_type_id')
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', FiscalYear::start($fy)))
            ->get();

        foreach ($users as $user) {
            $policies = LeavePolicy::query()->where('employment_type_id', $user->employment_type_id)->get()->keyBy('leave_type_id');
            foreach ($types as $type) {
                $policy = $policies->get($type->id);
                if (! $policy) {
                    continue;
                }
                $balance = LeaveBalance::query()->firstOrNew(['user_id' => $user->id, 'leave_type_id' => $type->id, 'fiscal_year' => $fy]);
                if ($balance->status === 'confirmed') {
                    continue;
                }

                $entitled = self::entitlement($user, $policy, $fy);
                $cap = self::cap($user, $policy, $fy);
                $carried = 0.0;
                // ยกยอดเฉพาะเมื่อปีก่อนมียอดในระบบจริง (ปีแรกที่เปิดระบบ HR กรอกยอดเริ่มต้นเอง ไม่เดาว่าไม่ได้ลา)
                $hasPrev = LeaveBalance::query()->where('user_id', $user->id)->where('leave_type_id', $type->id)->where('fiscal_year', $fy - 1)->exists();
                if ($type->carry_over && $hasPrev) {
                    $prev = collect(self::summary($user, $fy - 1))->firstWhere('id', $type->id);
                    $remaining = max(0, (float) ($prev['remaining'] ?? 0));
                    $carried = $cap !== null ? max(0, min($remaining, $cap - $entitled)) : $remaining;
                }

                $hrEdited = $balance->exists && $balance->carried_over !== $balance->system_carried_over;
                $balance->fill([
                    'entitled' => $entitled,
                    'cap' => $cap,
                    'system_carried_over' => $carried,
                    'carried_over' => $hrEdited ? $balance->carried_over : $carried,
                    'status' => 'draft',
                ])->save();
                $count++;
            }
        }

        return $count;
    }

    /**
     * วันลาที่ครอบคลุมแต่ละวันในช่วง [date => [user_id => LeaveRecord]] (เฉพาะวันทำการ)
     */
    public static function byDate(string $start, string $end, ?array $userIds = null): array
    {
        $holidays = Holiday::map();
        $map = [];
        LeaveRecord::query()->with('leaveType:id,name,category')
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)
            ->when($userIds !== null, fn ($q) => $q->whereIn('user_id', $userIds))
            ->get()
            ->each(function (LeaveRecord $r) use (&$map, $start, $end, $holidays) {
                $from = max($r->start_date->toDateString(), $start);
                $to = min($r->end_date->toDateString(), $end);
                foreach (CarbonPeriod::create($from, $to) as $d) {
                    if (! $d->isWeekend() && ! isset($holidays[$d->toDateString()])) {
                        $map[$d->toDateString()][$r->user_id] = $r;
                    }
                }
            });

        return $map;
    }

    /** ข้อความสถานะวันลา เช่น "ลาพักผ่อน" / "ลากิจ (ครึ่งเช้า)" */
    public static function label(LeaveRecord $r): string
    {
        return $r->leaveType->name.($r->half_day ? ' ('.$r->halfLabel().')' : '');
    }

    /** HR ปรับยอดยกมา (บันทึกเหตุผลเป็นประวัติที่ member เห็น) */
    public static function adjustCarried(LeaveBalance $balance, float $newValue, string $reason, User $actor): void
    {
        $diff = round($newValue - $balance->carried_over, 1);
        if ($diff == 0.0) {
            return;
        }
        $balance->update(['carried_over' => $newValue]);
        LeaveAdjustment::query()->create([
            'leave_balance_id' => $balance->id,
            'amount' => $diff,
            'reason' => $reason,
            'created_by' => $actor->id,
        ]);
    }
}
