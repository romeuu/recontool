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
        $tempInputFile = tempnam(sys_get_temp_dir(), 'massdns_input_');
        file_put_contents($tempInputFile, implode("\n", $subdomains));

        // MassDNS command (using 8.8.8.8 as public DNS resolver)
        $command = [
            'massdns',
            '-r', '8.8.8.8',
            '-o', 'J',
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
        $lines = explode("\n", $output);
        $results = [];

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $decoded = json_decode($line, true);
            if (isset($decoded['name'], $decoded['data'])) {
                $subdomain = $decoded['name'];
                $ip = $decoded['data'];
                $results[$subdomain] = $ip;
            }
        }

        return $results;
    }
}

