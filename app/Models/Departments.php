<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departments extends Model
{
    protected $table = 'departments';

    public $timestamps = false;

    protected $fillable = [
        'region_code', 'code', 'name', 'slug',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Regions::class, 'region_code', 'code');
    }

    public function cities(): HasMany
    {
        return $this->hasMany(Cities::class, 'department_code', 'code');
    }
}
