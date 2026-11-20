<?php

use App\Http\Controllers\Admin\KioskDeviceController;
use App\Http\Controllers\Admin\LeaveSettingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\Staff\CarryOverController;
use App\Http\Controllers\Staff\LeaveController;
use App\Http\Controllers\Staff\MonthCloseController;
use App\Http\Controllers\Staff\ReportController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\Staff\DailyController;
use App\Http\Controllers\Staff\MemberController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// หน้าแรกสาธารณะ: ปฏิทินวันลา (ข้อมูลจริงเพิ่มในเฟสวันลา)
Route::get('/', [CalendarController::class, 'home'])->name('home');
Route::get('/calendar/events', [CalendarController::class, 'events'])->middleware('throttle:60,1');

// จอลงเวลากลาง: หน้าเว็บเปิดได้เลย, API ต้องใช้ token ของเครื่องที่ลงทะเบียนแล้ว
Route::get('/kiosk', [KioskController::class, 'page']);
Route::prefix('kiosk/api')->group(function () {
    Route::post('/register', [KioskController::class, 'register'])->middleware('throttle:10,1');
    Route::middleware('kiosk')->group(function () {
        Route::get('/status', [KioskController::class, 'status']);
        Route::post('/punch', [KioskController::class, 'punch'])->middleware('throttle:120,1');
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', fn (\Illuminate\Http\Request $request) => redirect($request->user()->homePath()));
    Route::get('/photos/attendance/{attendance}/{kind}', [PhotoController::class, 'show'])->whereIn('kind', ['in', 'out']);

    // ทุก role
    Route::get('/calendar', [CalendarController::class, 'page']);
    Route::get('/leave-attachments/{leave}', [LeaveController::class, 'attachment']);

    // member (staff/admin ก็เปิดดูข้อมูลของตัวเองได้)
    Route::prefix('me')->group(function () {
        Route::get('/', [MeController::class, 'dashboard']);
        Route::get('/attendance', [MeController::class, 'attendance']);
        Route::get('/leave', [MeController::class, 'leave']);
        Route::post('/leave/inquiries', [MeController::class, 'inquire']);
    });

    // staff + admin
    Route::prefix('staff')->middleware('role:admin,staff')->group(function () {
        Route::get('/', [DailyController::class, 'index']);
        Route::post('/attendance', [DailyController::class, 'save']);
        Route::get('/attendance/logs', [DailyController::class, 'logs']);
        Route::get('/person', [ReportController::class, 'person']);
        Route::get('/reports/daily.pdf', [ReportController::class, 'dailyPdf']);
        Route::get('/reports/daily.xlsx', [ReportController::class, 'dailyExcel']);
        Route::get('/reports/person.pdf', [ReportController::class, 'personPdf']);
        Route::get('/reports/person.xlsx', [ReportController::class, 'personExcel']);
        Route::get('/leaves', [LeaveController::class, 'index']);
        Route::get('/leaves/preview', [LeaveController::class, 'preview']);
        Route::post('/leaves', [LeaveController::class, 'store']);
        Route::post('/leaves/{leave}', [LeaveController::class, 'update']); // POST เพราะมีไฟล์แนบ
        Route::delete('/leaves/{leave}', [LeaveController::class, 'destroy']);
        Route::get('/close', [MonthCloseController::class, 'index']);
        Route::post('/close/acknowledge', [MonthCloseController::class, 'acknowledge']);
        Route::post('/close', [MonthCloseController::class, 'close']);
        Route::post('/close/unlock', [MonthCloseController::class, 'unlock']);
        Route::get('/carry-over', [CarryOverController::class, 'index']);
        Route::post('/carry-over/generate', [CarryOverController::class, 'generate']);
        Route::post('/carry-over/confirm', [CarryOverController::class, 'confirm']);
        Route::post('/carry-over/unlock', [CarryOverController::class, 'unlock']);
        Route::put('/carry-over/{balance}', [CarryOverController::class, 'update']);
        Route::post('/carry-over/inquiries/{inquiry}/reply', [CarryOverController::class, 'reply']);

        Route::get('/members', [MemberController::class, 'index']);
        Route::post('/members', [MemberController::class, 'store']);
        Route::put('/members/{member}', [MemberController::class, 'update']);
        Route::post('/members/{member}/toggle', [MemberController::class, 'toggle']);
        Route::get('/members/{member}/history', [MemberController::class, 'history']);
        Route::post('/members/move', [MemberController::class, 'move']);
    });

    // admin
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings/general', [SettingController::class, 'updateGeneral']);
        Route::post('/settings/departments', [SettingController::class, 'storeDepartment']);
        Route::put('/settings/departments/{department}', [SettingController::class, 'updateDepartment']);
        Route::post('/settings/departments/reorder', [SettingController::class, 'reorderDepartments']);
        Route::post('/settings/employment-types', [SettingController::class, 'storeEmploymentType']);
        Route::put('/settings/employment-types/{type}', [SettingController::class, 'updateEmploymentType']);

        Route::post('/settings/schedules', [ScheduleController::class, 'store']);
        Route::put('/settings/schedules/{schedule}', [ScheduleController::class, 'update']);
        Route::post('/settings/special-days', [ScheduleController::class, 'storeSpecialDay']);
        Route::delete('/settings/special-days/{specialDay}', [ScheduleController::class, 'destroySpecialDay']);

        Route::post('/settings/holidays', [LeaveSettingController::class, 'storeHoliday']);
        Route::delete('/settings/holidays/{holiday}', [LeaveSettingController::class, 'destroyHoliday']);
        Route::post('/settings/leave-types', [LeaveSettingController::class, 'storeType']);
        Route::put('/settings/leave-types/{type}', [LeaveSettingController::class, 'updateType']);
        Route::put('/settings/leave-policies', [LeaveSettingController::class, 'savePolicies']);

        Route::put('/settings/kiosk', [KioskDeviceController::class, 'settings']);
        Route::post('/settings/kiosk-devices', [KioskDeviceController::class, 'store']);
        Route::post('/settings/kiosk-devices/{device}/reissue', [KioskDeviceController::class, 'reissue']);
        Route::post('/settings/kiosk-devices/{device}/toggle', [KioskDeviceController::class, 'toggle']);
    });
});
