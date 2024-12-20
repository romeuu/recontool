<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Registrar comandos en Telegram
        Telegram::setMyCommands([
            ['command' => 'hosts', 'description' => 'Get the hosts of a program'],
        ]);
    }
}
