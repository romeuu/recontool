<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wildcard extends Model
{
    protected $fillable = ['program_id', 'wildcard'];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}
