<?php

declare(strict_types=1);

namespace App\Data\Resolution;

use Carbon\CarbonImmutable;

/**
 * Where an old commune code points to today.
 */
final readonly class CodeResolution
{
    /**
     * @param list<ResolutionStep> $steps        every change met between the date and today
     * @param list<string>         $currentCodes the codes of the communes that exist today
     */
    public function __construct(
        public string $requestedCode,
        public ?CarbonImmutable $asOf,
        public array $steps,
        public array $currentCodes,
        public bool $disappeared,
    ) {}
}
