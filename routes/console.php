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

// Pre-checkout reminders — fires hourly, picks bookings whose checkout is
// inside each tenant's "X hours before" window and sends the host's checkout
// guidelines via WhatsApp. Per-tenant lead time is honoured inside the command.
Schedule::command('wa:dispatch-checkout-reminders')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Payment lifecycle — chases + auto-cancels unpaid booking fees and balances
// per each tenant's payment policy. Hourly so the hours-based fee window is
// honoured; the balance reminder/cancel are date-based and fire once.
Schedule::command('bookings:process-payment-lifecycle')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Subscription billing — issues the next RM 49 bill ahead of a trial/period
// ending, chases past-due tenants inside their grace window, and voids invoices
// for anyone who has fallen back to free. Runs BEFORE the lifecycle command so a
// tenant is always given a bill and a chance to pay before anything expires.
// No-ops entirely when platform billing has no credentials.
Schedule::command('subscriptions:bill-cycle')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

// Subscription lifecycle — expires trials, lapses unpaid paid periods into their
// grace window, and downgrades to free once grace runs out. Daily is enough:
// every window is measured in days, and comped accounts are skipped.
Schedule::command('subscriptions:process-lifecycle')
    ->dailyAt('02:15')
    ->withoutOverlapping()
    ->onOneServer();
