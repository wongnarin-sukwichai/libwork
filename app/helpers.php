<?php

use Carbon\CarbonInterface;

if (! function_exists('thai_date')) {
    /** 19 พฤศจิกายน 2569 · $weekday = true → วันพฤหัสบดีที่ 19 พฤศจิกายน 2569 */
    function thai_date(CarbonInterface|string|null $date, bool $short = false, bool $weekday = false): string
    {
        if (! $date) {
            return '—';
        }
        $d = \Carbon\CarbonImmutable::parse($date);
        $months = $short
            ? ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.']
            : ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        $days = $short ? ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'] : ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
        $text = $d->day.' '.$months[$d->month - 1].' '.($d->year + 543);

        if (! $weekday) {
            return $text;
        }

        return $short ? $days[$d->dayOfWeek].' '.$text : 'วัน'.$days[$d->dayOfWeek].'ที่ '.$text;
    }
}

if (! function_exists('thai_month')) {
    /** พฤศจิกายน 2569 */
    function thai_month(CarbonInterface|string $date): string
    {
        $d = \Carbon\CarbonImmutable::parse($date);
        $months = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

        return $months[$d->month - 1].' '.($d->year + 543);
    }
}
