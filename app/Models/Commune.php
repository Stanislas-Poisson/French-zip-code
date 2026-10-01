<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommuneKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int         $id
 * @property int         $department_id
 * @property string      $insee_code
 * @property CommuneKind $kind
 * @property string      $name
 * @property string      $slug
 * @property float|null  $centre_latitude
 * @property float|null  $centre_longitude
 */
final class Commune extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'department_id',
        'insee_code',
        'kind',
        'name',
        'slug',
        'centre_latitude',
        'centre_longitude',
        'valid_from',
        'valid_to',
    ];

    /**
     * @return HasMany<City, $this>
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'kind'             => CommuneKind::class,
            'centre_latitude'  => 'float',
            'centre_longitude' => 'float',
            'valid_from'       => 'date',
            'valid_to'         => 'date',
        ];
    }

    /**
     * Only the communes that are currently valid.
     *
     * @param Builder<Commune> $query
     */
    protected function scopeCurrent(Builder $query): void
    {
        $query->whereNull('valid_to');
    }
}
