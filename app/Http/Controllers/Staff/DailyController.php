<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\User;
use App\Support\AttendanceService;
use App\Support\DailyReport;
use App\Support\MonthClose;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** หน้าแรก HR: รายงานประจำวัน + แก้ไข/เพิ่มเวลา (เก็บประวัติ) */
class DailyController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'type' => ['nullable', Rule::in(['normal', 'ot'])],
        ]);

        $date = $data['date'] ?? now()->toDateString();
        $month = substr($date, 0, 7);

        return Inertia::render('Staff/Dashboard', [
            'report' => DailyReport::build($date, $data['type'] ?? 'normal'),
            'monthClose' => fn () => [
                'month' => $month,
                'title' => thai_month($date),
                'closed' => MonthClose::isClosed($date),
                'pending' => collect(MonthClose::anomalies($month))->where('blocking', true)->where('resolved', false)->count(),
            ],
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'type' => ['required', Rule::in(['normal', 'ot'])],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],
            'note' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'กรุณาระบุเหตุผลการแก้ไข',
            'check_out.after' => 'เวลาออกต้องหลังเวลาเข้า',
        ]);

        $subject = User::query()->findOrFail($data['user_id']);
        AttendanceService::manualSave($subject, $data['date'], $data['type'], $data['check_in'] ?? null, $data['check_out'] ?? null, $data['note'] ?? null, $request->user(), $data['reason']);

        return back()->with('success', "บันทึกเวลาของ {$subject->full_name} แล้ว");
    }

    public function logs(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer'], 'date' => ['required', 'date']]);

        return response()->json(AttendanceLog::query()
            ->with('actor:id,first_name,last_name,prefix')
            ->where('subject_user_id', $data['user_id'])
            ->whereDate('work_date', $data['date'])
            ->latest()
            ->get()
            ->map(fn (AttendanceLog $l) => [
                'id' => $l->id,
                'at' => $l->created_at->format('d/m/Y H:i'),
                'by' => $l->actor?->full_name,
                'action' => $l->action,
                'before' => $l->before,
                'after' => $l->after,
                'reason' => $l->reason,
            ]));
    }
}
