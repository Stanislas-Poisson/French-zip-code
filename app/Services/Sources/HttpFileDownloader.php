<?php

declare(strict_types=1);

namespace App\Services\Sources;

use App\Contracts\FileDownloader;
use App\Data\Sources\DownloadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class HttpFileDownloader implements FileDownloader
{
    public function download(string $url, string $destination): DownloadedFile
    {
        $directory = dirname($destination);

        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create the directory "%s".', $directory));
        }

        Http::timeout(300)->sink($destination)->get($url)->throw();

        $checksum = hash_file('sha256', $destination);
        $size     = filesize($destination);

        if (false === $checksum || false === $size) {
            throw new RuntimeException(sprintf('Cannot read the downloaded file "%s".', $destination));
        }

        return new DownloadedFile($destination, $checksum, $size);
    }
}
