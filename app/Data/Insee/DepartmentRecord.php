<?php

declare(strict_types=1);

namespace App\Data\Insee;

use App\Enums\DepartmentType;

final readonly class DepartmentRecord
{
    public function __construct(
        public string $code,
        public ?string $regionCode,
        public DepartmentType $type,
        public string $name,
        public ?string $seatCommuneCode,
    ) {}
}
