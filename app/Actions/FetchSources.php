<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\FileDownloader;
use App\Data\Insee\CogImportFiles;
use App\Data\Sources\DownloadedFile;
use App\Data\Sources\FetchedSources;
use App\Services\Sources\InseeCogLocator;

final readonly class FetchSources
{
    public const string COG = 'insee_cog';

    public const string LA_POSTE = 'laposte';

    public function __construct(
        private FileDownloader $fileDownloader,
        private InseeCogLocator $inseeCogLocator,
        private RecordSnapshot $recordSnapshot,
    ) {}

    /**
     * Downloads the official files (INSEE COG and La Poste) and records them as snapshots.
     * A file whose content did not change since the last import is recorded only once.
     */
    public function execute(): FetchedSources
    {
        $cogVintage   = $this->inseeCogLocator->latest();
        $directory    = $this->directory();

        $checksums = [];
        $paths     = [];

        foreach (['v_region', 'v_departement', 'v_comer', 'v_commune', 'v_commune_comer', 'v_commune_depuis_1943', 'v_mvt_commune'] as $name) {
            $file         = $this->fileDownloader->download($cogVintage->url($name), $directory . '/' . self::COG . '/' . $cogVintage->year . '/' . $name . '.csv');
            $checksums[]  = $file->checksum;
            $paths[$name] = $file->path;
        }

        $cogSnapshot = $this->recordSnapshot->execute(
            self::COG,
            (string) $cogVintage->year,
            new DownloadedFile($directory . '/' . self::COG . '/' . $cogVintage->year, hash('sha256', implode('', $checksums)), 0),
        );

        $laPosteUrl     = config('sources.laposte.file_url');
        $downloadedFile = $this->fileDownloader->download(
            is_string($laPosteUrl) ? $laPosteUrl : '',
            $directory . '/' . self::LA_POSTE . '/hexasmal.csv',
        );
        $laPosteSnapshot = $this->recordSnapshot->execute(self::LA_POSTE, now()->format('Y-m'), $downloadedFile);

        return new FetchedSources(
            cogFiles: new CogImportFiles(
                regions: $paths['v_region'],
                departments: $paths['v_departement'],
                overseasCollectivities: $paths['v_comer'],
                communes: $paths['v_commune'],
                overseasCommunes: $paths['v_commune_comer'],
                communeHistory: $paths['v_commune_depuis_1943'],
                movements: $paths['v_mvt_commune'],
            ),
            cogYear: $cogVintage->year,
            cogSnapshot: $cogSnapshot,
            cogChanged: null === $cogSnapshot->imported_at,
            laPostePath: $downloadedFile->path,
            laPosteSnapshot: $laPosteSnapshot,
            laPosteChanged: null === $laPosteSnapshot->imported_at,
        );
    }

    private function directory(): string
    {
        $directory = config('sources.directory');

        return rtrim(is_string($directory) ? $directory : storage_path('app/sources'), '/');
    }
}
