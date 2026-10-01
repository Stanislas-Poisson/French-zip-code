<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommuneKind;
use App\Enums\EventModality;
use App\Enums\SuccessionKind;
use App\Models\CommuneEvent;
use App\Models\CommuneSuccession;

final class BuildSuccessions
{
    private const int CHUNK_SIZE = 1000;

    /**
     * Derives, from the events, the succession of the commune codes (and arrondissement codes).
     * Only the events between two communes are used: delegated and associated communes do not own a code of their own.
     *
     * @return int number of successions written
     */
    public function execute(): int
    {
        CommuneSuccession::query()->delete();

        $count = 0;
        $rows  = [];

        foreach (CommuneEvent::query()->orderBy('id')->cursor() as $lazyCollection) {
            $kind = $this->kindOf($lazyCollection);

            if (! $kind instanceof SuccessionKind) {
                continue;
            }

            $rows[] = [
                'commune_event_id' => $lazyCollection->id,
                'from_code'        => (string) $lazyCollection->code_before,
                'to_code'          => SuccessionKind::Deleted === $kind ? null : $lazyCollection->code_after,
                'kind'             => $kind->value,
                'effective_date'   => $lazyCollection->effective_date->toDateString(),
            ];
            $count++;

            if (self::CHUNK_SIZE === count($rows)) {
                CommuneSuccession::query()->insert($rows);
                $rows = [];
            }
        }

        if ([] !== $rows) {
            CommuneSuccession::query()->insert($rows);
        }

        return $count;
    }

    private function isCommune(?CommuneKind $communeKind): bool
    {
        return CommuneKind::Commune === $communeKind || CommuneKind::Arrondissement === $communeKind;
    }

    private function kindOf(CommuneEvent $communeEvent): ?SuccessionKind
    {
        if (null === $communeEvent->code_before || ! $this->isCommune($communeEvent->kind_before)) {
            return null;
        }

        if (null === $communeEvent->code_after) {
            return EventModality::Deletion === $communeEvent->modality ? SuccessionKind::Deleted : null;
        }

        // A commune becoming an arrondissement (or the opposite) is not a change of commune code.
        if ($communeEvent->kind_before !== $communeEvent->kind_after || ! $this->isCommune($communeEvent->kind_after)) {
            return null;
        }

        $sameCode = $communeEvent->code_before === $communeEvent->code_after;

        return match ($communeEvent->modality) {
            EventModality::NameChange => SuccessionKind::Renamed,
            EventModality::Deletion,
            EventModality::SimpleMerger,
            EventModality::NewCommuneCreation,
            EventModality::AssociatedMerger => $sameCode ? SuccessionKind::CodeReused : SuccessionKind::Absorbed,
            EventModality::CodeChangeDepartment,
            EventModality::CodeChangeSeat => $sameCode ? null : SuccessionKind::Replaced,
            EventModality::Creation,
            EventModality::Reinstatement => $sameCode ? null : SuccessionKind::Split,
            default                      => null,
        };
    }
}
