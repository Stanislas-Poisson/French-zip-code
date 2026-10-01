<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\EventModality;
use App\Enums\SuccessionKind;
use App\Models\CommuneEvent;
use App\Models\CommuneSuccession;
use Illuminate\Support\LazyCollection;

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

        foreach ($this->rows()->chunk(self::CHUNK_SIZE) as $chunk) {
            CommuneSuccession::query()->insert($chunk->values()->all());
            $count += $chunk->count();
        }

        return $count;
    }

    private function kindOf(CommuneEvent $communeEvent): ?SuccessionKind
    {
        return match (true) {
            null === $communeEvent->code_before,
            true !== $communeEvent->kind_before?->ownsCode() => null,
            null === $communeEvent->code_after               => $this->kindWithoutSuccessor($communeEvent),
            // A commune becoming an arrondissement (or the opposite) is not a change of commune code.
            $communeEvent->kind_before !== $communeEvent->kind_after,
            ! $communeEvent->kind_after->ownsCode()         => null,
            default                                         => $this->kindWithSuccessor($communeEvent),
        };
    }

    private function kindWithoutSuccessor(CommuneEvent $communeEvent): ?SuccessionKind
    {
        return EventModality::Deletion === $communeEvent->modality ? SuccessionKind::Deleted : null;
    }

    private function kindWithSuccessor(CommuneEvent $communeEvent): ?SuccessionKind
    {
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

    /**
     * @return LazyCollection<int, non-empty-array<string, mixed>>
     */
    private function rows(): LazyCollection
    {
        return CommuneEvent::query()
            ->orderBy('id')
            ->cursor()
            ->map($this->toRow(...))
            ->filter();
    }

    /**
     * @return non-empty-array<string, mixed>|null
     */
    private function toRow(CommuneEvent $communeEvent): ?array
    {
        $kind = $this->kindOf($communeEvent);

        if (! $kind instanceof SuccessionKind) {
            return null;
        }

        return [
            'commune_event_id' => $communeEvent->id,
            'from_code'        => (string) $communeEvent->code_before,
            'to_code'          => SuccessionKind::Deleted === $kind ? null : $communeEvent->code_after,
            'kind'             => $kind->value,
            'effective_date'   => $communeEvent->effective_date->toDateString(),
        ];
    }
}
