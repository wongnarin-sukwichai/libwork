@include('pdf._style')
{{-- สรุปการมาปฏิบัติงานและการลา ทุกคน ช่วงวันที่ใดก็ได้ · A4 แนวนอน (คอลัมน์เยอะ) · แต่ละกลุ่มงานไม่ถูกตัดกลางหน้า --}}
<style>
    .grid th, .grid td { text-align: center; }
    .grid th.l, .grid td.l { text-align: left; }
    .grid .sub th { background: #2C4C86; font-weight: normal; font-size: 8.5pt; padding-top: 1pt; }
    .grid .tot td { background: #F3F6FB; font-weight: bold; }
    .grid .all td { background: #1E3A6E; color: #fff; font-weight: bold; }
    .z { color: #A9B4CC; }
    .sp { color: #5B21B6; font-weight: bold; }
</style>
<htmlpagefooter name="footer">
    <table style="border-top: 0.5pt solid #DFE5F0; font-size: 8.5pt; color: #4B587C;"><tr>
        <td>พิมพ์จากระบบลงเวลาปฏิบัติงาน · {{ thai_date($printedAt, true) }} เวลา {{ $printedAt->format('H:i') }} น.</td>
        <td style="text-align: right;">หน้า {PAGENO} / {nbpg}</td>
    </tr></table>
</htmlpagefooter>
<sethtmlpagefooter name="footer" value="on" />

@php
    $cols = $report['leave_columns'];
    $span = 13 + count($cols);
    $v = fn ($n) => $n ? (is_float($n) && floor($n) != $n ? number_format($n, 1) : (int) $n) : null;
    $z = fn ($n) => ($x = $v($n)) !== null ? $x : '<span class="z">–</span>';
    $t = $report['total'];
@endphp

<div class="head">
    <table style="width: 100%;"><tr>
        <td style="width: 62pt; vertical-align: middle;"><img src="{{ public_path('img/logos/library-dark.png') }}" style="width: 56pt;"></td>
        <td style="vertical-align: middle; padding-left: 6pt; border-left: 0.75pt solid #DFE5F0;">
            <div class="org">{{ $org }}</div>
            <h1>สรุปการมาปฏิบัติงานและการลา</h1>
            <div class="sub">ช่วงวันที่ {{ thai_date($report['from']) }} – {{ thai_date($report['to']) }} · วันทำการ {{ $report['workdays'] }} วัน@if ($report['counted_to'] !== $report['to']) (นับถึง {{ thai_date($report['counted_to']) }})@endif</div>
        </td>
    </tr></table>
</div>

<table class="sum"><tr>
    <td>บุคลากร<br><b>{{ $report['people'] }}</b></td>
    <td>มาปฏิบัติงาน (วัน)<br><b>{{ $t['present'] }}</b></td>
    <td>สาย (ครั้ง)<br><b>{{ $t['late_count'] }}</b></td>
    <td>ไม่ลงเวลา (วัน)<br><b>{{ $t['absent'] }}</b></td>
    <td>ลา (วัน)<br><b>{{ $v($t['leave_total']) ?? 0 }}</b></td>
    <td>ไปราชการ (วัน)<br><b>{{ $v($t['leave']['duty']) ?? 0 }}</b></td>
    <td>OT (ชม.)<br><b>{{ $t['ot'] ?? '0:00' }}</b></td>
</tr></table>

@php($n = 0)
@forelse ($report['groups'] as $g)
    <table class="grid">
        <thead>
            <tr>
                <th rowspan="2" style="width: 3.5%;">ที่</th><th rowspan="2" class="l" style="width: 19%;">ชื่อ-สกุล</th>
                <th rowspan="2">วัน<br>ทำการ</th><th rowspan="2">มา</th>
                <th colspan="2">สาย</th><th colspan="2">ออกก่อน</th><th rowspan="2">ไม่ลง<br>เวลา</th>
                <th colspan="{{ count($cols) + 2 }}">ลา (วัน)</th><th rowspan="2">ไป<br>ราชการ</th><th rowspan="2">OT (ชม.)</th>
            </tr>
            <tr class="sub">
                <th>ครั้ง</th><th>นาที</th><th>ครั้ง</th><th>นาที</th>
                @foreach ($cols as $label)<th>{{ $label }}</th>@endforeach
                <th>อื่นๆ</th><th>รวม</th>
            </tr>
        </thead>
        <tbody>
            <tr class="group"><td colspan="{{ $span }}" class="l">{{ $g['name'] }} <span>· {{ count($g['rows']) }} คน</span></td></tr>
            @foreach ([...$g['rows'], $g['total']] as $r)
                @php($isTotal = $r['user_id'] === null)
                <tr @class(['tot' => $isTotal])>
                    <td>{{ $isTotal ? '' : ++$n }}</td>
                    <td class="l">{{ $r['name'] }}</td>
                    <td>{{ $r['workdays'] }}</td>
                    <td>{{ $r['present'] }}</td>
                    <td>{!! $z($r['late_count']) !!}</td><td>{!! $z($r['late_minutes']) !!}</td>
                    <td>{!! $z($r['early_count']) !!}</td><td>{!! $z($r['early_minutes']) !!}</td>
                    <td>{!! $z($r['absent']) !!}</td>
                    @foreach (array_keys($cols) as $k)<td>{!! $z($r['leave'][$k]) !!}</td>@endforeach
                    <td>{!! $z($r['leave']['other']) !!}</td>
                    <td><b>{!! $z($r['leave_total']) !!}</b></td>
                    <td>{!! $z($r['leave']['duty']) !!}</td>
                    <td>{!! $r['ot'] ? e($r['ot']) : '<span class="z">–</span>' !!}@if ($r['ot_special']) <span class="sp">+{{ $r['ot_special'] }}</span>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@empty
    <p class="muted" style="margin-top: 20pt; text-align: center;">ไม่มีข้อมูลในช่วงนี้</p>
@endforelse

@if ($report['groups'])
    <table class="grid" style="margin-top: 4pt;"><tbody>
        <tr class="all">
            <td class="l" style="width: 22.5%;">รวมทั้งหมด {{ $report['people'] }} คน</td>
            <td>มา {{ $t['present'] }}</td>
            <td>สาย {{ $t['late_count'] }} ครั้ง / {{ $t['late_minutes'] }} นาที</td>
            <td>ออกก่อน {{ $t['early_count'] }} ครั้ง</td>
            <td>ไม่ลงเวลา {{ $t['absent'] }}</td>
            <td>ลา {{ $v($t['leave_total']) ?? 0 }} วัน</td>
            <td>ไปราชการ {{ $v($t['leave']['duty']) ?? 0 }} วัน</td>
            <td>OT {{ $t['ot'] ?? '0:00' }}@if ($t['ot_special']) +{{ $t['ot_special'] }}@endif ชม.</td>
        </tr>
    </tbody></table>
@endif

<p class="muted" style="font-size: 8.5pt; margin-top: 6pt;">ไม่ลงเวลา = วันทำการที่ผ่านมาแล้ว ไม่มีเวลาเข้าและไม่มีวันลา · วันลานับรวมวันลาล่วงหน้าในช่วง (ครึ่งวัน = 0.5) · OT ตัวม่วง = ล่วงเวลาพิเศษ</p>

<table class="sign" style="page-break-inside: avoid;"><tr>
    <td>ลงชื่อ ................................. ผู้รายงาน<br>( {{ $reporter }} )<br><span class="muted">เจ้าหน้าที่บุคคล</span></td>
    <td>ลงชื่อ ................................. ผู้ตรวจ<br>( {{ $headName ?: '.................................' }} )<br><span class="muted">{{ $headTitle }}</span></td>
</tr></table>
