<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Data\Sources\ReconciliationReport;
use App\Models\City;
use App\Models\Snapshot;
use App\Services\DatasetUpdater;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

final class DatasetStatusCommand extends Command
{
    private const array SOURCE_LABELS = [
        'ban'            => 'Points from the BAN',
        'nominatim'      => 'Points from Nominatim',
        'commune_centre' => 'Points at the commune centre',
    ];

    protected $description = 'Show the state of the dataset and of the last update';

    protected $signature = 'dataset:status';

    public function handle(): int
    {
        $this->row('<options=bold>Current cities</>', $this->number(City::query()->current()->count()));

        $this->heading('Sources');

        foreach (Snapshot::query()->orderByDesc('id')->limit(5)->get() as $snapshot) {
            $this->row($snapshot->source . ' ' . $snapshot->version, $this->state($snapshot->complete));
        }

        $last = Cache::get(DatasetUpdater::REPORT_CACHE_KEY);

        if (! is_array($last)) {
            $this->newLine();
            $this->components->warn('No update has been reconciled yet.');

            return self::SUCCESS;
        }

        $this->printReport(Arr::string($last, 'runId'), ReconciliationReport::fromArray(Arr::array($last, 'report')));

        return self::SUCCESS;
    }

    private function heading(string $title): void
    {
        $this->newLine();
        $this->line('  <fg=yellow;options=bold>' . $title . '</>');
    }

    /**
     * @param list<string> $codes
     */
    private function list(array $codes): string
    {
        return [] === $codes ? '<fg=green>none</>' : '<fg=red;options=bold>' . implode(', ', $codes) . '</>';
    }

    private function number(int $value): string
    {
        return number_format($value, 0, '.', ' ');
    }

    private function printReport(string $runId, ReconciliationReport $reconciliationReport): void
    {
        $this->heading('Last update ' . $runId);
        $status = $reconciliationReport->isComplete()
            ? '<fg=green;options=bold>COMPLETE</>'
            : '<fg=red;options=bold>INCOMPLETE</>';

        $this->row('Status', $status);

        $this->printSources($reconciliationReport->citiesBySource);

        $this->row('Cities without a point', $this->problem($reconciliationReport->citiesWithoutCoordinates));
        $withoutCity = $this->number($reconciliationReport->communesWithoutCity);

        $this->row('Communes without a city', '<fg=gray>' . $withoutCity . '</>');
        $this->row('Departments not processed', $this->list($reconciliationReport->departmentsNotRun));
        $this->row('Departments failed', $this->list($reconciliationReport->departmentsFailed));
        $this->row('Failed jobs', $this->problem($reconciliationReport->failedJobs));
    }

    /**
     * @param array<string, int> $bySource
     */
    private function printSources(array $bySource): void
    {
        $total = max(1, array_sum($bySource));

        foreach ($bySource as $source => $count) {
            $this->row(
                self::SOURCE_LABELS[$source] ?? 'Points from ' . $source,
                sprintf('%s <fg=gray>(%s %%)</>', $this->number($count), number_format(100 * $count / $total, 1)),
            );
        }
    }

    /**
     * A figure that should be zero: green when it is, red otherwise.
     */
    private function problem(int $value): string
    {
        return (0 < $value ? '<fg=red;options=bold>' : '<fg=green>') . $this->number($value) . '</>';
    }

    private function row(string $label, string $value): void
    {
        $this->components->twoColumnDetail($label, $value);
    }

    private function state(bool $complete, string $word = 'complete'): string
    {
        return $complete ? '<fg=green;options=bold>' . $word . '</>' : '<fg=red;options=bold>in' . $word . '</>';
    }
}
