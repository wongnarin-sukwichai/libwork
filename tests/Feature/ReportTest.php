<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\AttendanceService;
use App\Support\MonthClose;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $dept = Department::query()->first();
        $this->staff = User::factory()->staff()->create(['department_id' => $dept->id, 'first_name' => 'วรรณา']);
        $this->member = User::factory()->create(['department_id' => $dept->id, 'first_name' => 'สมชาย', 'employee_code' => '6501', 'start_date' => '2026-10-01',
            'employment_type_id' => EmploymentType::query()->where('code', 'university_staff')->value('id')]);
        $this->travelTo(CarbonImmutable::parse('2026-11-19 08:41'));
        AttendanceService::punch($this->member, 'in', now(), 'kiosk');
        $this->travelTo(CarbonImmutable::parse('2026-11-19 16:33'));
        AttendanceService::punch($this->member, 'out', now(), 'kiosk');
        $this->travelTo(CarbonImmutable::parse('2026-12-02 10:00'));
    }

    public function test_daily_pdf_is_a_real_pdf_with_text(): void
    {
        $res = $this->actingAs($this->staff)->get('/staff/reports/daily.pdf?date=2026-11-19');
        $res->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertLessThan(500_000, strlen($res->getContent()));
    }

    public function test_daily_excel_downloads(): void
    {
        $res = $this->actingAs($this->staff)->get('/staff/reports/daily.xlsx?date=2026-11-19');
        $res->assertOk();
        $this->assertStringStartsWith('PK', $res->streamedContent());
    }

    public function test_person_page_pdf_and_excel_for_any_range(): void
    {
        $this->actingAs($this->staff)->get("/staff/person?user={$this->member->id}&from=2026-11-16&to=2026-11-20")
            ->assertInertia(fn ($p) => $p->component('Staff/Person')->where('report.stats.present', 1)->where('report.stats.late_count', 1)->has('report.rows', 5));
        $this->assertStringStartsWith('%PDF', $this->get("/staff/reports/person.pdf?user={$this->member->id}&from=2026-11-01&to=2026-11-30")->getContent());
        $this->get("/staff/reports/person.xlsx?user={$this->member->id}&from=2026-11-01&to=2026-11-30")->assertOk();
    }

    public function test_members_cannot_download_reports(): void
    {
        $this->actingAs($this->member)->get('/staff/reports/daily.pdf?date=2026-11-19')->assertForbidden();
    }

    public function test_anomalies_block_close_until_fixed_or_acknowledged(): void
    {
        $items = collect(MonthClose::anomalies('2026-11'))->where('user_id', $this->member->id);
        $this->assertTrue($items->contains('kind', 'absent'), 'วันทำการที่ไม่มาและไม่มีใบลา');
        $this->assertTrue($items->contains(fn ($i) => $i['kind'] === 'late' && $i['resolved']), 'สายเป็นรายการเพื่อทราบ');

        $this->actingAs($this->staff)->post('/staff/close', ['month' => '2026-11'])->assertSessionHas('error');

        // รับทราบทุกรายการที่ต้องจัดการ
        foreach (collect(MonthClose::anomalies('2026-11'))->where('blocking', true) as $i) {
            $this->post('/staff/close/acknowledge', ['user_id' => $i['user_id'], 'date' => $i['date'], 'kind' => $i['kind'], 'note' => 'ทดสอบ'])->assertSessionHasNoErrors();
        }
        $this->post('/staff/close', ['month' => '2026-11'])->assertSessionHas('success');
        $this->assertTrue(MonthClose::isClosed('2026-11-19'));
    }

    public function test_closed_month_locks_attendance_and_leave_edits_until_admin_unlocks(): void
    {
        \Illuminate\Support\Facades\DB::table('month_closures')->insert(['month' => '2026-11', 'closed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->staff)->post('/staff/attendance', ['user_id' => $this->member->id, 'date' => '2026-11-19', 'type' => 'normal', 'check_in' => '08:20', 'check_out' => '16:35', 'reason' => 'แก้'])
            ->assertSessionHasErrors('reason');
        $this->post('/staff/leaves', ['user_id' => $this->member->id, 'leave_type_id' => LeaveType::query()->where('code', 'sick')->value('id'), 'start_date' => '2026-11-20', 'end_date' => '2026-11-20'])
            ->assertSessionHasErrors('start_date');
        $this->post('/staff/close/unlock', ['month' => '2026-11', 'reason' => 'x'])->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/staff/close/unlock', ['month' => '2026-11', 'reason' => 'แก้เวลาตกหล่น'])->assertSessionHas('success');
        $this->assertFalse(MonthClose::isClosed('2026-11-19'));
        $this->assertSame(1, Attendance::query()->count());
    }
}
