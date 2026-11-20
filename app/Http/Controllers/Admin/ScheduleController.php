<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SpecialDay;
use App\Support\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** รูปแบบเวลา และวันที่ใช้รูปแบบพิเศษ (เช่น ล่วงเวลาพิเศษถึง 22:00) */
class ScheduleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort_order'] = (int) Schedule::query()->max('sort_order') + 1;
        Schedule::query()->create($data);

        return back()->with('success', "เพิ่มรูปแบบ {$data['name']} แล้ว");
    }

    public function update(Request $request, Schedule $schedule): RedirectResponse
    {
        $schedule->update($this->validated($request));

        // คำนวณชั่วโมงใหม่ให้รายการของวันนี้และอนาคตที่ใช้รูปแบบนี้ (ข้อมูลย้อนหลังไม่เปลี่ยน)
        Attendance::query()->where('schedule_id', $schedule->id)->whereDate('work_date', '>=', today())->get()
            ->each(function (Attendance $a) {
                AttendanceService::recalculate($a);
                $a->save();
            });

        return back()->with('success', "บันทึกรูปแบบ {$schedule->name} แล้ว");
    }

    public function storeSpecialDay(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'unique:special_days,date'],
            'schedule_id' => ['required', Rule::exists('schedules', 'id')->where('type', 'ot')],
            'note' => ['nullable', 'string', 'max:191'],
        ], ['date.unique' => 'วันนี้กำหนดรูปแบบพิเศษไว้แล้ว']);
        $data['created_by'] = $request->user()->id;
        SpecialDay::query()->create($data);

        return back()->with('success', 'กำหนดวันล่วงเวลาพิเศษแล้ว');
    }

    public function destroySpecialDay(SpecialDay $specialDay): RedirectResponse
    {
        $specialDay->delete();

        return back()->with('success', 'ยกเลิกวันล่วงเวลาพิเศษแล้ว');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'type' => ['required', Rule::in(Schedule::TYPES)],
            'applies_to' => ['required', Rule::in(Schedule::APPLIES)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'count_from' => ['required', 'date_format:H:i'],
            'break_start' => ['nullable', 'date_format:H:i', 'required_with:break_end'],
            'break_end' => ['nullable', 'date_format:H:i', 'after:break_start', 'required_with:break_start'],
            'special_from' => ['nullable', 'date_format:H:i'],
            'rounding' => ['required', Rule::in(Schedule::ROUNDING)],
            'daily_cap_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'is_active' => ['boolean'],
        ], ['end_time.after' => 'เวลาสิ้นสุดต้องหลังเวลาเริ่ม']);

        if ($data['type'] === 'normal') {
            $data['special_from'] = null;
        }

        return $data;
    }
}
