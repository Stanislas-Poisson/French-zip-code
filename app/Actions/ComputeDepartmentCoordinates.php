<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\Sources\DownloadedFile;
use App\Models\Department;
use App\Services\CityPointCalculator;
use App\Services\Parsers\Ban\BanAddressParser;
use App\Services\Sources\BanClient;

final readonly class ComputeDepartmentCoordinates
{
    public function __construct(
        private BanClient $banClient,
        private BanAddressParser $banAddressParser,
        private CityPointCalculator $cityPointCalculator,
        private ApplyBanCoordinates $applyBanCoordinates,
    ) {}

    /**
     * Codes of the departments (and overseas collectivities) that may have a BAN file.
     *
     * @return list<string>
     */
    public function departmentCodes(): array
    {
        $codes = [];

        foreach (Department::query()->current()->orderBy('code')->get() as $department) {
            $codes[] = $department->code;
        }

        return $codes;
    }

    /**
     * Downloads the BAN file of a department, computes the point of each of its postal codes and stores it.
     *
     * @return array{points: int, updated: int, unmatched: int, skipped: bool}
     */
    public function execute(string $departmentCode): array
    {
        $directory   = config('sources.directory');
        $directory   = is_string($directory) ? $directory : storage_path('app/sources');

        $destination = $directory . '/ban/adresses-' . $departmentCode . '.csv.gz';

        $file = $this->banClient->fetch($departmentCode, $destination);

        if (! $file instanceof DownloadedFile) {
            return ['points' => 0, 'updated' => 0, 'unmatched' => 0, 'skipped' => true];
        }

        $minimum   = config('sources.ban.minimum_addresses');
        $generator = $this->banAddressParser->parse($file->path);
        $points    = $this->cityPointCalculator->compute($generator, is_int($minimum) ? $minimum : 1);
        $result    = $this->applyBanCoordinates->execute($points);

        return [
            'points'    => count($points),
            'updated'   => $result['updated'],
            'unmatched' => $result['unmatched'],
            'skipped'   => false,
        ];
    }
}
