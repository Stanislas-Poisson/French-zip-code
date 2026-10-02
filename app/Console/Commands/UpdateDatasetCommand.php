<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RunDatasetUpdateJob;
use App\Services\DatasetUpdater;
use App\Services\UpdateProgress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class UpdateDatasetCommand extends Command
{
    protected $description = 'Download the official files and update the dataset';

    protected $signature = 'dataset:update
        {--sync : Run everything in this process instead of the queue}
        {--force : Import again the files that did not change}
        {--skip-coordinates : Do not compute the point of each postal code}';

    public function handle(DatasetUpdater $datasetUpdater, UpdateProgress $updateProgress): int
    {
        $runId           = (string) Str::uuid();
        $force           = (bool) $this->option('force');
        $withCoordinates = ! $this->option('skip-coordinates');

        if (! $this->option('sync')) {
            dispatch(new RunDatasetUpdateJob($runId, $force, $withCoordinates));
            $this->info('Update ' . $runId . ' queued. Follow it with "make status" and "make horizon-logs".');

            return self::SUCCESS;
        }

        return $this->runHere($datasetUpdater, $updateProgress, $runId, $force, $withCoordinates);
    }

    private function isComplete(string $runId): bool
    {
        $last = Cache::get(DatasetUpdater::REPORT_CACHE_KEY);

        return is_array($last) && $runId === ($last['runId'] ?? null) && true === ($last['complete'] ?? false);
    }

    /**
     * In this mode everything ran in this process, so what the updater calls "dispatched" is over.
     */
    private function label(string $status): string
    {
        return 'dispatched' === $status ? 'completed' : str_replace('_', ' ', $status);
    }

    private function runHere(
        DatasetUpdater $datasetUpdater,
        UpdateProgress $updateProgress,
        string $runId,
        bool $force,
        bool $withCoordinates,
    ): int {
        config(['queue.default' => 'sync']);
        $updateProgress->attach($this->output);

        $result = $datasetUpdater->run($runId, $force, $withCoordinates);
        $updateProgress->finish();

        $this->info(sprintf('Update %s %s.', $runId, $this->label($result['status'])));
        $this->call('dataset:status');

        if ($withCoordinates && 'up_to_date' !== $result['status'] && ! $this->isComplete($runId)) {
            $this->error('The update is incomplete: do not publish this dataset.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
