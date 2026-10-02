<?php

declare(strict_types=1);

namespace App\Services\Parsers\LaPoste;

use App\Data\Postal\PostalRecord;
use App\Services\Parsers\CsvFile;
use Generator;

/**
 * Reads the La Poste file: semicolon separated, encoded in cp1252, columns read by position
 * because the header contains accents and a leading "#".
 */
final readonly class PostalCodeParser
{
    public function __construct(private CsvFile $csvFile) {}

    /**
     * @return Generator<int, PostalRecord>
     */
    public function parse(string $path): Generator
    {
        foreach ($this->csvFile->rows($path, ';', 'CP1252') as $row) {
            $columns = array_values($row);

            $inseeCode  = trim($columns[0] ?? '');
            $postalCode = trim($columns[2] ?? '');

            if ('' === $inseeCode || '' === $postalCode) {
                continue;
            }

            $label = trim($columns[3] ?? '');

            yield new PostalRecord($inseeCode, $postalCode, '' === $label ? null : $label);
        }
    }
}
