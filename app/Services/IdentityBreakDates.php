<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Insee\CommuneMovementRecord;
use App\Enums\EventModality;

/**
 * Finds, for each commune code, the dates at which the entity behind the code changes
 * (merger, creation, re-establishment...), as opposed to a mere change of name.
 */
final class IdentityBreakDates
{
    /**
     * @param iterable<CommuneMovementRecord> $movements
     *
     * @return array<int|string, array<string, true>> dates (Y-m-d) by commune code
     */
    public function fromMovements(iterable $movements): array
    {
        $breaks = [];

        foreach ($movements as $movement) {
            if (EventModality::NameChange === $movement->modality) {
                continue;
            }

            $date = $movement->effectiveDate->toDateString();

            foreach ($this->communeCodes($movement) as $code) {
                $breaks[$code][$date] = true;
            }
        }

        return $breaks;
    }

    /**
     * The codes of a movement that belong to a commune (or an arrondissement), before and after the event.
     *
     * @return list<string>
     */
    private function communeCodes(CommuneMovementRecord $communeMovementRecord): array
    {
        return array_values(array_filter([
            $communeMovementRecord->kindBefore?->ownsCode() ? $communeMovementRecord->codeBefore : null,
            $communeMovementRecord->kindAfter?->ownsCode() ? $communeMovementRecord->codeAfter : null,
        ], static fn (?string $code): bool => null !== $code));
    }
}
