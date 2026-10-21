<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('uploads:clean-orphaned')->hourly();
Schedule::command('reservations:clean')->everyFiveMinutes();
Schedule::command('carts:clean-abandoned')->daily();
Schedule::command('offers:revalidate-scheduled')->everyMinute();
Schedule::command('orders:expire-unpaid')->everyFifteenMinutes();
Schedule::command('print:expire-stale-quotes')->hourly();
Schedule::command('print:clean-old-files')->daily();
