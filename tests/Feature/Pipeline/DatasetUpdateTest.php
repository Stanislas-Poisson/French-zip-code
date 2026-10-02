<?php

declare(strict_types=1);

namespace Tests\Feature\Pipeline;

use App\Jobs\RunDatasetUpdateJob;
use App\Models\City;
use App\Models\Commune;
use App\Models\Snapshot;
use App\Services\DatasetUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DatasetUpdateTest extends TestCase
{
    use RefreshDatabase;

    private string $directory = '';

    private bool $withoutCommuneCentres = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/french-postal-code-update-' . bin2hex(random_bytes(4));
        config(['sources.directory' => $this->directory]);
        Cache::flush();

        Http::fake(fn (Request $request): mixed => $this->answer($request));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function it_can_skip_the_computation_of_the_coordinates(): void
    {
        $this->command('dataset:update', ['--sync' => true, '--skip-coordinates' => true])
            ->expectsOutputToContain('imported')
            ->assertSuccessful();

        $this->assertSame('commune_centre', City::query()->whereRelation('commune', 'insee_code', '37261')->firstOrFail()->coordinate_source?->value);
    }

    #[Test]
    public function it_does_nothing_when_the_sources_did_not_change(): void
    {
        $this->command('dataset:update', ['--sync' => true])->assertSuccessful();
        $snapshots = Snapshot::query()->count();

        $this->command('dataset:update', ['--sync' => true])
            ->expectsOutputToContain('up to date')
            ->assertSuccessful();

        $this->assertSame($snapshots, Snapshot::query()->count());
    }

    #[Test]
    public function it_fails_when_the_update_is_incomplete(): void
    {
        $this->withoutCommuneCentres = true;

        $this->command('dataset:update', ['--sync' => true])
            ->expectsOutputToContain('INCOMPLETE')
            ->expectsOutputToContain('The update is incomplete')
            ->assertFailed();
    }

    #[Test]
    public function it_gives_the_cities_without_ban_address_the_centre_of_their_commune(): void
    {
        $this->command('dataset:update', ['--sync' => true])->assertSuccessful();

        $city = City::query()->whereRelation('commune', 'insee_code', '85213')->sole();

        $this->assertSame('commune_centre', $city->coordinate_source?->value);
        $this->assertNotNull($city->latitude);
    }

    #[Test]
    public function it_imports_again_when_forced(): void
    {
        $this->command('dataset:update', ['--sync' => true])->assertSuccessful();

        $this->command('dataset:update', ['--sync' => true, '--force' => true])
            ->expectsOutputToContain('completed')
            ->assertSuccessful();

        $this->assertSame(3, City::query()->whereRelation('commune', 'insee_code', '37261')->count());
    }

    #[Test]
    public function it_marks_the_snapshots_complete_and_stores_the_report(): void
    {
        $this->command('dataset:update', ['--sync' => true])->assertSuccessful();

        $this->assertSame(0, Snapshot::query()->where('complete', false)->count());
        $this->assertGreaterThanOrEqual(2, Snapshot::query()->where('complete', true)->count());

        $last = Cache::get(DatasetUpdater::REPORT_CACHE_KEY);
        $this->assertIsArray($last);
        $this->assertTrue($last['complete']);
    }

    #[Test]
    public function it_reads_the_report_of_the_last_update_without_json(): void
    {
        $this->command('dataset:update', ['--sync' => true])->assertSuccessful();

        $this->command('dataset:status')
            ->expectsOutputToContain('Points by source:          ban ')
            ->expectsOutputToContain('Cities without a point:    0')
            ->expectsOutputToContain('Departments failed:        none')
            ->assertSuccessful();
    }

    #[Test]
    public function it_runs_the_update_from_the_queued_job(): void
    {
        config(['queue.default' => 'sync']);

        dispatch_sync(new RunDatasetUpdateJob('run-job', false, false));

        $this->assertSame(2, Snapshot::query()->whereNotNull('imported_at')->count());
        $this->assertGreaterThan(0, City::query()->count());
    }

    #[Test]
    public function it_runs_the_whole_update_and_gives_each_postal_code_its_own_point(): void
    {
        $this->command('dataset:update', ['--sync' => true])->assertSuccessful();

        $this->assertSame(1, Commune::query()->current()->where('insee_code', '37261')->count());

        $tours = City::query()->whereRelation('commune', 'insee_code', '37261')->orderBy('postal_code')->get();
        $this->assertCount(3, $tours);
        $this->assertSame(['ban', 'ban', 'ban'], $tours->map(static fn (City $city): string => (string) $city->coordinate_source?->value)->all());
        $this->assertCount(3, array_unique($tours->map(static fn (City $city): string => (string) $city->latitude)->all()));
    }

    #[Test]
    public function it_shows_the_status_of_the_last_update(): void
    {
        $this->command('dataset:update', ['--sync' => true])->assertSuccessful();

        $this->command('dataset:status')
            ->expectsOutputToContain('Current cities')
            ->assertSuccessful();
    }

    #[Test]
    public function it_tells_what_it_is_doing_while_it_runs(): void
    {
        $this->command('dataset:update', ['--sync' => true])
            ->expectsOutputToContain('Downloading the La Poste postal codes')
            ->expectsOutputToContain('Importing the postal codes of La Poste')
            ->expectsOutputToContain('Computing the GPS point of each postal code')
            ->expectsOutputToContain('Checking that nothing is missing')
            ->expectsOutputToContain('Current cities')
            ->assertSuccessful();
    }

    private function answer(Request $request): mixed
    {
        $url      = $request->url();
        $fixtures = __DIR__ . '/../../Fixtures/';

        if (str_contains($url, 'data.gouv.fr/api')) {
            return Http::response((string) file_get_contents($fixtures . 'insee/cog_dataset.json'), 200, ['Content-Type' => 'application/json']);
        }

        if (str_contains($url, 'insee.fr/fr/statistiques/fichier')) {
            $name = preg_replace('/_2026\.csv$/', '.csv', basename(parse_url($url, PHP_URL_PATH) ?: ''));

            return Http::response((string) file_get_contents($fixtures . 'insee/' . $name));
        }

        if (str_contains($url, 'data.laposte.fr')) {
            return Http::response((string) file_get_contents($fixtures . 'laposte/hexasmal.csv'));
        }

        if (str_contains($url, 'geo.api.gouv.fr') && $this->withoutCommuneCentres) {
            return Http::response('[]', 200, ['Content-Type' => 'application/json']);
        }

        if (str_contains($url, 'geo.api.gouv.fr')) {
            $file = str_contains($url, 'arrondissement-municipal') ? 'arrondissements.json' : 'communes.json';

            return Http::response((string) file_get_contents($fixtures . 'geo/' . $file), 200, ['Content-Type' => 'application/json']);
        }

        if (str_contains($url, 'adresses-37.csv.gz')) {
            return Http::response((string) file_get_contents($fixtures . 'ban/adresses-37.csv.gz'));
        }

        if (str_contains($url, 'adresse.data.gouv.fr')) {
            return Http::response('', 404);
        }

        return Http::response([], 200);
    }
}
