<?php

declare(strict_types=1);

namespace Tests\Feature\Pipeline;

use App\Actions\ComputeDepartmentCoordinates;
use App\Actions\ImportCities;
use App\Actions\ReconcileDataset;
use App\Data\Postal\PostalRecord;
use App\Data\Sources\ReconciliationReport;
use App\Enums\CoordinateSource;
use App\Jobs\ComputeBanCoordinatesJob;
use App\Models\City;
use App\Models\Snapshot;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CogFixtures;
use Tests\TestCase;

final class ReconcileDatasetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        CogFixtures::import($this->app);
        $this->app->make(ImportCities::class)->execute(
            [new PostalRecord('37261', '37000', 'TOURS'), new PostalRecord('37261', '37100', 'TOURS')],
            CarbonImmutable::parse('2026-01-01'),
            CogFixtures::snapshot($this->app, 'laposte'),
        );
    }

    #[Test]
    public function it_counts_the_communes_that_have_no_city(): void
    {
        $this->giveEveryCityAPoint();
        $this->markDepartmentsAsProcessed('run');

        $this->assertGreaterThan(0, $this->reconcile('run')->communesWithoutCity);
    }

    #[Test]
    public function it_is_incomplete_when_a_job_failed(): void
    {
        $this->giveEveryCityAPoint();
        $this->markDepartmentsAsProcessed('run');

        $this->assertFalse($this->reconcile('run', 1)->isComplete());
    }

    #[Test]
    public function it_is_incomplete_while_a_city_has_no_point(): void
    {
        $this->markDepartmentsAsProcessed('run');

        $reconciliationReport = $this->reconcile('run');

        $this->assertSame(2, $reconciliationReport->citiesWithoutCoordinates);
        $this->assertFalse($reconciliationReport->isComplete());
        $this->assertSame(0, Snapshot::query()->where('complete', true)->count());
    }

    #[Test]
    public function it_is_incomplete_while_a_department_has_not_been_processed(): void
    {
        $this->giveEveryCityAPoint();

        $reconciliationReport = $this->reconcile('run');

        $this->assertContains('37', $reconciliationReport->departmentsNotRun);
        $this->assertFalse($reconciliationReport->isComplete());
    }

    #[Test]
    public function it_keeps_the_result_of_a_department_job_and_its_failure(): void
    {
        $computeBanCoordinatesJob = new ComputeBanCoordinatesJob('run', '37');
        $computeBanCoordinatesJob->failed(new Exception('boom'));

        $this->assertSame('failed', Cache::get(ReconcileDataset::cacheKey('run', '37')));
    }

    #[Test]
    public function it_marks_the_snapshots_complete_when_nothing_is_missing(): void
    {
        $this->giveEveryCityAPoint();
        $this->markDepartmentsAsProcessed('run');

        $reconciliationReport = $this->reconcile('run');

        $this->assertTrue($reconciliationReport->isComplete());
        $this->assertSame(['commune_centre' => 2], $reconciliationReport->citiesBySource);
        $this->assertSame(Snapshot::query()->count(), Snapshot::query()->where('complete', true)->count());
    }

    #[Test]
    public function it_reports_the_departments_whose_job_failed(): void
    {
        $this->giveEveryCityAPoint();
        $this->markDepartmentsAsProcessed('run');
        Cache::put(ReconcileDataset::cacheKey('run', '37'), 'failed');

        $reconciliationReport = $this->reconcile('run');

        $this->assertSame(['37'], $reconciliationReport->departmentsFailed);
        $this->assertFalse($reconciliationReport->isComplete());
    }

    private function giveEveryCityAPoint(): void
    {
        City::query()->update(['latitude' => 47.39, 'longitude' => 0.69, 'coordinate_source' => CoordinateSource::CommuneCentre]);
    }

    private function markDepartmentsAsProcessed(string $runId): void
    {
        foreach ($this->app->make(ComputeDepartmentCoordinates::class)->departmentCodes() as $code) {
            Cache::put(ReconcileDataset::cacheKey($runId, $code), ['points' => 0, 'updated' => 0, 'unmatched' => 0, 'skipped' => true]);
        }
    }

    private function reconcile(string $runId, int $failedJobs = 0): ReconciliationReport
    {
        $snapshotIds = [];

        foreach (Snapshot::query()->get() as $snapshot) {
            $snapshotIds[] = $snapshot->id;
        }

        return $this->app->make(ReconcileDataset::class)->execute($runId, $snapshotIds, $failedJobs);
    }
}
