<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\ComputeDepartmentCoordinates;
use App\Actions\ReconcileDataset;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Computes the point of every postal code of one department from its BAN file.
 * The job is idempotent: it can be replayed after a failure.
 */
final class ComputeBanCoordinatesJob implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $timeout = 900;

    public int $tries = 3;

    public function __construct(
        public readonly string $runId,
        public readonly string $departmentCode,
    ) {
        $this->onQueue('ban');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function failed(Throwable $throwable): void
    {
        Cache::put(ReconcileDataset::cacheKey($this->runId, $this->departmentCode), 'failed', now()->addDay());
    }

    public function handle(ComputeDepartmentCoordinates $computeDepartmentCoordinates): void
    {
        $result = $computeDepartmentCoordinates->execute($this->departmentCode);

        Cache::put(ReconcileDataset::cacheKey($this->runId, $this->departmentCode), $result, now()->addDay());
    }
}
