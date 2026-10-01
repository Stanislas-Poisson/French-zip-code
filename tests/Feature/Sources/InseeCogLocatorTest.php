<?php

declare(strict_types=1);

namespace Tests\Feature\Sources;

use App\Services\Sources\InseeCogLocator;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class InseeCogLocatorTest extends TestCase
{
    #[Test]
    public function it_fails_when_the_dataset_has_no_vintage(): void
    {
        Http::fake(['*' => Http::response(['resources' => []])]);

        $this->expectException(RuntimeException::class);

        $this->app->make(InseeCogLocator::class)->latest();
    }

    #[Test]
    public function it_ignores_resources_that_are_not_csv_files_of_a_vintage(): void
    {
        Http::fake(['*' => Http::response($this->dataset())]);

        $cogVintage = $this->app->make(InseeCogLocator::class)->latest();

        $this->assertArrayNotHasKey('8740218', $cogVintage->files);
        $this->assertCount(15, $cogVintage->files);
    }

    #[Test]
    public function it_picks_the_most_recent_vintage_and_its_files(): void
    {
        Http::fake(['*' => Http::response($this->dataset())]);

        $cogVintage = $this->app->make(InseeCogLocator::class)->latest();

        $this->assertSame(2026, $cogVintage->year);
        $this->assertStringEndsWith('/8740222/v_mvt_commune_2026.csv', $cogVintage->url('v_mvt_commune'));
        $this->assertStringEndsWith('/8740222/v_commune_depuis_1943.csv', $cogVintage->url('v_commune_depuis_1943'));
        $this->assertStringContainsString('8740222', $cogVintage->url('v_region'));
    }

    /**
     * @return array<mixed, mixed>
     */
    private function dataset(): array
    {
        $json = file_get_contents(__DIR__ . '/../../Fixtures/insee/cog_dataset.json');

        $decoded = json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('The COG dataset fixture is not valid.');
        }

        return $decoded;
    }
}
