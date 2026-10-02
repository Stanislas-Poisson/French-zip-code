<?php

declare(strict_types=1);

namespace App\Services\Parsers\Insee;

use App\Data\Insee\DepartmentRecord;
use App\Enums\DepartmentType;
use App\Services\Parsers\CsvFile;
use Generator;

/**
 * Reads the departments file and the overseas collectivities file.
 */
final readonly class DepartmentParser
{
    public function __construct(private CsvFile $csvFile) {}

    /**
     * @return Generator<int, DepartmentRecord>
     */
    public function parseDepartments(string $path): Generator
    {
        foreach ($this->csvFile->rows($path) as $row) {
            yield new DepartmentRecord(
                code: $row['DEP'],
                regionCode: $row['REG'],
                type: DepartmentType::Department,
                name: $row['LIBELLE'],
                seatCommuneCode: $row['CHEFLIEU'],
            );
        }
    }

    /**
     * @return Generator<int, DepartmentRecord>
     */
    public function parseOverseasCollectivities(string $path): Generator
    {
        foreach ($this->csvFile->rows($path) as $row) {
            yield new DepartmentRecord(
                code: $row['COMER'],
                regionCode: null,
                type: DepartmentType::OverseasCollectivity,
                name: $row['LIBELLE'],
                seatCommuneCode: null,
            );
        }
    }
}
