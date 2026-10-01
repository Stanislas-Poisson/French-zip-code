<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Data\Ban\BanAddressPoint;
use App\Services\CityPointCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CityPointCalculatorTest extends TestCase
{
    #[Test]
    public function it_averages_the_two_middle_values_of_an_even_number_of_addresses(): void
    {
        $points = (new CityPointCalculator)->compute([
            new BanAddressPoint('37261', '37100', 47.40, 0.60),
            new BanAddressPoint('37261', '37100', 47.42, 0.70),
        ]);

        $this->assertEqualsWithDelta(47.41, $points[0]->latitude, 0.000001);
        $this->assertEqualsWithDelta(0.65, $points[0]->longitude, 0.000001);
    }

    #[Test]
    public function it_ignores_the_pairs_below_the_minimum_number_of_addresses(): void
    {
        $points = (new CityPointCalculator)->compute([
            new BanAddressPoint('37261', '37000', 47.38, 0.68),
            new BanAddressPoint('37261', '37000', 47.39, 0.69),
            new BanAddressPoint('37261', '37100', 47.41, 0.69),
        ], 2);

        $this->assertCount(1, $points);
        $this->assertSame('37000', $points[0]->postalCode);
    }

    #[Test]
    public function it_is_not_moved_by_a_badly_placed_address(): void
    {
        $points = (new CityPointCalculator)->compute([
            new BanAddressPoint('37261', '37000', 47.38, 0.69),
            new BanAddressPoint('37261', '37000', 47.39, 0.69),
            new BanAddressPoint('37261', '37000', 47.38, 0.68),
            new BanAddressPoint('37261', '37000', 0.0, 0.0),
            new BanAddressPoint('37261', '37000', 47.385, 0.685),
        ]);

        $this->assertEqualsWithDelta(47.38, $points[0]->latitude, 0.01);
        $this->assertEqualsWithDelta(0.685, $points[0]->longitude, 0.01);
    }

    #[Test]
    public function it_keeps_each_pair_of_commune_and_zip_code_apart(): void
    {
        $points = (new CityPointCalculator)->compute([
            new BanAddressPoint('37261', '37000', 47.38, 0.68),
            new BanAddressPoint('37261', '37100', 47.41, 0.69),
            new BanAddressPoint('37003', '37000', 47.50, 0.40),
        ]);

        $this->assertCount(3, $points);
    }

    #[Test]
    public function it_takes_the_median_of_an_odd_number_of_addresses(): void
    {
        $points = (new CityPointCalculator)->compute([
            new BanAddressPoint('37261', '37200', 47.30, 0.60),
            new BanAddressPoint('37261', '37200', 47.50, 0.80),
            new BanAddressPoint('37261', '37200', 47.40, 0.70),
        ]);

        $this->assertCount(1, $points);
        $this->assertEqualsWithDelta(47.40, $points[0]->latitude, 0.000001);
        $this->assertEqualsWithDelta(0.70, $points[0]->longitude, 0.000001);
        $this->assertSame(3, $points[0]->addressCount);
    }
}
