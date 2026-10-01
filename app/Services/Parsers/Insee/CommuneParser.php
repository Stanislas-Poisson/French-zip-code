<?php

declare(strict_types=1);

namespace App\Services\Parsers\Insee;

use App\Data\Insee\CommuneRecord;
use App\Data\Insee\HistoricCommuneRecord;
use App\Enums\CommuneKind;
use App\Services\Parsers\CsvFile;
use Carbon\CarbonImmutable;
use Generator;

final readonly class CommuneParser
{
    public function __construct(private CsvFile $csvFile) {}

    /**
     * Communes, arrondissements, delegated and associated communes of the current vintage.
     *
     * @return Generator<int, CommuneRecord>
     */
    public function parseCurrent(string $path): Generator
    {
        foreach ($this->csvFile->rows($path) as $row) {
            yield new CommuneRecord(
                inseeCode: $row['COM'],
                kind: CommuneKind::from($row['TYPECOM']),
                name: $row['LIBELLE'],
                departmentCode: '' === $row['DEP'] ? null : $row['DEP'],
                regionCode: ''     === $row['REG'] ? null : $row['REG'],
                parentCode: ''     === $row['COMPARENT'] ? null : $row['COMPARENT'],
            );
        }
    }

    /**
     * Every code with the period during which it carried a given name, since 1943.
     *
     * @return Generator<int, HistoricCommuneRecord>
     */
    public function parseHistory(string $path): Generator
    {
        foreach ($this->csvFile->rows($path) as $row) {
            yield new HistoricCommuneRecord(
                inseeCode: $row['COM'],
                kind: CommuneKind::from($row['TYPECOM']),
                name: $row['LIBELLE'],
                validFrom: CarbonImmutable::parse($row['DATE_DEBUT']),
                validTo: '' === $row['DATE_FIN'] ? null : CarbonImmutable::parse($row['DATE_FIN']),
            );
        }
    }

    /**
     * Communes of the overseas collectivities (COM), which have no department nor region.
     *
     * @return Generator<int, CommuneRecord>
     */
    public function parseOverseas(string $path): Generator
    {
        foreach ($this->csvFile->rows($path) as $row) {
            yield new CommuneRecord(
                inseeCode: $row['COM_COMER'],
                kind: CommuneKind::Commune,
                name: $row['LIBELLE'],
                departmentCode: $row['COMER'],
                regionCode: null,
                parentCode: null,
            );
        }
    }
}
