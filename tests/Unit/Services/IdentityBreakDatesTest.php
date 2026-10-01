<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Data\Insee\CommuneMovementRecord;
use App\Enums\CommuneKind;
use App\Enums\EventModality;
use App\Services\IdentityBreakDates;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IdentityBreakDatesTest extends TestCase
{
    #[Test]
    public function it_ignores_a_mere_change_of_name(): void
    {
        $breaks = (new IdentityBreakDates)->fromMovements([
            $this->movement(EventModality::NameChange, '2026-01-01', '28274', '28274'),
        ]);

        $this->assertSame([], $breaks);
    }

    #[Test]
    public function it_ignores_the_delegated_and_associated_communes(): void
    {
        $breaks = (new IdentityBreakDates)->fromMovements([
            $this->movement(
                EventModality::SimpleMerger,
                '2019-01-01',
                '01015',
                '01016',
                CommuneKind::Delegated,
                CommuneKind::Associated,
            ),
        ]);

        $this->assertSame([], $breaks);
    }

    #[Test]
    public function it_records_the_date_of_a_merger_for_the_codes_before_and_after(): void
    {
        $breaks = (new IdentityBreakDates)->fromMovements([
            $this->movement(EventModality::SimpleMerger, '2016-01-01', '85043', '85213'),
        ]);

        $this->assertSame(['85043' => ['2016-01-01' => true], '85213' => ['2016-01-01' => true]], $breaks);
    }

    private function movement(
        EventModality $eventModality,
        string $date,
        string $before,
        string $after,
        CommuneKind $kindBefore = CommuneKind::Commune,
        CommuneKind $kindAfter = CommuneKind::Commune,
    ): CommuneMovementRecord {
        return new CommuneMovementRecord(
            $eventModality,
            CarbonImmutable::parse($date),
            $kindBefore,
            $before,
            'Before',
            $kindAfter,
            $after,
            'After',
        );
    }
}
