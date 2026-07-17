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
