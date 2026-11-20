<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Support\WorkCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\ScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** กฎการนับชั่วโมงตามที่ตกลงกับผู้ใช้ */
class WorkCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function calc(string $schedule, ?string $in, ?string $out, string $date = '2026-11-19'): array
    {
        $this->seed(ScheduleSeeder::class);
        $s = Schedule::query()->where('name', $schedule)->firstOrFail();
        $at = fn ($t) => $t ? CarbonImmutable::parse("{$date} {$t}") : null;

        return WorkCalculator::calculate($s, CarbonImmutable::parse($date), $at($in), $at($out));
    }

    public function test_normal_day_early_arrival_counts_from_0830_and_lunch_is_deducted(): void
    {
        $r = $this->calc('เวลาปกติ', '08:10', '16:45');
        $this->assertSame(420, $r['worked_minutes']);
        $this->assertSame(0, $r['late_minutes']);
    }

    public function test_grace_period_counts_as_on_time_and_full_hours(): void
    {
        $r = $this->calc('เวลาปกติ', '08:35', '16:31');
        $this->assertSame(0, $r['late_minutes']);
        $this->assertSame(420, $r['worked_minutes']);
    }

    public function test_late_counts_minutes_from_0830_and_hours_from_arrival(): void
    {
        $r = $this->calc('เวลาปกติ', '08:41', '16:33');
        $this->assertSame(11, $r['late_minutes']);
        $this->assertSame(409, $r['worked_minutes'], '08:41–16:30 หักพัก = 6:49');
    }

    public function test_early_leave_is_recorded(): void
    {
        $r = $this->calc('เวลาปกติ', '08:20', '15:30');
        $this->assertSame(60, $r['early_leave_minutes']);
        $this->assertSame(360, $r['worked_minutes']);
    }

    public function test_missing_check_out_gives_no_hours(): void
    {
        $r = $this->calc('เวลาปกติ', '08:20', null);
        $this->assertSame(0, $r['worked_minutes']);
    }

    public function test_weekday_ot_counts_from_1700_whole_hours(): void
    {
        $this->assertSame(180, $this->calc('OT วันทำการ', '16:35', '20:02')['worked_minutes']);
        $this->assertSame(120, $this->calc('OT วันทำการ', '17:10', '20:00')['worked_minutes'], '2:50 ปัดลงเป็น 2 ชม.');
    }

    public function test_holiday_ot_is_seven_hours(): void
    {
        $r = $this->calc('OT วันหยุด', '09:56', '18:05', '2026-11-15');
        $this->assertSame(420, $r['worked_minutes']);
    }

    public function test_special_ot_splits_three_plus_two_hours(): void
    {
        $r = $this->calc('ล่วงเวลาพิเศษ (ถึง 22:00)', '16:25', '22:03');
        $this->assertSame(180, $r['worked_minutes']);
        $this->assertSame(120, $r['special_minutes']);
    }

    public function test_special_ot_leaving_early_floors_each_part(): void
    {
        $r = $this->calc('ล่วงเวลาพิเศษ (ถึง 22:00)', '16:40', '21:40');
        $this->assertSame(180, $r['worked_minutes']);
        $this->assertSame(60, $r['special_minutes'], '1:40 ปัดลงเป็น 1 ชม.');
    }
}
