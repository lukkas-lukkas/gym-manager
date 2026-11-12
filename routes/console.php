<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Runs every hour (at minute 1) even though enrollments only expire at midnight: the command is
 * idempotent, so extra runs are no-ops, and a failed 00:01 run is retried the same day.
 */
Schedule::command('enrollments:roll-over')
    ->hourlyAt(1)
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping(60)
    ->onOneServer();
