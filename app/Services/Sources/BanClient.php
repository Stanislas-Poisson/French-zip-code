<?php

declare(strict_types=1);

namespace App\Services\Sources;

use App\Contracts\FileDownloader;
use App\Data\Sources\DownloadedFile;
use Illuminate\Http\Client\RequestException;

/**
 * Downloads the department files of the BAN (Base Adresse Nationale).
 */
final readonly class BanClient
{
    /**
     * A file smaller than this is an empty department (the BAN publishes a 20-byte file).
     */
    private const int MINIMUM_SIZE = 100;

    public function __construct(private FileDownloader $fileDownloader) {}

    /**
     * @return DownloadedFile|null null when the BAN has no addresses for this department
     */
    public function fetch(string $departmentCode, string $destination): ?DownloadedFile
    {
        try {
            $file = $this->fileDownloader->download($this->url($departmentCode), $destination);
        }
        catch (RequestException $requestException) {
            if (404 === $requestException->response->status()) {
                return null;
            }

            throw $requestException;
        }

        if (self::MINIMUM_SIZE > $file->size) {
            return null;
        }

        return $file;
    }

    public function url(string $departmentCode): string
    {
        $base = config('sources.ban.base_url');

        return rtrim(is_string($base) ? $base : '', '/') . '/adresses-' . $departmentCode . '.csv.gz';
    }
}
