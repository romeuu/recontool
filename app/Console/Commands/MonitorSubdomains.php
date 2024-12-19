<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TelegramBotService;
use App\Models\Program;
use App\Models\Subdomain;

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
        $programs = Program::all();

        foreach ($programs as $program) {
            $subdomains = Subdomain::where('program_id', $program->id)->get();
            $newSubdomains = $this->getNewSubdomains($program, $subdomains);

            if ($newSubdomains->isNotEmpty()) {
                $message = "New subdomains found for program {$program->name}: \n";
                foreach ($newSubdomains as $subdomain) {
                    $message .= $subdomain->subdomain . "\n";
                }
                $this->telegramService->sendMessage(getenv('TELEGRAM_CHAT_ID'), $message);
            }
        }

        $this->info('Subdomain monitoring completed.');
    }

    private function getNewSubdomains($program, $subdomains)
    {
        return $subdomains->filter(function ($subdomain) {
            return $subdomain->created_at->gt(now()->subHours(2));
        });
    }
}
