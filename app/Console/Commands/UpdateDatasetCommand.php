<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RunDatasetUpdateJob;
use App\Services\DatasetUpdater;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class UpdateDatasetCommand extends Command
{
    protected $description = 'Download the official files and update the dataset';

    protected $signature = 'dataset:update
        {--sync : Run everything in this process instead of the queue}
        {--force : Import again the files that did not change}
        {--skip-coordinates : Do not compute the point of each postal code}';

    public function handle(DatasetUpdater $datasetUpdater): int
    {
        $runId           = (string) Str::uuid();
        $force           = (bool) $this->option('force');
        $withCoordinates = ! $this->option('skip-coordinates');

        if (! $this->option('sync')) {
            dispatch(new RunDatasetUpdateJob($runId, $force, $withCoordinates));
            $this->info('Update ' . $runId . ' queued. Follow it with "make status" and "make horizon-logs".');

            return self::SUCCESS;
        }

        config(['queue.default' => 'sync']);

        $result = $datasetUpdater->run($runId, $force, $withCoordinates);

        $this->info('Update ' . $runId . ': ' . $result['status'] . '.');

        return self::SUCCESS;
    }
}
