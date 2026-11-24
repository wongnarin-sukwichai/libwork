<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\CalendarEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** หน้าแรกสาธารณะ และปฏิทินหลัง login (เหตุการณ์โหลดทีละเดือนผ่าน JSON) */
class CalendarController extends Controller
{
    public function home(bool $openLogin = false): Response
    {
        $showType = (bool) Setting::get('public_calendar.show_leave_type');

        return Inertia::render('Home', [
            'events' => CalendarEvents::month(now()->year, now()->month, 'public'),
            'absentToday' => CalendarEvents::absentToday($showType),
            'upcomingHolidays' => CalendarEvents::upcomingHolidays(),
            'openLogin' => $openLogin,
        ]);
    }

    public function page(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Calendar', [
            'events' => CalendarEvents::month(now()->year, now()->month, $user->isStaff() ? 'staff' : 'member', $user),
            'canManageSpecial' => $user->isAdmin(),
        ]);
    }

    /** เปลี่ยนเดือนบนปฏิทิน */
    public function events(Request $request): JsonResponse
    {
        $data = $request->validate(['year' => ['required', 'integer', 'min:2000', 'max:2100'], 'month' => ['required', 'integer', 'min:1', 'max:12']]);
        $user = $request->user();
        $mode = ! $user ? 'public' : ($user->isStaff() ? 'staff' : 'member');

        return response()->json(CalendarEvents::month($data['year'], $data['month'], $mode, $user));
    }
}
