<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRecord;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\LeaveService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
        $dept = Department::query()->first();
        $this->staff = User::factory()->staff()->create(['department_id' => $dept->id]);
        $this->member = User::factory()->create([
            'department_id' => $dept->id,
            'employment_type_id' => EmploymentType::query()->where('code', 'university_staff')->value('id'),
            'start_date' => '2020-06-01',
        ]);
    }

    private function type(string $code): int
    {
        return LeaveType::query()->where('code', $code)->value('id');
    }

    public function test_days_skip_weekends_and_holidays_and_half_day_counts_half(): void
    {
        $this->assertSame(4.0, LeaveService::countDays('2026-11-24', '2026-11-29')); // อ.–อา.
        $this->assertSame(4.0, LeaveService::countDays('2026-12-07', '2026-12-11'), 'ไม่นับ 10 ธ.ค. วันรัฐธรรมนูญ');
        $this->assertSame(0.5, LeaveService::countDays('2026-11-24', '2026-11-24', 'am'));
    }

    public function test_hr_records_leave_and_balance_drops(): void
    {
        $this->actingAs($this->staff)->post('/staff/leaves', [
            'user_id' => $this->member->id, 'leave_type_id' => $this->type('vacation'),
            'start_date' => '2026-11-24', 'end_date' => '2026-11-27', 'reason' => 'พักผ่อน',
        ])->assertSessionHasNoErrors();

        $record = LeaveRecord::query()->sole();
        $this->assertSame(4.0, $record->days);
        $this->assertSame(2570, $record->fiscal_year);

        $vac = collect(LeaveService::summary($this->member, 2570))->firstWhere('code', 'vacation');
        $this->assertSame(10.0, $vac['entitled']);
        $this->assertSame(6.0, $vac['remaining']);
    }

    public function test_overlap_over_quota_and_cross_fiscal_year_are_rejected(): void
    {
        $base = ['user_id' => $this->member->id, 'leave_type_id' => $this->type('vacation')];
        $this->actingAs($this->staff)->post('/staff/leaves', $base + ['start_date' => '2026-11-24', 'end_date' => '2026-11-24']);

        $this->post('/staff/leaves', $base + ['start_date' => '2026-11-23', 'end_date' => '2026-11-25'])->assertSessionHasErrors('start_date');
        $this->post('/staff/leaves', $base + ['start_date' => '2026-12-01', 'end_date' => '2026-12-31'])->assertSessionHasErrors('allow_over');
        $this->post('/staff/leaves', $base + ['start_date' => '2026-12-01', 'end_date' => '2026-12-31', 'allow_over' => true])->assertSessionHasNoErrors();
        $this->post('/staff/leaves', $base + ['start_date' => '2027-09-29', 'end_date' => '2027-10-02', 'allow_over' => true])->assertSessionHasErrors('end_date');
    }

    public function test_official_duty_does_not_use_quota(): void
    {
        $this->actingAs($this->staff)->post('/staff/leaves', [
            'user_id' => $this->member->id, 'leave_type_id' => $this->type('official'),
            'start_date' => '2026-11-02', 'end_date' => '2026-11-30',
        ])->assertSessionHasNoErrors();

        $duty = collect(LeaveService::summary($this->member, 2570))->firstWhere('code', 'official');
        $this->assertFalse($duty['counts_quota']);
        $this->assertNull($duty['remaining']);
    }

    public function test_carry_over_respects_cap_and_hr_edit_survives_regeneration(): void
    {
        // ปีงบ 2569 ใช้ลาพักผ่อนไป 2 วัน → เหลือ 8 · ปีงบ 2570 สิทธิ์ 10 เพดาน 20 → ยกมาได้ 8
        LeaveBalance::query()->create(['user_id' => $this->member->id, 'leave_type_id' => $this->type('vacation'), 'fiscal_year' => 2569, 'entitled' => 10, 'status' => 'confirmed']);
        LeaveRecord::query()->create(['user_id' => $this->member->id, 'leave_type_id' => $this->type('vacation'), 'start_date' => '2026-03-02', 'end_date' => '2026-03-03', 'days' => 2, 'fiscal_year' => 2569]);
        LeaveService::draftYear(2570);
        $b = LeaveBalance::query()->where('user_id', $this->member->id)->where('fiscal_year', 2570)->where('leave_type_id', $this->type('vacation'))->sole();
        $this->assertSame(8.0, $b->carried_over);
        $this->assertSame('draft', $b->status);

        // member ยังไม่เห็นยอดยกมาใหม่จนกว่า HR ยืนยัน
        $this->actingAs($this->member)->get('/me/leave')->assertInertia(fn ($p) => $p->where('summary.0.carried_over', 0)->where('summary.0.pending', true));

        $this->actingAs($this->staff)->put("/staff/carry-over/{$b->id}", ['carried' => 9, 'reviewed' => true])->assertSessionHasErrors('reason');
        $this->actingAs($this->staff)->put("/staff/carry-over/{$b->id}", ['carried' => 9, 'reason' => 'ใบลาซ้ำ', 'reviewed' => true])->assertSessionHasNoErrors();
        LeaveService::draftYear(2570);
        $this->assertSame(9.0, $b->fresh()->carried_over, 'ร่างใหม่ไม่ทับค่าที่ HR แก้');

        LeaveBalance::query()->where('fiscal_year', 2570)->update(['reviewed' => true]);
        $this->actingAs($this->staff)->post('/staff/carry-over/confirm', ['fy' => 2570])->assertSessionHas('success');
        $this->actingAs($this->member)->get('/me/leave')->assertInertia(fn ($p) => $p
            ->where('confirmed', true)
            ->where('summary.0.carried_over', 9)
            ->where('adjustments.0.reason', 'ใบลาซ้ำ'));
    }

    public function test_cap_limits_total_with_this_years_entitlement(): void
    {
        LeaveBalance::query()->create(['user_id' => $this->member->id, 'leave_type_id' => $this->type('vacation'), 'fiscal_year' => 2569, 'entitled' => 10, 'carried_over' => 18, 'status' => 'confirmed']);
        LeaveService::draftYear(2570);

        $b = LeaveBalance::query()->where('user_id', $this->member->id)->where('fiscal_year', 2570)->where('leave_type_id', $this->type('vacation'))->sole();
        $this->assertSame(10.0, $b->carried_over, 'เหลือ 28 แต่ ยกมา + สิทธิ์ปีนี้ ต้องไม่เกิน 20');
    }

    public function test_daily_report_and_public_calendar_show_leave(): void
    {
        $this->actingAs($this->staff)->post('/staff/leaves', ['user_id' => $this->member->id, 'leave_type_id' => $this->type('sick'), 'start_date' => '2026-11-19', 'end_date' => '2026-11-20']);

        $this->get('/staff?date=2026-11-19')->assertInertia(fn ($p) => $p->where('report.stats.leave', 1));
        auth()->logout();
        $this->get('/')->assertInertia(fn ($p) => $p
            ->where('events.2026-11-19', fn ($e) => collect($e)->contains(fn ($x) => str_ends_with($x['label'], '· ลา')))
            ->where('absentToday.0.type', 'ลา'));
    }

    public function test_first_year_without_previous_data_carries_nothing(): void
    {
        LeaveService::draftYear(2570);
        $b = LeaveBalance::query()->where('user_id', $this->member->id)->where('fiscal_year', 2570)->where('leave_type_id', $this->type('vacation'))->sole();
        $this->assertSame(0.0, $b->carried_over, 'ไม่มียอดปีก่อนในระบบ ห้ามเดาว่ายังไม่ได้ลา');
        $this->assertSame(10.0, $b->entitled);
    }

    public function test_holiday_blocks_normal_check_in(): void
    {
        Holiday::query()->create(['date' => '2026-11-26', 'name' => 'วันทดสอบ']);
        $this->assertSame(0.0, LeaveService::countDays('2026-11-26', '2026-11-26'));
    }
}
