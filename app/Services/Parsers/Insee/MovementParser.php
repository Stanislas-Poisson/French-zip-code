<?php

declare(strict_types=1);

namespace App\Services\Parsers\Insee;

use App\Data\Insee\CommuneMovementRecord;
use App\Enums\CommuneKind;
use App\Enums\EventModality;
use App\Services\Parsers\CsvFile;
use Carbon\CarbonImmutable;
use Generator;

final readonly class MovementParser
{
    public function __construct(private CsvFile $csvFile) {}

    /**
     * @return Generator<int, CommuneMovementRecord>
     */
    public function parse(string $path): Generator
    {
        foreach ($this->csvFile->rows($path) as $row) {
            yield new CommuneMovementRecord(
                modality: EventModality::from((int) $row['MOD']),
                effectiveDate: CarbonImmutable::parse($row['DATE_EFF']),
                kindBefore: $this->kind($row['TYPECOM_AV']),
                codeBefore: $this->nullable($row['COM_AV']),
                nameBefore: $this->nullable($row['LIBELLE_AV']),
                kindAfter: $this->kind($row['TYPECOM_AP']),
                codeAfter: $this->nullable($row['COM_AP']),
                nameAfter: $this->nullable($row['LIBELLE_AP']),
            );
        }
    }

    private function kind(string $value): ?CommuneKind
    {
        return '' === $value ? null : CommuneKind::from($value);
    }

    private function nullable(string $value): ?string
    {
        return '' === $value ? null : $value;
    }
}
