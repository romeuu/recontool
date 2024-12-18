<?php

namespace App\Console\Commands;

use App\Models\Program;
use App\Models\Wildcard;
use App\Models\OutOfScope;
use App\Models\InScopeIp;


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
                            {in_scope_ips? : List of range of ips in scope separated by commas (ej. 192.168.1.1, 192.168.255.255)}
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
                'wildcard' => trim($out_of_scope_item),
            ]);
            $this->info("Assigning out_of_scope {$out_of_scope_item}");
         }

        // Create the in_scope_ips
        if ($this->argument('in_scope_ips')) {
            $in_scope_ips = explode(',', $this->argument('in_scope_ips'));
            if (count($in_scope_ips) % 2 != 0) {
                $this->error('The list of in_scope_ips has to be a list of ranges of IPs, for example: 192.168.1.1, 192.168.255.255');
                return Command::FAILURE;
            }
            for ($i = 0; $i < count($in_scope_ips); $i+=2) {
                InScopeIp::create([
                    'program_id' => $program->id,
                    'ip_start' => $in_scope_ips[$i],
                    'ip_end' => $in_scope_ips[$i+1],
                ]);
                $this->info("Assigning in_scope_ip range {$in_scope_ips[$i]} to {$in_scope_ips[$i+1]}");
            }
        }

        return Command::SUCCESS;
    }
}
