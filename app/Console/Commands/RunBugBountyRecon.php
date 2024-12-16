<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Program;
use App\Models\Subdomain;
use App\Models\OutOfScope;

class RunBugBountyRecon extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recon:bugbounty';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automates recon in bug bounty programs every hour.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Obtain the list of programs
        $programs = Program::all();

        $bar = $this->output->createProgressBar(count($programs));
        $bar->start();
        foreach ($programs as $program) {
            $wildcards = $program->wildcards()->get();

            foreach($wildcards as $wildcard) {
                $this->info("Starting search for wildcard: $wildcard->wildcard");

                // Execute assetfinder
                $assetFinderOutput = shell_exec("assetfinder --subs-only $wildcard->wildcard");
                
                $this->info($assetFinderOutput);
                // Saving in .txt
                $assetFinderFilePath = storage_path("app/assetfinder_{$wildcard->wildcard}.txt");
                file_put_contents($assetFinderFilePath, $assetFinderOutput);
    
                
                $this->info("Testing subdomains with httprobe");
    
                // Execute httprobe to check subdomains
                $httprobeOutput = shell_exec("cat $assetFinderFilePath | httprobe");
    
                // Save valid subdomains to the database
                $validSubdomains = explode("\n", $httprobeOutput);
    
                $this->info("Found " . count($validSubdomains) . " valid subdomains.");
    
                foreach ($validSubdomains as $subdomain) {
                    if (!empty($subdomain)) {
                        $isOutOfScopeExact = OutOfScope::where('wildcard', $subdomain)->exists();
    
                        if ($isOutOfScopeExact) {
                            $this->info("The exact subdomain $subdomain is out of scope and will not be saved.");
                            continue;
                        }
    
                        // (e.g., *.post.ch)
                        $wildcardMatch = OutOfScope::where('subdomain', 'like', "%$subdomain%")->exists();
    
                        if ($wildcardMatch) {
                            $this->info("The subdomain $subdomain matches a wildcard out of scope and will not be saved.");
                            continue;
                        }
    
                        Subdomain::create([
                            'program_id' => $program->id,
                            'subdomain' => $subdomain,
                        ]);
                        $this->info("Valid subdomain found: $subdomain");
                    }
                }
    
                // Clean up temp files
                unlink($assetFinderFilePath);
    
                $this->info('Completed recon for program ' . $program->name . '.');
                $bar->advance();
            }
        }
        $this->info('Recon completed.');
        $bar->finish();
        return Command::SUCCESS;
    }
}
