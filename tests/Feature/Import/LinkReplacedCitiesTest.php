<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Actions\LinkReplacedCities;
use App\Enums\CommuneKind;
use App\Enums\SuccessionKind;
use App\Models\City;
use App\Models\Commune;
use App\Models\CommuneSuccession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LinkReplacedCitiesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_leaves_a_closed_city_without_replacement_unlinked(): void
    {
        $city = $this->city('97123', '97123', '2007-02-23');

        $linked = $this->app->make(LinkReplacedCities::class)->execute();

        $this->assertSame(0, $linked);
        $this->assertNull($city->refresh()->replaced_by_city_id);
    }

    #[Test]
    public function it_links_a_closed_city_to_the_same_zip_code_of_the_commune_that_replaced_it(): void
    {
        $city = $this->city('85043', '85310', '2016-01-01');
        $new  = $this->city('85213', '85310', null);
        CommuneSuccession::query()->create([
            'from_code'      => '85043',
            'to_code'        => '85213',
            'kind'           => SuccessionKind::Absorbed,
            'effective_date' => '2016-01-01',
        ]);

        $linked = $this->app->make(LinkReplacedCities::class)->execute();

        $this->assertSame(1, $linked);
        $this->assertSame($new->id, $city->refresh()->replaced_by_city_id);
    }

    private function city(string $inseeCode, string $postalCode, ?string $validTo): City
    {
        $commune = Commune::query()->firstOrCreate(
            ['insee_code' => $inseeCode],
            [
                'kind'       => CommuneKind::Commune,
                'name'       => 'Commune ' . $inseeCode,
                'slug'       => 'commune-' . $inseeCode,
                'valid_from' => '1943-01-01',
            ],
        );

        return City::query()->create([
            'commune_id'  => $commune->id,
            'postal_code' => $postalCode,
            'valid_from'  => '1943-01-01',
            'valid_to'    => $validTo,
        ]);
    }
}
