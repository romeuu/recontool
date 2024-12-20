<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class MassDnsService
{
    /**
     * Runs MassDNS to resolve subdomains.
     *
     * @param array $subdomains List of subdomains to resolve
     * @return array Results in [subdomain => ip] format
     */
    public function resolveSubdomains(array $subdomains): array
    {
        // Create a temporary file with the subdomains
        $storagePath = storage_path('app/private');

        $tempInputFile = $storagePath . '/massdns_input_' . uniqid();

        file_put_contents($tempInputFile, implode("\n", $subdomains));

        $resolverFilePath = storage_path('app/private/resolvers.txt');

        // MassDNS command (using 8.8.8.8 as public DNS resolver)
        $command = [
            'massdns',
            '-r', $resolverFilePath,
            '-o', 'S',
            '-t', 'A',
            $tempInputFile
        ];

        // Run the process
        $process = new Process($command);
        $process->setTimeout(300); // 5 minutes timeout

        try {
            $process->mustRun();
        } catch (\Exception $e) {
            throw new \RuntimeException("MassDNS execution failed: " . $e->getMessage());
        }

        return $this->parseOutput($process->getOutput());
    }

    /**
     * Parse MassDNS output and extract subdomain and IP.
     *
     * @param string $output JSON produced by MassDNS
     * @return array Results in [subdomain => ip] format
     */
    protected function parseOutput(string $output): array
    {
        $subdomainIps = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            // Solo procesar líneas que contienen registros A (IPv4) o AAAA (IPv6)
            if (preg_match('/(\S+)\s+\S+\s+\S+\s+(\S+)/', $line, $matches)) {
                $subdomainIps[] = [
                    'subdomain' => $matches[1],
                    'ip' => $matches[2]
                ];
            }
        }

        return $subdomainIps;
    }
}