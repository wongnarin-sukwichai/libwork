<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\LeaveAdjustment;
use App\Models\LeaveBalance;
use App\Models\LeaveInquiry;
use App\Models\LeaveType;
use App\Support\FiscalYear;
use App\Support\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ตรวจยอดยกมาปีงบ: ระบบคำนวณร่าง → HR ตรวจ/แก้ (ต้องมีเหตุผล) → ยืนยัน → member เห็นยอดใหม่
 * ใช้เป็นที่กรอกยอดเริ่มต้นตอนเปิดระบบด้วย
 */
class CarryOverController extends Controller
{
    public function index(Request $request): Response
    {
        $fy = $request->integer('fy') ?: FiscalYear::current();
        $carryTypes = LeaveType::query()->where('carry_over', true)->pluck('id');

        $balances = LeaveBalance::query()
            ->with(['user:id,prefix,first_name,last_name,start_date,employment_type_id', 'user.employmentType:id,name', 'leaveType:id,name', 'adjustments.creator:id,prefix,first_name,last_name'])
            ->where('fiscal_year', $fy)
            ->whereIn('leave_type_id', $carryTypes)
            ->get()
            ->sortBy(fn ($b) => $b->user?->first_name)
            ->values();

        $usedPrev = fn ($b) => (float) (LeaveService::used($b->user_id, $fy - 1)->get($b->leave_type_id)?->total ?? 0);

        return Inertia::render('Staff/CarryOver', [
            'fy' => $fy,
            'status' => $balances->isEmpty() ? 'none' : ($balances->every(fn ($b) => $b->status === 'confirmed') ? 'confirmed' : 'draft'),
            'rows' => $balances->map(function (LeaveBalance $b) use ($fy, $usedPrev) {
                $prev = collect(LeaveService::summary($b->user, $fy - 1))->firstWhere('id', $b->leave_type_id);

                return [
                    'id' => $b->id,
                    'name' => $b->user?->full_name,
                    'employment_type' => $b->user?->employmentType?->name,
                    'service_years' => $b->user?->start_date ? (int) $b->user->start_date->diffInYears(FiscalYear::start($fy)) : null,
                    'type' => $b->leaveType?->name,
                    'prev_carried' => $prev['carried_over'] ?? 0,
                    'prev_entitled' => $prev['entitled'] ?? 0,
                    'prev_used' => $usedPrev($b),
                    'prev_remaining' => $prev['remaining'] ?? 0,
                    'entitled' => $b->entitled,
                    'cap' => $b->cap,
                    'system_carried' => $b->system_carried_over,
                    'carried' => $b->carried_over,
                    'reviewed' => $b->reviewed,
                    'status' => $b->status,
                    'adjustments' => $b->adjustments->map(fn (LeaveAdjustment $a) => [
                        'amount' => $a->amount, 'reason' => $a->reason, 'by' => $a->creator?->full_name, 'at' => $a->created_at->format('d/m/Y'),
                    ]),
                ];
            }),
            'inquiries' => LeaveInquiry::query()->with('user:id,prefix,first_name,last_name')->where('fiscal_year', $fy)->latest()->get()
                ->map(fn ($i) => ['id' => $i->id, 'name' => $i->user?->full_name, 'message' => $i->message, 'reply' => $i->reply, 'at' => $i->created_at->format('d/m/Y H:i')]),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $fy = (int) $request->validate(['fy' => ['required', 'integer', 'min:2560', 'max:2700']])['fy'];
        $count = LeaveService::draftYear($fy);

        return back()->with('success', "คำนวณร่างยอดปีงบ {$fy} แล้ว {$count} รายการ (ไม่ทับค่าที่แก้หรือยืนยันแล้ว)");
    }

    public function update(Request $request, LeaveBalance $balance): RedirectResponse
    {
        abort_if($balance->status === 'confirmed', 422, 'ยอดนี้ยืนยันแล้ว ต้องให้ admin ปลดล็อกก่อน');
        $data = $request->validate([
            'carried' => ['required', 'numeric', 'min:0', 'max:365'],
            'reason' => ['required_unless:carried,'.$balance->carried_over, 'nullable', 'string', 'max:255'],
            'reviewed' => ['boolean'],
        ], ['reason.required_unless' => 'กรุณาระบุเหตุผลเมื่อแก้ยอด']);

        LeaveService::adjustCarried($balance, round((float) $data['carried'], 1), (string) ($data['reason'] ?? ''), $request->user());
        $balance->update(['reviewed' => (bool) ($data['reviewed'] ?? true)]);

        return back()->with('success', 'บันทึกแล้ว');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $fy = (int) $request->validate(['fy' => ['required', 'integer']])['fy'];
        $query = LeaveBalance::query()->where('fiscal_year', $fy)->where('status', 'draft');
        if ((clone $query)->where('reviewed', false)->whereIn('leave_type_id', LeaveType::query()->where('carry_over', true)->pluck('id'))->exists()) {
            return back()->with('error', 'ยังมีรายการที่ยังไม่ได้ตรวจ');
        }
        $query->update(['status' => 'confirmed', 'confirmed_at' => now(), 'confirmed_by' => $request->user()->id]);

        return back()->with('success', "ยืนยันยอดยกมาปีงบ {$fy} แล้ว · บุคลากรเห็นยอดใหม่แล้ว");
    }

    public function unlock(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['fy' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:255']]);
        LeaveBalance::query()->where('fiscal_year', $data['fy'])->update(['status' => 'draft']);
        \Illuminate\Support\Facades\Log::info('ปลดล็อกยอดยกมา', ['fy' => $data['fy'], 'by' => $request->user()->id, 'reason' => $data['reason']]);

        return back()->with('success', "ปลดล็อกยอดปีงบ {$data['fy']} แล้ว");
    }

    public function reply(Request $request, LeaveInquiry $inquiry): RedirectResponse
    {
        $data = $request->validate(['reply' => ['required', 'string', 'max:1000']]);
        $inquiry->update(['reply' => $data['reply'], 'replied_by' => $request->user()->id, 'replied_at' => now()]);

        return back()->with('success', 'ตอบกลับแล้ว');
    }
}
