<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Actions\ImportRegions;
use App\Data\Insee\RegionRecord;
use App\Enums\ChangeType;
use App\Models\ReferenceChange;
use App\Models\Region;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CogFixtures;
use Tests\TestCase;

final class ReferenceChangesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_detects_a_created_a_modified_and_a_removed_region(): void
    {
        $this->importRegions('2026', [
            new RegionRecord('24', 'Centre-Val de Loire', '45234'),
            new RegionRecord('41', 'Lorraine', '57463'),
        ]);

        $this->importRegions('2027', [
            new RegionRecord('24', 'Centre', '45234'),
            new RegionRecord('99', 'New region', '99999'),
        ]);

        $changes = ReferenceChange::query()->orderBy('entity_code')->get();
        $this->assertCount(3, $changes);
        $this->assertSame(['24' => ChangeType::Modified, '41' => ChangeType::Removed, '99' => ChangeType::Created], $changes->pluck('change_type', 'entity_code')->all());

        $region = Region::query()->where('code', '41')->firstOrFail();
        $this->assertSame('2027-01-01', $region->valid_to?->toDateString());

        $created = Region::query()->where('code', '99')->firstOrFail();
        $this->assertSame('2027-01-01', $created->valid_from->toDateString());
        $this->assertSame(1, Region::query()->where('code', '24')->count());
    }

    #[Test]
    public function it_records_no_change_on_the_first_import(): void
    {
        $this->importRegions('2026', [new RegionRecord('24', 'Centre-Val de Loire', '45234')]);

        $this->assertSame(0, ReferenceChange::query()->count());
        $this->assertSame('1943-01-01', Region::query()->firstOrFail()->valid_from->toDateString());
    }

    /**
     * @param list<RegionRecord> $records
     */
    private function importRegions(string $year, array $records): void
    {
        $this->app->make(ImportRegions::class)->execute(
            $records,
            CarbonImmutable::parse($year . '-01-01'),
            CogFixtures::snapshot($this->app, $year),
        );
    }
}
