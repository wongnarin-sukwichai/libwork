<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LeaveRecord;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\AttendanceService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $dept = Department::query()->first();
        $this->staff = User::factory()->staff()->create(['department_id' => $dept->id]);
        $this->member = User::factory()->create(['department_id' => $dept->id, 'first_name' => 'สมชาย', 'start_date' => '2026-10-01']);

        // จ. 16 – ศ. 20 พ.ย. 2569: มาสาย 1 วัน (พฤ. 19), ไปราชการ อ. 17, ลากิจ ศ. 20, ที่เหลือไม่ลงเวลา
        $this->travelTo(CarbonImmutable::parse('2026-11-19 08:41'));
        AttendanceService::punch($this->member, 'in', now(), 'kiosk');
        $this->travelTo(CarbonImmutable::parse('2026-11-19 16:33'));
        AttendanceService::punch($this->member, 'out', now(), 'kiosk');
        $this->leave('official', '2026-11-17');
        $this->leave('personal', '2026-11-20');
        $this->travelTo(CarbonImmutable::parse('2026-12-02 10:00'));
    }

    private function leave(string $code, string $date): void
    {
        LeaveRecord::query()->create([
            'user_id' => $this->member->id, 'leave_type_id' => LeaveType::query()->where('code', $code)->value('id'),
            'start_date' => $date, 'end_date' => $date, 'days' => 1, 'fiscal_year' => 2570,
        ]);
    }

    public function test_summary_counts_attendance_and_leave_per_person(): void
    {
        $this->actingAs($this->staff)->get('/staff/summary?from=2026-11-16&to=2026-11-20')
            ->assertOk()
            ->assertInertia(function ($p) {
                $p->component('Staff/Summary')->where('report.workdays', 5);
                $row = collect($p->toArray()['props']['report']['groups'][0]['rows'])->firstWhere('user_id', $this->member->id);
                $this->assertSame(5, $row['workdays']);
                $this->assertSame(1, $row['present']);
                $this->assertSame(1, $row['late_count']);
                $this->assertSame(11, $row['late_minutes']);
                $this->assertSame(2, $row['absent'], 'จ. 16 และ พ. 18 ไม่มีเวลาเข้าและไม่มีวันลา');
                $this->assertEquals(1, $row['leave']['personal']);
                $this->assertEquals(1, $row['leave']['duty']);
                $this->assertEquals(1, $row['leave_total'], 'ไปราชการไม่นับรวมเป็นวันลา');
            });
    }

    public function test_workdays_start_from_start_date(): void
    {
        $this->member->update(['start_date' => '2026-11-18']);
        $this->actingAs($this->staff)->get('/staff/summary?from=2026-11-16&to=2026-11-20')
            ->assertInertia(fn ($p) => $this->assertSame(3, collect($p->toArray()['props']['report']['groups'][0]['rows'])->firstWhere('user_id', $this->member->id)['workdays']) ?? $p);
    }

    public function test_pdf_and_excel_download(): void
    {
        $pdf = $this->actingAs($this->staff)->get('/staff/reports/summary.pdf?from=2026-10-01&to=2026-11-30');
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringStartsWith('PK', $this->get('/staff/reports/summary.xlsx?from=2026-10-01&to=2026-11-30')->streamedContent());
    }

    public function test_members_cannot_open_summary(): void
    {
        $this->actingAs($this->member)->get('/staff/summary')->assertForbidden();
    }
}
