<?php

use Illuminate\Support\Facades\App;
use App\Providers\AppServiceProvider;
use App\Providers\TelegramServiceProvider;

return [
    AppServiceProvider::class,
    TelegramServiceProvider::class,
];
