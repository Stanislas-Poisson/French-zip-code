<?php

declare(strict_types=1);

namespace Tests\Feature\Sources;

use App\Actions\RecordSnapshot;
use App\Data\Sources\DownloadedFile;
use App\Models\Snapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RecordSnapshotTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_a_file_only_once(): void
    {
        $recordSnapshot   = $this->app->make(RecordSnapshot::class);
        $downloadedFile   = new DownloadedFile('/tmp/file.csv', hash('sha256', 'a'), 1);

        $snapshot  = $recordSnapshot->execute('insee_cog', '2026', $downloadedFile);
        $second    = $recordSnapshot->execute('insee_cog', '2026', $downloadedFile);

        $this->assertSame($snapshot->id, $second->id);
        $this->assertSame(1, Snapshot::query()->count());
        $this->assertFalse($snapshot->complete);
    }

    #[Test]
    public function it_records_a_new_snapshot_when_the_checksum_changes(): void
    {
        $recordSnapshot = $this->app->make(RecordSnapshot::class);

        $recordSnapshot->execute('laposte', '2026-09', new DownloadedFile('/tmp/a.csv', hash('sha256', 'a'), 1));
        $recordSnapshot->execute('laposte', '2026-09', new DownloadedFile('/tmp/a.csv', hash('sha256', 'b'), 1));

        $this->assertSame(2, Snapshot::query()->count());
    }
}
