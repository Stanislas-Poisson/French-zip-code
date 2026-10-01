<?php

declare(strict_types=1);

namespace Tests\Feature\Geocoding;

use App\Actions\ComputeDepartmentCoordinates;
use App\Actions\ImportCities;
use App\Contracts\FileDownloader;
use App\Data\Postal\PostalRecord;
use App\Data\Sources\DownloadedFile;
use App\Enums\CoordinateSource;
use App\Models\City;
use App\Services\Sources\BanClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CogFixtures;
use Tests\TestCase;

final class ComputeDepartmentCoordinatesTest extends TestCase
{
    use RefreshDatabase;

    private string $directory = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/french-zip-code-ban-' . bin2hex(random_bytes(4));
        config(['sources.directory' => $this->directory]);

        CogFixtures::import($this->app);
        $this->app->make(ImportCities::class)->execute(
            [
                new PostalRecord('37261', '37000', 'TOURS'),
                new PostalRecord('37261', '37100', 'TOURS'),
                new PostalRecord('37261', '37200', 'TOURS'),
            ],
            CarbonImmutable::parse('2026-01-01'),
            CogFixtures::snapshot($this->app, 'laposte'),
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function it_builds_the_url_of_a_department_file(): void
    {
        $this->assertSame('https://adresse.data.gouv.fr/data/ban/adresses/latest/csv/adresses-2A.csv.gz', $this->app->make(BanClient::class)->url('2A'));
    }

    #[Test]
    public function it_fails_on_a_server_error(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->expectException(RequestException::class);

        $this->app->make(ComputeDepartmentCoordinates::class)->execute('37');
    }

    #[Test]
    public function it_gives_each_zip_code_of_tours_a_distinct_point_from_the_department_file(): void
    {
        $this->app->bind(FileDownloader::class, fn (): FakeBanDownloader => new FakeBanDownloader(__DIR__ . '/../../Fixtures/ban/adresses-37.csv.gz'));

        $result = $this->app->make(ComputeDepartmentCoordinates::class)->execute('37');

        $this->assertFalse($result['skipped']);
        $this->assertSame(3, $result['updated']);
        $this->assertSame(1, $result['unmatched']);

        $points = City::query()->orderBy('postal_code')->get();
        $this->assertCount(3, $points);

        foreach ($points as $point) {
            $this->assertSame(CoordinateSource::Ban, $point->coordinate_source);
            $this->assertSame(15, $point->address_count);
        }

        $latitudes = array_map(static fn (City $city): float => (float) $city->latitude, $points->all());
        $this->assertCount(3, array_unique($latitudes));
        $this->assertGreaterThan($latitudes[0], $latitudes[1], 'The 37100 is north of the 37000.');
        $this->assertLessThan($latitudes[0], $latitudes[2], 'The 37200 is south of the 37000.');
    }

    #[Test]
    public function it_lists_the_departments_that_may_have_a_file(): void
    {
        $codes = $this->app->make(ComputeDepartmentCoordinates::class)->departmentCodes();

        $this->assertContains('37', $codes);
        $this->assertContains('975', $codes);
    }

    #[Test]
    public function it_skips_a_department_without_file(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $result = $this->app->make(ComputeDepartmentCoordinates::class)->execute('984');

        $this->assertTrue($result['skipped']);
    }

    #[Test]
    public function it_skips_an_empty_department_file(): void
    {
        Http::fake(['*' => Http::response('empty-gz-file-20bytes')]);

        $this->assertTrue($this->app->make(ComputeDepartmentCoordinates::class)->execute('986')['skipped']);
    }
}

/**
 * Copies a fixture instead of downloading a file.
 */
final readonly class FakeBanDownloader implements FileDownloader
{
    public function __construct(private string $fixture) {}

    public function download(string $url, string $destination): DownloadedFile
    {
        if (! is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0o755, true);
        }

        copy($this->fixture, $destination);

        return new DownloadedFile($destination, hash_file('sha256', $destination) ?: '', (int) filesize($destination));
    }
}
