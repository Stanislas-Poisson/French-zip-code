<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\Sources\DownloadedFile;

interface FileDownloader
{
    /**
     * Downloads a file to the given path and returns its checksum.
     */
    public function download(string $url, string $destination): DownloadedFile;
}
