<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cities extends Model
{
    protected $table = 'cities';

    public $timestamps = false;

    protected $fillable = [
        'department_code', 'insee_code', 'zip_code', 'name', 'slug', 'gps_lat', 'gps_lng', 'multi',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Departments::class, 'department_code', 'code');
    }
}
