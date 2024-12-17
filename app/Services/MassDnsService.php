<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class MassDnsService
{
    protected $massdnsPath;

    public function __construct(string $massdnsPath)
    {
        $this->massdnsPath = $massdnsPath;
    }

    /**
     * Run MassDNS to resolve subdomains.
     *
     * @param array $subdomains List of subdomains to resolve
     * @return array Results in [subdomain => ip] format
     */
    public function resolveSubdomains(array $subdomains): array
    {
        $tempInputFile = tempnam(sys_get_temp_dir(), 'massdns_input_');
        file_put_contents($tempInputFile, implode("\n", $subdomains));

        $command = [
            $this->massdnsPath,
            '-r', '8.8.8.8',
            '-o', 'J',
            $tempInputFile
        ];

        $process = new Process($command);
        $process->setTimeout(300);

        try {
            $process->mustRun();
        } catch (\Exception $e) {
            throw new \RuntimeException("MassDNS execution failed: " . $e->getMessage());
        }

        return $this->parseOutput($process->getOutput());
    }

    /**
     * Parse MassDNS output to extract subdomain and IP.
     *
     * @param string $output JSON output from MassDNS
     * @return array Processed results [subdomain => ip]
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

