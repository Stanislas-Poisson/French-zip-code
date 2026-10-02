<?php

declare(strict_types=1);

namespace Tests\Feature\Pipeline;

use App\Actions\GeocodeCityWithNominatim;
use App\Enums\CommuneKind;
use App\Enums\CoordinateSource;
use App\Enums\DepartmentType;
use App\Jobs\ComputeBanCoordinatesJob;
use App\Jobs\GeocodeCityJob;
use App\Models\City;
use App\Models\Commune;
use App\Models\Department;
use App\Services\DatasetUpdater;
use App\Services\UpdateProgress;
use Carbon\CarbonImmutable;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Testing\Fakes\BatchFake;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DatasetUpdaterBatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Bus::fake();
    }

    #[Test]
    public function it_batches_the_ban_files_of_the_departments_then_goes_on_when_the_batch_ends(): void
    {
        Department::query()->create([
            'code'       => '37',
            'type'       => DepartmentType::Department,
            'name'       => 'Indre-et-Loire',
            'slug'       => 'indre-et-loire',
            'valid_from' => '1943-01-01',
        ]);

        $this->app->make(DatasetUpdater::class)->dispatchBanBatch('run-1', []);

        Bus::assertBatched(function (PendingBatch $pendingBatch): bool {
            $this->assertSame('ban-coordinates:run-1', $pendingBatch->name);
            $this->assertInstanceOf(ComputeBanCoordinatesJob::class, $pendingBatch->jobs->first());

            $this->endBatch($pendingBatch);

            return true;
        });

        $this->assertIsArray(Cache::get(DatasetUpdater::REPORT_CACHE_KEY));
    }

    #[Test]
    public function it_batches_the_cities_without_ban_point_for_nominatim_then_closes_the_update(): void
    {
        $commune = Commune::query()->create([
            'kind'       => CommuneKind::Commune,
            'insee_code' => '85213',
            'name'       => "Les Rives de l'Yon",
            'slug'       => 'les-rives-de-l-yon',
            'valid_from' => '1943-01-01',
        ]);
        $city = City::query()->create([
            'commune_id'        => $commune->id,
            'postal_code'       => '85310',
            'valid_from'        => '1943-01-01',
            'coordinate_source' => CoordinateSource::CommuneCentre,
        ]);

        $this->app->make(DatasetUpdater::class)->afterBanBatch('run-2', [], 0);

        Bus::assertBatched(function (PendingBatch $pendingBatch) use ($city): bool {
            $this->assertSame('nominatim-fallback:run-2', $pendingBatch->name);

            $job = $pendingBatch->jobs->first();
            $this->assertInstanceOf(GeocodeCityJob::class, $job);
            $this->assertSame($city->id, $job->cityId);

            $this->endBatch($pendingBatch);

            return true;
        });

        $this->assertIsArray(Cache::get(DatasetUpdater::REPORT_CACHE_KEY));
    }

    #[Test]
    public function it_closes_the_update_without_batch_when_every_city_has_a_better_point(): void
    {
        $this->app->make(DatasetUpdater::class)->afterBanBatch('run-3', [], 0);

        Bus::assertNothingBatched();
        $this->assertIsArray(Cache::get(DatasetUpdater::REPORT_CACHE_KEY));
    }

    #[Test]
    public function it_goes_on_without_batch_when_there_is_no_department_to_compute(): void
    {
        $this->app->make(DatasetUpdater::class)->dispatchBanBatch('run-4', []);

        Bus::assertNothingBatched();
        $this->assertIsArray(Cache::get(DatasetUpdater::REPORT_CACHE_KEY));
    }

    #[Test]
    public function it_ignores_a_city_that_no_longer_exists_when_geocoding(): void
    {
        (new GeocodeCityJob(999_999))->handle($this->app->make(GeocodeCityWithNominatim::class), $this->app->make(UpdateProgress::class));

        $this->assertSame(0, City::query()->count());
    }

    private function endBatch(PendingBatch $pendingBatch): void
    {
        $batchFake = new BatchFake('batch-id', $pendingBatch->name, 1, 0, 0, [], [], CarbonImmutable::now());

        foreach ($pendingBatch->finallyCallbacks() as $callback) {
            $this->assertIsCallable($callback);
            $callback($batchFake);
        }
    }
}
