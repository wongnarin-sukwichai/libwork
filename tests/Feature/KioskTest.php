<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\KioskDevice;
use App\Models\Schedule;
use App\Models\SpecialDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class KioskTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(ScheduleSeeder::class);
        $dept = Department::query()->create(['name' => 'กลุ่ม A']);
        $this->user = User::factory()->create(['employee_code' => '6501', 'department_id' => $dept->id]);

        $device = KioskDevice::query()->create(['name' => 'จอกลาง ชั้น 1']);
        $code = $device->issueSetupCode();
        $this->token = $this->postJson('/kiosk/api/register', ['code' => $code])->assertOk()->json('token');
    }

    private function punch(array $data)
    {
        return $this->withHeader('X-Kiosk-Token', $this->token)->postJson('/kiosk/api/punch', $data);
    }

    /** รูป JPEG 1x1 */
    private function photo(): string
    {
        $img = imagecreatetruecolor(640, 480);
        ob_start();
        imagejpeg($img);

        return 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());
    }

    public function test_setup_code_works_once_and_api_requires_token(): void
    {
        $this->getJson('/kiosk/api/status')->assertUnauthorized();
        $this->withHeader('X-Kiosk-Token', $this->token)->getJson('/kiosk/api/status')->assertOk()->assertJsonPath('device', 'จอกลาง ชั้น 1');
        $this->postJson('/kiosk/api/register', ['code' => 'XXXX-XXXX'])->assertStatus(422);
    }

    public function test_check_in_and_out_calculates_hours_and_stores_small_photo(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 08:41:00'));
        $this->punch(['employee_code' => '6501', 'action' => 'in', 'photo' => $this->photo()])
            ->assertOk()->assertJsonPath('message', 'ลงเวลาเข้า 08:41 น. · สาย 11 นาที');

        $this->travelTo(CarbonImmutable::parse('2026-11-19 16:33:00'));
        $this->punch(['employee_code' => '6501', 'action' => 'out'])->assertOk()->assertJsonPath('message', 'ลงเวลาออก 16:33 น. · รวม 6:49 ชม.');

        $a = Attendance::query()->firstOrFail();
        $this->assertSame(409, $a->worked_minutes);
        $this->assertSame(11, $a->late_minutes);
        Storage::disk('local')->assertExists($a->check_in_photo);
        $this->assertSame(480, getimagesizefromstring(Storage::disk('local')->get($a->check_in_photo))[0]);
    }

    public function test_second_check_in_is_rejected_and_last_check_out_wins(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 08:20:00'));
        $this->punch(['employee_code' => '6501', 'action' => 'in'])->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-11-19 08:25:00'));
        $this->punch(['employee_code' => '6501', 'action' => 'in'])->assertStatus(422)->assertJsonPath('message', 'ลงเวลาเข้าไว้แล้ว');

        $this->travelTo(CarbonImmutable::parse('2026-11-19 16:31:00'));
        $this->punch(['employee_code' => '6501', 'action' => 'out'])->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-11-19 16:40:00'));
        $this->punch(['employee_code' => '6501', 'action' => 'out'])->assertOk();

        $this->assertSame('16:40', Attendance::query()->first()->check_out_at->format('H:i'));
    }

    public function test_unknown_code_and_weekend_normal_check_in_are_rejected(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 08:20:00'));
        $this->punch(['employee_code' => '9999', 'action' => 'in'])->assertStatus(422)->assertJsonPath('message', 'ไม่พบรหัสบุคลากรนี้');

        $this->travelTo(CarbonImmutable::parse('2026-11-21 09:00:00')); // วันเสาร์
        $this->punch(['employee_code' => '6501', 'action' => 'in'])->assertStatus(422)->assertJsonPath('message', 'วันนี้เป็นวันหยุด');
    }

    public function test_ot_requires_eligibility_and_special_day_splits_hours(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 16:25:00'));
        $this->punch(['employee_code' => '6501', 'action' => 'ot_in'])->assertStatus(422)->assertJsonPath('message', 'ไม่มีสิทธิ์ลงเวลา OT');

        $this->user->update(['ot_eligible' => true]);
        SpecialDay::query()->create(['date' => '2026-11-19', 'schedule_id' => Schedule::query()->where('applies_to', 'special')->value('id')]);
        $this->punch(['employee_code' => '6501', 'action' => 'ot_in'])->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-11-19 22:03:00'));
        $this->punch(['employee_code' => '6501', 'action' => 'ot_out'])->assertOk()->assertJsonPath('message', 'ออก OT 22:03 น. · OT 3:00 + พิเศษ 2:00 ชม.');
    }

    public function test_offline_queue_uses_client_time_once_and_is_flagged(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 10:00:00'));
        $uuid = (string) Str::uuid();
        $payload = ['employee_code' => '6501', 'action' => 'in', 'offline' => true, 'punched_at' => '2026-11-19T08:22:00+07:00', 'client_uuid' => $uuid];

        $this->punch($payload)->assertOk();
        $this->punch($payload)->assertOk()->assertJsonPath('duplicate', true);

        $a = Attendance::query()->sole();
        $this->assertSame('08:22', $a->check_in_at->format('H:i'));
        $this->assertTrue($a->recorded_offline);
    }

    public function test_offline_time_too_old_is_rejected(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 10:00:00'));
        $this->punch(['employee_code' => '6501', 'action' => 'in', 'offline' => true, 'punched_at' => '2026-11-01T08:22:00+07:00', 'client_uuid' => (string) Str::uuid()])
            ->assertStatus(422);
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_hr_edit_requires_reason_and_logs_change(): void
    {
        $staff = User::factory()->staff()->create();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:00'));

        $this->actingAs($staff)->post('/staff/attendance', ['user_id' => $this->user->id, 'date' => '2026-11-19', 'type' => 'normal', 'check_in' => '08:20', 'check_out' => '16:35'])
            ->assertSessionHasErrors('reason');
        $this->actingAs($staff)->post('/staff/attendance', ['user_id' => $this->user->id, 'date' => '2026-11-19', 'type' => 'normal', 'check_in' => '08:20', 'check_out' => '16:35', 'reason' => 'ลืมลงเวลา'])
            ->assertSessionHasNoErrors();

        $a = Attendance::query()->sole();
        $this->assertSame(420, $a->worked_minutes);
        $this->assertSame('manual', $a->check_in_source);
        $this->assertDatabaseHas('attendance_logs', ['subject_user_id' => $this->user->id, 'action' => 'create', 'reason' => 'ลืมลงเวลา']);
    }

    public function test_daily_report_groups_by_department_with_statuses(): void
    {
        $staff = User::factory()->staff()->create(['department_id' => $this->user->department_id]);
        $this->travelTo(CarbonImmutable::parse('2026-11-19 08:41:00'));
        $this->punch(['employee_code' => '6501', 'action' => 'in']);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:00'));

        $this->actingAs($staff)->get('/staff?date=2026-11-19')->assertInertia(fn ($p) => $p
            ->component('Staff/Dashboard')
            ->where('report.stats.present', 1)
            ->where('report.groups.0.name', 'กลุ่ม A')
            ->where('report.stats.no_out', 1));
    }

    public function test_photo_is_visible_to_owner_and_staff_only(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 08:20:00'));
        $this->punch(['employee_code' => '6501', 'action' => 'in', 'photo' => $this->photo()]);
        $a = Attendance::query()->sole();

        $this->actingAs($this->user)->get("/photos/attendance/{$a->id}/in")->assertOk();
        $this->actingAs(User::factory()->create())->get("/photos/attendance/{$a->id}/in")->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->get("/photos/attendance/{$a->id}/in")->assertOk();
    }
}
