<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Program;
use Illuminate\Support\Facades\Log;
use App\Models\Directory;

class RunFfuf extends Command
{
    protected $signature = 'scan:ffuf';
    protected $description = 'Run Ffuf scan for discovered hosts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $programs = Program::with('hosts')->get();

        foreach ($programs as $program) {
            $this->info("Processing program: {$program->name}");

            // Filtra los hosts no escaneados
            $hosts = $program->hosts()->whereNull('scanned_dir_at')->get();

            if ($hosts->isEmpty()) {
                $this->info("No unscanned hosts found for program {$program->name}");
                continue;
            }

            foreach ($hosts as $host) {
                $this->info("Scanning host: {$host->url}");
                Log::info("Scanning host: {$host->url}");

                $wordlistPath = storage_path('app/wordlists/default.txt');
                $outputFile = storage_path("app/public/ffuf_results/{$host->id}.json");

                // Ejecuta Ffuf
                $command = "ffuf -u {$host->url}/FUZZ -w $wordlistPath -mc 200,401,403 -o $outputFile -of json -rate 30 -sa";
                shell_exec($command);

                // Procesa los resultados de Ffuf
                if (file_exists($outputFile)) {
                    $results = json_decode(file_get_contents($outputFile), true);

                    $urls = [];

                    foreach ($results['results'] as $key => $result) {
                        if (isset($result['url'])) {
                            $urls[] = $result['url'];
                        }

                        $path = $result['input']['FUZZ'];
                        $status_code = $result['status'];
                        
                        Log::info("Result found: {$path} for host: {$host->url} with status code: {$status_code}");

                        if ($path) {
                            Directory::create([
                                'host_id' => $host->id,
                                'path' => $path,
                                'host_url' => $host->url,
                                'status_code' => $status_code
                            ]);
                        }
                    }
                }

                // Marca el host como escaneado
                $host->update(['scanned_dir_at' => now()]);
            }
        }

        $this->info('Ffuf scan completed for all programs.');
    }

    private function extractPathFromFfufOutput($line)
    {
        if (preg_match('/^\/[^\s]+/', $line, $matches)) {
            return $matches[0];
        }

        return null;
    }
}
