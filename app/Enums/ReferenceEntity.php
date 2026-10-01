<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Entity whose changes are detected by comparing two snapshots.
 */
enum ReferenceEntity: string
{
    case City       = 'city';
    case Department = 'department';
    case Region     = 'region';
}
