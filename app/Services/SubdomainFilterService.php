<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Models\OutOfScope;

class SubdomainFilterService
{
    public function filterValidSubdomainsIP($program)
    {
        // Read the file with the IPs and subdomains
        $filePath = storage_path('app/private/'.$program->name.'amass-results.txt');  // File path
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // Dictionary to store subdomains and IPs
        $subdomainsIps = [];

        // Process the lines to obtain subdomain and IP
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 3 && $parts[1] === "A") {
                $subdomain = $parts[0];
                $ip = $parts[2];
                $subdomainsIps[$subdomain] = $ip;
            }
        }

        // Filter the subdomains within the allowed range
        $validSubdomains = [];
        foreach ($subdomainsIps as $subdomain => $ip) {
            if ($this->isIpInScope($ip, $program)) {
                $validSubdomains[] = rtrim($subdomain, '.');
            }
        }

        $itemsOutOfScope = OutOfScope::where('program_id', $program->id)->get();

        foreach ($itemsOutOfScope as $item) {
            foreach($validSubdomains as $key => $subdomain) {
                if (str_contains($subdomain, $item->wildcard)) {
                    unset($validSubdomains[$key]);
                }
            }
        }

        // Save the valid subdomains to a new file
        $outputFile = storage_path('app/private/'.$program->name.'subdominios_validos.txt');
        File::put($outputFile, implode("\n", $validSubdomains));

        echo "Found " . count($validSubdomains) . " subdomains within the allowed range.";

        return $validSubdomains;
    }

    // Function to check if an IP is within the range in the database
    private function isIpInScope($ip, $program)
    {
        // Convert the subdomain IP to binary format
        $ipLong = ip2long($ip);
        
        // Get the IP ranges from the database (InScopeIps)
        $ipsInScope = DB::table('InScopeIps')->get(['ip_start', 'ip_end'])->where('program_id', $program->id);

        foreach ($ipsInScope as $range) {
            // Compare the IP with the ranges (ip_start and ip_end are stored in binary)
            if ($ipLong >= $range->ip_start && $ipLong <= $range->ip_end) {
                return true; // The IP is within the range
            }
        }

        return false; // The IP is not within the range of any entry in the database
    }

    private function filterValidSubdomains($program) {
        $filePath = storage_path('app/private/'.$program->name.'resultados');  // File path
        $subdomains = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $itemsOutOfScope = OutOfScope::where('program_id', $program->id)->get();

        foreach ($itemsOutOfScope as $item) {
            foreach($subdomains as $key => $subdomain) {
                if (str_contains($subdomain, $item->wildcard)) {
                    unset($subdomains[$key]);
                }
            }
        }

        // Save the valid subdomains to a new file
        $outputFile = storage_path('app/private/'.$program->name.'subdominios_validos.txt');
        File::put($outputFile, implode("\n", $subdomains));

        echo "Found " . count($subdomains) . " subdomains within the allowed range.";

        return $subdomains;
    }
}

