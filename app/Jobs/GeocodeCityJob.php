<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\GeocodeCityWithNominatim;
use App\Jobs\Middleware\ThrottleNominatim;
use App\Models\City;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Asks Nominatim for the point of one city that has no address in the BAN, at one request per second.
 */
final class GeocodeCityJob implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(public readonly int $cityId)
    {
        $this->onQueue('nominatim');
    }

    public function handle(GeocodeCityWithNominatim $geocodeCityWithNominatim): void
    {
        $city = City::query()->find($this->cityId);

        if (null === $city) {
            return;
        }

        $geocodeCityWithNominatim->execute($city);
    }

    /**
     * @return list<ThrottleNominatim>
     */
    public function middleware(): array
    {
        return [new ThrottleNominatim];
    }
}
