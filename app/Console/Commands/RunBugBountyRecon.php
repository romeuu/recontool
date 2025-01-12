<?php

namespace App\Console\Commands;

use App\Models\Host;
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
        // Lock file
        file_put_contents(storage_path('app/private/recon.lock'), 'locked', FILE_USE_INCLUDE_PATH);

        $programs = Program::where('active', true)->get();
        $bar = $this->output->createProgressBar(count($programs));
        $bar->start();

        foreach ($programs as $program) {
            $this->processProgram($program);
            $bar->advance();
        }

        $this->info('Recon completed.');
        $bar->finish();
        unlink(storage_path('app/private/recon.lock'));
        return Command::SUCCESS;
    }

    protected function processProgram($program)
    {
        try {

            $pathFolder = escapeshellarg(storage_path('app/private/'.$program->name));
            shell_exec("mkdir $pathFolder");

            $wildcardsFile = storage_path('app/private/'.$program->name.'/wildcards.txt');
            $wildcardsFileFormatted = storage_path('app/private/'.$program->name.'/wildcards-formatted.txt');
            $this->exportWildcardsToFile($wildcardsFile, $wildcardsFileFormatted, $program);

            $domainsFile = storage_path('app/private/'.$program->name.'/domains_assetfinder.txt');
            $this->runAssetFinder($wildcardsFile, $domainsFile);

            $domainsFileSubfinder = storage_path('app/private/'.$program->name.'/domains_subfinder.txt');
            $this->runSubfinder($wildcardsFileFormatted, $domainsFileSubfinder);

            $this->uniteSubdomainFiles($program);

            $fullDomainsList = storage_path('app/private/'.$program->name.'/all_subs.txt');

            $hasIpsInScope = InScopeIp::where('program_id', $program->id)->exists();

            if ($hasIpsInScope) {
                $resultsAmassFilePath = storage_path('app/private/'.$program->name.'/amass-results.txt');
                $resolversFilePath = storage_path('app/private/resolvers.txt');

                $this->runAmass($fullDomainsList, $resultsAmassFilePath, $resolversFilePath);

                $validSubdomains = $this->subdomainFilterService->filterValidSubdomainsIP($program);

                $this->info("Found " . count($validSubdomains) . " subdomains within the allowed range.");

                foreach($validSubdomains as $subdomain) {
                    Subdomain::firstOrCreate(
                        [
                            'program_id' => $program->id,
                            'subdomain' => $subdomain
                        ],
                        [
                            'active' => true
                        ]
                    );
                }
            } else {
                $validSubdomains = $this->subdomainFilterService->filterValidSubdomains($program);

                $this->info("Found " . count($validSubdomains) . " subdomains within the allowed range.");
                
                foreach($validSubdomains as $subdomain) {
                    Subdomain::firstOrCreate(
                        [
                            'program_id' => $program->id,
                            'subdomain' => $subdomain
                        ],
                        [
                            'active' => true
                        ]
                    );
                }
            }

            $validSubdomainsPath = storage_path('app/private/'.$program->name.'/valid-subdomains.txt');
            $hosts = $this->runHttprobe($validSubdomainsPath, storage_path('app/private/'.$program->name.'/hosts.txt'));

            foreach ($hosts as $host) {
                $subdomain = $this->findSubdomainForHost($host, $validSubdomains);
                Host::firstOrCreate(
                    [
                        'program_id' => $program->id,
                        'url' => $host,
                    ],
                    [
                        'is_alive' => true,
                        'subdomain' => $subdomain->id ?? null
                    ]
            );
            }
        } catch (\Exception $e) {
            $this->error('Error during recon process: ' . $e->getMessage());
        }
    }

    private function exportWildcardsToFile(string $filePath, string $filePathWithoutDots, $program) {
        $wildcards = $program->wildcards()->get();

        $wildcardsList = $wildcards->pluck('wildcard')->toArray();
        $wildcardsWithoutDots = $wildcards->pluck('wildcard')
                                      ->map(fn($wildcard) => ltrim($wildcard, '.'))
                                      ->toArray();

        $wildcardsString = implode("\n", $wildcardsList);
        $wildcardsWithoutDotsString = implode("\n", $wildcardsWithoutDots);

        file_put_contents($filePath, $wildcardsString, FILE_USE_INCLUDE_PATH);
        file_put_contents($filePathWithoutDots, $wildcardsWithoutDotsString, FILE_USE_INCLUDE_PATH);

        if ((!file_exists($filePath) || filesize($filePath) === 0) || (!file_exists($filePathWithoutDots) || filesize($filePathWithoutDots) === 0)) {
            throw new \Exception('No wildcards found to export.');
        }

        $this->info('Wildcards exported to ' . $filePath);
        $this->info('Formatted wildcards exported to ' . $filePathWithoutDots);
    }

    private function runAssetFinder($wildcardsFile, $domainsFile) {
        $this->info("Starting recon process with assetfinder...");

        $assetfinder = new Process(['assetfinder', '-subs-only']);
        $anew = new Process(['anew', $domainsFile]);

        $wildcards = file_get_contents($wildcardsFile);

        if (!$wildcards) {
            throw new \Exception('Wildcards file is empty or not readable.');
        }

        $assetfinder->setInput($wildcards);
        $assetfinder->setTimeout(600);

        $assetfinder->run();

        if (!$assetfinder->isSuccessful()) {
            throw new \Exception('Assetfinder failed: ' . $assetfinder->getErrorOutput());
        }

        $this->info("Appending results with anew...");

        $anew->setInput($assetfinder->getOutput());
        $anew->setTimeout(600);

        $anew->run();

        if (!$anew->isSuccessful()) {
            throw new \Exception('Anew failed: ' . $anew->getErrorOutput());
        }

        $this->info('Assetfinder finished successfully.');
    }

    private function runSubfinder($wildcardsFile, $domainsFile) {
        $this->info("Starting recon process with subfinder...");

        $command = [
            'subfinder',
            '-dL', $wildcardsFile,
            '-silent',
            '-all',
            '-recursive',
            '-o', $domainsFile,
        ];

        $subfinder = new Process($command);

        $wildcards = file_get_contents($wildcardsFile);

        if (!$wildcards) {
            throw new \Exception('Wildcards file is empty or not readable.');
        }

        $subfinder->setTimeout(600);

        $subfinder->run();

        if (!$subfinder->isSuccessful()) {
            throw new \Exception('Subfinder failed: ' . $subfinder->getErrorOutput());
        }

        $this->info('Subfinder finished successfully.');
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

    private function runHttprobe($subdomainsFilePath, $hostsFilePath) {
        $this->info("Starting recon process with httprobe...");
    
        $subdomainsFilePath = escapeshellarg($subdomainsFilePath);
        $command = "cat {$subdomainsFilePath} | httprobe -c 200 -p http:80 -p https:443 -p http:8080 -p https:8443 -p https:8080 -p http:8443 --prefer-https";
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(1200);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("Error executing httprobe and anew: " . $process->getErrorOutput());
        }

        file_put_contents($hostsFilePath, $process->getOutput(), FILE_USE_INCLUDE_PATH);

        $anew = new Process(['anew', $hostsFilePath]);

        $anew->setInput(file_get_contents($hostsFilePath));
        $anew->setTimeout(600);

        $anew->run();

        if (!$anew->isSuccessful()) {
            throw new \Exception('Anew failed: ' . $anew->getErrorOutput());
        }

        $hosts = file($hostsFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $this->info('Httprobe finished successfully, ' . count($hosts) . ' hosts were found.');

        return $hosts;
    }

    public function findSubdomainForHost($host, $validSubdomains)
    {
        foreach ($validSubdomains as $subdomain) {
            if (strpos($host, $subdomain) !== false) {
                return Subdomain::where('subdomain', $subdomain)->first();
            }
        }

        return null;
    }

    private function uniteSubdomainFiles($program) {
        $outputDir = escapeshellarg(storage_path('app/private/'.$program->name));
        $outputFile = "{$outputDir}/all_subs.txt";

        $command = [
            'bash',
            '-c',
            "cat {$outputDir}/domains_*.txt | sort -u | anew {$outputFile}"
        ];

        $process = new Process($command);
        $process->setTimeout(60);
        $process->run();

        $this->info('United subdomains file finished successfully.');
    }
}