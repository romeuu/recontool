<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Host extends Model
{
    protected $fillable = [
        'program_id',
        'url',
        'subdomain',
        'is_alive',
        'notes',
        'scanned_dir_at'
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function subdomain()
    {
        return $this->belongsTo(Subdomain::class);
    }
}
