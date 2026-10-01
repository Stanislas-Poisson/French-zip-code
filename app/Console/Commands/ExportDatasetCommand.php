<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Export\DatasetExporter;
use Illuminate\Console\Command;

final class ExportDatasetCommand extends Command
{
    protected $description = 'Export the dataset and its history to CSV and JSON files';

    protected $signature = 'zipcode:export {--path= : Directory of the export (storage/app/exports by default)}';

    public function handle(DatasetExporter $datasetExporter): int
    {
        $path      = $this->option('path');
        $directory = is_string($path) && '' !== $path ? $path : storage_path('app/exports');

        foreach ($datasetExporter->export($directory) as $name => $count) {
            $this->line(sprintf('%-22s %d rows', $name, $count));
        }

        $this->info('Exported to ' . $directory . ' (csv and json).');

        return self::SUCCESS;
    }
}
