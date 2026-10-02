<?php

declare(strict_types=1);

namespace Tests\Unit\Parsers;

use App\Data\Ban\BanAddressPoint;
use App\Services\Parsers\Ban\BanAddressParser;
use App\Services\Parsers\CsvFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BanAddressParserTest extends TestCase
{
    #[Test]
    public function it_skips_the_addresses_without_position(): void
    {
        $addresses = iterator_to_array((new BanAddressParser(new CsvFile))->parse(__DIR__ . '/../../Fixtures/ban/adresses-37.csv.gz'), false);

        // The fixture holds 49 lines: the one without longitude and latitude is not returned.
        $this->assertCount(48, $addresses);
        $this->assertEqualsWithDelta(47.38, $addresses[0]->latitude, 0.1);
        $this->assertEqualsWithDelta(0.69, $addresses[0]->longitude, 0.1);
    }

    #[Test]
    public function it_streams_the_addresses_of_a_gzip_department_file(): void
    {
        $addresses = iterator_to_array((new BanAddressParser(new CsvFile))->parse(__DIR__ . '/../../Fixtures/ban/adresses-37.csv.gz'), false);

        $tours         = array_filter($addresses, static fn (BanAddressPoint $banAddressPoint): bool => '37261' === $banAddressPoint->inseeCode);
        $perPostalCode = array_count_values(array_map(static fn (BanAddressPoint $banAddressPoint): string => $banAddressPoint->postalCode, $tours));

        $this->assertSame(['37000' => 15, '37100' => 15, '37200' => 15], $perPostalCode);
        $this->assertCount(48, $addresses);
    }
}
