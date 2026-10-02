<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\DepartmentType;
use App\Enums\SuccessionKind;
use App\Models\Commune;
use App\Models\CommuneEvent;
use App\Models\CommuneSuccession;
use App\Models\Department;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CogFixtures;
use Tests\TestCase;

final class ImportCogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_be_replayed_without_creating_duplicates(): void
    {
        $first    = CogFixtures::import($this->app, '2026');
        $communes = Commune::query()->count();
        $events   = CommuneEvent::query()->count();

        $second = CogFixtures::import($this->app, '2026');

        $this->assertSame($first, $second);
        $this->assertSame($communes, Commune::query()->count());
        $this->assertSame($events, CommuneEvent::query()->count());
        $this->assertSame(3, Region::query()->count());
    }

    #[Test]
    public function it_derives_the_department_of_a_closed_commune_from_its_code(): void
    {
        CogFixtures::import($this->app);

        $commune = Commune::query()->where('insee_code', '85043')->firstOrFail();

        $this->assertSame('Vendée', $commune->department()->firstOrFail()->name);
    }

    #[Test]
    public function it_derives_the_successions_of_the_saint_florent_merger(): void
    {
        CogFixtures::import($this->app);

        $communeSuccession = CommuneSuccession::query()->where('from_code', '85043')->where('kind', SuccessionKind::Absorbed)->firstOrFail();
        $this->assertSame('85213', $communeSuccession->to_code);
        $this->assertSame('2016-01-01', $communeSuccession->effective_date->toDateString());

        $reused = CommuneSuccession::query()->where('from_code', '85213')->where('kind', SuccessionKind::CodeReused)->firstOrFail();
        $this->assertSame('85213', $reused->to_code);
    }

    #[Test]
    public function it_imports_the_communes_of_the_overseas_collectivities(): void
    {
        CogFixtures::import($this->app);

        $commune = Commune::query()->where('insee_code', '97501')->firstOrFail();

        $this->assertSame('975', $commune->department()->firstOrFail()->code);
    }

    #[Test]
    public function it_imports_the_communes_with_their_validity_and_the_code_reuse(): void
    {
        CogFixtures::import($this->app);

        $commune = Commune::query()->where('insee_code', '37261')->firstOrFail();
        $this->assertNull($commune->valid_to);
        $this->assertSame('Indre-et-Loire', $commune->department()->firstOrFail()->name);

        $chaille = Commune::query()->where('insee_code', '85043')->firstOrFail();
        $this->assertSame('2016-01-01', $chaille->valid_to?->toDateString());

        $this->assertSame(2, Commune::query()->where('insee_code', '85213')->count());

        $before = Commune::query()->where('insee_code', '85213')->orderBy('valid_from')->firstOrFail();
        $after  = Commune::query()->where('insee_code', '85213')->orderByDesc('valid_from')->firstOrFail();

        $this->assertSame('Saint-Florent-des-Bois', $before->name);
        $this->assertSame('2016-01-01', $before->valid_to?->toDateString());
        $this->assertSame("Rives de l'Yon", $after->name);
        $this->assertNull($after->valid_to);
    }

    #[Test]
    public function it_imports_the_regions_and_the_departments_with_their_collectivities(): void
    {
        CogFixtures::import($this->app);

        $this->assertSame(3, Region::query()->count());
        $this->assertSame(5, Department::query()->count());

        $department = Department::query()->where('code', '975')->firstOrFail();
        $this->assertSame(DepartmentType::OverseasCollectivity, $department->type);
        $this->assertNull($department->region_id);

        $tours = Department::query()->where('code', '37')->firstOrFail();
        $this->assertSame('Centre-Val de Loire', $tours->region()->firstOrFail()->name);
    }
}
