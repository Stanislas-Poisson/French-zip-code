<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Actions\ApplyBanCoordinates;
use App\Actions\ImportCities;
use App\Data\Ban\CityPoint;
use App\Data\Postal\PostalRecord;
use App\Enums\CoordinateSource;
use App\Models\City;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CogFixtures;
use Tests\TestCase;

final class ApplyBanCoordinatesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_gives_each_postal_code_of_a_commune_its_own_point(): void
    {
        $this->importTours();

        $result = $this->app->make(ApplyBanCoordinates::class)->execute([
            new CityPoint('37261', '37000', 47.3858, 0.6886, 18746),
            new CityPoint('37261', '37100', 47.4164, 0.6930, 10489),
            new CityPoint('37261', '37200', 47.3661, 0.7044, 1007),
        ]);

        $this->assertSame(['updated' => 3, 'unmatched' => 0], $result);

        $city = City::query()->where('postal_code', '37200')->sole();
        $this->assertSame(47.3661, $city->latitude);
        $this->assertSame(0.7044, $city->longitude);
        $this->assertSame(1007, $city->address_count);
        $this->assertSame(CoordinateSource::Ban, $city->coordinate_source);
        $this->assertSame(47.4164, City::query()->where('postal_code', '37100')->sole()->latitude);
    }

    #[Test]
    public function it_replaces_a_fallback_point_and_counts_the_unknown_pairs(): void
    {
        $this->importTours();
        City::query()->update(['latitude' => 47.39, 'longitude' => 0.69, 'coordinate_source' => CoordinateSource::CommuneCentre]);

        $result = $this->app->make(ApplyBanCoordinates::class)->execute([
            new CityPoint('37261', '37000', 47.3858, 0.6886, 10),
            new CityPoint('37261', '37999', 47.0, 0.0, 10),
        ]);

        $this->assertSame(['updated' => 1, 'unmatched' => 1], $result);
        $this->assertSame(CoordinateSource::Ban, City::query()->where('postal_code', '37000')->sole()->coordinate_source);
        $this->assertSame(CoordinateSource::CommuneCentre, City::query()->where('postal_code', '37100')->sole()->coordinate_source);
    }

    private function importTours(): void
    {
        CogFixtures::import($this->app);

        $this->app->make(ImportCities::class)->execute(
            [
                new PostalRecord('37261', '37000', 'TOURS'),
                new PostalRecord('37261', '37100', 'TOURS'),
                new PostalRecord('37261', '37200', 'TOURS'),
            ],
            CarbonImmutable::parse('2026-01-01'),
            CogFixtures::snapshot($this->app, 'laposte'),
        );
    }
}
