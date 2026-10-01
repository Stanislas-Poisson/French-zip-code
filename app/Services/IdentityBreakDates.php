<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Insee\CommuneMovementRecord;
use App\Enums\CommuneKind;
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

            if (null !== $movement->codeBefore && $this->isCommune($movement->kindBefore)) {
                $breaks[$movement->codeBefore][$date] = true;
            }

            if (null !== $movement->codeAfter && $this->isCommune($movement->kindAfter)) {
                $breaks[$movement->codeAfter][$date] = true;
            }
        }

        return $breaks;
    }

    private function isCommune(?CommuneKind $communeKind): bool
    {
        return CommuneKind::Commune === $communeKind || CommuneKind::Arrondissement === $communeKind;
    }
}
