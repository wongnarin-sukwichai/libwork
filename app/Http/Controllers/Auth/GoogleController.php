<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Login ด้วย Google: ต้องเป็นอีเมลที่ admin เพิ่มไว้แล้วเท่านั้น (ไม่สมัครเองอัตโนมัติ)
 */
class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect|RedirectResponse
    {
        if (! Setting::get('login.google') || blank(config('services.google.client_id'))) {
            return redirect('/login')->with('error', 'ยังไม่ได้เปิดใช้การเข้าสู่ระบบด้วย Google');
        }

        $driver = Socialite::driver('google')->redirectUrl(url('/auth/google/callback'));
        if ($domain = config('services.google.allowed_domain')) {
            $driver->with(['hd' => $domain]);
        }

        return $driver->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->redirectUrl(url('/auth/google/callback'))->user();
        } catch (Throwable $e) {
            Log::warning('Google login failed', ['error' => $e->getMessage()]);

            return redirect('/login')->with('error', 'เข้าสู่ระบบด้วย Google ไม่สำเร็จ กรุณาลองใหม่');
        }

        $email = strtolower((string) $google->getEmail());
        $domain = config('services.google.allowed_domain');
        if ($domain && ! str_ends_with($email, '@'.strtolower($domain))) {
            return redirect('/login')->with('error', "ใช้ได้เฉพาะอีเมล @{$domain}");
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            return redirect('/login')->with('error', "อีเมล {$email} ยังไม่มีสิทธิ์ใช้งาน กรุณาติดต่อผู้ดูแลระบบ");
        }
        if (! $user->is_active) {
            return redirect('/login')->with('error', 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
        }

        $user->forceFill([
            'google_id' => $google->getId(),
            'avatar' => $google->getAvatar(),
            'last_login_at' => now(),
        ])->save();

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended($user->homePath());
    }
}
