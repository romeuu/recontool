<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Program;
use App\Models\Directory;
use Illuminate\Support\Facades\Log;

class RunGobuster extends Command
{
    protected $signature = 'scan:gobuster';
    protected $description = 'Run Gobuster scan for discovered hosts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $programs = Program::with('hosts')->get();

        foreach ($programs as $program) {
            $this->info("Processing program: {$program->name}");

            // Filtra los hosts no escaneados
            $hosts = $program->hosts()->whereNull('scanned_at')->get();

            if ($hosts->isEmpty()) {
                $this->info("No unscanned hosts found for program {$program->name}");
                continue;
            }

            foreach ($hosts as $host) {
                $this->info("Scanning host: {$host->url}");
                Log::info("Scanning host: {$host->url}");

                $wordlistPath = storage_path('app/wordlists/default.txt');
                $outputFile = storage_path("app/public/gobuster_results/{$host->url}.txt");

                // Ejecuta Gobuster
                $command = "gobuster dir -u {$host->url} -w $wordlistPath -s 200 -o $outputFile -q";
                shell_exec($command);

                // Procesa los resultados de Gobuster
                if (file_exists($outputFile)) {
                    $results = file($outputFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                    foreach ($results as $line) {
                        $path = $this->extractPathFromGobusterOutput($line);
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

        $this->info('Gobuster scan completed for all programs.');
    }

    private function extractPathFromGobusterOutput($line)
    {
        if (preg_match('/^\/[^\s]+/', $line, $matches)) {
            return $matches[0];
        }

        return null;
    }
}
