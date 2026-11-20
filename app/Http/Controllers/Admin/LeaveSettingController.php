<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** ตั้งค่า: วันหยุด · ประเภทลา · นโยบายการลาตามประเภทบุคลากร */
class LeaveSettingController extends Controller
{
    public function storeHoliday(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'unique:holidays,date'],
            'name' => ['required', 'string', 'max:191'],
        ], ['date.unique' => 'วันนี้เป็นวันหยุดอยู่แล้ว']);
        Holiday::query()->create($data);

        return back()->with('success', "เพิ่มวันหยุด {$data['name']} แล้ว");
    }

    public function destroyHoliday(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return back()->with('success', 'ลบวันหยุดแล้ว');
    }

    public function storeType(Request $request): RedirectResponse
    {
        $data = $this->validatedType($request);
        $data['code'] = 'type_'.(LeaveType::query()->max('id') + 1);
        $data['sort_order'] = (int) LeaveType::query()->max('sort_order') + 1;
        LeaveType::query()->create($data);

        return back()->with('success', "เพิ่มประเภทลา {$data['name']} แล้ว");
    }

    public function updateType(Request $request, LeaveType $type): RedirectResponse
    {
        $type->update($this->validatedType($request, $type));

        return back()->with('success', "บันทึก {$type->name} แล้ว");
    }

    /** บันทึกตารางนโยบายทั้งหมดในครั้งเดียว */
    public function savePolicies(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'policies' => ['array'],
            'policies.*.employment_type_id' => ['required', 'exists:employment_types,id'],
            'policies.*.leave_type_id' => ['required', 'exists:leave_types,id'],
            'policies.*.days_per_year' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'policies.*.min_service_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'policies.*.max_total_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'policies.*.max_total_days_10y' => ['nullable', 'numeric', 'min:0', 'max:365'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['policies'] ?? [] as $p) {
                $key = ['employment_type_id' => $p['employment_type_id'], 'leave_type_id' => $p['leave_type_id']];
                if ($p['days_per_year'] === null || $p['days_per_year'] === '') {
                    LeavePolicy::query()->where($key)->delete();

                    continue;
                }
                LeavePolicy::query()->updateOrCreate($key, [
                    'days_per_year' => $p['days_per_year'],
                    'min_service_months' => $p['min_service_months'] ?? 0,
                    'max_total_days' => $p['max_total_days'] ?? null,
                    'max_total_days_10y' => $p['max_total_days_10y'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'บันทึกนโยบายการลาแล้ว · ยอดของปีงบที่ยังเป็นร่าง คำนวณใหม่ได้ที่หน้ายอดยกมา');
    }

    private function validatedType(Request $request, ?LeaveType $type = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('leave_types', 'name')->ignore($type)],
            'category' => ['required', Rule::in(['leave', 'duty'])],
            'counts_quota' => ['boolean'],
            'carry_over' => ['boolean'],
            'allow_half_day' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }
}
