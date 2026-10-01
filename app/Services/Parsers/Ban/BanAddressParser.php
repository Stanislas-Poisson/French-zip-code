<?php

declare(strict_types=1);

namespace App\Services\Parsers\Ban;

use App\Data\Ban\BanAddressPoint;
use App\Services\Parsers\CsvFile;
use Generator;

/**
 * Streams the addresses of a BAN department file (gzip compressed CSV, semicolon separated).
 */
final readonly class BanAddressParser
{
    public function __construct(private CsvFile $csvFile) {}

    /**
     * @return Generator<int, BanAddressPoint>
     */
    public function parse(string $path): Generator
    {
        foreach ($this->csvFile->rows('compress.zlib://' . $path, ';') as $row) {
            $inseeCode  = $row['code_insee']  ?? '';
            $postalCode = $row['code_postal'] ?? '';
            $latitude   = $row['lat']         ?? '';
            $longitude  = $row['lon']         ?? '';

            if ('' === $inseeCode || '' === $postalCode || ! is_numeric($latitude) || ! is_numeric($longitude)) {
                continue;
            }

            yield new BanAddressPoint($inseeCode, $postalCode, (float) $latitude, (float) $longitude);
        }
    }
}
