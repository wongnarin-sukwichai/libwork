<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_and_login_pages_render(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn ($p) => $p->component('Home'));
        $this->get('/login')->assertOk()->assertInertia(fn ($p) => $p->component('Home')->where('openLogin', true));
    }

    public function test_password_login_is_closed_unless_enabled(): void
    {
        User::factory()->create(['email' => 'a@test.th', 'password' => 'secret']);

        $this->post('/login', ['email' => 'a@test.th', 'password' => 'secret'])->assertNotFound();
        $this->assertGuest();
    }

    public function test_password_login_sends_each_role_to_its_home(): void
    {
        Setting::put('login.password', true);
        User::factory()->create(['email' => 'member@test.th', 'password' => 'secret']);
        User::factory()->staff()->create(['email' => 'hr@test.th', 'password' => 'secret']);

        $this->post('/login', ['email' => 'MEMBER@test.th', 'password' => 'secret'])->assertRedirect('/me');
        $this->post('/logout');
        $this->post('/login', ['email' => 'hr@test.th', 'password' => 'secret'])->assertRedirect('/staff');
    }

    public function test_suspended_user_cannot_login_and_is_signed_out(): void
    {
        Setting::put('login.password', true);
        $user = User::factory()->create(['email' => 'off@test.th', 'password' => 'secret', 'is_active' => false]);

        $this->post('/login', ['email' => 'off@test.th', 'password' => 'secret'])->assertSessionHasErrors('email');

        $this->actingAs($user)->get('/me')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_intern_past_end_date_is_signed_out(): void
    {
        $intern = User::factory()->create(['end_date' => now()->subDay()->toDateString()]);

        $this->actingAs($intern)->get('/me')->assertRedirect('/login');
    }

    public function test_roles_guard_staff_and_admin_areas(): void
    {
        $member = User::factory()->create();
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($member)->get('/staff')->assertForbidden();
        $this->actingAs($staff)->get('/staff')->assertOk();
        $this->actingAs($staff)->get('/admin/settings')->assertForbidden();
        $this->actingAs($admin)->get('/admin/settings')->assertOk();
    }

    public function test_menu_hides_settings_from_staff(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/staff')->assertInertia(fn ($p) => $p
            ->where('auth.menu', fn ($menu) => collect($menu)->doesntContain('href', '/admin/settings')));
    }
}
