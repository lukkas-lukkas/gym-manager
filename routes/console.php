<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('enrollments:roll-over')
    ->dailyAt('00:01')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->onOneServer();
