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
        $url   = config('sources.geo.communes_url');
        $query = ['fields' => self::FIELDS, 'format' => 'json', 'geometry' => 'centre'];

        if (null !== $type) {
            $query['type'] = $type;
        }

        $items = Http::timeout(120)->get(is_string($url) ? $url : '', $query)->throw()->json();

        if (! is_array($items)) {
            return [];
        }

        $records = [];

        foreach ($items as $item) {
            $record = $this->toRecord($item);

            if ($record instanceof GeoCommuneRecord) {
                $records[] = $record;
            }
        }

        return $records;
    }

    private function toRecord(mixed $item): ?GeoCommuneRecord
    {
        if (! is_array($item)) {
            return null;
        }

        $code        = $item['code']         ?? null;
        $name        = $item['nom']          ?? null;
        $postalCodes = $item['codesPostaux'] ?? [];
        $centre      = $item['centre']       ?? null;
        $coordinates = is_array($centre) ? ($centre['coordinates'] ?? null) : null;

        if (! is_string($code) || ! is_string($name) || ! is_array($postalCodes) || ! is_array($coordinates) || 2 !== count($coordinates)) {
            return null;
        }

        [$longitude, $latitude] = array_values($coordinates);

        if (! is_numeric($longitude) || ! is_numeric($latitude)) {
            return null;
        }

        return new GeoCommuneRecord(
            inseeCode: $code,
            name: $name,
            postalCodes: array_values(array_filter($postalCodes, is_string(...))),
            latitude: (float) $latitude,
            longitude: (float) $longitude,
        );
    }
}
