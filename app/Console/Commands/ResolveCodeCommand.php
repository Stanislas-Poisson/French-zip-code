<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ResolveCity;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class ResolveCodeCommand extends Command
{
    protected $description = 'Find where an old commune code (and zip code) points to today';

    protected $signature = 'zipcode:resolve
        {code : INSEE code of the commune}
        {--zip= : Zip code of the old address}
        {--date= : Date (Y-m-d) from which the code was used, the whole history by default}';

    public function handle(ResolveCity $resolveCity): int
    {
        $code = (string) $this->argument('code');
        $zip  = $this->option('zip');
        $date = $this->option('date');

        $cityResolution = $resolveCity->execute(
            $code,
            is_string($zip)  && '' !== $zip ? $zip : null,
            is_string($date) && '' !== $date ? CarbonImmutable::parse($date) : null,
        );

        foreach ($cityResolution->commune->steps as $step) {
            $this->line(sprintf('%s  %s: %s -> %s', $step->effectiveDate->toDateString(), $step->kind->value, $step->fromCode, $step->toCode ?? 'no successor'));
        }

        if ($cityResolution->commune->disappeared) {
            $this->warn('The code ' . $code . ' has disappeared without any current commune.');

            return self::FAILURE;
        }

        $this->info('Current communes: ' . implode(', ', $cityResolution->commune->currentCodes));

        foreach ($cityResolution->cities as $city) {
            $this->line(sprintf('city #%d  %s  %s  (%s, %s)', $city->id, $city->postal_code, $city->label ?? '', $city->latitude, $city->longitude));
        }

        if (null !== $cityResolution->postalCode && ! $cityResolution->exactPostal) {
            $this->warn('The zip code ' . $cityResolution->postalCode . ' is not used any more by these communes: all their zip codes are listed.');
        }

        return self::SUCCESS;
    }
}
