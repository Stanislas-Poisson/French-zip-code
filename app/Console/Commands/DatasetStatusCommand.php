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
    private const array SOURCE_LABELS = [
        'ban'            => 'Points from the BAN',
        'nominatim'      => 'Points from Nominatim',
        'commune_centre' => 'Points at the commune centre',
    ];

    protected $description = 'Show the state of the dataset and of the last update';

    protected $signature = 'dataset:status';

    public function handle(): int
    {
        $this->components->twoColumnDetail('<options=bold>Current cities</>', $this->number(City::query()->current()->count()));

        $this->heading('Sources');

        foreach (Snapshot::query()->orderByDesc('id')->limit(5)->get() as $snapshot) {
            $this->components->twoColumnDetail(
                $snapshot->source . ' ' . $snapshot->version,
                $snapshot->complete ? '<fg=green;options=bold>complete</>' : '<fg=red;options=bold>incomplete</>',
            );
        }

        $last = Cache::get(DatasetUpdater::REPORT_CACHE_KEY);

        if (! is_array($last)) {
            $this->newLine();
            $this->components->warn('No update has been reconciled yet.');

            return self::SUCCESS;
        }

        $this->printReport($last);

        return self::SUCCESS;
    }

    private function heading(string $title): void
    {
        $this->newLine();
        $this->line('  <fg=yellow;options=bold>' . $title . '</>');
    }

    private function list(mixed $codes): string
    {
        return is_array($codes) && [] !== $codes
            ? '<fg=red;options=bold>' . implode(', ', array_map($this->text(...), $codes)) . '</>'
            : '<fg=green>none</>';
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
        $report   = is_array($last['report'] ?? null) ? $last['report'] : [];
        $bySource = is_array($report['citiesBySource'] ?? null) ? $report['citiesBySource'] : [];
        $total    = array_sum(array_map(static fn (mixed $count): int => is_numeric($count) ? (int) $count : 0, $bySource));

        $this->heading('Last update ' . $this->text($last['runId'] ?? ''));
        $this->components->twoColumnDetail(
            'Status',
            true === ($last['complete'] ?? false) ? '<fg=green;options=bold>COMPLETE</>' : '<fg=red;options=bold>INCOMPLETE</>',
        );

        foreach ($bySource as $source => $count) {
            $this->components->twoColumnDetail(
                self::SOURCE_LABELS[$source] ?? 'Points from ' . $source,
                sprintf('%s <fg=gray>(%s %%)</>', $this->number($count), number_format(0 < $total && is_numeric($count) ? 100 * (int) $count / $total : 0, 1)),
            );
        }

        $this->components->twoColumnDetail('Cities without a point', $this->problem($report['citiesWithoutCoordinates'] ?? 0));
        $this->components->twoColumnDetail('Communes without a city', '<fg=gray>' . $this->number($report['communesWithoutCity'] ?? 0) . '</>');
        $this->components->twoColumnDetail('Departments not processed', $this->list($report['departmentsNotRun'] ?? []));
        $this->components->twoColumnDetail('Departments failed', $this->list($report['departmentsFailed'] ?? []));
        $this->components->twoColumnDetail('Failed jobs', $this->problem($report['failedJobs'] ?? 0));
    }

    /**
     * A figure that should be zero: green when it is, red otherwise.
     */
    private function problem(mixed $value): string
    {
        $number = is_numeric($value) ? (int) $value : 0;

        return (0 < $number ? '<fg=red;options=bold>' : '<fg=green>') . $this->number($number) . '</>';
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
