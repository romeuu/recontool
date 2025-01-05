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
                    $results = file($outputFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                    foreach ($results as $line) {
                        $path = $this->extractPathFromFfufOutput($line);
                        Log::info("Result found: {$path} for host: {$host->url}");

                        if ($path) {
                            Directory::create([
                                'host_id' => $host->id,
                                'path' => $path,
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
