<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutOfScope extends Model
{
    protected $fillable = ['program_id', 'domain'];

    public function program() {
        return $this->belongsTo(Program::class);
    }
}
