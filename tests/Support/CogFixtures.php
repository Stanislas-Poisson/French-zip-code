<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\ImportCog;
use App\Actions\RecordSnapshot;
use App\Data\Insee\CogImportFiles;
use App\Data\Sources\DownloadedFile;
use App\Models\Snapshot;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;

/**
 * Helpers to import the small INSEE fixtures extracted from the real files.
 */
final class CogFixtures
{
    public static function files(): CogImportFiles
    {
        $directory = __DIR__ . '/../Fixtures/insee/';

        return new CogImportFiles(
            regions: $directory . 'v_region.csv',
            departments: $directory . 'v_departement.csv',
            overseasCollectivities: $directory . 'v_comer.csv',
            communes: $directory . 'v_commune.csv',
            overseasCommunes: $directory . 'v_commune_comer.csv',
            communeHistory: $directory . 'v_commune_depuis_1943.csv',
            movements: $directory . 'v_mvt_commune.csv',
        );
    }

    /**
     * @return array{movements: int, successions: int, communes: int}
     */
    public static function import(Application $application, string $version = '2026'): array
    {
        return $application->make(ImportCog::class)->execute(
            self::files(),
            CarbonImmutable::parse($version . '-01-01'),
            self::snapshot($application, $version),
        );
    }

    public static function snapshot(Application $application, string $version = '2026'): Snapshot
    {
        return $application->make(RecordSnapshot::class)->execute('insee_cog', $version, new DownloadedFile('/tmp/x', hash('sha256', $version), 1));
    }
}
