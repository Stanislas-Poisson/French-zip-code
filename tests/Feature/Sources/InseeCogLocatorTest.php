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
    public function it_fails_when_the_dataset_has_no_resources_at_all(): void
    {
        Http::fake(['*' => Http::response(['title' => 'COG'])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The COG dataset has no resources.');

        $this->app->make(InseeCogLocator::class)->latest();
    }

    #[Test]
    public function it_fails_when_the_dataset_has_no_vintage(): void
    {
        Http::fake(['*' => Http::response(['resources' => []])]);

        $this->expectException(RuntimeException::class);

        $this->app->make(InseeCogLocator::class)->latest();
    }

    #[Test]
    public function it_ignores_a_resource_with_an_unexpected_title_or_file_name(): void
    {
        Http::fake(['*' => Http::response(['resources' => [
            ['url' => 'https://example.test/v_region_2026.csv'],
            ['title' => 'Documentation', 'url' => 'https://example.test/v_region_2026.csv'],
            ['title' => 'Millésime 2026 : région', 'url' => 'https://example.test/readme.txt'],
            ['title' => 'Millésime 2026 : région', 'url' => 'https://example.test/v_region_2026.csv'],
        ]])]);

        $cogVintage = $this->app->make(InseeCogLocator::class)->latest();

        $this->assertSame(2026, $cogVintage->year);
        $this->assertSame(['v_region'], array_keys($cogVintage->files));
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
