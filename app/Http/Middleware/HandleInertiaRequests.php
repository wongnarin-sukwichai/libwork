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
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    private function menu($user): array
    {
        $items = config($user->isStaff() ? 'menu.staff' : 'menu.member');

        return array_values(array_filter($items, fn ($item) => empty($item['admin_only']) || $user->isAdmin()));
    }
}
