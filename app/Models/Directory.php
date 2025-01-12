<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Directory extends Model
{
    protected $fillable = [
        'host_id',
        'host_url',
        'path',
        'status_code'
    ];

    public function host()
    {
        return $this->belongsTo(Host::class);
    }
}
