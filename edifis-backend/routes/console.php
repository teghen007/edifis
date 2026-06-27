<?php

use Illuminate\Support\Facades\Schedule;

if (app()->environment('production')) {
    Schedule::command('edifis:monitor-node-status')->everyFiveMinutes();
}

Schedule::command('edifis:sync')->everyMinute()
    ->runInBackground()
    ->withoutOverlapping();

// Nightly database backup (spatie/laravel-backup) + daily cleanup of old backups.
Schedule::command('backup:run --only-db')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('backup:clean')->dailyAt('02:30');

// Refresh health checks (DB, Redis, disk, queue) every 5 minutes.
Schedule::command('health:check')->everyFiveMinutes();

