<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KioskDevice;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** เครื่องจอกลาง: เพิ่มเครื่องแล้วได้รหัสลงทะเบียน (ใช้ครั้งเดียว ภายใน 24 ชม.) */
class KioskDeviceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $device = KioskDevice::query()->create($data + ['created_by' => $request->user()->id]);
        $code = $device->issueSetupCode();

        return back()->with('success', "เพิ่มเครื่อง {$device->name} แล้ว · รหัสลงทะเบียน {$code}");
    }

    /** ออกรหัสใหม่ (เช่น เปลี่ยนเครื่อง/ล้างเบราว์เซอร์) — token เดิมใช้ไม่ได้ทันที */
    public function reissue(KioskDevice $device): RedirectResponse
    {
        $device->forceFill(['token_hash' => null, 'registered_at' => null, 'is_active' => true])->save();
        $code = $device->issueSetupCode();

        return back()->with('success', "รหัสลงทะเบียนใหม่ของ {$device->name}: {$code}");
    }

    public function toggle(KioskDevice $device): RedirectResponse
    {
        $device->update(['is_active' => ! $device->is_active]);

        return back()->with('success', ($device->is_active ? 'เปิดใช้ ' : 'ยกเลิก ').$device->name.' แล้ว');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kiosk_enabled' => ['boolean'],
            'auth_url' => ['nullable', 'url', 'max:255'],
        ]);
        Setting::put('checkin.kiosk', (bool) ($data['kiosk_enabled'] ?? true));
        Setting::put('kiosk.auth_url', $data['auth_url'] ?? null);

        return back()->with('success', 'บันทึกการตั้งค่าจอกลางแล้ว');
    }
}
