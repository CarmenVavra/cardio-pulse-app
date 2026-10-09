<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 | Geplante Aufgaben – am Server ruft eine geplante Aufgabe alle 5 Minuten
 | `php artisan schedule:run` auf (siehe README, „Geplante Aufgabe einrichten“).
 */
Schedule::command('cardiopulse:send-reminders')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('cardiopulse:backup')->dailyAt('02:00');
