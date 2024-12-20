<?php

use App\Console\Commands\MonitorHosts;
use App\Console\Commands\RunBugBountyRecon;
use App\Console\Commands\MonitorSubdomains;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

if (env('APP_ENV') === 'PROD') {
    Schedule::command(RunBugBountyRecon::class, [])->everyTwoHours();

    //Schedule::command(MonitorSubdomains::class, [])->everyTwoHours();
    Schedule::command(MonitorHosts::class, [])->everyTwoHours();
}