<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int         $id
 * @property Carbon      $valid_from
 * @property Carbon|null $valid_to
 * @property string      $code
 * @property string      $name
 * @property string      $slug
 */
final class Region extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'slug',
        'valid_from',
        'valid_to',
    ];

    /**
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to'   => 'date',
        ];
    }

    /**
     * Only the regions that are currently valid.
     *
     * @param Builder<Region> $builder
     */
    protected function scopeCurrent(Builder $builder): void
    {
        $builder->whereNull('valid_to');
    }
}
