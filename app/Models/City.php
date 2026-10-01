<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CoordinateSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A postal entry: a commune and one of its zip codes, with its own GPS point.
 * It is the target of the foreign keys of the addresses of an application.
 *
 * @property int                   $id
 * @property int                   $commune_id
 * @property string                $postal_code
 * @property string|null           $label
 * @property float|null            $latitude
 * @property float|null            $longitude
 * @property int                   $address_count
 * @property CoordinateSource|null $coordinate_source
 * @property int|null              $replaced_by_city_id
 */
final class City extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'commune_id',
        'postal_code',
        'label',
        'latitude',
        'longitude',
        'address_count',
        'coordinate_source',
        'valid_from',
        'valid_to',
        'replaced_by_city_id',
    ];

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * The city that replaces this one once its validity is closed.
     *
     * @return BelongsTo<City, $this>
     */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_city_id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'latitude'          => 'float',
            'longitude'         => 'float',
            'address_count'     => 'integer',
            'coordinate_source' => CoordinateSource::class,
            'valid_from'        => 'date',
            'valid_to'          => 'date',
        ];
    }

    /**
     * Only the cities that are currently valid.
     *
     * @param Builder<City> $query
     */
    protected function scopeCurrent(Builder $query): void
    {
        $query->whereNull('valid_to');
    }
}
