<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\Insee\CommuneRecord;
use App\Data\Insee\HistoricCommuneRecord;
use App\Enums\CommuneKind;
use App\Models\Commune;
use App\Models\Department;
use Illuminate\Support\Str;

final readonly class ImportCommunes
{
    private const int CHUNK_SIZE = 1000;

    /**
     * Imports the communes and the municipal arrondissements with their validity periods.
     * A commune is never deleted: when it disappears, its validity is closed.
     *
     * Consecutive periods of a code are one single row, unless the entity behind the code changed
     * at the boundary (merger, creation...): a mere rename keeps the same row.
     *
     * @param iterable<HistoricCommuneRecord>        $history        every code and period since 1943
     * @param iterable<CommuneRecord>                $current        communes of the current vintage (department of each code)
     * @param iterable<CommuneRecord>                $overseas       communes of the overseas collectivities (no history available)
     * @param array<int|string, array<string, true>> $identityBreaks dates at which the entity behind a code changes
     *
     * @return int number of rows written
     */
    public function execute(iterable $history, iterable $current, iterable $overseas, array $identityBreaks): int
    {
        /** @var array<string, int> $departmentIds */
        $departmentIds = Department::query()->pluck('id', 'code')->all();

        /** @var array<string, string> $currentDepartments */
        $currentDepartments = [];

        foreach ($current as $record) {
            if (null !== $record->departmentCode && $this->isCommune($record->kind)) {
                $currentDepartments[$record->inseeCode] = $record->departmentCode;
            }
        }

        $rows = [];

        foreach ($this->mergedPeriods($history, $identityBreaks) as $period) {
            $departmentCode = $currentDepartments[$period['code']] ?? $this->departmentCodeFromInseeCode($period['code']);

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

        $known = array_flip(array_column($rows, 'insee_code'));

        foreach ($overseas as $oversea) {
            if (isset($known[$oversea->inseeCode])) {
                continue;
            }

            $rows[] = [
                'department_id' => null === $oversea->departmentCode ? null : ($departmentIds[$oversea->departmentCode] ?? null),
                'insee_code'    => $oversea->inseeCode,
                'kind'          => $oversea->kind->value,
                'name'          => $oversea->name,
                'slug'          => Str::slug($oversea->name),
                'valid_from'    => ImportRegions::ORIGIN,
                'valid_to'      => null,
            ];
        }

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            Commune::query()->upsert($chunk, ['insee_code', 'valid_from'], ['department_id', 'kind', 'name', 'slug', 'valid_to']);
        }

        return count($rows);
    }

    private function departmentCodeFromInseeCode(string $inseeCode): string
    {
        return str_starts_with($inseeCode, '97') || str_starts_with($inseeCode, '98')
            ? substr($inseeCode, 0, 3)
            : substr($inseeCode, 0, 2);
    }

    private function isCommune(CommuneKind $communeKind): bool
    {
        return CommuneKind::Commune === $communeKind || CommuneKind::Arrondissement === $communeKind;
    }

    /**
     * @param iterable<HistoricCommuneRecord>        $history
     * @param array<int|string, array<string, true>> $identityBreaks
     *
     * @return list<array{code: string, kind: CommuneKind, name: string, from: string, to: string|null}>
     */
    private function mergedPeriods(iterable $history, array $identityBreaks): array
    {
        /** @var array<string, list<HistoricCommuneRecord>> $byCode */
        $byCode = [];

        foreach ($history as $record) {
            if ($this->isCommune($record->kind)) {
                $byCode[$record->inseeCode][] = $record;
            }
        }

        $periods = [];

        foreach ($byCode as $key => $records) {
            // Numeric string keys are turned into integers by PHP.
            $code = (string) $key;

            usort($records, static fn (HistoricCommuneRecord $a, HistoricCommuneRecord $b): int => $a->validFrom <=> $b->validFrom);

            $open = null;

            foreach ($records as $record) {
                $from = $record->validFrom->toDateString();
                $to   = $record->validTo?->toDateString();

                if (null !== $open && $open['kind'] === $record->kind && $open['to'] === $from && ! isset($identityBreaks[$code][$from])) {
                    $open['to']   = $to;
                    $open['name'] = $record->name;

                    continue;
                }

                if (null !== $open) {
                    $periods[] = $open;
                }

                $open = ['code' => $code, 'kind' => $record->kind, 'name' => $record->name, 'from' => $from, 'to' => $to];
            }

            if (null !== $open) {
                $periods[] = $open;
            }
        }

        return $periods;
    }
}
