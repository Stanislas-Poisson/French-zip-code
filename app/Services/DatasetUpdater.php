<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\ComputeDepartmentCoordinates;
use App\Actions\FetchSources;
use App\Actions\ImportOfficialSources;
use App\Actions\ReconcileDataset;
use App\Data\Sources\ReconciliationReport;
use App\Enums\CoordinateSource;
use App\Jobs\ComputeBanCoordinatesJob;
use App\Jobs\GeocodeCityJob;
use App\Models\City;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Runs an update of the dataset: official files, then the point of each zip code, then the reconciliation.
 * The point of each zip code is computed by batches of jobs, which can run in parallel.
 */
final readonly class DatasetUpdater
{
    public const string REPORT_CACHE_KEY = 'dataset:last-report';

    public function __construct(
        private FetchSources $fetchSources,
        private ImportOfficialSources $importOfficialSources,
        private ComputeDepartmentCoordinates $computeDepartmentCoordinates,
        private ReconcileDataset $reconcileDataset,
    ) {}

    /**
     * Asks Nominatim for the cities that still have no better point than the centre of their commune.
     *
     * @param list<int> $snapshotIds
     */
    public function afterBanBatch(string $runId, array $snapshotIds, int $failedBanJobs): void
    {
        $cityIds = City::query()
            ->current()
            ->where('coordinate_source', CoordinateSource::CommuneCentre->value)
            ->pluck('id')
            ->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)
            ->all();

        if ([] === $cityIds) {
            $this->finalize($runId, $snapshotIds, $failedBanJobs);

            return;
        }

        Bus::batch(array_map(static fn (int $id): GeocodeCityJob => new GeocodeCityJob($id), $cityIds))
            ->name('nominatim-fallback:' . $runId)
            ->allowFailures()
            ->finally(static function () use ($runId, $snapshotIds, $failedBanJobs): void {
                // A city that Nominatim cannot place keeps the centre of its commune: it is not a failure.
                resolve(self::class)->finalize($runId, $snapshotIds, $failedBanJobs);
            })
            ->dispatch();
    }

    /**
     * @param list<int> $snapshotIds
     */
    public function dispatchBanBatch(string $runId, array $snapshotIds): void
    {
        $jobs = array_map(
            static fn (string $code): ComputeBanCoordinatesJob => new ComputeBanCoordinatesJob($runId, $code),
            $this->computeDepartmentCoordinates->departmentCodes(),
        );

        if ([] === $jobs) {
            $this->afterBanBatch($runId, $snapshotIds, 0);

            return;
        }

        Bus::batch($jobs)
            ->name('ban-coordinates:' . $runId)
            ->allowFailures()
            ->finally(static function (Batch $batch) use ($runId, $snapshotIds): void {
                resolve(self::class)->afterBanBatch($runId, $snapshotIds, $batch->failedJobs);
            })
            ->dispatch();
    }

    /**
     * @param list<int> $snapshotIds
     */
    public function finalize(string $runId, array $snapshotIds, int $failedBanJobs): ReconciliationReport
    {
        $reconciliationReport = $this->reconcileDataset->execute($runId, $snapshotIds, $failedBanJobs);

        Cache::forever(self::REPORT_CACHE_KEY, [
            'runId'    => $runId,
            'complete' => $reconciliationReport->isComplete(),
            'report'   => (array) $reconciliationReport,
        ]);

        Log::log(
            $reconciliationReport->isComplete() ? 'info' : 'warning',
            'Dataset update ' . $runId . ($reconciliationReport->isComplete() ? ' complete' : ' incomplete'),
            (array) $reconciliationReport,
        );

        return $reconciliationReport;
    }

    /**
     * @return array{status: string, runId: string}
     */
    public function run(string $runId, bool $force, bool $withCoordinates): array
    {
        $result = $this->importOfficialSources->execute($this->fetchSources->execute(), $force);

        if (! $result['imported'] && ! $force) {
            return ['status' => 'up_to_date', 'runId' => $runId];
        }

        if (! $withCoordinates) {
            $this->finalize($runId, $result['snapshotIds'], 0);

            return ['status' => 'imported', 'runId' => $runId];
        }

        $this->dispatchBanBatch($runId, $result['snapshotIds']);

        return ['status' => 'dispatched', 'runId' => $runId];
    }
}
