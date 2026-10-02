<?php

declare(strict_types=1);

namespace App\Services\Sources;

use Illuminate\Support\Facades\Http;

/**
 * Nominatim (OpenStreetMap) search. Its usage policy allows one request per second and requires a User-Agent.
 */
final class NominatimClient
{
    /**
     * Looks for the point of a postal code in a commune.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function findPostalCode(string $postalCode, string $communeName): ?array
    {
        $results = Http::withUserAgent(config()->string('sources.nominatim.user_agent', 'French-postal-code'))
            ->timeout(30)
            ->get(config()->string('sources.nominatim.search_url', ''), [
                'postalcode' => $postalCode,
                'city'       => $communeName,
                'country'    => 'France',
                'format'     => 'json',
                'limit'      => 1,
            ])
            ->throw()
            ->json();

        $latitude  = data_get($results, '0.lat');
        $longitude = data_get($results, '0.lon');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return ['latitude' => (float) $latitude, 'longitude' => (float) $longitude];
    }
}
