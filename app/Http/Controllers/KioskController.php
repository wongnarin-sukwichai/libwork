<?php

namespace App\Http\Controllers;

use App\Models\KioskDevice;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\User;
use App\Support\AttendanceService;
use App\Support\PhotoStore;
use App\Support\ScheduleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * จอลงเวลากลาง (kiosk C3) · หน้าเว็บไม่ต้อง login แต่ API ต้องใช้ token ของเครื่องที่ลงทะเบียนแล้ว
 */
class KioskController extends Controller
{
    /** รับคิวออฟไลน์ย้อนหลังได้ไม่เกินกี่ชั่วโมง */
    private const OFFLINE_MAX_HOURS = 72;

    public function page(): Response
    {
        return Inertia::render('Kiosk/Index', [
            'enabled' => (bool) Setting::get('checkin.kiosk'),
            'authUrl' => Setting::get('kiosk.auth_url'),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $device = KioskDevice::query()
            ->where('setup_code', strtoupper(trim($data['code'])))
            ->where('setup_expires_at', '>', now())
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return response()->json(['message' => 'รหัสลงทะเบียนไม่ถูกต้องหรือหมดอายุ'], 422);
        }

        return response()->json(['token' => $device->register(), 'name' => $device->name]);
    }

    public function status(Request $request): JsonResponse
    {
        /** @var KioskDevice $device */
        $device = $request->attributes->get('kiosk');
        $today = CarbonImmutable::today();
        $normal = ScheduleResolver::for('normal', $today);

        $recent = Punch::query()
            ->with('user:id,first_name,last_name,prefix')
            ->where('result', 'ok')
            ->whereDate('punched_at', $today)
            ->latest('punched_at')
            ->limit(5)
            ->get()
            ->map(fn (Punch $p) => [
                'name' => $p->user?->first_name.' '.$p->user?->last_name,
                'initials' => mb_substr($p->user?->first_name ?? '?', 0, 2),
                'text' => ['in' => 'เข้า', 'out' => 'ออก', 'ot_in' => 'เข้า OT', 'ot_out' => 'ออก OT'][$p->action].' '.$p->punched_at->format('H:i'),
            ]);

        return response()->json([
            'device' => $device->name,
            'server_time' => now()->toIso8601String(),
            'enabled' => (bool) Setting::get('checkin.kiosk'),
            'today' => $normal ? "เวลาปกติ {$normal->hm('start_time')}–{$normal->hm('end_time')} น." : 'วันหยุด · ลงเวลาได้เฉพาะ OT',
            'recent' => $recent,
        ]);
    }

    public function punch(Request $request): JsonResponse
    {
        /** @var KioskDevice $device */
        $device = $request->attributes->get('kiosk');
        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:20'],
            'action' => ['required', Rule::in(Punch::ACTIONS)],
            'photo' => ['nullable', 'string'],
            'client_uuid' => ['nullable', 'uuid'],
            'offline' => ['boolean'],
            'punched_at' => ['nullable', 'date'],
        ]);

        // ส่งคิวซ้ำ: ตอบผลเดิม
        if (! empty($data['client_uuid']) && $existing = Punch::query()->where('client_uuid', $data['client_uuid'])->first()) {
            return response()->json(['ok' => $existing->result === 'ok', 'message' => $existing->message, 'duplicate' => true]);
        }

        if (! Setting::get('checkin.kiosk')) {
            return response()->json(['ok' => false, 'message' => 'ปิดการลงเวลาที่จอกลางชั่วคราว'], 423);
        }

        $offline = (bool) ($data['offline'] ?? false);
        $now = CarbonImmutable::now();
        $at = $now;
        if ($offline && ! empty($data['punched_at'])) {
            $client = CarbonImmutable::parse($data['punched_at'])->setTimezone(config('app.timezone'));
            if ($client->greaterThan($now->addMinutes(2)) || $client->lessThan($now->subHours(self::OFFLINE_MAX_HOURS))) {
                return $this->log($device, $request, $data, null, $now, $offline, null, false, 'เวลาในคิวออฟไลน์ไม่ถูกต้อง');
            }
            $at = $client;
        }

        $user = User::query()->where('employee_code', trim($data['employee_code']))->where('is_active', true)->first();
        if (! $user || ($user->end_date && $user->end_date->endOfDay()->lessThan($at))) {
            return $this->log($device, $request, $data, null, $at, $offline, null, false, 'ไม่พบรหัสบุคลากรนี้');
        }

        $photo = PhotoStore::saveBase64($data['photo'] ?? null, $at->toDateString());
        $result = AttendanceService::punch($user, $data['action'], $at, 'kiosk', $photo, $offline);

        return $this->log($device, $request, $data, $user, $at, $offline, $photo, $result['ok'], $result['message'], $result['detail'], $result['attendance']?->id);
    }

    private function log(KioskDevice $device, Request $request, array $data, ?User $user, CarbonImmutable $at, bool $offline, ?string $photo, bool $ok, string $message, ?string $detail = null, ?int $attendanceId = null): JsonResponse
    {
        Punch::query()->create([
            'client_uuid' => $data['client_uuid'] ?? null,
            'user_id' => $user?->id,
            'employee_code' => $data['employee_code'],
            'kiosk_device_id' => $device->id,
            'action' => $data['action'],
            'punched_at' => $at,
            'received_at' => now(),
            'offline' => $offline,
            'photo' => $photo,
            'ip' => $request->ip(),
            'result' => $ok ? 'ok' : 'rejected',
            'message' => $message,
            'attendance_id' => $attendanceId,
        ]);

        return response()->json([
            'ok' => $ok,
            'name' => $user?->full_name,
            'message' => $message,
            'detail' => $detail,
        ], $ok ? 200 : 422);
    }
}
