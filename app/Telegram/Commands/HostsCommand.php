<?php
namespace App\Telegram\Commands;

use Telegram\Bot\Commands\Command;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Models\Program;
use App\Models\Subdomain;
use App\Models\Host;

class HostsCommand extends Command
{
    protected string $name = 'hosts';
    protected array $aliases = ['host'];
    protected string $description = 'Retrieve the hosts of a program';

    public function handle()
    {
        // Obtén los argumentos enviados con el comando
        $arguments = $this->getArguments();
        $programName = $arguments[0] ?? null;

        if (!$programName) {
            $this->replyWithMessage(['text' => "Please, specify the program name. Example: /hosts <program_name>"]);
            return;
        }

        // Buscar el programa en la base de datos
        $program = Program::where('name', $programName)->first();
        if (!$program) {
            $this->replyWithMessage("No program found for '$programName'.");
            return;
        }

        $hosts = Host::where('program_id', $program->id)->pluck('url');

        if ($hosts->isEmpty()) {
            $this->replyWithMessage("No hosts were found for '$programName'.");
            return;
        }

        $filename = storage_path("app/private/telegram_hosts_{$programName}.txt");
        file_put_contents($filename, $hosts->implode("\n"));

        $this->replyWithDocument([
            'document' => $filename,
            'caption' => "Hosts of program '$programName'",
        ]);

        unlink($filename);
    }
}
