<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Api;

class TelegramPollingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:telegram-polling-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $telegram = new Api(getenv('TELEGRAM_BOT_TOKEN'));

        while (true) {
            $updates = $telegram->getUpdates();

            foreach ($updates as $update) {
                $message = $update->getMessage();
                $chatId = $message->getChat()->getId();
                $text = $message->getText();

                if ($text == '/start') {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Beep Boop...'
                    ]);
                }
            }
            sleep(1);
        }
    }
}
