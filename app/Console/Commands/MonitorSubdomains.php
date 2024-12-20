<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TelegramBotService;
use App\Models\Program;
use App\Models\Subdomain;
use Illuminate\Support\Facades\Log;

class MonitorSubdomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:monitor-subdomains';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor subdomains and send updates via Telegram';
    
    protected $telegramService;

    public function __construct(TelegramBotService $telegramService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('El comando ' . $this->signature . ' se ejecutó correctamente.');

        $programs = Program::all();

        foreach ($programs as $program) {
            $subdomains = Subdomain::where('program_id', $program->id)->get();
            $newSubdomains = $this->getNewSubdomains($program, $subdomains);

            $filePath = storage_path('app/private/'.$program->name.'/subdomains-telegram.txt');
            file_put_contents($filePath, $newSubdomains->pluck('subdomain')->implode("\n"), FILE_USE_INCLUDE_PATH);

            if ($newSubdomains->isNotEmpty()) {
                $message = "New subdomains found for program {$program->name}: \n";
                foreach ($newSubdomains as $subdomain) {
                    $message .= $subdomain->subdomain . "\n";
                }
                $this->telegramService->sendMessage(getenv('TELEGRAM_CHAT_ID'), $message);
                //$this->telegramService->sendFileToUser(getenv('TELEGRAM_CHAT_ID'), $filePath);
            } else {
                $this->telegramService->sendMessage(getenv('TELEGRAM_CHAT_ID'), "No new subdomains found for {$program->name}.");
            }
        }

        $this->info('Subdomain monitoring completed.');
    }

    private function getNewSubdomains($program, $subdomains)
    {
        return $subdomains->filter(function ($subdomain) {
            return $subdomain->created_at->gt(now()->subHours(2));
        })->take(10);
    }
}