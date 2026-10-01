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
     * Looks for the point of a zip code in a commune.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function findPostalCode(string $postalCode, string $communeName): ?array
    {
        $url       = config('sources.nominatim.search_url');
        $userAgent = config('sources.nominatim.user_agent');

        $results = Http::withUserAgent(is_string($userAgent) ? $userAgent : 'French-zip-code')
            ->timeout(30)
            ->get(is_string($url) ? $url : '', [
                'postalcode' => $postalCode,
                'city'       => $communeName,
                'country'    => 'France',
                'format'     => 'json',
                'limit'      => 1,
            ])
            ->throw()
            ->json();

        $first = is_array($results) ? ($results[0] ?? null) : null;

        if (! is_array($first) || ! is_numeric($first['lat'] ?? null) || ! is_numeric($first['lon'] ?? null)) {
            return null;
        }

        return ['latitude' => (float) $first['lat'], 'longitude' => (float) $first['lon']];
    }
}
