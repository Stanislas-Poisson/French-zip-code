<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\CommuneKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommuneKindTest extends TestCase
{
    /**
     * @return iterable<string, array{CommuneKind, bool}>
     */
    public static function kinds(): iterable
    {
        yield 'commune' => [CommuneKind::Commune, true];

        yield 'arrondissement' => [CommuneKind::Arrondissement, true];

        yield 'delegated commune' => [CommuneKind::Delegated, false];

        yield 'associated commune' => [CommuneKind::Associated, false];
    }

    #[Test]
    #[DataProvider('kinds')]
    public function it_owns_a_code_only_when_it_is_a_commune_or_an_arrondissement(
        CommuneKind $communeKind,
        bool $expected,
    ): void {
        $this->assertSame($expected, $communeKind->ownsCode());
    }
}
