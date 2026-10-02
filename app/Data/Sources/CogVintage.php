<?php

declare(strict_types=1);

namespace App\Data\Sources;

use InvalidArgumentException;

/**
 * A vintage (millésime) of the INSEE COG with the URL of each of its files.
 */
final readonly class CogVintage
{
    /**
     * @param array<string, string> $files URL of each file, keyed by its name without the year (v_commune, v_mvt_commune...)
     */
    public function __construct(
        public int $year,
        public array $files,
    ) {}

    public function url(string $file): string
    {
        return $this->files[$file]
            ?? throw new InvalidArgumentException(sprintf('The COG %d has no file "%s".', $this->year, $file));
    }
}
