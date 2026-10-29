<?php

use App\Support\AutomationSchedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\AccessLog::class,
            \App\Http\Middleware\IdempotencyMiddleware::class,
        ]);
        $middleware->web(prepend: [
            \App\Http\Middleware\AccessLog::class,
            \App\Http\Middleware\IdempotencyMiddleware::class,
        ]);
        $middleware->alias([
            'profile.completed' => \App\Http\Middleware\ProfileCompleted::class,
            'status' => \App\Http\Middleware\CheckUserStatus::class,
            'business' => \App\Http\Middleware\EnsureBusiness::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'idempotency' => \App\Http\Middleware\IdempotencyMiddleware::class,
            'legal.acceptance' => \App\Http\Middleware\LegalAcceptance::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        // Every scheduled command lives in AutomationRegistry so the admin
        // panel can show, explain, toggle and manually run it from one list.
        AutomationSchedule::register($schedule);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
