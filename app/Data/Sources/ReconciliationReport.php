<?php

declare(strict_types=1);

namespace App\Data\Sources;

use Illuminate\Support\Arr;

/**
 * Comparison between what was expected from the sources and what the update produced.
 */
final readonly class ReconciliationReport
{
    /**
     * @param array<string, int> $citiesBySource    count of current cities by source of their point
     * @param list<string>       $departmentsNotRun departments whose BAN job left no result
     * @param list<string>       $departmentsFailed departments whose BAN job failed
     */
    public function __construct(
        public int $openCities,
        public int $citiesWithoutCoordinates,
        public array $citiesBySource,
        public int $communesWithoutCity,
        public array $departmentsNotRun,
        public array $departmentsFailed,
        public int $failedJobs,
    ) {}

    /**
     * Rebuilds a report from what the update stored in the cache.
     *
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $bySource = [];

        foreach (Arr::array($data, 'citiesBySource') as $source => $total) {
            $bySource[(string) $source] = is_numeric($total) ? (int) $total : 0;
        }

        return new self(
            openCities: Arr::integer($data, 'openCities'),
            citiesWithoutCoordinates: Arr::integer($data, 'citiesWithoutCoordinates'),
            citiesBySource: $bySource,
            communesWithoutCity: Arr::integer($data, 'communesWithoutCity'),
            departmentsNotRun: self::codes(Arr::array($data, 'departmentsNotRun')),
            departmentsFailed: self::codes(Arr::array($data, 'departmentsFailed')),
            failedJobs: Arr::integer($data, 'failedJobs'),
        );
    }

    /**
     * Every expected city has a point, every department has been processed and no job failed.
     */
    public function isComplete(): bool
    {
        return 0  === $this->citiesWithoutCoordinates
            && [] === $this->departmentsNotRun
            && [] === $this->departmentsFailed
            && 0  === $this->failedJobs;
    }

    /**
     * @param array<mixed> $codes
     *
     * @return list<string>
     */
    private static function codes(array $codes): array
    {
        $toString = static fn (mixed $code): string => is_scalar($code) ? (string) $code : '';

        return array_values(array_map($toString, $codes));
    }
}
