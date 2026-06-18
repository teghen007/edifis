<?php

use Illuminate\Support\Facades\Schedule;

if (app()->environment('production')) {
    Schedule::command('edifis:monitor-node-status')->everyFiveMinutes();
}

Schedule::command('edifis:sync')->everyMinute()
    ->runInBackground()
    ->withoutOverlapping();

