<?php

declare(strict_types=1);

namespace App\Data\Sources;

final readonly class DownloadedFile
{
    public function __construct(
        public string $path,
        public string $checksum,
        public int $size,
    ) {}
}
