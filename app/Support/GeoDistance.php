<?php

declare(strict_types=1);

namespace App\Support;

final class GeoDistance
{
    private const float EARTH_RADIUS_KM = 6371.0088;

    /**
     * Great-circle distance between two GPS points (haversine formula), in kilometres.
     */
    public static function kilometers(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $deltaLatitude  = deg2rad($latitudeB - $latitudeA);
        $deltaLongitude = deg2rad($longitudeB - $longitudeA);

        $a = sin($deltaLatitude / 2)                                                         ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($deltaLongitude / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }
}
