<?php

declare(strict_types=1);

namespace App\Data\Resolution;

use App\Models\City;

/**
 * Where an old (commune code, zip code) pair points to today.
 */
final readonly class CityResolution
{
    /**
     * @param list<City> $cities      the current cities that correspond to the pair
     * @param bool       $exactPostal whether the zip code is still used by the target communes
     */
    public function __construct(
        public CodeResolution $commune,
        public ?string $postalCode,
        public array $cities,
        public bool $exactPostal,
    ) {}
}
