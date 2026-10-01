<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kind of territorial entity found in the INSEE COG (TYPECOM column).
 */
enum CommuneKind: string
{
    /**
     * Municipal arrondissement (Paris, Lyon, Marseille).
     */
    case Arrondissement = 'ARM';

    /**
     * Associated commune (commune associée).
     */
    case Associated = 'COMA';

    /**
     * Commune.
     */
    case Commune = 'COM';

    /**
     * Delegated commune (commune déléguée).
     */
    case Delegated = 'COMD';
}
