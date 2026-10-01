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

            if (isset($current[$record->code])) {
                $this->update($current[$record->code], $record, $snapshot);

                continue;
            }

            $this->create($record, $isFirstImport, $effectiveDate, $snapshot);
        }

        $this->closeMissing($current, $seen, $effectiveDate, $snapshot);
    }

    /**
     * @param array<string, Region> $current
     * @param array<string, true>   $seen
     */
    private function closeMissing(array $current, array $seen, CarbonImmutable $effectiveDate, Snapshot $snapshot): void
    {
        foreach ($current as $key => $region) {
            $code = (string) $key;

            if (isset($seen[$code])) {
                continue;
            }

            $region->update(['valid_to' => $effectiveDate->toDateString()]);
            $this->recordReferenceChange->execute(
                $snapshot,
                ReferenceEntity::Region,
                $code,
                ChangeType::Removed,
                ['name' => $region->name],
            );
        }
    }

    private function create(
        RegionRecord $regionRecord,
        bool $isFirstImport,
        CarbonImmutable $effectiveDate,
        Snapshot $snapshot,
    ): void {
        Region::query()->create([
            'code'       => $regionRecord->code,
            'name'       => $regionRecord->name,
            'slug'       => Str::slug($regionRecord->name),
            'valid_from' => $isFirstImport ? self::ORIGIN : $effectiveDate->toDateString(),
        ]);

        if ($isFirstImport) {
            return;
        }

        $this->recordReferenceChange->execute(
            $snapshot,
            ReferenceEntity::Region,
            $regionRecord->code,
            ChangeType::Created,
            null,
            ['name' => $regionRecord->name],
        );
    }

    private function update(Region $region, RegionRecord $regionRecord, Snapshot $snapshot): void
    {
        if ($region->name === $regionRecord->name) {
            return;
        }

        $this->recordReferenceChange->execute(
            $snapshot,
            ReferenceEntity::Region,
            $regionRecord->code,
            ChangeType::Modified,
            ['name' => $region->name],
            ['name' => $regionRecord->name],
        );
        $region->update(['name' => $regionRecord->name, 'slug' => Str::slug($regionRecord->name)]);
    }
}
