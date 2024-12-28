<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Program;
use App\Models\Host;
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

    public function __construct()
    {
        parent::__construct();
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
            $messages = [];

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
                    $messages[$program->name] = $message;
                } else {
                    $messages[$program->name] = "No new hosts found for {$program->name}.";
                    Log::info("No new hosts found for {$program->name}.");
                }
            }

            Log::info('Host monitoring completed.');
            $this->output->writeln(json_encode($messages));

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