@include('pdf._style')
{{-- รายงานการลงเวลาประจำวัน ส่งหัวหน้าสำนัก · A4 แนวตั้ง · แต่ละกลุ่มงานไม่ถูกตัดกลางหน้า --}}
<htmlpagefooter name="footer">
    <table style="border-top: 0.5pt solid #DFE5F0; font-size: 8.5pt; color: #4B587C;"><tr>
        <td>พิมพ์จากระบบลงเวลาปฏิบัติงาน · {{ thai_date($printedAt, true) }} เวลา {{ $printedAt->format('H:i') }} น. · {{ $closed ? 'ปิดงวดแล้ว' : 'ข้อมูลยังไม่ปิดงวด' }}</td>
        <td style="text-align: right;">หน้า {PAGENO} / {nbpg}</td>
    </tr></table>
</htmlpagefooter>
<sethtmlpagefooter name="footer" value="on" />

<div class="head">
    {{-- โลโก้ฉบับสีเข้ม (ส่วนที่เป็นสีขาวในไฟล์เดิมเปลี่ยนเป็นน้ำเงิน เพื่อให้เห็นบนกระดาษขาว) --}}
    <table style="width: 100%;"><tr>
        <td style="width: 62pt; vertical-align: middle;"><img src="{{ public_path('img/logos/library-dark.png') }}" style="width: 56pt;"></td>
        <td style="vertical-align: middle; padding-left: 6pt; border-left: 0.75pt solid #DFE5F0;">
            <div class="org">{{ $org }}</div>
            <h1>รายงานการลงเวลาปฏิบัติงานประจำวัน{{ $report['type'] === 'ot' ? ' (ล่วงเวลา)' : '' }}</h1>
            <div class="sub">{{ thai_date($report['date'], false, true) }}@if ($report['schedule']) · {{ $report['schedule']['name'] }} {{ $report['schedule']['start'] }}–{{ $report['schedule']['end'] }} น.@endif</div>
        </td>
    </tr></table>
</div>

<table class="sum"><tr>
@if ($report['type'] === 'ot')
    {{-- OT แสดงเฉพาะคนที่มีรายการ OT: สรุปเหมือนการ์ด 3 ใบบนหน้าเว็บ --}}
    <td>มาปฏิบัติงาน (OT)<br><b>{{ $report['stats']['present'] }}</b></td>
    <td>ล่วงเวลาพิเศษ<br><b>{{ $report['stats']['special'] }}</b></td>
    <td>ไม่ได้ลงเวลาออก<br><b>{{ $report['stats']['no_out'] }}</b></td>
@else
    <td>บุคลากร<br><b>{{ $report['stats']['total'] }}</b></td>
    <td>มาปฏิบัติงาน<br><b>{{ $report['stats']['present'] }}</b></td>
    <td>สาย / ออกก่อน<br><b>{{ $report['stats']['late'] }}</b></td>
    <td>ลา<br><b>{{ $report['stats']['leave'] }}</b></td>
    <td>ไปราชการ<br><b>{{ $report['stats']['duty'] }}</b></td>
    <td>ไม่ได้ลงเวลา<br><b>{{ $report['stats']['absent'] + $report['stats']['no_out'] }}</b></td>
@endif
</tr></table>

@php($n = 0)
@forelse ($report['groups'] as $g)
    <table class="grid">
        <thead><tr>
            <th style="width: 5%;">ที่</th><th style="width: 24%;">ชื่อ-สกุล</th>
            @if ($photos)<th style="width: 6%;">รูป</th>@endif<th style="width: 8%;">เข้า</th>
            @if ($photos)<th style="width: 6%;">รูป</th>@endif<th style="width: 8%;">ออก</th>
            <th style="width: 7%;">ชม.</th><th style="width: 22%;">สถานะ</th><th style="width: 14%;">หมายเหตุ</th>
        </tr></thead>
        <tbody>
            <tr class="group"><td colspan="{{ $photos ? 9 : 7 }}">{{ $g['name'] }} <span>· {{ $g['summary'] }}</span></td></tr>
            @foreach ($g['rows'] as $r)
                <tr>
                    <td>{{ ++$n }}</td>
                    <td>{{ $r['name'] }}</td>
                    @if ($photos)<td>@if ($p = $r['in_photo_path'])<img class="photo" src="{{ $p }}">@endif</td>@endif
                    <td>{{ $r['check_in'] ?? '—' }}</td>
                    @if ($photos)<td>@if ($p = $r['out_photo_path'])<img class="photo" src="{{ $p }}">@endif</td>@endif
                    <td>{{ $r['check_out'] ?? '—' }}</td>
                    <td>{{ $r['hours'] ?? '—' }}@if ($r['special_hours']) +{{ $r['special_hours'] }}@endif</td>
                    <td>@include('pdf._status', ['status' => $r['status'], 'tone' => $r['tone']])</td>
                    <td class="muted">{{ $r['note'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@empty
    <p class="muted" style="margin-top: 20pt; text-align: center;">ไม่มีข้อมูลในวันนี้</p>
@endforelse

<table class="sign" style="page-break-inside: avoid;"><tr>
    <td>ลงชื่อ ................................. ผู้รายงาน<br>( {{ $reporter }} )<br><span class="muted">เจ้าหน้าที่บุคคล</span></td>
    <td>ลงชื่อ ................................. ผู้ตรวจ<br>( {{ $headName ?: '.................................' }} )<br><span class="muted">{{ $headTitle }}</span></td>
</tr></table>
