<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\LeaveAdjustment;
use App\Models\LeaveBalance;
use App\Models\LeaveInquiry;
use App\Models\LeaveRecord;
use App\Support\DailyReport;
use App\Support\FiscalYear;
use App\Support\LeaveService;
use App\Support\MonthClose;
use App\Support\PersonReport;
use Illuminate\Http\RedirectResponse;
use App\Support\ScheduleResolver;
use App\Support\WorkCalculator;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** หน้าของบุคลากร: สรุปเดือน กราฟ 10 วัน และตารางเวลาปฏิบัติงาน */
class MeController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $today = CarbonImmutable::today();
        $month = $this->month($user, $today->startOfMonth());

        $todayRow = Attendance::query()->where('user_id', $user->id)->whereDate('work_date', $today)->where('type', 'normal')->first();

        $recent = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', '>', $today->subDays(14))
            ->get()
            ->groupBy(fn ($a) => $a->work_date->toDateString());
        $bars = collect(CarbonPeriod::create($today->subDays(13), $today))
            ->map(fn ($d) => CarbonImmutable::parse($d))
            ->filter(fn ($d) => ScheduleResolver::isWorkday($d) || $recent->has($d->toDateString()))
            ->take(-10)
            ->map(function (CarbonImmutable $d) use ($recent) {
                $items = $recent->get($d->toDateString(), collect());
                $n = $items->firstWhere('type', 'normal');
                $o = $items->firstWhere('type', 'ot');

                return [
                    'day' => $d->day,
                    'normal' => round(($n?->worked_minutes ?? 0) / 60, 2),
                    'ot' => round((($o?->worked_minutes ?? 0) + ($o?->special_minutes ?? 0)) / 60, 2),
                    'flag' => $n && $n->check_in_at && ! $n->check_out_at && $d->lessThan(CarbonImmutable::today()) ? '!' : ($d->isToday() && ! $n?->check_out_at ? '…' : ''),
                ];
            })
            ->values();

        return Inertia::render('Me/Dashboard', [
            'today' => [
                'check_in' => $todayRow?->check_in_at?->format('H:i'),
                'check_out' => $todayRow?->check_out_at?->format('H:i'),
                'late' => $todayRow?->late_minutes ?? 0,
            ],
            'month' => $month,
            'bars' => $bars,
            'leaves' => collect(LeaveService::summary($user, FiscalYear::current(), memberView: true))
                ->filter(fn ($l) => $l['counts_quota'] || $l['times'])->values(),
        ]);
    }

    /** สิทธิ์วันลาของฉัน: แจกแจงยอด ประวัติการลา ประวัติการปรับยอด และคำขอตรวจสอบ */
    public function leave(Request $request): Response
    {
        $user = $request->user();
        $fy = $request->integer('fy') ?: FiscalYear::current();
        $balances = LeaveBalance::query()->where('user_id', $user->id)->where('fiscal_year', $fy)->get();
        $confirmed = $balances->isNotEmpty() && $balances->every(fn ($b) => $b->status === 'confirmed');

        return Inertia::render('Me/Leave', [
            'fy' => $fy,
            'employmentType' => $user->employmentType?->name,
            'serviceYears' => $user->start_date ? (int) $user->start_date->diffInYears(FiscalYear::start($fy)) : null,
            'confirmed' => $confirmed,
            'confirmedAt' => $confirmed ? $balances->max('confirmed_at')?->format('d/m/Y') : null,
            'summary' => LeaveService::summary($user, $fy, memberView: true),
            'records' => LeaveRecord::query()->with('leaveType:id,name,category')->where('user_id', $user->id)->where('fiscal_year', $fy)
                ->orderByDesc('start_date')->get()->map(fn (LeaveRecord $r) => [
                    'id' => $r->id, 'type' => $r->leaveType->name, 'category' => $r->leaveType->category,
                    'start_date' => $r->start_date->toDateString(), 'end_date' => $r->end_date->toDateString(),
                    'half_label' => $r->halfLabel(), 'days' => $r->days, 'reason' => $r->reason, 'has_attachment' => (bool) $r->attachment,
                ]),
            'adjustments' => $confirmed ? LeaveAdjustment::query()->with(['balance.leaveType:id,name', 'creator:id,prefix,first_name,last_name'])
                ->whereIn('leave_balance_id', $balances->pluck('id'))->latest()->get()->map(fn ($a) => [
                    'at' => $a->created_at->format('d/m/Y'), 'type' => $a->balance?->leaveType?->name, 'amount' => $a->amount, 'reason' => $a->reason, 'by' => $a->creator?->full_name,
                ]) : [],
            'inquiries' => LeaveInquiry::query()->where('user_id', $user->id)->where('fiscal_year', $fy)->latest()->get(['id', 'message', 'reply', 'created_at', 'replied_at'])
                ->map(fn ($i) => ['id' => $i->id, 'message' => $i->message, 'reply' => $i->reply, 'at' => $i->created_at->format('d/m/Y')]),
        ]);
    }

    public function inquire(Request $request): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:1000'], 'fy' => ['required', 'integer']]);
        LeaveInquiry::query()->create(['user_id' => $request->user()->id, 'fiscal_year' => $data['fy'], 'message' => $data['message']]);

        return back()->with('success', 'ส่งคำขอตรวจสอบถึง HR แล้ว');
    }

    public function attendance(Request $request): Response
    {
        $month = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? now()->format('Y-m');

        return Inertia::render('Me/Attendance', [
            'month' => $this->month($request->user(), CarbonImmutable::parse("{$month}-01")),
        ]);
    }

    /** สรุปและตารางรายวันของเดือน (เวลาปกติ + OT ในแถวเดียวกัน) */
    private function month($user, CarbonImmutable $start): array
    {
        $report = PersonReport::build($user, $start, $start->endOfMonth());

        return [
            'month' => $start->format('Y-m'),
            'closed' => MonthClose::isClosed($start->toDateString()),
            'stats' => $report['stats'],
            'rows' => array_reverse(array_map(fn ($r) => array_diff_key($r, ['in_photo' => 1, 'out_photo' => 1]), $report['rows'])),
        ];
    }
}
