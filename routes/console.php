<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check-in instructions — fires hourly, picks bookings with check-in inside
// the next ~24h that haven't been notified yet. Per-tenant lead time is
// honoured inside the command.
Schedule::command('wa:dispatch-checkin-instructions')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
