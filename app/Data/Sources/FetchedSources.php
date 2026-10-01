<?php

declare(strict_types=1);

namespace App\Data\Sources;

use App\Data\Insee\CogImportFiles;
use App\Models\Snapshot;

/**
 * The official files downloaded by an update, with the snapshot of each source.
 */
final readonly class FetchedSources
{
    public function __construct(
        public CogImportFiles $cogFiles,
        public int $cogYear,
        public Snapshot $cogSnapshot,
        public bool $cogChanged,
        public string $laPostePath,
        public Snapshot $laPosteSnapshot,
        public bool $laPosteChanged,
    ) {}
}
