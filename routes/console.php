<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Form 19 weekly report reminders (deadline 11:59 PM)
|--------------------------------------------------------------------------
| 8:00 PM - "add today to your weekly report" and "submit your week" reminders
| 8:00 AM - overdue notices for weeks that ended without a submission
| Requires the Laravel scheduler to be running (php artisan schedule:run every minute).
*/
Schedule::command('internlink:send-reminders')
    ->dailyAt('20:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();

Schedule::command('internlink:send-reminders --overdue')
    ->dailyAt('08:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();
