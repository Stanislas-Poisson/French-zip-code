<?php

declare(strict_types=1);

namespace Tests\Feature\Sources;

use App\Services\Sources\GeoApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class GeoApiClientTest extends TestCase
{
    #[Test]
    public function it_ignores_the_entries_without_centre(): void
    {
        Http::fake(['*' => Http::response([['code' => '00001', 'nom' => 'Sans centre', 'codesPostaux' => ['00001']]])]);

        $this->assertSame([], $this->app->make(GeoApiClient::class)->communes());
    }

    #[Test]
    public function it_returns_the_communes_and_the_arrondissements_with_their_centre(): void
    {
        Http::fake(fn (Request $request) => Http::response($this->fixture(str_contains($request->url(), 'arrondissement-municipal') ? 'arrondissements.json' : 'communes.json')));

        $records = $this->app->make(GeoApiClient::class)->communes();

        $tours = array_find($records, static fn ($record): bool => '37261' === $record->inseeCode);
        $rives = array_find($records, static fn ($record): bool => '85213' === $record->inseeCode);
        $paris = array_find($records, static fn ($record): bool => '75101' === $record->inseeCode);

        $this->assertCount(7, $records);
        $this->assertNotNull($tours);
        $this->assertSame('Tours', $tours->name);
        $this->assertEqualsWithDelta(47.3943, $tours->latitude, 0.01);
        $this->assertEqualsWithDelta(0.6949, $tours->longitude, 0.01);
        $this->assertSame(['85310'], $rives?->postalCodes);
        $this->assertNotNull($paris);
    }

    /**
     * @return array<mixed>
     */
    private function fixture(string $name): array
    {
        $decoded = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/geo/' . $name), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid fixture.');
        }

        return $decoded;
    }
}
