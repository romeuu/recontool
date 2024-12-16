<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subdomain extends Model
{
    protected $fillable = ['program_id', 'subdomain', 'active'];

    public function program() {
        return $this->belongsTo(Program::class);
    }
}
