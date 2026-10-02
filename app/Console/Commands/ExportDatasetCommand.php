<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\BuildDatasetStatistics;
use App\Services\Export\DatasetExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class ExportDatasetCommand extends Command
{
    protected $description = 'Export the dataset and its history to CSV and JSON files';

    protected $signature = 'dataset:export {--path= : Directory of the export (storage/app/exports by default)}';

    public function handle(DatasetExporter $datasetExporter, BuildDatasetStatistics $buildDatasetStatistics): int
    {
        $path      = $this->option('path');
        $directory = is_string($path) && '' !== $path ? $path : storage_path('app/exports');

        foreach ($datasetExporter->export($directory) as $name => $count) {
            $this->line(sprintf('%-22s %d rows', $name, $count));
        }

        File::put(
            $directory . '/statistics.json',
            json_encode($buildDatasetStatistics->execute()->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );

        $this->info('Exported to ' . $directory . ' (csv, json and statistics.json).');

        return self::SUCCESS;
    }
}
