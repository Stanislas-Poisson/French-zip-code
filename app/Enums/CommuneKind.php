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

    /**
     * Whether the entity owns an INSEE code of its own: a commune or a municipal arrondissement.
     * Delegated and associated communes are only parts of another commune.
     */
    public function ownsCode(): bool
    {
        return self::Commune === $this || self::Arrondissement === $this;
    }
}
