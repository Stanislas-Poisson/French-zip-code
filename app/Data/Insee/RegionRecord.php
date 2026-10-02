<?php

declare(strict_types=1);

namespace App\Data\Insee;

final readonly class RegionRecord
{
    public function __construct(
        public string $code,
        public string $name,
        public string $seatCommuneCode,
    ) {}
}
