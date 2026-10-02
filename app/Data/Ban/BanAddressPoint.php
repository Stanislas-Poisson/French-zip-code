<?php

declare(strict_types=1);

namespace App\Data\Ban;

/**
 * The position of one address of the BAN, with the commune and the postal code it belongs to.
 */
final readonly class BanAddressPoint
{
    public function __construct(
        public string $inseeCode,
        public string $postalCode,
        public float $latitude,
        public float $longitude,
    ) {}
}
