<?php

declare(strict_types=1);

namespace App\Data\Insee;

/**
 * Local paths of the COG files needed by an import.
 */
final readonly class CogImportFiles
{
    public function __construct(
        public string $regions,
        public string $departments,
        public string $overseasCollectivities,
        public string $communes,
        public string $overseasCommunes,
        public string $communeHistory,
        public string $movements,
    ) {}
}
