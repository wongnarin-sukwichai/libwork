<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_renames_and_reorders_departments(): void
    {
        $admin = User::factory()->admin()->create();
        $a = Department::query()->create(['name' => 'เดิม', 'sort_order' => 1]);
        $b = Department::query()->create(['name' => 'สอง', 'sort_order' => 2]);

        $this->actingAs($admin)->put("/admin/settings/departments/{$a->id}", ['name' => 'ชื่อใหม่'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/settings/departments/reorder', ['ids' => [$b->id, $a->id]]);

        $this->assertSame('ชื่อใหม่', $a->fresh()->name);
        $this->assertSame([$b->id, $a->id], Department::query()->ordered()->pluck('id')->all());
    }

    public function test_department_with_active_members_cannot_be_deactivated(): void
    {
        $admin = User::factory()->admin()->create();
        $dept = Department::query()->create(['name' => 'มีคน']);
        User::factory()->create(['department_id' => $dept->id]);

        $this->actingAs($admin)->put("/admin/settings/departments/{$dept->id}", ['name' => 'มีคน', 'is_active' => false])
            ->assertSessionHasErrors('is_active');
        $this->assertTrue($dept->fresh()->is_active);
    }

    public function test_at_least_one_login_method_must_stay_on(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/settings/general', [
            'org_name' => 'หน่วยงาน', 'login_google' => false, 'login_password' => false, 'public_show_leave_type' => false,
        ])->assertSessionHasErrors('login_google');

        $this->actingAs($admin)->put('/admin/settings/general', [
            'org_name' => 'หน่วยงานทดสอบ', 'login_google' => true, 'login_password' => true, 'public_show_leave_type' => false,
        ])->assertSessionHasNoErrors();
        $this->assertSame('หน่วยงานทดสอบ', Setting::get('org_name'));
        $this->assertTrue(Setting::get('login.password'));
    }
}
