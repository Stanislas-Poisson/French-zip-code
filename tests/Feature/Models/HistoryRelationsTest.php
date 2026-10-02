<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\ChangeType;
use App\Enums\EventModality;
use App\Enums\ReferenceEntity;
use App\Enums\SuccessionKind;
use App\Models\CommuneEvent;
use App\Models\CommuneSuccession;
use App\Models\ReferenceChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CogFixtures;
use Tests\TestCase;

final class HistoryRelationsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_links_a_reference_change_to_the_snapshot_that_detected_it(): void
    {
        $snapshot = CogFixtures::snapshot($this->app);

        $referenceChange = ReferenceChange::query()->create([
            'snapshot_id' => $snapshot->id,
            'entity_type' => ReferenceEntity::Region,
            'entity_code' => '24',
            'change_type' => ChangeType::Created,
            'detected_at' => now(),
        ]);

        $this->assertTrue($referenceChange->snapshot()->firstOrFail()->is($snapshot));
    }

    #[Test]
    public function it_links_a_succession_to_the_event_it_comes_from(): void
    {
        $communeEvent = CommuneEvent::query()->create([
            'snapshot_id'    => CogFixtures::snapshot($this->app)->id,
            'modality'       => EventModality::NameChange,
            'effective_date' => '2026-01-01',
        ]);

        $communeSuccession = CommuneSuccession::query()->create([
            'commune_event_id' => $communeEvent->id,
            'from_code'        => '28274',
            'to_code'          => '28274',
            'kind'             => SuccessionKind::Renamed,
            'effective_date'   => '2026-01-01',
        ]);

        $this->assertTrue($communeSuccession->event()->firstOrFail()->is($communeEvent));
    }

    #[Test]
    public function it_links_an_event_to_the_snapshot_that_brought_it(): void
    {
        $snapshot = CogFixtures::snapshot($this->app);

        $communeEvent = CommuneEvent::query()->create([
            'snapshot_id'    => $snapshot->id,
            'modality'       => EventModality::NameChange,
            'effective_date' => '2026-01-01',
        ]);

        $this->assertTrue($communeEvent->snapshot()->firstOrFail()->is($snapshot));
    }
}
