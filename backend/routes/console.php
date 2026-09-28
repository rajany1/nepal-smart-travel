<?php

use App\Jobs\SyncBipadData;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Live processing is handled by queue jobs (AnalyzeReport, ModerateReview, TranslateContent)
// dispatched from controllers. Queue worker runs persistently via Windows Startup script.
// Keep this as a fallback cleanup if you want re-scan disabled content:
// Schedule::command('ai:orchestrate')->everyMinute();

// Auto-expire reward offers whose end time passed (also runs lazily on list/API calls)
Schedule::command('offers:expire')->everyMinute()->withoutOverlapping();

// Auto-pause ad campaigns whose end time passed (also guarded lazily by isServable)
Schedule::command('ads:expire')->everyMinute()->withoutOverlapping();

// BIPAD API Sync - runs every minute to fetch latest incidents & alerts
Schedule::job(new SyncBipadData('both'))->everyMinute()->withoutOverlapping();

// Data retention purge - runs daily at 3 AM
Schedule::command('data:purge')->dailyAt('03:00')->withoutOverlapping();

// Reverse invalid self-reward coin credits (report owner rewarded on own report).
// Safety net behind the prevention layer; bounded scan, append-only reversals.
Schedule::command('coins:reconcile-self-rewards')->dailyAt('03:30')->withoutOverlapping();
