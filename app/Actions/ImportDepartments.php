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
            $regionId            = $regionIds[$record->regionCode ?? ''] ?? null;

            if (isset($current[$record->code])) {
                $this->update($current[$record->code], $record, $regionId, $snapshot);

                continue;
            }

            $this->create($record, $regionId, $isFirstImport, $effectiveDate, $snapshot);
        }

        $this->closeMissing($current, $seen, $effectiveDate, $snapshot);
    }

    /**
     * @param array<string, Department> $current
     * @param array<string, true>       $seen
     */
    private function closeMissing(array $current, array $seen, CarbonImmutable $effectiveDate, Snapshot $snapshot): void
    {
        foreach ($current as $key => $department) {
            $code = (string) $key;

            if (isset($seen[$code])) {
                continue;
            }

            $department->update(['valid_to' => $effectiveDate->toDateString()]);
            $this->recordReferenceChange->execute(
                $snapshot,
                ReferenceEntity::Department,
                $code,
                ChangeType::Removed,
                ['name' => $department->name],
            );
        }
    }

    private function create(
        DepartmentRecord $departmentRecord,
        ?int $regionId,
        bool $isFirstImport,
        CarbonImmutable $effectiveDate,
        Snapshot $snapshot,
    ): void {
        Department::query()->create([
            'region_id'  => $regionId,
            'code'       => $departmentRecord->code,
            'type'       => $departmentRecord->type,
            'name'       => $departmentRecord->name,
            'slug'       => Str::slug($departmentRecord->name),
            'valid_from' => $isFirstImport ? ImportRegions::ORIGIN : $effectiveDate->toDateString(),
        ]);

        if ($isFirstImport) {
            return;
        }

        $this->recordReferenceChange->execute(
            $snapshot,
            ReferenceEntity::Department,
            $departmentRecord->code,
            ChangeType::Created,
            null,
            ['name' => $departmentRecord->name],
        );
    }

    private function update(
        Department $department,
        DepartmentRecord $departmentRecord,
        ?int $regionId,
        Snapshot $snapshot,
    ): void {
        if ($department->name === $departmentRecord->name && $department->region_id === $regionId) {
            return;
        }

        $this->recordReferenceChange->execute(
            $snapshot,
            ReferenceEntity::Department,
            $departmentRecord->code,
            ChangeType::Modified,
            ['name' => $department->name, 'region_id' => $department->region_id],
            ['name' => $departmentRecord->name, 'region_id' => $regionId],
        );
        $department->update([
            'name'      => $departmentRecord->name,
            'slug'      => Str::slug($departmentRecord->name),
            'region_id' => $regionId,
        ]);
    }
}
