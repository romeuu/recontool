<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Program;
use App\Models\Wildcard;

class AddScopeToProgram extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'program:add-scope 
                            {--program_id= : ID of program}
                            {--wildcard= : The scope to add (subdomain, etc)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adds new scope to program';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $programId = $this->option('program_id');
        $scopeValue = $this->option('wildcard');

        $program = Program::find($programId);

        if (!$program) {
            $this->error('Program not found.');
            return Command::FAILURE;
        }

        $wildcard = Wildcard::create([
            'program_id' => $program->id,
            'wildcard' => $scopeValue
        ]);

        $this->info("Wildcard {$wildcard->wildcard} added to program {$program->name}.");
    }
}
