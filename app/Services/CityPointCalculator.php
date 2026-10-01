<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Ban\BanAddressPoint;
use App\Data\Ban\CityPoint;

/**
 * Computes the GPS point of each commune and zip code pair as the median of the latitudes and of the longitudes
 * of its addresses. The median is not moved by a badly placed address, unlike the mean.
 */
final class CityPointCalculator
{
    /**
     * @param iterable<BanAddressPoint> $addresses
     *
     * @return list<CityPoint>
     */
    public function compute(iterable $addresses, int $minimumAddresses = 1): array
    {
        /** @var array<string, array{insee: string, postal: string, lat: list<float>, lon: list<float>}> $groups */
        $groups = [];

        foreach ($addresses as $address) {
            $key = $address->inseeCode . '|' . $address->postalCode;

            $groups[$key] ??= [
                'insee'  => $address->inseeCode,
                'postal' => $address->postalCode,
                'lat'    => [],
                'lon'    => [],
            ];
            $groups[$key]['lat'][] = $address->latitude;
            $groups[$key]['lon'][] = $address->longitude;
        }

        $points = [];

        foreach ($groups as $group) {
            $count = count($group['lat']);

            if ($count < $minimumAddresses) {
                continue;
            }

            $points[] = new CityPoint(
                inseeCode: $group['insee'],
                postalCode: $group['postal'],
                latitude: $this->median($group['lat']),
                longitude: $this->median($group['lon']),
                addressCount: $count,
            );
        }

        return $points;
    }

    /**
     * @param list<float> $values
     */
    private function median(array $values): float
    {
        sort($values);

        $count  = count($values);
        $middle = intdiv($count, 2);
        $upper  = $values[$middle] ?? 0.0;

        return 0 === $count % 2
            ? (($values[$middle - 1] ?? $upper) + $upper) / 2
            : $upper;
    }
}
