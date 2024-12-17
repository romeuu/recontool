<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InScopeIp extends Model
{
    // Name of the table in the database
    protected $table = 'in_scope_ips';

    // Fields that can be filled massively
    protected $fillable = [
        'program_id',
        'ip_start',
        'ip_end',
        'port_start',
        'port_end',
    ];

    /**
     * Relationship with the program (Program model).
     * Adjust 'Program' to the name of your program model if it's different.
     */
    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Checks if an IP is within the allowed range.
     *
     * @param string $ip IP address (in text format, e.g., '192.168.1.1').
     * @return bool
     */
    public static function isInScope(string $ip): bool
    {
        // Convert the IP to binary
        $ipBinary = inet_pton($ip);

        // Search if it's within any allowed range
        return self::whereRaw('? BETWEEN ip_start AND ip_end', [$ipBinary])->exists();
    }

    /**
     * Checks if an IP and a port are within the allowed range.
     *
     * @param string $ip IP address (in text format, e.g., '192.168.1.1').
     * @param int|null $port Port to check (optional).
     * @return bool
     */
    public static function isInScopeWithPort(string $ip, ?int $port = null): bool
    {
        // Convert the IP to binary
        $ipBinary = inet_pton($ip);

        // Check with or without a port
        if ($port) {
            return self::whereRaw('? BETWEEN ip_start AND ip_end AND ? BETWEEN port_start AND port_end', [$ipBinary, $port])->exists();
        }

        return self::isInScope($ip);
    }
}

