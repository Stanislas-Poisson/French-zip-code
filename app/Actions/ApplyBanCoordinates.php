<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\Ban\CityPoint;
use App\Enums\CoordinateSource;
use App\Models\City;
use Illuminate\Support\Facades\DB;

final class ApplyBanCoordinates
{
    /**
     * Gives each current city the point computed from the BAN. The BAN always wins over the other sources.
     *
     * @param iterable<CityPoint> $points
     *
     * @return array{updated: int, unmatched: int}
     */
    public function execute(iterable $points): array
    {
        $updated   = 0;
        $unmatched = 0;

        DB::transaction(function () use ($points, &$updated, &$unmatched): void {
            foreach ($points as $point) {
                $count = City::query()
                    ->current()
                    ->where('postal_code', $point->postalCode)
                    ->whereRelation('commune', 'insee_code', $point->inseeCode)
                    ->update([
                        'latitude'          => $point->latitude,
                        'longitude'         => $point->longitude,
                        'address_count'     => $point->addressCount,
                        'coordinate_source' => CoordinateSource::Ban->value,
                    ]);

                if (0 === $count) {
                    $unmatched++;

                    continue;
                }

                $updated += $count;
            }
        });

        return ['updated' => $updated, 'unmatched' => $unmatched];
    }
}
