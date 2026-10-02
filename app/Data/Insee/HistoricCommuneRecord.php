<?php

declare(strict_types=1);

namespace App\Data\Insee;

use App\Enums\CommuneKind;
use Carbon\CarbonImmutable;

/**
 * A commune code with the period during which it carried a given name.
 */
final readonly class HistoricCommuneRecord
{
    public function __construct(
        public string $inseeCode,
        public CommuneKind $kind,
        public string $name,
        public CarbonImmutable $validFrom,
        public ?CarbonImmutable $validTo,
    ) {}
}
