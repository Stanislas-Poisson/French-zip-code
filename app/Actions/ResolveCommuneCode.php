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
        $steps       = [];
        $terminals   = [];
        $disappeared = false;

        /** @var list<array{0: string, 1: CarbonImmutable|null}> $queue */
        $queue   = [[$code, $asOf]];
        $visited = [];

        while ([] !== $queue) {
            [$current, $since] = array_shift($queue);

            if (isset($visited[$current . '|' . ($since?->toDateString() ?? '')])) {
                continue;
            }

            $visited[$current . '|' . ($since?->toDateString() ?? '')] = true;

            $gone = false;

            foreach ($this->successionsOf($current, $since) as $succession) {
                $step = new ResolutionStep(
                    fromCode: $current,
                    toCode: $succession->to_code,
                    kind: $succession->kind,
                    effectiveDate: CarbonImmutable::parse($succession->effective_date),
                );
                $steps[] = $step;

                if (SuccessionKind::Deleted === $succession->kind) {
                    $disappeared = true;
                    $gone        = true;

                    break;
                }

                if (null === $succession->to_code) {
                    continue;
                }

                if (SuccessionKind::Split === $succession->kind) {
                    $queue[] = [$succession->to_code, $step->effectiveDate];

                    continue;
                }

                if (in_array($succession->kind, [SuccessionKind::Replaced, SuccessionKind::Absorbed], true) && $succession->to_code !== $current) {
                    $queue[] = [$succession->to_code, $step->effectiveDate];
                    $gone    = true;

                    break;
                }
            }

            if (! $gone) {
                $terminals[$current] = true;
            }
        }

        $currentCodes = array_values(array_filter(
            array_map(strval(...), array_keys($terminals)),
            static fn (string $terminal): bool => Commune::query()->current()->where('insee_code', $terminal)->exists(),
        ));

        return new CodeResolution(
            requestedCode: $code,
            asOf: $asOf,
            steps: $steps,
            currentCodes: $currentCodes,
            disappeared: $disappeared || [] === $currentCodes,
        );
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
