<?php

declare(strict_types=1);

namespace Tests\Feature\Sources;

use App\Contracts\FileDownloader;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class HttpFileDownloaderTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/french-zip-code-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
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
}
