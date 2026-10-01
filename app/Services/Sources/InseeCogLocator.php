<?php

declare(strict_types=1);

namespace App\Services\Sources;

use App\Data\Sources\CogVintage;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Finds the most recent vintage of the INSEE COG by reading the dataset published on data.gouv.fr.
 * INSEE sends no ETag nor Last-Modified header: a new vintage shows up as new resources of the dataset.
 */
final class InseeCogLocator
{
    public function latest(): CogVintage
    {
        $url = config('sources.insee.dataset_url');

        $resources = Http::get(is_string($url) ? $url : '')->throw()->json('resources');

        if (! is_array($resources)) {
            throw new RuntimeException('The COG dataset has no resources.');
        }

        /** @var array<int, array<string, string>> $byYear */
        $byYear = [];

        foreach ($resources as $resource) {
            if (! is_array($resource)) {
                continue;
            }

            $title       = $resource['title'] ?? null;
            $resourceUrl = $resource['url']   ?? null;

            if (! is_string($title) || ! is_string($resourceUrl)) {
                continue;
            }

            if (1 !== preg_match('/^Millésime (\d{4})\s*:/u', $title, $vintage)) {
                continue;
            }

            // Only the suffix of the vintage itself is removed: v_commune_depuis_1943 keeps its 1943.
            if (1 !== preg_match('~/(v_[a-z0-9_]+?)(?:_' . $vintage[1] . ')?\.csv$~', $resourceUrl, $file)) {
                continue;
            }

            $byYear[(int) $vintage[1]][$file[1]] = $resourceUrl;
        }

        if ([] === $byYear) {
            throw new RuntimeException('No COG vintage found in the dataset.');
        }

        $year = max(array_keys($byYear));

        return new CogVintage($year, $byYear[$year]);
    }
}
