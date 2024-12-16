<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = ['name', 'wildcard', 'description'];

    public function outOfScope() {
        return $this->hasMany(OutOfScope::class);
    }

    public function subdomains() {
        return $this->hasMany(Subdomain::class);
    }

    public function reconResults() {
        return $this->hasMany(ReconResult::class);
    }
}
