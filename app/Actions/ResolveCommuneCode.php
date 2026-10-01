<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\Resolution\CodeResolution;
use App\Data\Resolution\ResolutionStep;
use App\Enums\SuccessionKind;
use App\Models\Commune;
use App\Models\CommuneSuccession;
use Carbon\CarbonImmutable;

final class ResolveCommuneCode
{
    /**
     * Follows the successions of a commune code from a date (or from the beginning) up to today.
     *
     * A code that disappeared without any successor is reported as such, never as unknown.
     */
    public function execute(string $code, ?CarbonImmutable $asOf = null): CodeResolution
    {
        $steps     = [];
        $terminals = [];

        /** @var list<array{0: string, 1: CarbonImmutable|null}> $queue */
        $queue   = [[$code, $asOf]];
        $visited = [];

        while ([] !== $queue) {
            [$current, $since] = array_shift($queue);

            $visit = $current . '|' . ($since?->toDateString() ?? '');

            if (isset($visited[$visit])) {
                continue;
            }

            $visited[$visit] = true;

            $walked = $this->stepsOf($current, $since);
            $steps  = [...$steps, ...$walked];
            $queue  = [...$queue, ...$this->followed($walked)];

            if (! $this->isLeft($walked)) {
                $terminals[$current] = true;
            }
        }

        $currentCodes = $this->currentCodes(array_map(strval(...), array_keys($terminals)));

        return new CodeResolution(
            requestedCode: $code,
            asOf: $asOf,
            steps: $steps,
            currentCodes: $currentCodes,
            disappeared: [] === $currentCodes || array_any($steps, $this->isDeletion(...)),
        );
    }

    /**
     * @param list<string> $codes
     *
     * @return list<string> the codes that belong to a commune still valid
     */
    private function currentCodes(array $codes): array
    {
        return array_values(array_filter(
            $codes,
            static fn (string $code): bool => Commune::query()->current()->where('insee_code', $code)->exists(),
        ));
    }

    /**
     * The codes to follow next, with the date from which to follow them.
     *
     * @param list<ResolutionStep> $steps
     *
     * @return list<array{0: string, 1: CarbonImmutable}>
     */
    private function followed(array $steps): array
    {
        $next = [];

        foreach ($steps as $step) {
            if (null !== $step->toCode && $this->isFollowed($step)) {
                $next[] = [$step->toCode, $step->effectiveDate];
            }
        }

        return $next;
    }

    private function isDeletion(ResolutionStep $resolutionStep): bool
    {
        return SuccessionKind::Deleted === $resolutionStep->kind;
    }

    /**
     * A split keeps the code alive but its parts are followed too.
     */
    private function isFollowed(ResolutionStep $resolutionStep): bool
    {
        return SuccessionKind::Split === $resolutionStep->kind || $this->leavesTheCode($resolutionStep);
    }

    /**
     * @param list<ResolutionStep> $steps
     */
    private function isLeft(array $steps): bool
    {
        return [] !== $steps && $this->leavesTheCode(end($steps));
    }

    /**
     * A deletion, a replacement by another code or an absorption ends the life of the code.
     */
    private function leavesTheCode(ResolutionStep $resolutionStep): bool
    {
        $kind     = $resolutionStep->kind;
        $sameCode = $resolutionStep->toCode === $resolutionStep->fromCode;

        return match (true) {
            SuccessionKind::Deleted === $kind                              => true,
            null                    === $resolutionStep->toCode, $sameCode => false,
            default                                                        => $this->movesTheCode($kind),
        };
    }

    private function movesTheCode(SuccessionKind $successionKind): bool
    {
        return in_array($successionKind, [SuccessionKind::Replaced, SuccessionKind::Absorbed], true);
    }

    /**
     * The successions of one code, up to the first one that makes the code leave.
     *
     * @return list<ResolutionStep>
     */
    private function stepsOf(string $current, ?CarbonImmutable $since): array
    {
        $steps = [];

        foreach ($this->successionsOf($current, $since) as $succession) {
            $steps[] = new ResolutionStep(
                fromCode: $current,
                toCode: $succession->to_code,
                kind: $succession->kind,
                effectiveDate: CarbonImmutable::parse($succession->effective_date),
            );

            if ($this->leavesTheCode(end($steps))) {
                break;
            }
        }

        return $steps;
    }

    /**
     * @return iterable<CommuneSuccession>
     */
    private function successionsOf(string $code, ?CarbonImmutable $since): iterable
    {
        $builder = CommuneSuccession::query()->where('from_code', $code);

        if ($since instanceof CarbonImmutable) {
            $builder->where('effective_date', '>=', $since->toDateString());
        }

        return $builder->oldest('effective_date')->orderBy('id')->get();
    }
}
