<?php

declare(strict_types=1);

namespace App\Data\Resolution;

use App\Enums\SuccessionKind;
use Carbon\CarbonImmutable;

final readonly class ResolutionStep
{
    public function __construct(
        public string $fromCode,
        public ?string $toCode,
        public SuccessionKind $kind,
        public CarbonImmutable $effectiveDate,
    ) {}
}
