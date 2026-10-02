<?php

declare(strict_types=1);

namespace App\Services\Parsers\Insee;

use App\Data\Insee\RegionRecord;
use App\Services\Parsers\CsvFile;
use Generator;

final readonly class RegionParser
{
    public function __construct(private CsvFile $csvFile) {}

    /**
     * @return Generator<int, RegionRecord>
     */
    public function parse(string $path): Generator
    {
        foreach ($this->csvFile->rows($path) as $row) {
            yield new RegionRecord(
                code: $row['REG'],
                name: $row['LIBELLE'],
                seatCommuneCode: $row['CHEFLIEU'],
            );
        }
    }
}
