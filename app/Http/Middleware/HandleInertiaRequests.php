<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => fn () => [
                'name' => config('app.name'),
                'org' => Setting::get('org_name'),
                'dev_login' => app()->isLocal(), // [DEV ONLY] ปุ่มทางลัดทดสอบ
            ],
            'auth' => fn () => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'first_name' => $user->first_name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'role' => $user->role,
                    'role_label' => $user::ROLE_LABELS[$user->role] ?? $user->role,
                ] : null,
                'menu' => $user ? $this->menu($user) : [],
            ],
            'login' => fn () => $user ? null : \App\Http\Controllers\Auth\LoginController::options(),
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    private function menu($user): array
    {
        $allowed = fn (array $entry) => empty($entry['admin_only']) || $user->isAdmin();
        $modules = config($user->isStaff() ? 'menu.staff' : 'menu.member');

        return collect($modules)
            ->filter($allowed)
            ->map(fn (array $m) => ['module' => $m['module'], 'items' => array_values(array_filter($m['items'], $allowed))])
            ->filter(fn (array $m) => $m['items'])
            ->values()
            ->all();
    }
}
