<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\Insee\CommuneRecord;
use App\Data\Insee\HistoricCommuneRecord;
use App\Enums\CommuneKind;
use App\Models\Commune;
use App\Models\Department;
use App\Services\CommunePeriodMerger;
use Illuminate\Support\Str;

final readonly class ImportCommunes
{
    private const int CHUNK_SIZE = 1000;

    public function __construct(private CommunePeriodMerger $communePeriodMerger) {}

    /**
     * Imports the communes and the municipal arrondissements with their validity periods.
     * A commune is never deleted: when it disappears, its validity is closed.
     *
     * @param iterable<HistoricCommuneRecord>        $history        every code and period since 1943
     * @param iterable<CommuneRecord>                $current        communes of the current vintage
     * @param iterable<CommuneRecord>                $overseas       communes of the overseas collectivities
     * @param array<int|string, array<string, true>> $identityBreaks dates at which the entity behind a code changes
     *
     * @return int number of rows written
     */
    public function execute(iterable $history, iterable $current, iterable $overseas, array $identityBreaks): int
    {
        /** @var array<string, int> $departmentIds */
        $departmentIds = Department::query()->pluck('id', 'code')->all();

        $periods = $this->communePeriodMerger->merge($history, $identityBreaks);

        $rows = [
            ...$this->historicRows($periods, $this->currentDepartments($current), $departmentIds),
            ...$this->overseasRows($overseas, array_flip(array_column($periods, 'code')), $departmentIds),
        ];

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            Commune::query()->upsert(
                $chunk,
                ['insee_code', 'valid_from'],
                ['department_id', 'kind', 'name', 'slug', 'valid_to'],
            );
        }

        return count($rows);
    }

    /**
     * @param iterable<CommuneRecord> $current
     *
     * @return array<string, string> department code of each commune of the current vintage
     */
    private function currentDepartments(iterable $current): array
    {
        $departments = [];

        foreach ($current as $record) {
            if (null !== $record->departmentCode && $record->kind->ownsCode()) {
                $departments[$record->inseeCode] = $record->departmentCode;
            }
        }

        return $departments;
    }

    private function departmentCodeFromInseeCode(string $inseeCode): string
    {
        return substr($inseeCode, 0, in_array(substr($inseeCode, 0, 2), ['97', '98'], true) ? 3 : 2);
    }

    /**
     * @param list<array{code: string, kind: CommuneKind, name: string, from: string, to: string|null}> $periods
     * @param array<string, string>                                                                     $currentDepartments
     * @param array<string, int>                                                                        $departmentIds
     *
     * @return list<array<string, mixed>>
     */
    private function historicRows(array $periods, array $currentDepartments, array $departmentIds): array
    {
        $rows = [];

        foreach ($periods as $period) {
            $code           = $period['code'];
            $departmentCode = $currentDepartments[$code] ?? $this->departmentCodeFromInseeCode($code);

            $rows[] = [
                'department_id' => $departmentIds[$departmentCode] ?? null,
                'insee_code'    => $period['code'],
                'kind'          => $period['kind']->value,
                'name'          => $period['name'],
                'slug'          => Str::slug($period['name']),
                'valid_from'    => $period['from'],
                'valid_to'      => $period['to'],
            ];
        }

        return $rows;
    }

    /**
     * The overseas collectivities have no history: they are valid since the origin, unless already known.
     *
     * @param iterable<CommuneRecord> $overseas
     * @param array<int|string, int>  $known         codes already written
     * @param array<string, int>      $departmentIds
     *
     * @return list<array<string, mixed>>
     */
    private function overseasRows(iterable $overseas, array $known, array $departmentIds): array
    {
        $rows = [];

        foreach ($overseas as $oversea) {
            if (isset($known[$oversea->inseeCode])) {
                continue;
            }

            $rows[] = [
                'department_id' => $departmentIds[$oversea->departmentCode ?? ''] ?? null,
                'insee_code'    => $oversea->inseeCode,
                'kind'          => $oversea->kind->value,
                'name'          => $oversea->name,
                'slug'          => Str::slug($oversea->name),
                'valid_from'    => ImportRegions::ORIGIN,
                'valid_to'      => null,
            ];
        }

        return $rows;
    }
}
