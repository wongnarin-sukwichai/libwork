<?php

use App\Support\FiscalYear;
use App\Support\LeaveService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// ร่างยอดวันลาของปีงบ (สิทธิ์ + ยกมา) ให้ HR ตรวจที่หน้า "ยอดยกมาปีงบ" · ไม่ทับค่าที่ HR แก้หรือยืนยันแล้ว
Artisan::command('libwork:draft-fiscal-year {fy? : ปีงบประมาณ พ.ศ. (ค่าเริ่มต้น = ปีงบปัจจุบัน)}', function () {
    $fy = (int) ($this->argument('fy') ?: FiscalYear::current());
    $count = LeaveService::draftYear($fy);
    $this->info("ร่างยอดปีงบ {$fy} แล้ว {$count} รายการ");
})->purpose('ร่างยอดวันลาปีงบประมาณ');

// ทุกวันที่ 1 ต.ค. (ต้องตั้ง cron เรียก `php artisan schedule:run` ทุกนาทีบนเซิร์ฟเวอร์)
Schedule::command('libwork:draft-fiscal-year')->yearlyOn(10, 1, '00:30');
