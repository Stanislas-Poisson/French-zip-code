<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\City;
use App\Models\Commune;
use Carbon\CarbonImmutable;
use LogicException;

final readonly class LinkReplacedCities
{
    public function __construct(private ResolveCommuneCode $resolveCommuneCode) {}

    /**
     * Links each city whose validity was closed to the city that replaces it, so that the foreign keys of an
     * application can be moved to the new row. A city is linked only when the target is unambiguous: the same
     * postal code in the commune that took over the old one.
     *
     * @return int number of cities linked
     */
    public function execute(): int
    {
        $linked = 0;

        $closed = City::query()
            ->whereNotNull('valid_to')
            ->whereNull('replaced_by_city_id')
            ->with('commune')
            ->get();

        foreach ($closed as $city) {
            $commune  = $city->commune ?? throw new LogicException('A city always belongs to a commune.');
            $closedAt = CarbonImmutable::parse($city->valid_to ?? throw new LogicException('The city is not closed.'));

            $target = $this->targetOf($city, $commune, $closedAt);

            if (! $target instanceof City) {
                continue;
            }

            $city->update(['replaced_by_city_id' => $target->id]);
            $linked++;
        }

        return $linked;
    }

    private function targetOf(City $city, Commune $commune, CarbonImmutable $closedAt): ?City
    {
        $codeResolution = $this->resolveCommuneCode->execute($commune->insee_code, $closedAt->subDay());

        $candidates = City::query()
            ->current()
            ->where('postal_code', $city->postal_code)
            ->whereRelation('commune', static function ($query) use ($codeResolution): void {
                $query->whereIn('insee_code', $codeResolution->currentCodes);
            })
            ->where('id', '!=', $city->id)
            ->get();

        return 1 === $candidates->count() ? $candidates->first() : null;
    }
}
