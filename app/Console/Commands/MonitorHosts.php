<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Program;
use App\Models\Host;
use App\Services\TelegramBotService;
use Illuminate\Support\Facades\Log;

class MonitorHosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:monitor-hosts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        try{
            while (file_exists(storage_path('app/private/recon.lock'))) {
                $this->info('Recon is still being executed. Waiting 5 minutes...');
            
                sleep(300);
            }
            $programs = Program::all();

            foreach ($programs as $program) {
                $hosts = Host::where('program_id', $program->id)->get();
                $newHosts = $this->getNewHosts($program, $hosts);

                $filePath = storage_path('app/private/'.$program->name.'/hosts-telegram.txt');
                file_put_contents($filePath, $newHosts->pluck('url')->implode("\n"), FILE_USE_INCLUDE_PATH);

                if ($newHosts->isNotEmpty()) {
                    $message = "New hosts found for program {$program->name}: \n";
                    foreach ($newHosts as $host) {
                        $message .= $host->url . "\n";
                    }
                    $this->telegramService->sendMessage(getenv('TELEGRAM_CHAT_ID'), $message);
                    //$this->telegramService->sendFileToUser(getenv('TELEGRAM_CHAT_ID'), $filePath);
                } else {
                    $this->telegramService->sendMessage(getenv('TELEGRAM_CHAT_ID'), "No new hosts found for {$program->name}.");
                }
            }

            Log::info('Host monitoring completed.');
            $this->info('Host monitoring completed.');

        } catch (\Throwable $e) {
        Log::error('Error during command execution: ' . $e->getMessage());
        Log::error('Trace: ' . $e->getTraceAsString());
        }
        Log::info('El comando ' . $this->signature . ' se ejecutó correctamente.');
    }

    private function getNewHosts($program, $hosts)
    {
        return $hosts->filter(function ($hosts) {
            return $hosts->created_at->gt(now()->subHours(2));
        })->take(10);
    }
}
