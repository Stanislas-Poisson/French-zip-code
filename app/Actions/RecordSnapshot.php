<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\Sources\DownloadedFile;
use App\Models\Snapshot;

final class RecordSnapshot
{
    /**
     * Records a downloaded file. A file with the same source, version and checksum is recorded only once.
     */
    public function execute(string $source, string $version, DownloadedFile $downloadedFile): Snapshot
    {
        return Snapshot::query()->firstOrCreate(
            [
                'source'   => $source,
                'version'  => $version,
                'checksum' => $downloadedFile->checksum,
            ],
            [
                'fetched_at' => now(),
                'complete'   => false,
            ],
        );
    }
}
