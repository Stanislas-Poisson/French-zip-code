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

    protected $signature = 'zipcode:status';

    public function handle(): int
    {
        $this->line('Current cities: ' . City::query()->current()->count());

        foreach (Snapshot::query()->orderByDesc('id')->limit(5)->get() as $snapshot) {
            $this->line(sprintf('Snapshot #%d %s %s: %s', $snapshot->id, $snapshot->source, $snapshot->version, $snapshot->complete ? 'complete' : 'incomplete'));
        }

        $last = Cache::get(DatasetUpdater::REPORT_CACHE_KEY);

        if (! is_array($last)) {
            $this->warn('No update has been reconciled yet.');

            return self::SUCCESS;
        }

        $this->line('Last update: ' . json_encode($last, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
