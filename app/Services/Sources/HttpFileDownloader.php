<?php

declare(strict_types=1);

namespace App\Services\Sources;

use App\Contracts\FileDownloader;
use App\Data\Sources\DownloadedFile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class HttpFileDownloader implements FileDownloader
{
    public function download(string $url, string $destination): DownloadedFile
    {
        File::ensureDirectoryExists(dirname($destination));

        $startedAt = microtime(true);
        $budget    = config()->integer('sources.download.budget_seconds');

        Http::timeout(300)
            ->retry(
                max(1, config()->integer('sources.download.attempts')),
                fn (int $attempt): int => $this->delay($url, $attempt, $startedAt, $budget),
                fn (Throwable $throwable): bool => $this->isTemporary($throwable) && (microtime(true) - $startedAt) < $budget,
            )
            ->sink($destination)
            ->get($url)
            ->throw();

        $checksum = hash_file('sha256', $destination);
        $size     = filesize($destination);

        if (false === $checksum || false === $size) {
            throw new RuntimeException(sprintf('Cannot read the downloaded file "%s".', $destination));
        }

        return new DownloadedFile($destination, $checksum, $size);
    }

    /**
     * Milliseconds to wait before the next attempt: doubled after each failure, capped, and never past the budget.
     */
    private function delay(string $url, int $attempt, float $startedAt, int $budget): int
    {
        $delay     = min(
            config()->integer('sources.download.max_delay_ms'),
            config()->integer('sources.download.initial_delay_ms') * (2 ** ($attempt - 1)),
        );
        $remaining = max(0, (int) (($budget - (microtime(true) - $startedAt)) * 1000));
        $delay     = min($delay, $remaining);

        Log::warning(sprintf('Download of %s failed (attempt %d), retrying in %.1f s.', $url, $attempt, $delay / 1000));

        return $delay;
    }

    /**
     * A connection failure, a rate limit or a server error may go away; a missing file or a refusal will not.
     */
    private function isTemporary(Throwable $throwable): bool
    {
        if ($throwable instanceof ConnectionException) {
            return true;
        }

        return $throwable instanceof RequestException
            && (429 === $throwable->response->status() || $throwable->response->status() >= 500);
    }
}
