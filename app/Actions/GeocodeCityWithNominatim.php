<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CoordinateSource;
use App\Models\City;
use App\Models\Commune;
use App\Services\Sources\NominatimClient;
use App\Support\GeoDistance;

final readonly class GeocodeCityWithNominatim
{
    public function __construct(private NominatimClient $nominatimClient) {}

    /**
     * Fallback for a city that has no address in the BAN: asks Nominatim for the point of its zip code.
     * A point too far from the centre of the commune is a wrong match and is ignored.
     *
     * @return bool whether the point of the city has been updated
     */
    public function execute(City $city): bool
    {
        if (CoordinateSource::Ban === $city->coordinate_source) {
            return false;
        }

        $commune = $city->commune()->firstOrFail();

        $point = $this->nominatimClient->findPostalCode($city->postal_code, $commune->name);

        if (null === $point) {
            return false;
        }

        if ($this->isTooFar($commune, $point)) {
            return false;
        }

        $city->update([
            'latitude'          => $point['latitude'],
            'longitude'         => $point['longitude'],
            'address_count'     => 0,
            'coordinate_source' => CoordinateSource::Nominatim,
        ]);

        return true;
    }

    /**
     * @param array{latitude: float, longitude: float} $point
     */
    private function isTooFar(Commune $commune, array $point): bool
    {
        if (null === $commune->centre_latitude || null === $commune->centre_longitude) {
            return false;
        }

        $distance = GeoDistance::kilometers(
            $commune->centre_latitude,
            $commune->centre_longitude,
            $point['latitude'],
            $point['longitude'],
        );

        return config()->integer('sources.nominatim.max_distance_km', 30) < $distance;
    }
}
