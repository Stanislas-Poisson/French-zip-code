<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where the GPS point of a city (commune + zip code) comes from.
 */
enum CoordinateSource: string
{
    /**
     * Median of the addresses of the BAN (Base Adresse Nationale).
     */
    case Ban = 'ban';

    /**
     * Centre of the commune, last fallback.
     */
    case CommuneCentre = 'commune_centre';

    /**
     * Nominatim search on the zip code and the commune name.
     */
    case Nominatim = 'nominatim';
}
