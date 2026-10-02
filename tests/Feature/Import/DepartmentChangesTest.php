<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Actions\ImportDepartments;
use App\Data\Insee\DepartmentRecord;
use App\Enums\ChangeType;
use App\Enums\DepartmentType;
use App\Models\Department;
use App\Models\ReferenceChange;
use App\Models\Region;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CogFixtures;
use Tests\TestCase;

final class DepartmentChangesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_detects_a_created_a_modified_and_a_removed_department(): void
    {
        $this->importDepartments('2026', [$this->record('45', 'Loiret'), $this->record('85', 'Vendee')]);
        $this->importDepartments('2027', [$this->record('45', 'Loiret (45)'), $this->record('99', 'Nouveau')]);

        $changes = ReferenceChange::query()->orderBy('entity_code')->get();

        $this->assertSame(
            ['45' => ChangeType::Modified, '85' => ChangeType::Removed, '99' => ChangeType::Created],
            $changes->pluck('change_type', 'entity_code')->all(),
        );
        $this->assertSame('2027-01-01', Department::query()->where('code', '85')->firstOrFail()->valid_to?->toDateString());
        $this->assertSame('2027-01-01', Department::query()->where('code', '99')->firstOrFail()->valid_from->toDateString());
        $this->assertSame('Loiret (45)', Department::query()->where('code', '45')->firstOrFail()->name);
    }

    #[Test]
    public function it_detects_a_department_that_changes_region(): void
    {
        $this->importDepartments('2026', [$this->record('45', 'Loiret', '24')]);
        Region::query()->create(['code' => '99', 'name' => 'Autre', 'slug' => 'autre', 'valid_from' => '1943-01-01']);

        $this->importDepartments('2027', [$this->record('45', 'Loiret', '99')]);

        $referenceChange = ReferenceChange::query()->sole();

        $this->assertSame(ChangeType::Modified, $referenceChange->change_type);
        $this->assertSame(
            Region::query()->where('code', '99')->value('id'),
            Department::query()->where('code', '45')->value('region_id'),
        );
    }

    #[Test]
    public function it_does_not_record_a_change_when_nothing_changed(): void
    {
        $this->importDepartments('2026', [$this->record('45', 'Loiret')]);
        $this->importDepartments('2027', [$this->record('45', 'Loiret')]);

        $this->assertSame(0, ReferenceChange::query()->count());
    }

    #[Test]
    public function it_gives_no_region_to_a_department_without_a_known_region(): void
    {
        $this->importDepartments('2026', [$this->record('975', 'Saint-Pierre-et-Miquelon', null)]);

        $this->assertNull(Department::query()->sole()->region_id);
    }

    #[Test]
    public function it_records_no_change_on_the_first_import(): void
    {
        $this->importDepartments('2026', [$this->record('45', 'Loiret')]);

        $this->assertSame(0, ReferenceChange::query()->count());
        $this->assertSame('1943-01-01', Department::query()->firstOrFail()->valid_from->toDateString());
    }

    /**
     * @param list<DepartmentRecord> $records
     */
    private function importDepartments(string $year, array $records): void
    {
        $this->app->make(ImportDepartments::class)->execute(
            $records,
            CarbonImmutable::parse($year . '-01-01'),
            CogFixtures::snapshot($this->app, $year),
        );
    }

    private function record(string $code, string $name, ?string $regionCode = '24'): DepartmentRecord
    {
        return new DepartmentRecord($code, $regionCode, DepartmentType::Department, $name, null);
    }
}
