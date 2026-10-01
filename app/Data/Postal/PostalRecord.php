<?php

declare(strict_types=1);

namespace App\Data\Postal;

/**
 * A line of the La Poste "base officielle des codes postaux": a commune and one of its zip codes.
 */
final readonly class PostalRecord
{
    public function __construct(
        public string $inseeCode,
        public string $postalCode,
        public ?string $label,
    ) {}
}
