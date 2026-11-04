<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('requirements:notify-overdue')->daily();

// Employees added from registrations: merge the ones HR's monthly list now
// includes, and restore rows the re-import removed.
Schedule::command('employees:sync-provisional')->daily()->withoutOverlapping();
