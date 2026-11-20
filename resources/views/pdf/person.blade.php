@include('pdf._style')
{{-- รายงานการลงเวลารายบุคคล ช่วงวันที่ใดก็ได้ · นิสิตฝึกงานมีช่องลงนาม 3 ช่อง --}}
<htmlpagefooter name="footer">
    <table style="border-top: 0.5pt solid #DFE5F0; font-size: 8.5pt; color: #4B587C;"><tr>
        <td>พิมพ์จากระบบลงเวลาปฏิบัติงาน · {{ thai_date($printedAt, true) }} เวลา {{ $printedAt->format('H:i') }} น.</td>
        <td style="text-align: right;">หน้า {PAGENO} / {nbpg}</td>
    </tr></table>
</htmlpagefooter>
<sethtmlpagefooter name="footer" value="on" />

<div class="head">
    <div class="org">{{ $org }}</div>
    <h1>รายงานการลงเวลาปฏิบัติงานรายบุคคล</h1>
    <div class="sub">{{ $person['name'] }} · {{ $person['type'] ?? '—' }} · {{ $person['department'] ?? '—' }}</div>
    <div class="muted">ช่วงวันที่ {{ thai_date($report['from']) }} – {{ thai_date($report['to']) }}@if ($person['period']) · {{ $person['period'] }}@endif</div>
</div>

<table class="sum"><tr>
    <td>มาปฏิบัติงาน<br><b>{{ $report['stats']['present'] }} / {{ $report['stats']['workdays'] }} วัน</b></td>
    <td>ชั่วโมงปกติ<br><b>{{ $report['stats']['hours'] }}</b></td>
    <td>ชั่วโมง OT<br><b>{{ $report['stats']['ot'] }}</b></td>
    <td>มาสาย<br><b>{{ $report['stats']['late_count'] }} ครั้ง</b></td>
    <td>ลา<br><b>{{ $report['stats']['leave_days'] }} วัน</b></td>
</tr></table>

<table class="grid" style="page-break-inside: auto;">
    <thead><tr>
        <th style="width: 17%;">วันที่</th>
        @if ($photos)<th style="width: 6%;">รูป</th>@endif<th style="width: 9%;">เข้า</th>
        @if ($photos)<th style="width: 6%;">รูป</th>@endif<th style="width: 9%;">ออก</th>
        <th style="width: 8%;">ชม.</th><th style="width: 9%;">OT</th><th style="width: 18%;">สถานะ</th><th>หมายเหตุ</th>
    </tr></thead>
    <tbody>
        @foreach ($report['rows'] as $r)
            <tr class="{{ $r['holiday'] && ! $r['check_in'] ? 'holiday' : '' }}">
                <td>{{ thai_date($r['date'], true, true) }}</td>
                @if ($photos)<td>@if ($p = $r['in_photo_path'] ?? null)<img class="photo" src="{{ $p }}">@endif</td>@endif
                <td>{{ $r['check_in'] ?? '—' }}</td>
                @if ($photos)<td>@if ($p = $r['out_photo_path'] ?? null)<img class="photo" src="{{ $p }}">@endif</td>@endif
                <td>{{ $r['check_out'] ?? '—' }}</td>
                <td>{{ $r['hours'] ?? '—' }}</td>
                <td>{{ $r['ot'] ?? '' }}{{ $r['ot_special'] ?? '' }}</td>
                <td>@include('pdf._status', ['status' => $r['status'], 'tone' => $r['tone']])</td>
                <td class="muted">{{ $r['note'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="sign" style="page-break-inside: avoid;"><tr>
    @foreach ($signers as $s)
        <td style="width: {{ 100 / count($signers) }}%;">ลงชื่อ ................................<br>( {{ $s['name'] ?: '................................' }} )<br><span class="muted">{{ $s['title'] }}</span></td>
    @endforeach
</tr></table>
