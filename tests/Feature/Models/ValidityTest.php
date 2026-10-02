<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\CommuneKind;
use App\Enums\DepartmentType;
use App\Models\City;
use App\Models\Commune;
use App\Models\Department;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ValidityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_closes_a_city_and_links_its_replacement(): void
    {
        $commune = $this->createCommune();

        $city = City::query()->create([
            'commune_id'  => $commune->id,
            'postal_code' => '37000',
            'valid_from'  => '2000-01-01',
            'valid_to'    => '2026-01-01',
        ]);

        $new = City::query()->create([
            'commune_id'  => $commune->id,
            'postal_code' => '37001',
            'valid_from'  => '2026-01-01',
        ]);

        $city->update(['replaced_by_city_id' => $new->id]);

        $this->assertSame($new->id, $city->refresh()->replacedBy?->id);
        $this->assertSame([$new->id], City::query()->current()->pluck('id')->all());
    }

    #[Test]
    public function it_keeps_a_commune_after_its_validity_is_closed(): void
    {
        $commune = $this->createCommune();
        $commune->update(['valid_to' => '2016-01-01']);

        $this->assertSame(0, Commune::query()->current()->count());
        $this->assertSame(1, Commune::query()->count());
    }

    private function createCommune(): Commune
    {
        $region = Region::query()->create([
            'code'       => '52',
            'name'       => 'Pays de la Loire',
            'slug'       => 'pays-de-la-loire',
            'valid_from' => '2016-01-01',
        ]);

        $department = Department::query()->create([
            'region_id'  => $region->id,
            'code'       => '85',
            'type'       => DepartmentType::Department,
            'name'       => 'Vendée',
            'slug'       => 'vendee',
            'valid_from' => '1943-01-01',
        ]);

        return Commune::query()->create([
            'department_id' => $department->id,
            'insee_code'    => '85213',
            'kind'          => CommuneKind::Commune,
            'name'          => "Rives de l'Yon",
            'slug'          => 'rives-de-l-yon',
            'valid_from'    => '2016-01-01',
        ]);
    }
}
