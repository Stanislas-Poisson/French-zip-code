<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CoordinateSource;
use App\Models\City;
use App\Models\Commune;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class FillCityCoordinatesFromCommuneCentre
{
    private const string CENTRE_LATITUDE = '(select centre_latitude from communes'
        . ' where communes.id = cities.commune_id)';

    private const string CENTRE_LONGITUDE = '(select centre_longitude from communes'
        . ' where communes.id = cities.commune_id)';

    /**
     * Gives every city without a better point the centre of its commune (last fallback).
     * A point coming from the BAN or from Nominatim is never overwritten.
     *
     * @return int number of cities updated
     */
    public function execute(): int
    {
        return City::query()
            ->current()
            ->where(static function (Builder $builder): void {
                $builder->whereNull('coordinate_source')
                    ->orWhere('coordinate_source', CoordinateSource::CommuneCentre->value);
            })
            ->whereIn('commune_id', Commune::query()->whereNotNull('centre_latitude')->select('id'))
            ->update([
                'latitude'          => DB::raw(self::CENTRE_LATITUDE),
                'longitude'         => DB::raw(self::CENTRE_LONGITUDE),
                'coordinate_source' => CoordinateSource::CommuneCentre->value,
            ]);
    }
}
