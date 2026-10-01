<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\Sources\ReconciliationReport;
use App\Models\City;
use App\Models\Commune;
use App\Models\Snapshot;
use Illuminate\Support\Facades\Cache;

final readonly class ReconcileDataset
{
    public function __construct(private ComputeDepartmentCoordinates $computeDepartmentCoordinates) {}

    public static function cacheKey(string $runId, string $departmentCode): string
    {
        return 'dataset:' . $runId . ':ban:' . $departmentCode;
    }

    /**
     * Compares what was expected with what the update produced, and marks the snapshots as complete
     * only when nothing is missing.
     *
     * @param list<int> $snapshotIds snapshots imported by this update
     */
    public function execute(string $runId, array $snapshotIds, int $failedJobs): ReconciliationReport
    {
        $notRun = [];
        $failed = [];

        foreach ($this->computeDepartmentCoordinates->departmentCodes() as $code) {
            $result = Cache::get(self::cacheKey($runId, $code));

            if (null === $result) {
                $notRun[] = $code;

                continue;
            }

            if ('failed' === $result) {
                $failed[] = $code;
            }
        }

        /** @var array<string, int> $bySource */
        $bySource = City::query()
            ->current()
            ->selectRaw("coalesce(coordinate_source, 'none') as source, count(*) as total")
            ->groupBy('source')
            ->pluck('total', 'source')
            ->map(static fn (mixed $total): int => is_numeric($total) ? (int) $total : 0)
            ->all();

        $reconciliationReport = new ReconciliationReport(
            openCities: City::query()->current()->count(),
            citiesWithoutCoordinates: City::query()->current()->whereNull('latitude')->count(),
            citiesBySource: $bySource,
            communesWithoutCity: Commune::query()->current()->whereDoesntHave('cities', static fn ($query) => $query->whereNull('valid_to'))->count(),
            departmentsNotRun: $notRun,
            departmentsFailed: $failed,
            failedJobs: $failedJobs,
        );

        if ($reconciliationReport->isComplete()) {
            Snapshot::query()->whereIn('id', $snapshotIds)->update(['complete' => true]);
        }

        return $reconciliationReport;
    }
}
