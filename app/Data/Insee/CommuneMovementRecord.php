<?php

declare(strict_types=1);

namespace App\Data\Insee;

use App\Enums\CommuneKind;
use App\Enums\EventModality;
use Carbon\CarbonImmutable;

/**
 * An event of the INSEE movements file: the entity before and after the event.
 */
final readonly class CommuneMovementRecord
{
    public function __construct(
        public EventModality $modality,
        public CarbonImmutable $effectiveDate,
        public ?CommuneKind $kindBefore,
        public ?string $codeBefore,
        public ?string $nameBefore,
        public ?CommuneKind $kindAfter,
        public ?string $codeAfter,
        public ?string $nameAfter,
    ) {}
}
