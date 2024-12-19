<?php
namespace App\Services;

use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class TelegramBotService
{
    protected $telegram;

    public function __construct()
    {
        $this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
    }

    public function handleUpdates()
    {
        $updates = $this->telegram->getUpdates();

        foreach ($updates as $update) {
            $this->processMessage($update);
        }
    }

    protected function processMessage(Update $update)
    {
        $userId = $update->getMessage()->getFrom()->getId();

        if ($userId != env('TELEGRAM_USER_ID')) {
            $this->sendMessage($userId, 'You are not authorized to receive this message.');
        }
    }

    // Enviar mensaje al bot
    public function sendMessage($chatId, $message)
    {
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
        ]);
    }

    // Enviar mensaje a múltiples usuarios o canales
    public function broadcastMessage($message)
    {
        $chatIds = [];

        foreach ($chatIds as $chatId) {
            $this->sendMessage($chatId, $message);
        }
    }
}
?>
