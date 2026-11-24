<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ทางลัดเข้าระบบสำหรับทดสอบบนเครื่องพัฒนาเท่านั้น (APP_ENV=local)
 * route ลงทะเบียนเฉพาะ local และ abort ซ้ำอีกชั้น — ลบไฟล์นี้ + route + ปุ่มใน PublicLayout ก่อนขึ้น host
 */
class DevLoginController extends Controller
{
    public function __invoke(Request $request, string $role): RedirectResponse
    {
        abort_unless(app()->isLocal(), 404);

        $user = User::query()->where('role', $role)->where('is_active', true)->orderBy('id')->first()
            ?? User::query()->create([
                'first_name' => $role === 'admin' ? 'ผู้ดูแล' : 'เจ้าหน้าที่',
                'last_name' => 'ทดสอบ',
                'email' => "{$role}@libwork.test",
                'role' => $role,
                'password' => 'password',
                'is_active' => true,
            ]);

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect($user->homePath())->with('success', "ทางลัดทดสอบ: เข้าสู่ระบบเป็น {$user->full_name} ({$role})");
    }
}
