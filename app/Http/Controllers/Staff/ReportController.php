<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Support\DailyReport;
use App\Support\MonthClose;
use App\Support\Pdf;
use App\Support\PersonReport;
use App\Support\Xlsx;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/** รายงาน: PDF/Excel รายวัน (ส่งหัวหน้าสำนัก) และรายบุคคล (ช่วงวันที่ใดก็ได้) */
class ReportController extends Controller
{
    public function dailyPdf(Request $request): Response
    {
        [$date, $type] = $this->dailyParams($request);
        $report = DailyReport::build($date, $type, withPhotoPaths: true);

        $html = view('pdf.daily', [
            'report' => $report,
            'org' => Setting::get('org_name'),
            'photos' => $request->boolean('photos', true),
            'closed' => MonthClose::isClosed($date),
            'reporter' => $request->user()->full_name,
            'headName' => Setting::get('report.head_name'),
            'headTitle' => Setting::get('report.head_title'),
            'printedAt' => now(),
        ])->render();

        return Pdf::download($html, "รายงานประจำวัน-{$date}".($type === 'ot' ? '-OT' : '').'.pdf');
    }

    public function dailyExcel(Request $request): Response
    {
        [$date, $type] = $this->dailyParams($request);
        $report = DailyReport::build($date, $type);
        $rows = [];
        $n = 0;
        foreach ($report['groups'] as $g) {
            $rows[] = "{$g['name']} · {$g['summary']}";
            foreach ($g['rows'] as $r) {
                $rows[] = [++$n, $r['name'], $r['check_in'], $r['check_out'], $r['hours'], $r['special_hours'], $r['status'], $r['note']];
            }
        }

        return Xlsx::download(
            "รายงานประจำวัน-{$date}".($type === 'ot' ? '-OT' : '').'.xlsx',
            [Setting::get('org_name'), 'รายงานการลงเวลาปฏิบัติงานประจำวัน'.($type === 'ot' ? ' (ล่วงเวลา)' : ''), thai_date($date, false, true)],
            ['ที่', 'ชื่อ-สกุล', 'เข้า', 'ออก', 'ชม.', 'OT พิเศษ', 'สถานะ', 'หมายเหตุ'],
            $rows,
        );
    }

    /** หน้ารายบุคคล (หน้า 12) */
    public function person(Request $request): InertiaResponse
    {
        [$user, $from, $to] = $this->personParams($request, required: false);

        return Inertia::render('Staff/Person', [
            'people' => User::query()->with(['department:id,name', 'employmentType:id,code,name'])->orderBy('first_name')
                ->get()->map(fn (User $u) => ['id' => $u->id, 'name' => $u->full_name, 'code' => $u->employee_code, 'department' => $u->department?->name, 'active' => $u->is_active]),
            'selected' => $user ? $this->personInfo($user) : null,
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'report' => $user ? (function () use ($user, $from, $to) {
                $r = PersonReport::build($user, $from, $to, includeHolidays: true);
                $r['rows'] = array_map(fn ($row) => [...array_diff_key($row, ['in_photo' => 1, 'out_photo' => 1]), 'has_in_photo' => (bool) $row['in_photo'], 'has_out_photo' => (bool) $row['out_photo']], $r['rows']);

                return $r;
            })() : null,
        ]);
    }

    public function personPdf(Request $request): Response
    {
        [$user, $from, $to] = $this->personParams($request);
        $report = PersonReport::build($user, $from, $to, includeHolidays: true);
        $report['rows'] = array_map(fn ($r) => [...$r, 'in_photo_path' => Pdf::photoPath($r['in_photo']), 'out_photo_path' => Pdf::photoPath($r['out_photo'])], $report['rows']);
        $info = $this->personInfo($user);

        $signers = $info['intern']
            ? [['name' => $user->full_name, 'title' => 'ผู้ฝึกงาน'], ['name' => '', 'title' => 'ผู้ควบคุมการฝึกงาน'], ['name' => Setting::get('report.head_name'), 'title' => Setting::get('report.head_title')]]
            : [['name' => $request->user()->full_name, 'title' => 'ผู้รายงาน'], ['name' => Setting::get('report.head_name'), 'title' => Setting::get('report.head_title')]];

        $html = view('pdf.person', [
            'report' => $report,
            'person' => $info,
            'org' => Setting::get('org_name'),
            'photos' => $request->boolean('photos', true),
            'signers' => $signers,
            'printedAt' => now(),
        ])->render();

        return Pdf::download($html, "รายบุคคล-{$user->first_name}-{$from->toDateString()}-{$to->toDateString()}.pdf");
    }

    public function personExcel(Request $request): Response
    {
        [$user, $from, $to] = $this->personParams($request);
        $report = PersonReport::build($user, $from, $to, includeHolidays: true);
        $s = $report['stats'];

        return Xlsx::download(
            "รายบุคคล-{$user->first_name}-{$from->toDateString()}-{$to->toDateString()}.xlsx",
            [Setting::get('org_name'), "รายงานการลงเวลารายบุคคล · {$user->full_name}", 'ช่วงวันที่ '.thai_date($from).' – '.thai_date($to),
                "มา {$s['present']}/{$s['workdays']} วัน · ชั่วโมงปกติ {$s['hours']} · OT {$s['ot']} · สาย {$s['late_count']} ครั้ง · ลา {$s['leave_days']} วัน"],
            ['วันที่', 'เข้า', 'ออก', 'ชม. ปกติ', 'OT', 'OT พิเศษ', 'สถานะ', 'หมายเหตุ'],
            array_map(fn ($r) => [thai_date($r['date'], true, true), $r['check_in'], $r['check_out'], $r['hours'], $r['ot'], $r['ot_special'], $r['status'], $r['note']], $report['rows']),
        );
    }

    private function dailyParams(Request $request): array
    {
        $data = $request->validate(['date' => ['required', 'date'], 'type' => ['nullable', Rule::in(['normal', 'ot'])]]);

        return [CarbonImmutable::parse($data['date'])->toDateString(), $data['type'] ?? 'normal'];
    }

    /** @return array{0:?User,1:?CarbonImmutable,2:?CarbonImmutable} */
    private function personParams(Request $request, bool $required = true): array
    {
        $data = $request->validate([
            'user' => [$required ? 'required' : 'nullable', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $user = isset($data['user']) ? User::query()->with(['department', 'employmentType'])->find($data['user']) : null;
        if (! $user) {
            return [null, null, null];
        }
        $from = CarbonImmutable::parse($data['from'] ?? ($user->start_date && $user->end_date ? $user->start_date : now()->startOfMonth()));
        $to = CarbonImmutable::parse($data['to'] ?? now())->min($from->addYear());

        return [$user, $from, $to];
    }

    private function personInfo(User $user): array
    {
        $intern = $user->employmentType?->code === 'intern';

        return [
            'id' => $user->id,
            'name' => $user->full_name,
            'type' => $user->employmentType?->name,
            'department' => $user->department?->name,
            'intern' => $intern,
            'start_date' => $user->start_date?->toDateString(),
            'end_date' => $user->end_date?->toDateString(),
            'period' => $user->start_date && $user->end_date ? 'ช่วง '.($intern ? 'ฝึกงาน ' : '').thai_date($user->start_date, true).' – '.thai_date($user->end_date, true) : null,
        ];
    }
}
