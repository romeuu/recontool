<?php

use App\Console\Commands\RunBugBountyRecon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

if (env('APP_ENV') === 'PROD') {
    Artisan::command(RunBugBountyRecon::class)->everyTwoHours()->withoutOverlapping(30);
    Artisan::command(RunBugBountyRecon::class)->everyFourHours()->withoutOverlapping(30);
}