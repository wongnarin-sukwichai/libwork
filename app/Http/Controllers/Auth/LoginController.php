<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/Login', [
            'googleEnabled' => (bool) Setting::get('login.google') && filled(config('services.google.client_id')),
            'passwordEnabled' => (bool) Setting::get('login.password') || app()->isLocal(),
        ]);
    }

    /** login ด้วยรหัสผ่านสำรอง (เปิดจากหน้าตั้งค่า หรือใช้ได้เสมอบนเครื่องพัฒนา) */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(Setting::get('login.password') || app()->isLocal(), 404);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.strtolower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'พยายามเข้าสู่ระบบบ่อยเกินไป กรุณารอสักครู่']);
        }

        $ok = Auth::attempt(['email' => strtolower($data['email']), 'password' => $data['password'], 'is_active' => true], true);
        if (! $ok) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        /** @var User $user */
        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended($user->homePath());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
