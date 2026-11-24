<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    private Department $a;

    private Department $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Department::query()->create(['name' => 'กลุ่ม A', 'sort_order' => 1]);
        $this->b = Department::query()->create(['name' => 'กลุ่ม B', 'sort_order' => 2]);
    }

    public function test_admin_adds_member_with_initial_department_history(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/staff/members', [
            'first_name' => 'ภาคิน', 'last_name' => 'ทดลอง', 'email' => 'Pakin@Test.th',
            'role' => 'member', 'department_id' => $this->a->id, 'start_date' => '2026-11-03',
            'end_date' => '2027-02-27', 'ot_eligible' => false, 'mobile_checkin' => false,
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'pakin@test.th')->firstOrFail();
        $this->assertSame($this->a->id, $user->department_id);
        $this->assertSame($this->a->id, $user->departmentIdOn('2026-11-03'));
    }

    public function test_only_admin_can_assign_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create(['department_id' => $this->a->id]);

        $this->actingAs($admin)->put("/staff/members/{$member->id}", [
            'first_name' => $member->first_name, 'last_name' => $member->last_name, 'role' => 'staff',
        ])->assertSessionHasNoErrors();

        $this->assertSame('staff', $member->fresh()->role);
    }

    public function test_members_module_is_admin_only(): void
    {
        $staff = User::factory()->staff()->create();
        $other = User::factory()->create();

        $this->actingAs($staff)->get('/staff/members')->assertForbidden();
        $this->actingAs($staff)->post('/staff/members', ['first_name' => 'x', 'last_name' => 'y'])->assertForbidden();
        $this->actingAs($staff)->put("/staff/members/{$other->id}", ['first_name' => 'x', 'last_name' => 'y', 'role' => 'member'])->assertForbidden();
        $this->actingAs($staff)->post('/staff/members/move', ['user_ids' => [$other->id]])->assertForbidden();
    }

    public function test_sidebar_modules_by_role(): void
    {
        $modules = fn ($p) => collect($p->toArray()['props']['auth']['menu'])->pluck('module')->all();

        $this->actingAs(User::factory()->staff()->create())->get('/staff')
            ->assertInertia(fn ($p) => $this->assertSame(['หน้าหลัก'], $modules($p)) ?? $p);
        $this->actingAs(User::factory()->admin()->create())->get('/staff')
            ->assertInertia(fn ($p) => $this->assertSame(['หน้าหลัก', 'ระบบ'], $modules($p)) ?? $p);
    }

    public function test_move_keeps_old_department_for_dates_before_effective_date(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['department_id' => $this->a->id]);
        $user->departmentAssignments()->create(['department_id' => $this->a->id, 'effective_from' => '2025-01-01']);

        $this->actingAs($admin)->post('/staff/members/move', [
            'user_ids' => [$user->id], 'department_id' => $this->b->id, 'effective_from' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame($this->b->id, $user->department_id);
        $this->assertSame($this->a->id, $user->departmentIdOn(now()->subDay()));
        $this->assertSame($this->b->id, $user->departmentIdOn(now()));
    }

    public function test_future_move_does_not_change_current_department_yet(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['department_id' => $this->a->id]);
        $user->departmentAssignments()->create(['department_id' => $this->a->id, 'effective_from' => '2025-01-01']);

        $this->actingAs($admin)->post('/staff/members/move', [
            'user_ids' => [$user->id], 'department_id' => $this->b->id, 'effective_from' => now()->addWeek()->toDateString(),
        ]);

        $this->assertSame($this->a->id, $user->fresh()->department_id);
    }

    public function test_members_list_filters_by_search(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['first_name' => 'สมชาย', 'employee_code' => '6501']);
        User::factory()->create(['first_name' => 'อรุณี']);

        $this->actingAs($admin)->get('/staff/members?q=6501')->assertInertia(fn ($p) => $p
            ->component('Staff/Members/Index')
            ->has('members.data', 1)
            ->where('members.data.0.first_name', 'สมชาย'));
    }
}
