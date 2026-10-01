<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\Sources\FetchedSources;
use App\Services\Parsers\LaPoste\PostalCodeParser;
use App\Services\Sources\GeoApiClient;
use Carbon\CarbonImmutable;

final readonly class ImportOfficialSources
{
    public function __construct(
        private ImportCog $importCog,
        private ImportCities $importCities,
        private ApplyCommuneCentres $applyCommuneCentres,
        private FillCityCoordinatesFromCommuneCentre $fillCityCoordinatesFromCommuneCentre,
        private LinkReplacedCities $linkReplacedCities,
        private PostalCodeParser $postalCodeParser,
        private GeoApiClient $geoApiClient,
    ) {}

    /**
     * Imports the official files that changed since the last update (or all of them when forced),
     * then gives every city the centre of its commune as a first point.
     *
     * @return array{imported: bool, snapshotIds: list<int>, unmatched: list<string>}
     */
    public function execute(FetchedSources $fetchedSources, bool $force): array
    {
        $importCog    = $force     || $fetchedSources->cogChanged;
        $importCities = $importCog || $fetchedSources->laPosteChanged;

        if (! $importCities) {
            return ['imported' => false, 'snapshotIds' => [], 'unmatched' => []];
        }

        $snapshotIds = [];
        $unmatched   = [];

        if ($importCog) {
            $this->importCog->execute($fetchedSources->cogFiles, CarbonImmutable::parse($fetchedSources->cogYear . '-01-01'), $fetchedSources->cogSnapshot);
            $fetchedSources->cogSnapshot->update(['imported_at' => now()]);
            $snapshotIds[] = $fetchedSources->cogSnapshot->id;
        }

        $result = $this->importCities->execute(
            $this->postalCodeParser->parse($fetchedSources->laPostePath),
            CarbonImmutable::today(),
            $fetchedSources->laPosteSnapshot,
        );
        $fetchedSources->laPosteSnapshot->update(['imported_at' => now()]);
        $snapshotIds[] = $fetchedSources->laPosteSnapshot->id;
        $unmatched     = $result['unmatched'];

        $this->linkReplacedCities->execute();
        $this->applyCommuneCentres->execute($this->geoApiClient->communes());
        $this->fillCityCoordinatesFromCommuneCentre->execute();

        return ['imported' => true, 'snapshotIds' => $snapshotIds, 'unmatched' => $unmatched];
    }
}
