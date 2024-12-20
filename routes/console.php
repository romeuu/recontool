<?php

use App\Console\Commands\RunBugBountyRecon;
use App\Console\Commands\MonitorSubdomains;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

if (env('APP_ENV') === 'PROD') {
    Artisan::command('recon:bugbounty', function () {
        $this->info('Comando recon:bugbounty ejecutado');
    })->everyTwoHours()->withoutOverlapping(30);    

    Artisan::command('app:monitor-subdomains', function () {
       Log::info('Comando app:monitor-subdomains ejecutado');
    })->describe('Descripción del comando.')->everyMinute()->withoutOverlapping(30)->emailOutputTo('s1lentzzz@proton.me');
}