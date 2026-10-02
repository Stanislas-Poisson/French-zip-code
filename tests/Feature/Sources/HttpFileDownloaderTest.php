<?php

declare(strict_types=1);

namespace Tests\Feature\Sources;

use App\Contracts\FileDownloader;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class HttpFileDownloaderTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/french-postal-code-' . bin2hex(random_bytes(4));

        Sleep::fake();
        config()->set('sources.download', [
            'attempts'         => 4,
            'initial_delay_ms' => 1000,
            'max_delay_ms'     => 3000,
            'budget_seconds'   => 300,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function it_does_not_retry_a_missing_file(): void
    {
        Http::fake(['*' => Http::response('missing', 404)]);

        $this->expectException(RequestException::class);

        try {
            $this->app->make(FileDownloader::class)->download('https://example.test/file.csv', $this->directory . '/file.csv');
        }
        finally {
            Http::assertSentCount(1);
        }
    }

    #[Test]
    public function it_downloads_a_file_and_computes_its_checksum(): void
    {
        Http::fake(['*' => Http::response("a;b\n1;2\n")]);

        $downloadedFile = $this->app->make(FileDownloader::class)
            ->download('https://example.test/file.csv', $this->directory . '/insee/2026/file.csv');

        $this->assertFileExists($downloadedFile->path);
        $this->assertSame("a;b\n1;2\n", file_get_contents($downloadedFile->path));
        $this->assertSame(hash('sha256', "a;b\n1;2\n"), $downloadedFile->checksum);
        $this->assertSame(8, $downloadedFile->size);
    }

    #[Test]
    public function it_fails_on_an_http_error(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->expectException(RequestException::class);

        $this->app->make(FileDownloader::class)
            ->download('https://example.test/file.csv', $this->directory . '/insee/2026/file.csv');
    }

    #[Test]
    public function it_fails_when_the_downloaded_file_cannot_be_read(): void
    {
        Http::fake(['*' => Http::response('content')]);

        // The destination sits under a regular file: nothing can be written there.
        File::ensureDirectoryExists($this->directory);
        file_put_contents($this->directory . '/blocker', 'x');

        // The warnings are silenced to reach the guard, instead of being turned into exceptions.
        set_error_handler(static fn (): bool => true);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot read the downloaded file');

            $this->app->make(FileDownloader::class)
                ->download('https://example.test/file.csv', $this->directory . '/blocker/file.csv');
        }
        finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function it_gives_up_after_the_last_attempt(): void
    {
        Http::fake(['*' => Http::response('no available server', 503)]);

        try {
            $this->app->make(FileDownloader::class)->download('https://example.test/file.csv', $this->directory . '/file.csv');
            $this->fail('The download should have failed.');
        }
        catch (RequestException $requestException) {
            $this->assertSame(503, $requestException->response->status());
        }

        Http::assertSentCount(4);
    }

    #[Test]
    public function it_retries_a_connection_failure(): void
    {
        Http::fake(['*' => Http::sequence()
            ->pushFailedConnection()
            ->push('a;b')]);

        $downloadedFile = $this->app->make(FileDownloader::class)
            ->download('https://example.test/file.csv', $this->directory . '/file.csv');

        $this->assertSame('a;b', file_get_contents($downloadedFile->path));
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_retries_a_temporary_error_with_a_growing_delay_then_succeeds(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push('no available server', 503)
            ->push('slow down', 429)
            ->push('a;b', 502)
            ->push("a;b\n1;2\n")]);

        $downloadedFile = $this->app->make(FileDownloader::class)
            ->download('https://example.test/file.csv', $this->directory . '/file.csv');

        $this->assertSame("a;b\n1;2\n", file_get_contents($downloadedFile->path));
        Http::assertSentCount(4);
        Sleep::assertSequence([Sleep::for(1000)->milliseconds(), Sleep::for(2000)->milliseconds(), Sleep::for(3000)->milliseconds()]);
    }

    #[Test]
    public function it_stops_retrying_once_the_time_budget_is_spent(): void
    {
        config()->set('sources.download.budget_seconds', 0);
        Http::fake(['*' => Http::response('no available server', 503)]);

        $this->expectException(RequestException::class);

        try {
            $this->app->make(FileDownloader::class)->download('https://example.test/file.csv', $this->directory . '/file.csv');
        }
        finally {
            Http::assertSentCount(1);
        }
    }
}
