<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconResult extends Model
{
    protected $fillable = ['program_id', 'tool_name', 'output'];

    protected $casts = [
        'output' => 'array'
    ];

    public function program() {
        return $this->belongsTo(Program::class);
    }
}
