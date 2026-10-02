<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ResolveCity;
use App\Data\Resolution\CityResolution;
use App\Data\Resolution\ResolutionStep;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class ResolveCodeCommand extends Command
{
    protected $description = 'Find where an old commune code (and postal code) points to today';

    protected $signature = 'dataset:resolve
        {code : INSEE code of the commune}
        {--postal-code= : Postal code of the old address}
        {--date= : Date (Y-m-d) from which the code was used, the whole history by default}';

    public function handle(ResolveCity $resolveCity): int
    {
        $code = (string) $this->argument('code');

        $cityResolution = $resolveCity->execute($code, $this->stringOption('postal-code'), $this->dateOption());

        foreach ($cityResolution->commune->steps as $step) {
            $this->line($this->describe($step));
        }

        if ($cityResolution->commune->disappeared) {
            $this->warn('The code ' . $code . ' has disappeared without any current commune.');

            return self::FAILURE;
        }

        $this->info('Current communes: ' . implode(', ', $cityResolution->commune->currentCodes));
        $this->renderCities($cityResolution);

        return self::SUCCESS;
    }

    private function dateOption(): ?CarbonImmutable
    {
        $date = $this->stringOption('date');

        return null === $date ? null : CarbonImmutable::parse($date);
    }

    private function describe(ResolutionStep $resolutionStep): string
    {
        return sprintf(
            '%s  %s: %s -> %s',
            $resolutionStep->effectiveDate->toDateString(),
            $resolutionStep->kind->value,
            $resolutionStep->fromCode,
            $resolutionStep->toCode ?? 'no successor',
        );
    }

    private function renderCities(CityResolution $cityResolution): void
    {
        foreach ($cityResolution->cities as $city) {
            $this->line(sprintf(
                'city #%d  %s  %s  (%s, %s)',
                $city->id,
                $city->postal_code,
                $city->label ?? '',
                $city->latitude,
                $city->longitude,
            ));
        }

        if (null !== $cityResolution->postalCode && ! $cityResolution->exactPostal) {
            $this->warn(
                'The postal code ' . $cityResolution->postalCode
                . ' is not used any more by these communes: all their postal codes are listed.',
            );
        }
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && '' !== $value ? $value : null;
    }
}
