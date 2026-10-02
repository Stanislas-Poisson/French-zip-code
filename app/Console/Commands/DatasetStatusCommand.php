<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Snapshot;
use App\Services\DatasetUpdater;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class DatasetStatusCommand extends Command
{
    protected $description = 'Show the state of the dataset and of the last update';

    protected $signature = 'dataset:status';

    public function handle(): int
    {
        $this->line('Current cities: ' . City::query()->current()->count());

        foreach (Snapshot::query()->orderByDesc('id')->limit(5)->get() as $snapshot) {
            $this->line(sprintf(
                'Snapshot #%d %s %s: %s',
                $snapshot->id,
                $snapshot->source,
                $snapshot->version,
                $snapshot->complete ? 'complete' : 'incomplete',
            ));
        }

        $last = Cache::get(DatasetUpdater::REPORT_CACHE_KEY);

        if (! is_array($last)) {
            $this->warn('No update has been reconciled yet.');

            return self::SUCCESS;
        }

        $this->printReport($last);

        return self::SUCCESS;
    }

    private function list(mixed $codes): string
    {
        return is_array($codes) && [] !== $codes ? implode(', ', array_map($this->text(...), $codes)) : 'none';
    }

    private function number(mixed $value): string
    {
        return is_numeric($value) ? number_format((int) $value, 0, '.', ' ') : '0';
    }

    /**
     * @param array<mixed> $last
     */
    private function printReport(array $last): void
    {
        $report = is_array($last['report'] ?? null) ? $last['report'] : [];

        $bySource = [];

        foreach (is_array($report['citiesBySource'] ?? null) ? $report['citiesBySource'] : [] as $source => $total) {
            $bySource[] = $source . ' ' . $this->number($total);
        }

        $this->line(sprintf(
            'Last update %s: %s',
            $this->text($last['runId'] ?? ''),
            true === ($last['complete'] ?? false) ? 'complete' : 'INCOMPLETE',
        ));
        $this->line('  Points by source:          ' . ([] === $bySource ? 'none' : implode(', ', $bySource)));
        $this->line('  Cities without a point:    ' . $this->number($report['citiesWithoutCoordinates'] ?? 0));
        $this->line('  Communes without a city:   ' . $this->number($report['communesWithoutCity'] ?? 0));
        $this->line('  Departments not processed: ' . $this->list($report['departmentsNotRun'] ?? []));
        $this->line('  Departments failed:        ' . $this->list($report['departmentsFailed'] ?? []));
        $this->line('  Failed jobs:               ' . $this->number($report['failedJobs'] ?? 0));
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
