<?php

declare(strict_types=1);

namespace Tests\Feature\Geocoding;

use App\Actions\FillCityCoordinatesFromCommuneCentre;
use App\Actions\GeocodeCityWithNominatim;
use App\Actions\ImportCities;
use App\Data\Postal\PostalRecord;
use App\Enums\CoordinateSource;
use App\Models\City;
use App\Models\Commune;
use App\Services\Sources\NominatimClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CogFixtures;
use Tests\TestCase;

final class GeocodeCityWithNominatimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CogFixtures::import($this->app);
        Commune::query()->where('insee_code', '37261')->update(['centre_latitude' => 47.3943, 'centre_longitude' => 0.6949]);
        $this->app->make(ImportCities::class)->execute(
            [new PostalRecord('37261', '37200', 'TOURS')],
            CarbonImmutable::parse('2026-01-01'),
            CogFixtures::snapshot($this->app, 'laposte'),
        );
        $this->app->make(FillCityCoordinatesFromCommuneCentre::class)->execute();
    }

    #[Test]
    public function it_asks_for_the_zip_code_and_the_commune_with_a_user_agent(): void
    {
        Http::fake(['*' => Http::response([['lat' => '47.3658595', 'lon' => '0.6888564']])]);

        $this->app->make(GeocodeCityWithNominatim::class)->execute(City::query()->sole());

        Http::assertSent(static fn (Request $request): bool => 'French-zip-code' === $request->header('User-Agent')[0]
            && str_contains($request->url(), 'postalcode=37200')
            && str_contains($request->url(), 'city=Tours'));
    }

    #[Test]
    public function it_ignores_a_point_too_far_from_the_commune(): void
    {
        Http::fake(['*' => Http::response([['lat' => '48.8566', 'lon' => '2.3522']])]);

        $updated = $this->app->make(GeocodeCityWithNominatim::class)->execute(City::query()->sole());

        $this->assertFalse($updated);
        $this->assertSame(CoordinateSource::CommuneCentre, City::query()->sole()->coordinate_source);
    }

    #[Test]
    public function it_keeps_the_fallback_when_nominatim_finds_nothing(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->assertFalse($this->app->make(GeocodeCityWithNominatim::class)->execute(City::query()->sole()));
    }

    #[Test]
    public function it_never_touches_a_point_that_comes_from_the_ban(): void
    {
        Http::fake();
        City::query()->update(['coordinate_source' => CoordinateSource::Ban]);

        $this->assertFalse($this->app->make(GeocodeCityWithNominatim::class)->execute(City::query()->sole()));

        Http::assertNothingSent();
    }

    #[Test]
    public function it_returns_no_point_for_an_empty_answer(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->assertNull($this->app->make(NominatimClient::class)->findPostalCode('37200', 'Tours'));
    }

    #[Test]
    public function it_stores_the_point_found_by_nominatim(): void
    {
        Http::fake(['*' => Http::response([['lat' => '47.3658595', 'lon' => '0.6888564']])]);

        $updated = $this->app->make(GeocodeCityWithNominatim::class)->execute(City::query()->sole());

        $this->assertTrue($updated);

        $city = City::query()->sole();
        $this->assertSame(47.3658595, $city->latitude);
        $this->assertSame(CoordinateSource::Nominatim, $city->coordinate_source);
    }
}
