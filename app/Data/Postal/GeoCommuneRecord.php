<?php

declare(strict_types=1);

namespace App\Data\Postal;

/**
 * A commune of geo.api.gouv.fr with the centre of the commune.
 */
final readonly class GeoCommuneRecord
{
    /**
     * @param list<string> $postalCodes
     */
    public function __construct(
        public string $inseeCode,
        public string $name,
        public array $postalCodes,
        public float $latitude,
        public float $longitude,
    ) {}
}
