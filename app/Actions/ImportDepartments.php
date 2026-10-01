<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\Insee\DepartmentRecord;
use App\Enums\ChangeType;
use App\Enums\ReferenceEntity;
use App\Models\Department;
use App\Models\Region;
use App\Models\Snapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class ImportDepartments
{
    public function __construct(private RecordReferenceChange $recordReferenceChange) {}

    /**
     * Brings the departments and the overseas collectivities in line with the files.
     * The regions must have been imported first.
     *
     * @param iterable<DepartmentRecord> $records
     */
    public function execute(iterable $records, CarbonImmutable $effectiveDate, Snapshot $snapshot): void
    {
        $isFirstImport = ! Department::query()->exists();

        /** @var array<string, int> $regionIds */
        $regionIds = Region::query()->current()->pluck('id', 'code')->all();

        /** @var array<string, Department> $current */
        $current = Department::query()->current()->get()->keyBy('code')->all();
        $seen    = [];

        foreach ($records as $record) {
            $seen[$record->code] = true;
            $regionId            = null === $record->regionCode ? null : ($regionIds[$record->regionCode] ?? null);
            $slug                = Str::slug($record->name);

            $department = $current[$record->code] ?? null;

            if (null === $department) {
                Department::query()->create([
                    'region_id'  => $regionId,
                    'code'       => $record->code,
                    'type'       => $record->type,
                    'name'       => $record->name,
                    'slug'       => $slug,
                    'valid_from' => $isFirstImport ? ImportRegions::ORIGIN : $effectiveDate->toDateString(),
                ]);

                if (! $isFirstImport) {
                    $this->recordReferenceChange->execute($snapshot, ReferenceEntity::Department, $record->code, ChangeType::Created, null, ['name' => $record->name]);
                }

                continue;
            }

            if ($department->name !== $record->name || $department->region_id !== $regionId) {
                $this->recordReferenceChange->execute(
                    $snapshot,
                    ReferenceEntity::Department,
                    $record->code,
                    ChangeType::Modified,
                    ['name' => $department->name, 'region_id' => $department->region_id],
                    ['name' => $record->name, 'region_id' => $regionId],
                );
                $department->update(['name' => $record->name, 'slug' => $slug, 'region_id' => $regionId]);
            }
        }

        foreach ($current as $key => $department) {
            $code = (string) $key;

            if (isset($seen[$code])) {
                continue;
            }

            $department->update(['valid_to' => $effectiveDate->toDateString()]);
            $this->recordReferenceChange->execute($snapshot, ReferenceEntity::Department, $code, ChangeType::Removed, ['name' => $department->name]);
        }
    }
}
