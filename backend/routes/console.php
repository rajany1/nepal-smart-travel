<?php

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
