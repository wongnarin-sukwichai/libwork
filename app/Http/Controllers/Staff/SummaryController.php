<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Pdf;
use App\Support\SummaryReport;
use App\Support\Xlsx;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/** สรุปภาพรวมการมาปฏิบัติงานและการลา ทุกคน ช่วงวันที่อิสระ (เช่น ตามปีงบประมาณ) */
class SummaryController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        [$from, $to] = $this->range($request);

        return Inertia::render('Staff/Summary', ['report' => SummaryReport::build($from, $to)]);
    }

    public function pdf(Request $request): Response
    {
        [$from, $to] = $this->range($request);
        $html = view('pdf.summary', [
            'report' => SummaryReport::build($from, $to),
            'org' => Setting::get('org_name'),
            'reporter' => $request->user()->full_name,
            'headTitle' => Setting::get('report.head_title'),
            'headName' => Setting::get('report.head_name'),
            'printedAt' => now(),
        ])->render();

        return Pdf::download($html, "สรุปภาพรวม-{$from->toDateString()}-{$to->toDateString()}.pdf", 'L');
    }

    public function excel(Request $request): Response
    {
        [$from, $to] = $this->range($request);
        $report = SummaryReport::build($from, $to);
        $line = fn (array $r, $no = null) => [
            $no, $r['name'], $r['workdays'], $r['present'],
            $r['late_count'], $r['late_minutes'], $r['early_count'], $r['early_minutes'], $r['absent'],
            ...array_map(fn ($k) => $r['leave'][$k], array_keys(SummaryReport::LEAVE_COLUMNS)),
            $r['leave']['other'], $r['leave_total'], $r['leave']['duty'], $r['ot'], $r['ot_special'],
        ];
        $rows = [];
        $n = 0;
        foreach ($report['groups'] as $g) {
            $rows[] = $g['name'];
            foreach ($g['rows'] as $r) {
                $rows[] = $line($r, ++$n);
            }
            $rows[] = $line($g['total']);
        }
        $rows[] = $line([...$report['total'], 'name' => 'รวมทั้งหมด']);

        return Xlsx::download(
            "สรุปภาพรวม-{$from->toDateString()}-{$to->toDateString()}.xlsx",
            [Setting::get('org_name'), 'สรุปการมาปฏิบัติงานและการลา', 'ช่วงวันที่ '.thai_date($from).' – '.thai_date($to)
                .' · วันทำการ '.$report['workdays'].' วัน (นับถึง '.thai_date($report['counted_to']).')'],
            ['ที่', 'ชื่อ-สกุล', 'วันทำการ', 'มา', 'สาย (ครั้ง)', 'สาย (นาที)', 'ออกก่อน (ครั้ง)', 'ออกก่อน (นาที)', 'ไม่ลงเวลา',
                ...array_map(fn ($l) => "ลา{$l}", SummaryReport::LEAVE_COLUMNS), 'ลาอื่นๆ', 'รวมลา', 'ไปราชการ', 'OT (ชม.)', 'OT พิเศษ (ชม.)'],
            $rows,
        );
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} ค่าเริ่มต้น = ต้นเดือนถึงวันนี้ · ยาวสุด 1 ปีงบ (ไม่เกิน 366 วัน) */
    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], ['to.after_or_equal' => 'วันสิ้นสุดต้องไม่ก่อนวันเริ่มต้น']);
        $from = CarbonImmutable::parse($data['from'] ?? now()->startOfMonth())->startOfDay();
        $to = CarbonImmutable::parse($data['to'] ?? now())->startOfDay()->min($from->addDays(365));

        return [$from, $to];
    }
}
