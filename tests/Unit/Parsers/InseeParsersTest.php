<?php

declare(strict_types=1);

namespace Tests\Unit\Parsers;

use App\Data\Insee\CommuneMovementRecord;
use App\Enums\CommuneKind;
use App\Enums\DepartmentType;
use App\Enums\EventModality;
use App\Services\Parsers\CsvFile;
use App\Services\Parsers\Insee\CommuneParser;
use App\Services\Parsers\Insee\DepartmentParser;
use App\Services\Parsers\Insee\MovementParser;
use App\Services\Parsers\Insee\RegionParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InseeParsersTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../../Fixtures/insee/';

    #[Test]
    public function it_parses_the_communes_of_the_overseas_collectivities(): void
    {
        $communes = iterator_to_array((new CommuneParser(new CsvFile))->parseOverseas(self::FIXTURES . 'v_commune_comer.csv'), false);

        $this->assertCount(2, $communes);
        $this->assertSame('97501', $communes[0]->inseeCode);
        $this->assertSame('975', $communes[0]->departmentCode);
        $this->assertNull($communes[0]->regionCode);
    }

    #[Test]
    public function it_parses_the_current_communes_with_their_kind_and_parent(): void
    {
        $communes = iterator_to_array((new CommuneParser(new CsvFile))->parseCurrent(self::FIXTURES . 'v_commune.csv'), false);
        $byKey    = [];

        foreach ($communes as $commune) {
            $byKey[$commune->kind->value . $commune->inseeCode] = $commune;
        }

        $this->assertSame('Tours', $byKey['COM37261']->name);
        $this->assertSame('37', $byKey['COM37261']->departmentCode);
        $this->assertNull($byKey['COM37261']->parentCode);

        $this->assertSame(CommuneKind::Delegated, $byKey['COMD01015']->kind);
        $this->assertNull($byKey['COMD01015']->departmentCode);
        $this->assertSame('01015', $byKey['COMD01015']->parentCode);

        $this->assertSame(CommuneKind::Arrondissement, $byKey['ARM75101']->kind);
    }

    #[Test]
    public function it_parses_the_departments_and_the_overseas_collectivities(): void
    {
        $departmentParser = new DepartmentParser(new CsvFile);

        $departments    = iterator_to_array($departmentParser->parseDepartments(self::FIXTURES . 'v_departement.csv'), false);
        $collectivities = iterator_to_array($departmentParser->parseOverseasCollectivities(self::FIXTURES . 'v_comer.csv'), false);

        $this->assertCount(4, $departments);
        $this->assertSame('37', $departments[0]->code);
        $this->assertSame('24', $departments[0]->regionCode);
        $this->assertSame(DepartmentType::Department, $departments[0]->type);

        $this->assertCount(1, $collectivities);
        $this->assertSame('975', $collectivities[0]->code);
        $this->assertNull($collectivities[0]->regionCode);
        $this->assertSame(DepartmentType::OverseasCollectivity, $collectivities[0]->type);
    }

    #[Test]
    public function it_parses_the_movements_of_the_saint_florent_case(): void
    {
        $movements = iterator_to_array((new MovementParser(new CsvFile))->parse(self::FIXTURES . 'v_mvt_commune.csv'), false);

        $absorbed = array_values(array_filter(
            $movements,
            static fn (CommuneMovementRecord $communeMovementRecord): bool => '85043' === $communeMovementRecord->codeBefore
                && CommuneKind::Commune                                               === $communeMovementRecord->kindAfter,
        ));

        $this->assertCount(1, $absorbed);
        $this->assertSame(EventModality::NewCommuneCreation, $absorbed[0]->modality);
        $this->assertSame('85213', $absorbed[0]->codeAfter);
        $this->assertSame("Rives de l'Yon", $absorbed[0]->nameAfter);
        $this->assertSame('2016-01-01', $absorbed[0]->effectiveDate->toDateString());
    }

    #[Test]
    public function it_parses_the_regions(): void
    {
        $regions = iterator_to_array((new RegionParser(new CsvFile))->parse(self::FIXTURES . 'v_region.csv'), false);

        $this->assertCount(3, $regions);
        $this->assertSame('24', $regions[1]->code);
        $this->assertSame('Centre-Val de Loire', $regions[1]->name);
        $this->assertSame('45234', $regions[1]->seatCommuneCode);
    }

    #[Test]
    public function it_parses_the_validity_periods_since_1943(): void
    {
        $history       = iterator_to_array((new CommuneParser(new CsvFile))->parseHistory(self::FIXTURES . 'v_commune_depuis_1943.csv'), false);
        $byCodeAndName = [];

        foreach ($history as $entry) {
            $byCodeAndName[$entry->inseeCode . '|' . $entry->name] = $entry;
        }

        $this->assertNull($byCodeAndName['37261|Tours']->validTo);
        $this->assertSame('1943-01-01', $byCodeAndName['37261|Tours']->validFrom->toDateString());
        $this->assertSame('2016-01-01', $byCodeAndName['85043|Chaillé-sous-les-Ormeaux']->validTo?->toDateString());
        $this->assertSame('2016-01-01', $byCodeAndName["85213|Rives de l'Yon"]->validFrom->toDateString());
    }
}
