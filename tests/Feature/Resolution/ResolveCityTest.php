<?php

declare(strict_types=1);

namespace Tests\Feature\Resolution;

use App\Actions\LinkReplacedCities;
use App\Actions\ResolveCity;
use App\Models\City;
use App\Models\Commune;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CogFixtures;
use Tests\TestCase;

final class ResolveCityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CogFixtures::import($this->app);
    }

    #[Test]
    public function it_does_not_link_a_closed_city_when_the_target_is_ambiguous_or_missing(): void
    {
        $city = $this->city('85043', '85310', '2016-01-01');
        $this->city('85213', '85311');

        $this->assertSame(0, $this->app->make(LinkReplacedCities::class)->execute());
        $this->assertNull($city->refresh()->replaced_by_city_id);
    }

    #[Test]
    public function it_fails_for_a_code_that_disappeared(): void
    {
        $this->command('zipcode:resolve', ['code' => '00000'])
            ->expectsOutputToContain('has disappeared')
            ->assertFailed();
    }

    #[Test]
    public function it_finds_nothing_for_a_code_that_disappeared(): void
    {
        $cityResolution = $this->app->make(ResolveCity::class)->execute('00000', '00000', CarbonImmutable::parse('2000-01-01'));

        $this->assertTrue($cityResolution->commune->disappeared);
        $this->assertSame([], $cityResolution->cities);
    }

    #[Test]
    public function it_links_a_closed_city_to_the_city_that_replaces_it(): void
    {
        $city = $this->city('85043', '85310', '2016-01-01');
        $new  = $this->city('85213', '85310');

        $linked = $this->app->make(LinkReplacedCities::class)->execute();

        $this->assertSame(1, $linked);
        $this->assertSame($new->id, $city->refresh()->replaced_by_city_id);
    }

    #[Test]
    public function it_lists_every_zip_code_of_the_merged_commune_when_the_old_one_is_not_used_any_more(): void
    {
        $this->city('85213', '85310');
        $this->city('85213', '85311');

        $cityResolution = $this->app->make(ResolveCity::class)->execute('85043', '85999');

        $this->assertFalse($cityResolution->exactPostal);
        $this->assertCount(2, $cityResolution->cities);
    }

    #[Test]
    public function it_lists_the_zip_codes_of_a_commune_when_none_is_given(): void
    {
        $this->city('85213', '85310');

        $cityResolution = $this->app->make(ResolveCity::class)->execute('85043');

        $this->assertTrue($cityResolution->exactPostal);
        $this->assertCount(1, $cityResolution->cities);
    }

    #[Test]
    public function it_prints_the_resolution_of_an_old_code(): void
    {
        $this->city('85213', '85310');

        $this->command('zipcode:resolve', ['code' => '85043', '--zip' => '85310'])
            ->expectsOutputToContain('absorbed: 85043 -> 85213')
            ->expectsOutputToContain('Current communes: 85213')
            ->assertSuccessful();
    }

    #[Test]
    public function it_resolves_an_absorbed_commune_to_the_same_zip_code_of_the_merged_commune(): void
    {
        $new = $this->city('85213', '85310');
        $this->city('85213', '85311');

        $cityResolution = $this->app->make(ResolveCity::class)->execute('85043', '85310');

        $this->assertTrue($cityResolution->exactPostal);
        $this->assertSame([$new->id], array_map(static fn (City $city): int => $city->id, $cityResolution->cities));
    }

    private function city(string $inseeCode, string $postalCode, ?string $validTo = null): City
    {
        $commune = Commune::query()->where('insee_code', $inseeCode)->orderByDesc('valid_from')->firstOrFail();

        return City::query()->create([
            'commune_id'  => $commune->id,
            'postal_code' => $postalCode,
            'valid_from'  => '2000-01-01',
            'valid_to'    => $validTo,
        ]);
    }
}
