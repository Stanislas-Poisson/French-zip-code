<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use Generator;
use League\Csv\Reader;

/**
 * Streams the rows of a CSV file with a header as associative arrays.
 */
final class CsvFile
{
    /**
     * @return Generator<int, array<string, string>>
     */
    public function rows(string $path, string $delimiter = ',', ?string $fromEncoding = null): Generator
    {
        $reader = Reader::from($path, 'r');
        $reader->setDelimiter($delimiter);
        $reader->setHeaderOffset(0);

        if (null !== $fromEncoding) {
            $reader->addStreamFilter('convert.iconv.' . $fromEncoding . '/UTF-8');
        }

        foreach ($reader->getRecords() as $record) {
            $row = [];

            foreach ($record as $column => $value) {
                $row[(string) $column] = is_scalar($value) ? (string) $value : '';
            }

            yield $row;
        }
    }
}
