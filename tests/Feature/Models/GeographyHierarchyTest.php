<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\CommuneKind;
use App\Enums\CoordinateSource;
use App\Enums\DepartmentType;
use App\Models\City;
use App\Models\Commune;
use App\Models\Department;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GeographyHierarchyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_allows_an_overseas_collectivity_without_region(): void
    {
        $department = Department::query()->create([
            'region_id'  => null,
            'code'       => '975',
            'type'       => DepartmentType::OverseasCollectivity,
            'name'       => 'Saint-Pierre-et-Miquelon',
            'slug'       => 'saint-pierre-et-miquelon',
            'valid_from' => '1943-01-01',
        ]);

        $this->assertNull($department->region()->first());
        $this->assertSame(DepartmentType::OverseasCollectivity, $department->type);
    }

    #[Test]
    public function it_climbs_from_a_city_up_to_its_region(): void
    {
        $city = $this->createTours37200();

        $this->assertSame('37200', $city->postal_code);
        $commune    = $city->commune()->firstOrFail();
        $department = $commune->department()->firstOrFail();

        $this->assertSame('Tours', $commune->name);
        $this->assertSame('Indre-et-Loire', $department->name);
        $this->assertSame('Centre-Val de Loire', $department->region()->firstOrFail()->name);
    }

    #[Test]
    public function it_goes_down_from_a_region_to_its_cities(): void
    {
        $city   = $this->createTours37200();
        $region = Region::query()->firstOrFail();

        $department = $region->departments()->firstOrFail();
        $commune    = $department->communes()->firstOrFail();

        $this->assertSame($city->id, $commune->cities()->firstOrFail()->id);
    }

    #[Test]
    public function it_keeps_the_point_of_a_postal_code_and_its_source(): void
    {
        $city = $this->createTours37200();

        $fresh = City::query()->findOrFail($city->id);

        $this->assertSame(47.3661, $fresh->latitude);
        $this->assertSame(CoordinateSource::Ban, $fresh->coordinate_source);
        $this->assertSame(1007, $fresh->address_count);
    }

    private function createTours37200(): City
    {
        $region = Region::query()->create([
            'code'       => '24',
            'name'       => 'Centre-Val de Loire',
            'slug'       => 'centre-val-de-loire',
            'valid_from' => '2016-01-01',
        ]);

        $department = Department::query()->create([
            'region_id'  => $region->id,
            'code'       => '37',
            'type'       => DepartmentType::Department,
            'name'       => 'Indre-et-Loire',
            'slug'       => 'indre-et-loire',
            'valid_from' => '1943-01-01',
        ]);

        $commune = Commune::query()->create([
            'department_id'    => $department->id,
            'insee_code'       => '37261',
            'kind'             => CommuneKind::Commune,
            'name'             => 'Tours',
            'slug'             => 'tours',
            'centre_latitude'  => 47.3943,
            'centre_longitude' => 0.6949,
            'valid_from'       => '1943-01-01',
        ]);

        return City::query()->create([
            'commune_id'        => $commune->id,
            'postal_code'       => '37200',
            'label'             => 'TOURS',
            'latitude'          => 47.3661,
            'longitude'         => 0.7044,
            'address_count'     => 1007,
            'coordinate_source' => CoordinateSource::Ban,
            'valid_from'        => '2000-01-01',
        ]);
    }
}
