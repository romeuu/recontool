<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Telegram\Commands\HostsCommand;
use Telegram\Bot\Objects\BotCommand;

class TelegramServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $commands = new BotCommand(
            ['command' => 'hosts', 'description' => 'Get the hosts of a program']
        );

        Telegram::addCommand(HostsCommand::class);

        // Registrar comandos en Telegram
        Telegram::setMyCommands(['commands' => [$commands]]);
    }
}
