<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** บัญชีที่ถูกระงับหรือพ้นวันสิ้นสุด (เช่น นิสิตฝึกงาน) จะถูกออกจากระบบทันที */
class SignOutInactive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->is_active || ($user->end_date && $user->end_date->endOfDay()->isPast()))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->with('error', 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
        }

        return $next($request);
    }
}
