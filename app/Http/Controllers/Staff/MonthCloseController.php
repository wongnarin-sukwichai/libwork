<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Support\MonthClose;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** ตรวจสอบและปิดงวดรายเดือน (หน้า 8) */
class MonthCloseController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? now()->format('Y-m');
        $start = CarbonImmutable::parse("{$month}-01");
        $closure = DB::table('month_closures')->leftJoin('users', 'users.id', '=', 'month_closures.closed_by')
            ->where('month', $month)->first(['month_closures.closed_at', 'users.first_name', 'users.last_name']);

        return Inertia::render('Staff/MonthClose', [
            'month' => $month,
            'title' => thai_month($start),
            'closed' => $closure ? ['at' => thai_date($closure->closed_at, true).' '.substr($closure->closed_at, 11, 5), 'by' => trim($closure->first_name.' '.$closure->last_name)] : null,
            'monthEnded' => $start->endOfMonth()->isPast(),
            'items' => MonthClose::anomalies($month),
            'history' => DB::table('month_closures')->orderByDesc('month')->limit(6)->get(['month', 'closed_at'])
                ->map(fn ($c) => ['month' => $c->month, 'title' => thai_month("{$c->month}-01"), 'at' => thai_date($c->closed_at, true)]),
        ]);
    }

    /** รับทราบรายการ (ไม่ต้องแก้ข้อมูล) พร้อมหมายเหตุ */
    public function acknowledge(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date'],
            'kind' => ['required', Rule::in(MonthClose::BLOCKING)],
            'note' => ['required', 'string', 'max:255'],
        ], ['note.required' => 'กรุณาระบุหมายเหตุ']);
        MonthClose::assertOpen($data['date']);

        DB::table('anomaly_acks')->updateOrInsert(
            ['user_id' => $data['user_id'], 'date' => $data['date'], 'kind' => $data['kind']],
            ['note' => $data['note'], 'acknowledged_by' => $request->user()->id, 'updated_at' => now(), 'created_at' => now()],
        );

        return back()->with('success', 'บันทึกการรับทราบแล้ว');
    }

    public function close(Request $request): RedirectResponse
    {
        $month = $request->validate(['month' => ['required', 'date_format:Y-m']])['month'];
        $start = CarbonImmutable::parse("{$month}-01");
        if (! $start->endOfMonth()->isPast()) {
            return back()->with('error', 'ปิดงวดได้หลังสิ้นเดือนแล้วเท่านั้น');
        }
        if (collect(MonthClose::anomalies($month))->contains(fn ($i) => $i['blocking'] && ! $i['resolved'])) {
            return back()->with('error', 'ยังมีรายการที่ต้องจัดการก่อนปิดงวด');
        }

        DB::table('month_closures')->insertOrIgnore(['month' => $month, 'closed_at' => now(), 'closed_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('month_closure_logs')->insert(['month' => $month, 'action' => 'close', 'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'ปิดงวด '.thai_month($start).' แล้ว · ข้อมูลเดือนนี้ถูกล็อก');
    }

    public function unlock(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['month' => ['required', 'date_format:Y-m'], 'reason' => ['required', 'string', 'max:255']]);
        DB::table('month_closures')->where('month', $data['month'])->delete();
        DB::table('month_closure_logs')->insert(['month' => $data['month'], 'action' => 'unlock', 'reason' => $data['reason'], 'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'ปลดล็อกงวดแล้ว');
    }
}
