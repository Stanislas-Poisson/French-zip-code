<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\DatasetUpdater;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Entry point of an update when it runs on the queue: it imports the official files then dispatches the batches.
 */
final class RunDatasetUpdateJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public readonly string $runId,
        public readonly bool $force,
        public readonly bool $withCoordinates,
    ) {}

    public function handle(DatasetUpdater $datasetUpdater): void
    {
        $datasetUpdater->run($this->runId, $this->force, $this->withCoordinates);
    }
}
