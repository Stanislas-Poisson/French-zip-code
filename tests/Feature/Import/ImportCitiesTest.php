<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Actions\ApplyCommuneCentres;
use App\Actions\FillCityCoordinatesFromCommuneCentre;
use App\Actions\ImportCities;
use App\Data\Postal\GeoCommuneRecord;
use App\Data\Postal\PostalRecord;
use App\Enums\ChangeType;
use App\Enums\CoordinateSource;
use App\Models\City;
use App\Models\Commune;
use App\Models\ReferenceChange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CogFixtures;
use Tests\TestCase;

final class ImportCitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CogFixtures::import($this->app);
    }

    #[Test]
    public function it_closes_a_removed_pair_and_records_the_new_one(): void
    {
        $this->import('2026', [
            new PostalRecord('37261', '37000', 'TOURS'),
            new PostalRecord('37261', '37100', 'TOURS'),
        ]);
        $city = City::query()->where('postal_code', '37100')->firstOrFail();

        $result = $this->import('2027', [
            new PostalRecord('37261', '37000', 'TOURS'),
            new PostalRecord('37261', '37300', 'JOUE LES TOURS'),
        ]);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['closed']);

        $this->assertSame('2027-01-01', $city->refresh()->valid_to?->toDateString());
        $this->assertSame(1, City::query()->where('postal_code', '37100')->count());

        $changes = ReferenceChange::query()->orderBy('entity_code')->get();
        $this->assertSame(['37261-37100' => ChangeType::Removed, '37261-37300' => ChangeType::Created], $changes->pluck('change_type', 'entity_code')->all());
    }

    #[Test]
    public function it_counts_a_pair_repeated_by_the_lieux_dits_only_once(): void
    {
        $result = $this->import('2026', [
            new PostalRecord('85213', '85310', 'RIVES DE L YON'),
            new PostalRecord('85213', '85310', 'RIVES DE L YON'),
        ]);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, City::query()->count());
    }

    #[Test]
    public function it_creates_one_city_per_commune_and_postal_code(): void
    {
        $result = $this->import('2026', [
            new PostalRecord('37261', '37000', 'TOURS'),
            new PostalRecord('37261', '37100', 'TOURS'),
            new PostalRecord('37261', '37200', 'TOURS'),
            new PostalRecord('85213', '85310', 'RIVES DE L YON'),
        ]);

        $this->assertSame(4, $result['created']);
        $this->assertSame(3, City::query()->whereRelation('commune', 'insee_code', '37261')->count());
        $this->assertSame(['37000', '37100', '37200'], City::query()->whereRelation('commune', 'insee_code', '37261')->orderBy('postal_code')->pluck('postal_code')->all());
    }

    #[Test]
    public function it_gives_each_city_the_centre_of_its_commune_as_a_last_fallback(): void
    {
        $this->import('2026', [
            new PostalRecord('37261', '37000', 'TOURS'),
            new PostalRecord('37261', '37200', 'TOURS'),
        ]);

        $updated = $this->app->make(ApplyCommuneCentres::class)->execute([
            new GeoCommuneRecord('37261', 'Tours', ['37000', '37200'], 47.3943, 0.6949),
        ]);
        $filled = $this->app->make(FillCityCoordinatesFromCommuneCentre::class)->execute();

        $this->assertSame(1, $updated);
        $this->assertSame(2, $filled);

        foreach (City::query()->get() as $city) {
            $this->assertSame(47.3943, $city->latitude);
            $this->assertSame(CoordinateSource::CommuneCentre, $city->coordinate_source);
        }

        $this->assertSame(47.3943, Commune::query()->where('insee_code', '37261')->sole()->centre_latitude);
    }

    #[Test]
    public function it_keeps_the_same_row_and_updates_the_label(): void
    {
        $this->import('2026', [new PostalRecord('37261', '37000', 'TOURS')]);
        $id = City::query()->firstOrFail()->id;

        $result = $this->import('2027', [new PostalRecord('37261', '37000', 'TOURS CEDEX')]);

        $this->assertSame(1, $result['updated']);
        $this->assertSame($id, City::query()->sole()->id);
        $this->assertSame('TOURS CEDEX', City::query()->sole()->label);
    }

    #[Test]
    public function it_never_overwrites_a_better_point(): void
    {
        $this->import('2026', [new PostalRecord('37261', '37200', 'TOURS')]);
        City::query()->update(['latitude' => 47.3661, 'longitude' => 0.7044, 'coordinate_source' => CoordinateSource::Ban, 'address_count' => 1007]);
        $this->app->make(ApplyCommuneCentres::class)->execute([new GeoCommuneRecord('37261', 'Tours', ['37200'], 47.3943, 0.6949)]);

        $filled = $this->app->make(FillCityCoordinatesFromCommuneCentre::class)->execute();

        $this->assertSame(0, $filled);
        $this->assertSame(47.3661, City::query()->sole()->latitude);
    }

    #[Test]
    public function it_records_no_change_on_the_first_import(): void
    {
        $this->import('2026', [new PostalRecord('37261', '37000', 'TOURS')]);

        $this->assertSame(0, ReferenceChange::query()->count());
    }

    #[Test]
    public function it_reports_the_codes_that_match_no_commune(): void
    {
        $result = $this->import('2026', [new PostalRecord('99999', '99000', 'INCONNUE')]);

        $this->assertSame(['99999'], $result['unmatched']);
        $this->assertSame(0, City::query()->count());
    }

    /**
     * @param list<PostalRecord> $records
     *
     * @return array{created: int, updated: int, closed: int, unmatched: list<string>}
     */
    private function import(string $year, array $records): array
    {
        return $this->app->make(ImportCities::class)->execute(
            $records,
            CarbonImmutable::parse($year . '-01-01'),
            CogFixtures::snapshot($this->app, 'laposte-' . $year),
        );
    }
}
