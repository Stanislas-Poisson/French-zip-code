<?php

declare(strict_types=1);

namespace App\Data\Insee;

use App\Enums\CommuneKind;

final readonly class CommuneRecord
{
    public function __construct(
        public string $inseeCode,
        public CommuneKind $kind,
        public string $name,
        public ?string $departmentCode,
        public ?string $regionCode,
        public ?string $parentCode,
    ) {}
}
