<?php

declare(strict_types=1);

namespace App\Data\Ban;

/**
 * The GPS point of a commune and postal code pair, computed from the addresses of the BAN.
 */
final readonly class CityPoint
{
    public function __construct(
        public string $inseeCode,
        public string $postalCode,
        public float $latitude,
        public float $longitude,
        public int $addressCount,
    ) {}
}
