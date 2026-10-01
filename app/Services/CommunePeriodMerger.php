<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Insee\HistoricCommuneRecord;
use App\Enums\CommuneKind;

/**
 * Turns the history of the commune codes into validity periods.
 *
 * Consecutive periods of a code are one single period, unless the entity behind the code changed
 * at the boundary (merger, creation...): a mere rename keeps the same period.
 */
final class CommunePeriodMerger
{
    /**
     * @param iterable<HistoricCommuneRecord>        $history
     * @param array<int|string, array<string, true>> $identityBreaks dates at which the entity behind a code changes
     *
     * @return list<array{code: string, kind: CommuneKind, name: string, from: string, to: string|null}>
     */
    public function merge(iterable $history, array $identityBreaks): array
    {
        $periods = [];

        foreach ($this->groupByCode($history) as $key => $records) {
            // Numeric string keys are turned into integers by PHP.
            $code = (string) $key;

            $periods = [...$periods, ...$this->mergeCode($code, $records, $identityBreaks[$code] ?? [])];
        }

        return $periods;
    }

    /**
     * @param array{code: string, kind: CommuneKind, name: string, from: string, to: string|null}|null $open
     * @param array<string, true>                                                                      $breaks
     *
     * @phpstan-assert-if-true array{code: string, kind: CommuneKind, name: string, from: string, to: string|null} $open
     */
    private function continues(
        ?array $open,
        HistoricCommuneRecord $historicCommuneRecord,
        string $from,
        array $breaks,
    ): bool {
        return null !== $open
            && $open['kind'] === $historicCommuneRecord->kind
            && $open['to']   === $from
            && ! isset($breaks[$from]);
    }

    /**
     * @param iterable<HistoricCommuneRecord> $history
     *
     * @return array<int|string, list<HistoricCommuneRecord>>
     */
    private function groupByCode(iterable $history): array
    {
        $byCode = [];

        foreach ($history as $record) {
            if ($record->kind->ownsCode()) {
                $byCode[$record->inseeCode][] = $record;
            }
        }

        return $byCode;
    }

    /**
     * @param list<HistoricCommuneRecord> $records
     * @param array<string, true>         $breaks  dates at which the entity behind the code changes
     *
     * @return list<array{code: string, kind: CommuneKind, name: string, from: string, to: string|null}>
     */
    private function mergeCode(string $code, array $records, array $breaks): array
    {
        usort(
            $records,
            static fn (HistoricCommuneRecord $a, HistoricCommuneRecord $b): int => $a->validFrom <=> $b->validFrom,
        );

        $periods = [];
        $open    = null;

        foreach ($records as $record) {
            $from = $record->validFrom->toDateString();
            $to   = $record->validTo?->toDateString();

            if ($this->continues($open, $record, $from, $breaks)) {
                $open['to']   = $to;
                $open['name'] = $record->name;

                continue;
            }

            if (null !== $open) {
                $periods[] = $open;
            }

            $open = [
                'code' => $code,
                'kind' => $record->kind,
                'name' => $record->name,
                'from' => $from,
                'to'   => $to,
            ];
        }

        return null === $open ? $periods : [...$periods, $open];
    }
}
