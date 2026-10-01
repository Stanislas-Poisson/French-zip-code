<?php

declare(strict_types=1);

namespace App\Services\Sources;

use App\Data\Postal\GeoCommuneRecord;
use Illuminate\Support\Facades\Http;

/**
 * geo.api.gouv.fr returns every commune in one request, with its centre and its zip codes.
 */
final class GeoApiClient
{
    private const string FIELDS = 'nom,code,codesPostaux,centre';

    /**
     * Current communes and municipal arrondissements.
     *
     * @return list<GeoCommuneRecord>
     */
    public function communes(): array
    {
        return [
            ...$this->fetch(null),
            ...$this->fetch('arrondissement-municipal'),
        ];
    }

    /**
     * @return list<GeoCommuneRecord>
     */
    private function fetch(?string $type): array
    {
        $query = array_filter(
            ['fields' => self::FIELDS, 'format' => 'json', 'geometry' => 'centre', 'type' => $type],
            static fn (?string $value): bool => null !== $value,
        );

        $items = Http::timeout(120)->get(config()->string('sources.geo.communes_url', ''), $query)->throw()->json();

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(
            array_map($this->toRecord(...), $items),
            static fn (?GeoCommuneRecord $geoCommuneRecord): bool => $geoCommuneRecord instanceof GeoCommuneRecord,
        ));
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private function point(mixed $coordinates): ?array
    {
        if (! is_array($coordinates) || 2 !== count($coordinates)) {
            return null;
        }

        [$longitude, $latitude] = array_values($coordinates);

        if (! is_numeric($longitude) || ! is_numeric($latitude)) {
            return null;
        }

        return ['latitude' => (float) $latitude, 'longitude' => (float) $longitude];
    }

    private function toRecord(mixed $item): ?GeoCommuneRecord
    {
        $code        = data_get($item, 'code');
        $name        = data_get($item, 'nom');
        $postalCodes = data_get($item, 'codesPostaux', []);
        $point       = $this->point(data_get($item, 'centre.coordinates'));

        if (! is_string($code) || ! is_string($name) || ! is_array($postalCodes) || null === $point) {
            return null;
        }

        return new GeoCommuneRecord(
            inseeCode: $code,
            name: $name,
            postalCodes: array_values(array_filter($postalCodes, is_string(...))),
            latitude: $point['latitude'],
            longitude: $point['longitude'],
        );
    }
}
