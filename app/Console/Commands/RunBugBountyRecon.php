<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Program;
use App\Models\Subdomain;
use App\Models\OutOfScope;
use App\Models\InScopeIp;

use App\Services\MassDnsService;

class RunBugBountyRecon extends Command
{
    protected $signature = 'recon:bugbounty';
    protected $description = 'Automates recon in bug bounty programs every hour.';
    protected $massDnsService;

    public function __construct(MassDnsService $massDnsService)
    {
        parent::__construct();
        $this->massDnsService = $massDnsService;
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

        foreach ($wildcards as $wildcard) {
            $this->info("Starting search for wildcard: {$wildcard->wildcard}");
            $assetFinderOutput = $this->runAssetFinder($wildcard->wildcard);
            $validSubdomains = $this->runHttprobe($wildcard->wildcard, $assetFinderOutput);
            $this->processSubdomains($program, $validSubdomains);
            $this->info('Completed recon for program ' . $program->name . '.');
        }
    }

    protected function runAssetFinder($wildcard)
    {
        $output = shell_exec("assetfinder --subs-only $wildcard");
        $this->info($output);

        $filePath = storage_path("app/assetfinder_{$wildcard}.txt");
        file_put_contents($filePath, $output);
        return $filePath;
    }

    protected function runHttprobe($wildcard, $filePath)
    {
        $this->info("Testing subdomains with httprobe");
        $output = shell_exec("cat $filePath | httprobe -c 80 --prefer-https");
        $validSubdomains = explode("\n", $output);
        $this->info("Found " . count($validSubdomains) . " valid subdomains.");
        unlink($filePath);
        return $validSubdomains;
    }

    protected function processSubdomains($program, $subdomains)
    {
        $hasIpsInScope = InScopeIp::where('program_id', $program->id)->exists();

        $cleanedSubdomains = array_map(function ($subdomain) {
            return preg_replace('~^https?://~', '', $subdomain);
        }, $subdomains);

        $subdomainsToProcess = $hasIpsInScope
            ? $this->massDnsService->resolveSubdomains($cleanedSubdomains)
            : $subdomains;

        foreach ($subdomainsToProcess as $subdomain => $ip) {
            if (!empty($subdomain) && $this->isValidSubdomain($subdomain, $ip ?? null)) {
                Subdomain::create([
                    'program_id' => $program->id,
                    'subdomain' => $subdomain,
                ]);
                $this->info("Valid subdomain found: $subdomain");
            }
        }
        
    }

    protected function isValidSubdomain($subdomain, $ip = null)
    {
        if ($ip) {
            $this->info("The IP address for $subdomain is $ip.");
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $this->info("The IP address for $subdomain could not be resolved.");
                return false;
            }
    
            $ipBinary = inet_pton($ip);
            if (!InScopeIp::whereRaw('? BETWEEN ip_start AND ip_end', [$ipBinary])->exists()) {
                $this->info("The subdomain $subdomain with IP $ip is out of scope.");
                return false;
            }
        }

        if (OutOfScope::where('wildcard', $subdomain)->exists() ||
            OutOfScope::where('subdomain', 'like', "%$subdomain%")->exists()) {
            $this->info("The subdomain $subdomain is out of scope and will not be saved.");
            return false;
        }

        return true;
    }
}

