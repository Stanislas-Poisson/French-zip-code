<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Change detected between two snapshots of a reference file.
 */
enum ChangeType: string
{
    case Created  = 'created';
    case Modified = 'modified';
    case Removed  = 'removed';
}
