<?php

namespace App\Console\Commands;

use App\Models\Program;
use App\Models\Wildcard;
use App\Models\OutOfScope;


use Illuminate\Console\Command;

class InitProgram extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'program:init 
                            {name : The name of the program.} 
                            {wildcards : List of wildcards separated by commas (ej. *.example.com,*.test.com)}
                            {out_of_scope : List of wildcards or terms out of scope separated by commas (ej. *.incamail-dev.com,*.test.com, test)}
                            {description? : Optional description of the program.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = trim($this->argument('name'));
        $wildcards = explode(',', $this->argument('wildcards'));
        $out_of_scope = explode(',', $this->argument('out_of_scope'));
        $description = trim($this->argument('description')) ?? '';

        // Validate that the program doesn't exist already.
        if (Program::where('name', $name)->exists()) {
            $this->error('There is already a program with the same name.');
            return Command::FAILURE;
        }

        // Create the program
        $program = Program::create([
            'name' => $name,
            'description' => $description,
        ]);

        $this->info("Creating program {$name}.");

        // Create the wildcards
        foreach($wildcards as $wildcard) {
            Wildcard::create([
                'program_id' => $program->id,
                'wildcard' => trim($wildcard),
            ]);
            $this->info("Assigning wildcard {$wildcard}");
        }

         // Create the out_of_scope items
         foreach($out_of_scope as $out_of_scope_item) {
            OutOfScope::create([
                'program_id' => $program->id,
                'out_of_scope' => trim($out_of_scope_item),
            ]);
            $this->info("Assigning out_of_scope {$out_of_scope_item}");
         }

        return Command::SUCCESS;
    }
}
