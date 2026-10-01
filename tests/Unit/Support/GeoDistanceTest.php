<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\GeoDistance;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GeoDistanceTest extends TestCase
{
    #[Test]
    public function it_measures_a_short_distance_inside_a_commune(): void
    {
        $this->assertEqualsWithDelta(2.19, GeoDistance::kilometers(47.3858, 0.6886, 47.3661, 0.6889), 0.05);
    }

    #[Test]
    public function it_measures_the_distance_between_tours_and_paris(): void
    {
        $this->assertEqualsWithDelta(205.0, GeoDistance::kilometers(47.3943, 0.6949, 48.8566, 2.3522), 5.0);
    }

    #[Test]
    public function it_returns_zero_for_the_same_point(): void
    {
        $this->assertEqualsWithDelta(0.0, GeoDistance::kilometers(47.39, 0.69, 47.39, 0.69), 0.0001);
    }
}
