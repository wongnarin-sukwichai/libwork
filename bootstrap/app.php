<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\KioskAuth;
use App\Http\Middleware\SignOutInactive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SignOutInactive::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'kiosk' => KioskAuth::class,
        ]);

        // API ของจอ kiosk ยืนยันตัวด้วย token ของเครื่อง ไม่ใช้ session/CSRF
        $middleware->validateCsrfTokens(except: ['kiosk/api/*']);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(fn ($request) => $request->user()->homePath());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
