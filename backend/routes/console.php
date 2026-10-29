<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| All schedules now live in App\Support\AutomationRegistry and are registered
| by App\Support\AutomationSchedule in bootstrap/app.php. That single list
| powers the admin Automation page (visibility + enable/disable controls).
|
| Do not add Schedule:: calls here — they would not appear in the admin panel.
*/
