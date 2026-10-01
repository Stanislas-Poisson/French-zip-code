<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\Postal\PostalRecord;
use App\Enums\ChangeType;
use App\Enums\ReferenceEntity;
use App\Models\City;
use App\Models\Commune;
use App\Models\Snapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class ImportCities
{
    public function __construct(private RecordReferenceChange $recordReferenceChange) {}

    /**
     * Brings the cities (commune + zip code) in line with the La Poste file.
     * A city is never deleted: when its pair disappears, its validity is closed.
     * The first import records no change, as there is nothing to compare with.
     *
     * @param iterable<PostalRecord> $records
     *
     * @return array{created: int, updated: int, closed: int, unmatched: list<string>}
     */
    public function execute(iterable $records, CarbonImmutable $effectiveDate, Snapshot $snapshot): array
    {
        return DB::transaction(function () use ($records, $effectiveDate, $snapshot): array {
            $isFirstImport = ! City::query()->exists();

            /** @var array<string, int> $communeIds */
            $communeIds = Commune::query()->current()->pluck('id', 'insee_code')->all();

            /** @var array<int, string> $inseeCodes */
            $inseeCodes = Commune::query()->pluck('insee_code', 'id')->all();

            /** @var array<string, City> $current */
            $current = [];

            foreach (City::query()->current()->get() as $city) {
                $current[$city->commune_id . '|' . $city->postal_code] = $city;
            }

            $created   = 0;
            $updated   = 0;
            $unmatched = [];
            $seen      = [];

            foreach ($records as $record) {
                $communeId = $communeIds[$record->inseeCode] ?? null;

                if (null === $communeId) {
                    $unmatched[$record->inseeCode] = $record->inseeCode;

                    continue;
                }

                $key = $communeId . '|' . $record->postalCode;

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;

                $city = $current[$key] ?? null;

                if (null === $city) {
                    City::query()->create([
                        'commune_id'  => $communeId,
                        'postal_code' => $record->postalCode,
                        'label'       => $record->label,
                        'valid_from'  => $isFirstImport ? ImportRegions::ORIGIN : $effectiveDate->toDateString(),
                    ]);
                    $created++;

                    if (! $isFirstImport) {
                        $this->recordReferenceChange->execute($snapshot, ReferenceEntity::City, $record->inseeCode . '-' . $record->postalCode, ChangeType::Created, null, ['label' => $record->label]);
                    }

                    continue;
                }

                if ($city->label !== $record->label) {
                    $city->update(['label' => $record->label]);
                    $updated++;
                }
            }

            $closed = 0;

            foreach ($current as $key => $city) {
                if (isset($seen[$key])) {
                    continue;
                }

                $city->update(['valid_to' => $effectiveDate->toDateString()]);
                $closed++;
                $this->recordReferenceChange->execute($snapshot, ReferenceEntity::City, ($inseeCodes[$city->commune_id] ?? (string) $city->commune_id) . '-' . $city->postal_code, ChangeType::Removed, ['label' => $city->label]);
            }

            return [
                'created'   => $created,
                'updated'   => $updated,
                'closed'    => $closed,
                'unmatched' => array_values($unmatched),
            ];
        });
    }
}
