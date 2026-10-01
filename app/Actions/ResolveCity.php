<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\Resolution\CityResolution;
use App\Models\City;
use Carbon\CarbonImmutable;

final readonly class ResolveCity
{
    public function __construct(private ResolveCommuneCode $resolveCommuneCode) {}

    /**
     * Finds the current cities that correspond to an old commune code and zip code: the same zip code in the
     * communes that took over the old one or, when the zip code is not used any more, every zip code of those
     * communes.
     */
    public function execute(
        string $inseeCode,
        ?string $postalCode = null,
        ?CarbonImmutable $asOf = null,
    ): CityResolution {
        $codeResolution = $this->resolveCommuneCode->execute($inseeCode, $asOf);

        $inTargets = City::query()
            ->current()
            ->whereRelation('commune', static function ($query) use ($codeResolution): void {
                $query->whereIn('insee_code', $codeResolution->currentCodes);
            });

        if (null !== $postalCode) {
            $exact = array_values(
                (clone $inTargets)->where('postal_code', $postalCode)->orderBy('postal_code')->get()->all(),
            );

            if ([] !== $exact) {
                return new CityResolution($codeResolution, $postalCode, $exact, true);
            }
        }

        $all = array_values($inTargets->orderBy('postal_code')->get()->all());

        return new CityResolution($codeResolution, $postalCode, $all, null === $postalCode);
    }
}
