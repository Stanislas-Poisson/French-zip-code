<?php

declare(strict_types=1);

namespace App\Data\Sources;

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
     * Every expected city has a point, every department has been processed and no job failed.
     */
    public function isComplete(): bool
    {
        return 0  === $this->citiesWithoutCoordinates
            && [] === $this->departmentsNotRun
            && [] === $this->departmentsFailed
            && 0  === $this->failedJobs;
    }
}
