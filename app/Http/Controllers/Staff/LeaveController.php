<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\LeaveRecord;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\AttendanceService;
use App\Support\FiscalYear;
use App\Support\LeaveService;
use App\Support\MonthClose;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** บันทึกวันลา (HR บันทึกโดยตรง ไม่มีขั้นตอนอนุมัติ) */
class LeaveController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $request->integer('user') ?: null;
        $user = $userId ? User::query()->find($userId) : null;

        return Inertia::render('Staff/Leaves', [
            'people' => $this->people(),
            'types' => LeaveType::query()->where('is_active', true)->ordered()->get(['id', 'name', 'category', 'counts_quota', 'allow_half_day']),
            'selected' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'summary' => LeaveService::summary($user, FiscalYear::current()),
            ] : null,
            'fiscalYear' => FiscalYear::current(),
            'recent' => LeaveRecord::query()->with(['user:id,prefix,first_name,last_name', 'leaveType:id,name,category', 'creator:id,first_name,last_name,prefix'])
                ->when($user, fn ($q) => $q->where('user_id', $user->id))
                ->latest()->limit(15)->get()->map(fn (LeaveRecord $r) => $this->present($r)),
        ]);
    }

    /** คำนวณจำนวนวันทำการระหว่างกรอกฟอร์ม */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate(['start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date'], 'half_day' => ['nullable', Rule::in(['am', 'pm'])]]);

        return response()->json(['days' => LeaveService::countDays($data['start_date'], $data['end_date'], $data['half_day'] ?? null)]);
    }

    /** ข้อมูลสำหรับ modal บันทึกลาในหน้ารายวัน: รายชื่อ, ประเภทลา, ยอดคงเหลือของคนที่เลือก */
    public function options(Request $request): JsonResponse
    {
        $user = ($id = $request->integer('user')) ? User::query()->find($id) : null;

        return response()->json([
            'people' => $request->boolean('people') ? $this->people() : null,
            'types' => LeaveType::query()->where('is_active', true)->ordered()->get(['id', 'name', 'category', 'counts_quota', 'allow_half_day']),
            'summary' => $user ? LeaveService::summary($user, FiscalYear::of($request->date('date') ?? now())) : null,
        ]);
    }

    private function people(): array
    {
        return User::query()->where('is_active', true)->with('department:id,name')->orderBy('first_name')
            ->get(['id', 'prefix', 'first_name', 'last_name', 'employee_code', 'department_id'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->full_name, 'code' => $u->employee_code, 'department' => $u->department?->name, 'active' => true])
            ->all();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('leave-attachments', 'local');
        }
        $record = LeaveRecord::query()->create($data);
        AttendanceService::recalculateRange($record->user_id, $record->start_date->toDateString(), $record->end_date->toDateString());

        // บันทึกจากหน้ารายวัน (modal) กลับหน้าเดิม
        return ($request->boolean('stay') ? back() : redirect('/staff/leaves?user='.$record->user_id))
            ->with('success', 'บันทึก'.$record->leaveType->name.' ของ '.$record->user->full_name.' แล้ว');
    }

    public function update(Request $request, LeaveRecord $leave): RedirectResponse
    {
        $data = $this->validated($request, $leave);
        $data['updated_by'] = $request->user()->id;
        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('leave-attachments', 'local');
        }
        $old = [$leave->user_id, $leave->start_date->toDateString(), $leave->end_date->toDateString()];
        $leave->update($data);
        AttendanceService::recalculateRange(...$old);
        AttendanceService::recalculateRange($leave->user_id, $leave->start_date->toDateString(), $leave->end_date->toDateString());

        return back()->with('success', 'แก้ไขวันลาแล้ว');
    }

    public function destroy(Request $request, LeaveRecord $leave): RedirectResponse
    {
        if (MonthClose::isClosed($leave->start_date->toDateString()) || MonthClose::isClosed($leave->end_date->toDateString())) {
            return back()->with('error', 'งวดของวันลานี้ปิดแล้ว ต้องให้ admin ปลดล็อกก่อน');
        }
        $leave->update(['deleted_by' => $request->user()->id]);
        $leave->delete();
        AttendanceService::recalculateRange($leave->user_id, $leave->start_date->toDateString(), $leave->end_date->toDateString());

        return back()->with('success', 'ลบรายการวันลาแล้ว');
    }

    public function attachment(Request $request, LeaveRecord $leave): StreamedResponse
    {
        abort_unless($request->user()->isStaff() || $leave->user_id === $request->user()->id, 403);
        abort_unless($leave->attachment && Storage::disk('local')->exists($leave->attachment), 404);

        return Storage::disk('local')->response($leave->attachment);
    }

    private function validated(Request $request, ?LeaveRecord $existing = null): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'half_day' => ['nullable', Rule::in(['am', 'pm'])],
            'reason' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'allow_over' => ['boolean'],
        ], ['end_date.after_or_equal' => 'วันสิ้นสุดต้องไม่ก่อนวันเริ่ม']);

        MonthClose::assertOpen($data['start_date'], 'start_date');
        MonthClose::assertOpen($data['end_date'], 'end_date');
        if ($existing) {
            MonthClose::assertOpen($existing->start_date->toDateString(), 'start_date');
        }

        $type = LeaveType::query()->findOrFail($data['leave_type_id']);
        if ($data['half_day'] ?? null) {
            if ($data['start_date'] !== $data['end_date']) {
                throw ValidationException::withMessages(['half_day' => 'ลาครึ่งวันได้เฉพาะวันเดียว']);
            }
            if (! $type->allow_half_day) {
                throw ValidationException::withMessages(['half_day' => "{$type->name} ลาครึ่งวันไม่ได้"]);
            }
        }

        $fy = FiscalYear::of($data['start_date']);
        if (FiscalYear::of($data['end_date']) !== $fy) {
            throw ValidationException::withMessages(['end_date' => 'ช่วงลาคร่อมปีงบประมาณ กรุณาแยกบันทึกเป็น 2 รายการ']);
        }

        $days = LeaveService::countDays($data['start_date'], $data['end_date'], $data['half_day'] ?? null);
        if ($days <= 0) {
            throw ValidationException::withMessages(['start_date' => 'ช่วงวันที่เลือกไม่มีวันทำการ']);
        }

        $overlap = LeaveRecord::query()->where('user_id', $data['user_id'])
            ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->get()
            ->reject(fn ($r) => ($data['half_day'] ?? null) && $r->half_day && $r->half_day !== $data['half_day']);
        if ($overlap->isNotEmpty()) {
            throw ValidationException::withMessages(['start_date' => 'มีรายการลาในช่วงวันนี้อยู่แล้ว']);
        }

        if ($type->counts_quota && ! ($data['allow_over'] ?? false)) {
            $user = User::query()->findOrFail($data['user_id']);
            $row = collect(LeaveService::summary($user, $fy))->firstWhere('id', $type->id);
            $remaining = ($row['remaining'] ?? 0) + ($existing && $existing->leave_type_id === $type->id ? $existing->days : 0);
            if ($row['counts_quota'] && $days > $remaining) {
                throw ValidationException::withMessages(['allow_over' => "{$type->name} คงเหลือ {$remaining} วัน แต่บันทึก {$days} วัน · ถ้าต้องการบันทึกเกินสิทธิ์ ให้ติ๊กยืนยัน"]);
            }
        }

        unset($data['allow_over'], $data['attachment']);

        return $data + ['days' => $days, 'fiscal_year' => $fy, 'half_day' => $data['half_day'] ?? null];
    }

    private function present(LeaveRecord $r): array
    {
        return [
            'id' => $r->id,
            'user_id' => $r->user_id,
            'name' => $r->user?->full_name,
            'leave_type_id' => $r->leave_type_id,
            'type' => $r->leaveType?->name,
            'category' => $r->leaveType?->category,
            'start_date' => $r->start_date->toDateString(),
            'end_date' => $r->end_date->toDateString(),
            'half_day' => $r->half_day,
            'half_label' => $r->halfLabel(),
            'days' => $r->days,
            'reason' => $r->reason,
            'has_attachment' => (bool) $r->attachment,
            'by' => $r->creator?->full_name,
        ];
    }
}
