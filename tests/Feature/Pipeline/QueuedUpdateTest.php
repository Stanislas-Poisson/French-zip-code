<?php

declare(strict_types=1);

namespace Tests\Feature\Pipeline;

use App\Jobs\RunDatasetUpdateJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class QueuedUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_queues_the_update_instead_of_running_it(): void
    {
        Queue::fake();

        $this->command('zipcode:update', ['--force' => true, '--skip-coordinates' => true])
            ->expectsOutputToContain('queued')
            ->assertSuccessful();

        Queue::assertPushed(
            RunDatasetUpdateJob::class,
            static fn (RunDatasetUpdateJob $runDatasetUpdateJob): bool => $runDatasetUpdateJob->force && ! $runDatasetUpdateJob->withCoordinates,
        );
    }

    #[Test]
    public function it_tells_when_no_update_has_been_reconciled_yet(): void
    {
        Cache::flush();

        $this->command('zipcode:status')
            ->expectsOutputToContain('No update has been reconciled yet.')
            ->assertSuccessful();
    }
}
