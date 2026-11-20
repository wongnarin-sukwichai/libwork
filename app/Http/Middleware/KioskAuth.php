<?php

namespace App\Http\Middleware;

use App\Models\KioskDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** API ของจอ kiosk: ต้องส่ง header X-Kiosk-Token ของเครื่องที่ลงทะเบียนแล้ว */
class KioskAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $device = KioskDevice::findByToken($request->header('X-Kiosk-Token'));
        if (! $device) {
            return response()->json(['message' => 'เครื่องนี้ยังไม่ได้ลงทะเบียน หรือถูกยกเลิกแล้ว'], 401);
        }

        $device->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip()])->saveQuietly();
        $request->attributes->set('kiosk', $device);

        return $next($request);
    }
}
