<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Program;
use App\Models\Subdomain;
use App\Models\OutOfScope;
use App\Models\InScopeIp;
use Symfony\Component\Process\Process;

use App\Services\MassDnsService;
use Exception;
use App\Services\SubdomainFilterService;

class RunBugBountyRecon extends Command
{
    protected $signature = 'recon:bugbounty';
    protected $description = 'Automates recon in bug bounty programs every hour.';
    protected $massDnsService;
    protected $subdomainFilterService;

    public function __construct(MassDnsService $massDnsService, SubdomainFilterService $subdomainFilterService)
    {
        parent::__construct();
        $this->massDnsService = $massDnsService;
        $this->subdomainFilterService = $subdomainFilterService;
    }

    public function handle()
    {
        $programs = Program::all();
        $bar = $this->output->createProgressBar(count($programs));
        $bar->start();

        foreach ($programs as $program) {
            $this->processProgram($program);
            $bar->advance();
        }

        $this->info('Recon completed.');
        $bar->finish();
        return Command::SUCCESS;
    }

    protected function processProgram($program)
    {
        $wildcards = $program->wildcards()->get();

        try {

            $pathFolder = escapeshellarg(storage_path('app/private/'.$program->name));
            shell_exec("mkdir $pathFolder");

            $wildcardsFile = storage_path('app/private/'.$program->name.'/wildcards.txt');
            $this->exportWildcardsToFile($wildcardsFile, $program);

            $domainsFile = storage_path('app/private/'.$program->name.'/domains.txt');
            $this->runAssetFinder($wildcardsFile, $domainsFile);

            $hasIpsInScope = InScopeIp::where('program_id', $program->id)->exists();

            if ($hasIpsInScope) {
                $resultsAmassFilePath = storage_path('app/private/'.$program->name.'/amass-results.txt');
                $resolversFilePath = storage_path('app/private/resolvers.txt');

                $this->runAmass($domainsFile, $resultsAmassFilePath, $resolversFilePath);

                $validSubdomains = $this->subdomainFilterService->filterValidSubdomainsIP($program);

                $this->info(print_r($validSubdomains, true));
            }


        } catch (\Exception $e) {
            $this->error('Error during recon process: ' . $e->getMessage());
        }
    }

    private function exportWildcardsToFile(string $filePath, $program) {
        $wildcards = $program->wildcards()->get();

        $wildcardsList = $wildcards->pluck('wildcard')->toArray();

        $wildcardsString = implode("\n", $wildcardsList);

        file_put_contents($filePath, $wildcardsString, FILE_USE_INCLUDE_PATH);

        if (!file_exists($filePath) || filesize($filePath) === 0) {
            throw new \Exception('No wildcards found to export.');
        }

        $this->info('Wildcards exported to ' . $filePath);
    }

    private function runAssetFinder($wildcardsFile, $domainsFile) {
        $this->info("Starting recon process with assetfinder...");

        $assetfinder = new Process(['assetfinder', '-subs-only']);
        $anew = new Process(['anew', $domainsFile]);

        // 1. Lee el contenido del archivo wildcards
        $wildcards = file_get_contents($wildcardsFile);

        if (!$wildcards) {
            throw new \Exception('Wildcards file is empty or not readable.');
        }

        // 2. Ejecuta assetfinder con los wildcards como entrada
        $assetfinder->setInput($wildcards);
        $assetfinder->setTimeout(600); // 10 minutos

        $assetfinder->run();

        if (!$assetfinder->isSuccessful()) {
            throw new \Exception('Assetfinder failed: ' . $assetfinder->getErrorOutput());
        }

        $this->info("Appending results with anew...");

        // 3. Pasa la salida de assetfinder a anew
        $anew->setInput($assetfinder->getOutput());
        $anew->setTimeout(600);

        $anew->run();

        if (!$anew->isSuccessful()) {
            throw new \Exception('Anew failed: ' . $anew->getErrorOutput());
        }

        $this->info('Assetfinder finished successfully.');
    }

    private function runAmass($subdomainsFilePath, $resultsAmassFilePath, $resolversFilePath) {
        $process = new Process([
            'massdns',
            '-r', $resolversFilePath,
            '-o', 'S',
            '-t', 'A',
            $subdomainsFilePath
        ]);
        $process->setTimeout(600);

        try {
            $process->mustRun();
            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();

            file_put_contents($resultsAmassFilePath, $output, FILE_USE_INCLUDE_PATH);

            $anew = new Process(['anew', $resultsAmassFilePath]);

            $anew->setInput(file_get_contents($resultsAmassFilePath));
            $anew->setTimeout(600);

            $anew->run();

            if (!$anew->isSuccessful()) {
                throw new \Exception('Anew failed: ' . $anew->getErrorOutput());
            }

            $this->info('Amass finished successfully.');
        } catch (Exception $exception) {
            // Manejo de errores si el proceso falla
            echo "Error while running Amass: " . $exception->getMessage();
        }
    }
}