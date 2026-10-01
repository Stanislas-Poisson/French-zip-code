<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Regions extends Model
{
    protected $table = 'regions';

    public $timestamps = false;

    protected $fillable = [
        'code', 'name', 'slug',
    ];

    public function departments(): HasMany
    {
        return $this->hasMany(Departments::class, 'region_code', 'code');
    }
}
