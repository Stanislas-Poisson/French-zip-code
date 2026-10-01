<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepartmentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int            $id
 * @property int|null       $region_id
 * @property string         $code
 * @property DepartmentType $type
 * @property string         $name
 * @property string         $slug
 */
final class Department extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'region_id',
        'code',
        'type',
        'name',
        'slug',
        'valid_from',
        'valid_to',
    ];

    /**
     * @return HasMany<Commune, $this>
     */
    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type'       => DepartmentType::class,
            'valid_from' => 'date',
            'valid_to'   => 'date',
        ];
    }

    /**
     * Only the departments that are currently valid.
     *
     * @param Builder<Department> $builder
     */
    protected function scopeCurrent(Builder $builder): void
    {
        $builder->whereNull('valid_to');
    }
}
