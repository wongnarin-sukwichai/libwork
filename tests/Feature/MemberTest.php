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

    public function test_staff_adds_member_with_initial_department_history(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post('/staff/members', [
            'first_name' => 'ภาคิน', 'last_name' => 'ทดลอง', 'email' => 'Pakin@Test.th',
            'role' => 'admin', 'department_id' => $this->a->id, 'start_date' => '2026-11-03',
            'end_date' => '2027-02-27', 'ot_eligible' => false, 'mobile_checkin' => false,
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'pakin@test.th')->firstOrFail();
        $this->assertSame('member', $user->role, 'staff ตั้งสิทธิ์ admin ให้คนอื่นไม่ได้');
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

    public function test_staff_cannot_edit_another_staff(): void
    {
        $staff = User::factory()->staff()->create();
        $other = User::factory()->staff()->create();

        $this->actingAs($staff)->put("/staff/members/{$other->id}", [
            'first_name' => 'x', 'last_name' => 'y', 'role' => 'staff',
        ])->assertForbidden();
    }

    public function test_move_keeps_old_department_for_dates_before_effective_date(): void
    {
        $staff = User::factory()->staff()->create();
        $user = User::factory()->create(['department_id' => $this->a->id]);
        $user->departmentAssignments()->create(['department_id' => $this->a->id, 'effective_from' => '2025-01-01']);

        $this->actingAs($staff)->post('/staff/members/move', [
            'user_ids' => [$user->id], 'department_id' => $this->b->id, 'effective_from' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame($this->b->id, $user->department_id);
        $this->assertSame($this->a->id, $user->departmentIdOn(now()->subDay()));
        $this->assertSame($this->b->id, $user->departmentIdOn(now()));
    }

    public function test_future_move_does_not_change_current_department_yet(): void
    {
        $staff = User::factory()->staff()->create();
        $user = User::factory()->create(['department_id' => $this->a->id]);
        $user->departmentAssignments()->create(['department_id' => $this->a->id, 'effective_from' => '2025-01-01']);

        $this->actingAs($staff)->post('/staff/members/move', [
            'user_ids' => [$user->id], 'department_id' => $this->b->id, 'effective_from' => now()->addWeek()->toDateString(),
        ]);

        $this->assertSame($this->a->id, $user->fresh()->department_id);
    }

    public function test_members_list_filters_by_search(): void
    {
        $staff = User::factory()->staff()->create();
        User::factory()->create(['first_name' => 'สมชาย', 'employee_code' => '6501']);
        User::factory()->create(['first_name' => 'อรุณี']);

        $this->actingAs($staff)->get('/staff/members?q=6501')->assertInertia(fn ($p) => $p
            ->component('Staff/Members/Index')
            ->has('members.data', 1)
            ->where('members.data.0.first_name', 'สมชาย'));
    }
}
