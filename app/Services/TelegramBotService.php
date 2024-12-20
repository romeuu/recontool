<?php
namespace App\Services;

use App\Models\Host;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;
use Illuminate\Support\Facades\Http;
use App\Models\Program;

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
        $message = $update->getMessage()->getText();

        if ($userId != env('TELEGRAM_USER_ID')) {
            $this->sendMessage($userId, 'You are not authorized to receive this message.');
        }

        if (str_starts_with($message, '/hosts')) {
            $this->handleHostsCommand($userId, $message);
            return;
        }

        $this->sendMessage($userId, 'I don\'t understand your message...');
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

    public function sendFileToUser($filePath)
    {
        $telegramApi = 'https://api.telegram.org/bot'.env('TELEGRAM_BOT_TOKEN').'/sendDocument';
        $data = [
            'chat_id' => env('TELEGRAM_CHAT_ID'),
            'document' => new \CURLFile($filePath)
        ];

        Http::post($telegramApi, $data);
    }

    public function handleHostsCommand($chatId, $message) {
        $parts = explode(' ', $message, 2);
        if (count($parts) < 2) {
            $this->sendMessage($chatId, "Please, specify the program name. Example: /hosts <program_name>");
            return;
        }

        $programName = trim($parts[1]);

        $program = Program::where('name', $programName)->first();
        if (!$program) {
            $this->sendMessage($chatId, "No program found for '$programName'.");
            return;
        }

        $hosts = Host::where('program_id', $program->id)->pluck('url');

        if ($hosts->isEmpty()) {
            $this->sendMessage($chatId, "No hosts were found for '$programName'.");
            return;
        }

        $filename = storage_path("app/private/telegram_hosts_{$programName}.txt");
        file_put_contents($filename, $hosts->implode("\n"));

        $this->sendFileToUser($chatId, $filename);

        unlink($filename);
    }
}
?>
