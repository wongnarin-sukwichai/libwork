<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\AttendanceService;
use App\Support\DailyReport;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ลาครึ่งวัน (สาย/ออกก่อนคิดจากครึ่งที่ทำงาน) และเวลายืดหยุ่นรายคน · ตกลงกับผู้ใช้ 2026-11-24 */
class HalfDayFlexTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = '2026-11-19'; // พฤหัสบดี วันทำการ

    private User $staff;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $dept = Department::query()->first();
        $this->staff = User::factory()->staff()->create(['department_id' => $dept->id]);
        $this->member = User::factory()->create(['department_id' => $dept->id, 'first_name' => 'สมชาย', 'start_date' => '2026-10-01']);
    }

    private function punch(string $in, string $out): Attendance
    {
        $this->travelTo(CarbonImmutable::parse(self::DAY.' '.$in));
        AttendanceService::punch($this->member, 'in', now(), 'kiosk');
        $this->travelTo(CarbonImmutable::parse(self::DAY.' '.$out));
        AttendanceService::punch($this->member, 'out', now(), 'kiosk');

        return Attendance::query()->where('user_id', $this->member->id)->whereDate('work_date', self::DAY)->firstOrFail();
    }

    /** บันทึกลาผ่านหน้ารายวัน (modal) แบบเดียวกับที่ HR กด */
    private function halfLeave(string $half): void
    {
        $this->actingAs($this->staff)->from('/staff?date='.self::DAY)->post('/staff/leaves', [
            'user_id' => $this->member->id, 'leave_type_id' => LeaveType::query()->where('code', 'personal')->value('id'),
            'start_date' => self::DAY, 'end_date' => self::DAY, 'half_day' => $half, 'stay' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect('/staff?date='.self::DAY);
    }

    private function row(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00'));

        return collect(DailyReport::build(self::DAY)['groups'])->flatMap(fn ($g) => $g['rows'])->firstWhere('user_id', $this->member->id);
    }

    public function test_morning_leave_counts_late_from_afternoon_start(): void
    {
        $this->halfLeave('am');
        $a = $this->punch('13:03', '16:31');

        $this->assertSame(0, $a->late_minutes, 'มา 13:03 อยู่ในผ่อนผันของครึ่งบ่าย');
        $this->assertSame(0, $a->early_leave_minutes);
        $this->assertSame(210, $a->worked_minutes, '13:00–16:30');
        $this->assertSame('ลากิจ (ครึ่งเช้า)', $this->row()['status']);
    }

    public function test_morning_leave_late_in_afternoon_is_shown(): void
    {
        $this->halfLeave('am');
        $this->assertSame(20, $this->punch('13:20', '16:35')->late_minutes);
        $row = $this->row();
        $this->assertSame('ลากิจ (ครึ่งเช้า) · สาย 20 นาที', $row['status']);
        $this->assertSame('late', $row['tone']);
    }

    public function test_afternoon_leave_ends_at_noon(): void
    {
        $this->halfLeave('pm');
        $a = $this->punch('08:28', '12:01');

        $this->assertSame(0, $a->early_leave_minutes, 'ลาครึ่งบ่าย ออก 12:00 ไม่ใช่ออกก่อน');
        $this->assertSame(210, $a->worked_minutes, '08:30–12:00');
    }

    public function test_leave_recorded_after_punches_recalculates_hours(): void
    {
        $a = $this->punch('13:00', '16:30');
        $this->assertSame(270, $a->late_minutes, 'ก่อนบันทึกลา = สาย 4.5 ชม.');

        $this->halfLeave('am');
        $this->assertSame(0, $a->fresh()->late_minutes);
    }

    public function test_flex_time_counts_actual_hours_from_0830(): void
    {
        $this->member->update(['flex_time' => true]);
        $a = $this->punch('08:55', '17:10');

        $this->assertSame(0, $a->late_minutes, 'เข้าไม่เกิน 09:00');
        $this->assertSame(425, $a->worked_minutes, '08:55–17:00 หักพัก 1 ชม. (นับได้ถึง 09:00+7+1 = 17:00)');
        $this->assertSame(0, $a->early_leave_minutes);
        $this->assertSame('ปกติ', $this->row()['status']);
    }

    public function test_flex_time_short_hours_and_early_arrival(): void
    {
        $this->member->update(['flex_time' => true]);
        $a = $this->punch('08:00', '15:00');

        $this->assertSame(330, $a->worked_minutes, 'มาก่อน 08:30 ไม่นับ: 08:30–15:00 หักพัก');
        $this->assertSame(90, $a->early_leave_minutes);
        $this->assertSame('ชม. ไม่ครบ (ขาด 90 นาที)', $this->row()['status']);
    }

    public function test_flex_time_late_after_latest_start(): void
    {
        $this->member->update(['flex_time' => true]);
        $this->assertSame(20, $this->punch('09:20', '17:00')->late_minutes);
    }

    public function test_admin_changes_flex_rule_and_staff_cannot(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($this->staff)->put('/admin/settings/flex', ['latest_start' => '09:30', 'min_hours' => 7])->assertForbidden();
        $this->actingAs($admin)->put('/admin/settings/flex', ['latest_start' => '09:30', 'min_hours' => 6.5])->assertSessionHasNoErrors();

        $this->member->update(['flex_time' => true]);
        $a = $this->punch('09:25', '16:00');
        $this->assertSame(0, $a->late_minutes);
        $this->assertSame(335, $a->worked_minutes, '09:25–16:00 หักพัก = 5:35');
        $this->assertSame(55, $a->early_leave_minutes, 'ขั้นต่ำใหม่ 6.5 ชม. = 390 นาที');
    }

    public function test_absent_has_its_own_tone(): void
    {
        $this->assertSame('absent', $this->row()['tone']);
    }
}
