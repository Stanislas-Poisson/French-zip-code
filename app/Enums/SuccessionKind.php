<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a commune code is followed by another one.
 */
enum SuccessionKind: string
{
    /**
     * Commune absorbed by another one (merger).
     */
    case Absorbed = 'absorbed';

    /**
     * The code is kept by the merged entity, but now designates a different commune.
     */
    case CodeReused = 'code_reused';

    /**
     * Commune that disappeared without any successor.
     */
    case Deleted = 'deleted';

    /**
     * Same code, new name.
     */
    case Renamed = 'renamed';

    /**
     * One commune replaced by another one (new code).
     */
    case Replaced = 'replaced';

    /**
     * Commune split into several communes.
     */
    case Split = 'split';
}
