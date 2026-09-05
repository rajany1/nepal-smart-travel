<?php

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
        ]);
        $middleware->web(prepend: [
            \App\Http\Middleware\AccessLog::class,
        ]);
        $middleware->alias([
'profile.completed' => \App\Http\Middleware\ProfileCompleted::class,
'status' => \App\Http\Middleware\CheckUserStatus::class,
'business' => \App\Http\Middleware\EnsureBusiness::class,
'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        $schedule->command('weather:fetch')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('ai:process-tasks')->everyMinute()->withoutOverlapping(60);
        $schedule->command('ai:process-reports')->everyFifteenMinutes()->withoutOverlapping(120);
        $schedule->command('ai:auto-work')->everyThirtyMinutes()->withoutOverlapping(300);
        // Review AI agent: 24/7 content safety sweep (censoring + strike escalation)
        $schedule->command('ai:safety-sweep')->everyMinute()->withoutOverlapping(120);
        // Weekly refresh of imported OSM districts (queue-backed, upsert only)
        $schedule->command('osm:refresh')->weekly()->sundays()->at('03:00')->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
