<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** ปีงบประมาณ (พ.ศ.): 1 ต.ค. – 30 ก.ย. เช่น 1 ต.ค. 2569 – 30 ก.ย. 2570 = ปีงบ 2570 */
class FiscalYear
{
    public static function of(CarbonInterface|string $date): int
    {
        $d = CarbonImmutable::parse($date);

        return ($d->month >= 10 ? $d->year + 1 : $d->year) + 543;
    }

    public static function current(): int
    {
        return self::of(now());
    }

    public static function start(int $fy): CarbonImmutable
    {
        return CarbonImmutable::create($fy - 543 - 1, 10, 1);
    }

    public static function end(int $fy): CarbonImmutable
    {
        return CarbonImmutable::create($fy - 543, 9, 30);
    }
}
