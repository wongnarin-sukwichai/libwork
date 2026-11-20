<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\Holiday;
use App\Models\KioskDevice;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\SpecialDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * หน้าตั้งค่า (admin): ทั่วไป · โครงสร้างหน่วยงาน · ประเภทบุคลากร
 * แท็บอื่น (รูปแบบเวลา วันหยุด นโยบายการลา เครื่อง kiosk API) เพิ่มในเฟสถัดไป
 */
class SettingController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'tab' => $request->query('tab', 'general'),
            'general' => [
                'org_name' => Setting::get('org_name'),
                'login_google' => (bool) Setting::get('login.google'),
                'login_password' => (bool) Setting::get('login.password'),
                'public_show_leave_type' => (bool) Setting::get('public_calendar.show_leave_type'),
                'head_title' => Setting::get('report.head_title'),
                'head_name' => Setting::get('report.head_name'),
                'google_configured' => filled(config('services.google.client_id')),
            ],
            'departments' => Department::query()->ordered()->withCount(['users' => fn ($q) => $q->where('is_active', true)])->get(),
            'employmentTypes' => EmploymentType::query()->ordered()->withCount('users')->get(),
            'schedules' => Schedule::query()->ordered()->get()->map(fn (Schedule $s) => [
                ...$s->only('id', 'name', 'type', 'applies_to', 'grace_minutes', 'rounding', 'daily_cap_minutes', 'is_active'),
                ...collect(['start_time', 'end_time', 'count_from', 'break_start', 'break_end', 'special_from'])->mapWithKeys(fn ($f) => [$f => $s->hm($f)])->all(),
            ]),
            'specialDays' => SpecialDay::query()->with('schedule:id,name')->whereDate('date', '>=', now()->subDays(30))->orderBy('date')->get()
                ->map(fn (SpecialDay $d) => ['id' => $d->id, 'date' => $d->date->toDateString(), 'schedule' => $d->schedule?->name, 'note' => $d->note]),
            'holidays' => Holiday::query()->whereDate('date', '>=', now()->subYear()->startOfYear())->orderBy('date')->get(['id', 'date', 'name'])
                ->map(fn ($h) => ['id' => $h->id, 'date' => $h->date->toDateString(), 'name' => $h->name]),
            'leaveTypes' => LeaveType::query()->ordered()->get(),
            'policies' => LeavePolicy::query()->get(['employment_type_id', 'leave_type_id', 'days_per_year', 'min_service_months', 'max_total_days', 'max_total_days_10y']),
            'kiosk' => [
                'enabled' => (bool) Setting::get('checkin.kiosk'),
                'auth_url' => Setting::get('kiosk.auth_url'),
                'devices' => KioskDevice::query()->latest()->get()->map(fn (KioskDevice $d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'registered' => (bool) $d->registered_at,
                    'setup_code' => $d->setup_expires_at?->isFuture() ? $d->setup_code : null,
                    'setup_expires_at' => $d->setup_expires_at?->format('d/m/Y H:i'),
                    'last_seen_at' => $d->last_seen_at?->format('d/m/Y H:i'),
                    'last_ip' => $d->last_ip,
                    'is_active' => $d->is_active,
                ]),
            ],
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'org_name' => ['required', 'string', 'max:191'],
            'login_google' => ['boolean'],
            'login_password' => ['boolean'],
            'public_show_leave_type' => ['boolean'],
            'head_title' => ['nullable', 'string', 'max:191'],
            'head_name' => ['nullable', 'string', 'max:191'],
        ]);

        Setting::put('report.head_title', $data['head_title'] ?? Setting::DEFAULTS['report.head_title']);
        Setting::put('report.head_name', $data['head_name'] ?? null);

        if (! $data['login_google'] && ! $data['login_password']) {
            return back()->withErrors(['login_google' => 'ต้องเปิดวิธีเข้าสู่ระบบอย่างน้อยหนึ่งวิธี']);
        }

        Setting::put('org_name', $data['org_name']);
        Setting::put('login.google', $data['login_google']);
        Setting::put('login.password', $data['login_password']);
        Setting::put('public_calendar.show_leave_type', $data['public_show_leave_type']);

        return back()->with('success', 'บันทึกการตั้งค่าแล้ว');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'head_name' => ['nullable', 'string', 'max:191'],
        ]);
        $data['sort_order'] = (int) Department::query()->max('sort_order') + 1;
        Department::query()->create($data);

        return back()->with('success', "เพิ่มกลุ่มงาน {$data['name']} แล้ว");
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'head_name' => ['nullable', 'string', 'max:191'],
            'is_active' => ['boolean'],
        ]);

        if (array_key_exists('is_active', $data) && ! $data['is_active'] && $department->users()->where('is_active', true)->exists()) {
            return back()->withErrors(['is_active' => 'ยังมีบุคลากรอยู่ในกลุ่มงานนี้ กรุณาย้ายออกก่อนปิดใช้งาน']);
        }

        $department->update($data);

        return back()->with('success', 'บันทึกกลุ่มงานแล้ว');
    }

    public function reorderDepartments(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:departments,id']]);

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $i => $id) {
                Department::query()->whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });

        return back()->with('success', 'บันทึกลำดับแล้ว');
    }

    public function storeEmploymentType(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', 'unique:employment_types,name'],
            'has_leave_quota' => ['boolean'],
        ]);
        $data['code'] = 'type_'.(EmploymentType::query()->max('id') + 1);
        $data['sort_order'] = (int) EmploymentType::query()->max('sort_order') + 1;
        EmploymentType::query()->create($data);

        return back()->with('success', "เพิ่มประเภท {$data['name']} แล้ว");
    }

    public function updateEmploymentType(Request $request, EmploymentType $type): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', 'unique:employment_types,name,'.$type->id],
            'has_leave_quota' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
        $type->update($data);

        return back()->with('success', 'บันทึกประเภทบุคลากรแล้ว');
    }
}
