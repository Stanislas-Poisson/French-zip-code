<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\Insee\CogImportFiles;
use App\Data\Insee\DepartmentRecord;
use App\Models\Snapshot;
use App\Services\IdentityBreakDates;
use App\Services\Parsers\Insee\CommuneParser;
use App\Services\Parsers\Insee\DepartmentParser;
use App\Services\Parsers\Insee\MovementParser;
use App\Services\Parsers\Insee\RegionParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class ImportCog
{
    public function __construct(
        private RegionParser $regionParser,
        private DepartmentParser $departmentParser,
        private CommuneParser $communeParser,
        private MovementParser $movementParser,
        private ImportRegions $importRegions,
        private ImportDepartments $importDepartments,
        private ImportCommunes $importCommunes,
        private ImportMovements $importMovements,
        private BuildSuccessions $buildSuccessions,
        private IdentityBreakDates $identityBreakDates,
    ) {}

    /**
     * Imports a vintage of the INSEE COG in one transaction. It can be replayed without creating duplicates.
     *
     * @return array{movements: int, successions: int, communes: int}
     */
    public function execute(CogImportFiles $cogImportFiles, CarbonImmutable $effectiveDate, Snapshot $snapshot): array
    {
        return DB::transaction(function () use ($cogImportFiles, $effectiveDate, $snapshot): array {
            $generator = $this->regionParser->parse($cogImportFiles->regions);
            $this->importRegions->execute($generator, $effectiveDate, $snapshot);

            $this->importDepartments->execute(
                $this->mergedDepartments($cogImportFiles),
                $effectiveDate,
                $snapshot,
            );

            $movements = $this->importMovements->execute(
                $this->movementParser->parse($cogImportFiles->movements),
                $snapshot,
            );
            $successions = $this->buildSuccessions->execute();

            $communes = $this->importCommunes->execute(
                $this->communeParser->parseHistory($cogImportFiles->communeHistory),
                $this->communeParser->parseCurrent($cogImportFiles->communes),
                $this->communeParser->parseOverseas($cogImportFiles->overseasCommunes),
                $this->identityBreakDates->fromMovements(
                    $this->movementParser->parse($cogImportFiles->movements),
                ),
            );

            return ['movements' => $movements, 'successions' => $successions, 'communes' => $communes];
        });
    }

    /**
     * @return iterable<DepartmentRecord>
     */
    private function mergedDepartments(CogImportFiles $cogImportFiles): iterable
    {
        yield from $this->departmentParser->parseDepartments($cogImportFiles->departments);

        yield from $this->departmentParser->parseOverseasCollectivities($cogImportFiles->overseasCollectivities);
    }
}
