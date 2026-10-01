<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\Insee\RegionRecord;
use App\Enums\ChangeType;
use App\Enums\ReferenceEntity;
use App\Models\Region;
use App\Models\Snapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class ImportRegions
{
    public const string ORIGIN = '1943-01-01';

    public function __construct(private RecordReferenceChange $recordReferenceChange) {}

    /**
     * Brings the regions in line with the file. A region is never deleted: its validity is closed.
     * The first import has no previous state to compare with, so it records no change.
     *
     * @param iterable<RegionRecord> $records
     */
    public function execute(iterable $records, CarbonImmutable $effectiveDate, Snapshot $snapshot): void
    {
        $isFirstImport = ! Region::query()->exists();

        /** @var array<string, Region> $current */
        $current = Region::query()->current()->get()->keyBy('code')->all();
        $seen    = [];

        foreach ($records as $record) {
            $seen[$record->code] = true;
            $slug                = Str::slug($record->name);

            $region = $current[$record->code] ?? null;

            if (null === $region) {
                Region::query()->create([
                    'code'       => $record->code,
                    'name'       => $record->name,
                    'slug'       => $slug,
                    'valid_from' => $isFirstImport ? self::ORIGIN : $effectiveDate->toDateString(),
                ]);

                if (! $isFirstImport) {
                    $this->recordReferenceChange->execute($snapshot, ReferenceEntity::Region, $record->code, ChangeType::Created, null, ['name' => $record->name]);
                }

                continue;
            }

            if ($region->name !== $record->name) {
                $this->recordReferenceChange->execute($snapshot, ReferenceEntity::Region, $record->code, ChangeType::Modified, ['name' => $region->name], ['name' => $record->name]);
                $region->update(['name' => $record->name, 'slug' => $slug]);
            }
        }

        foreach ($current as $key => $region) {
            $code = (string) $key;

            if (isset($seen[$code])) {
                continue;
            }

            $region->update(['valid_to' => $effectiveDate->toDateString()]);
            $this->recordReferenceChange->execute($snapshot, ReferenceEntity::Region, $code, ChangeType::Removed, ['name' => $region->name]);
        }
    }
}
