<?php

declare(strict_types=1);

namespace App\Services\Export;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use League\Csv\Writer;
use RuntimeException;

/**
 * Exports the tables of the dataset to CSV and JSON files. The rows are streamed, never loaded all at once.
 * Foreign keys are replaced by the codes of the target rows so that a file can be read on its own.
 */
final class DatasetExporter
{
    /**
     * @return array<string, int> number of rows written by dataset
     */
    public function export(string $directory): array
    {
        $counts = [];

        foreach ($this->datasets() as $name => $dataset) {
            $counts[$name] = $this->write($directory, $name, $dataset['columns'], $dataset['query']());
        }

        return $counts;
    }

    /**
     * @return array<string, array{columns: list<string>, query: callable(): Builder}>
     */
    private function datasets(): array
    {
        return [
            'regions' => [
                'columns' => ['id', 'code', 'name', 'slug', 'valid_from', 'valid_to'],
                'query'   => static fn (): Builder => DB::table('regions')
                    ->select('id', 'code', 'name', 'slug', 'valid_from', 'valid_to')
                    ->orderBy('id'),
            ],
            'departments' => [
                'columns' => ['id', 'region_code', 'code', 'type', 'name', 'slug', 'valid_from', 'valid_to'],
                'query'   => static fn (): Builder => DB::table('departments')
                    ->leftJoin('regions', 'regions.id', '=', 'departments.region_id')
                    ->select('departments.id', 'regions.code as region_code', 'departments.code', 'departments.type', 'departments.name', 'departments.slug', 'departments.valid_from', 'departments.valid_to')
                    ->orderBy('departments.id'),
            ],
            'communes' => [
                'columns' => ['id', 'department_code', 'region_code', 'insee_code', 'kind', 'name', 'slug', 'centre_latitude', 'centre_longitude', 'valid_from', 'valid_to'],
                'query'   => static fn (): Builder => DB::table('communes')
                    ->leftJoin('departments', 'departments.id', '=', 'communes.department_id')
                    ->leftJoin('regions', 'regions.id', '=', 'departments.region_id')
                    ->select('communes.id', 'departments.code as department_code', 'regions.code as region_code', 'communes.insee_code', 'communes.kind', 'communes.name', 'communes.slug', 'communes.centre_latitude', 'communes.centre_longitude', 'communes.valid_from', 'communes.valid_to')
                    ->orderBy('communes.id'),
            ],
            'cities' => [
                'columns' => ['id', 'commune_insee_code', 'commune_name', 'department_code', 'region_code', 'postal_code', 'label', 'latitude', 'longitude', 'address_count', 'coordinate_source', 'valid_from', 'valid_to', 'replaced_by_city_id'],
                'query'   => static fn (): Builder => DB::table('cities')
                    ->join('communes', 'communes.id', '=', 'cities.commune_id')
                    ->leftJoin('departments', 'departments.id', '=', 'communes.department_id')
                    ->leftJoin('regions', 'regions.id', '=', 'departments.region_id')
                    ->select('cities.id', 'communes.insee_code as commune_insee_code', 'communes.name as commune_name', 'departments.code as department_code', 'regions.code as region_code', 'cities.postal_code', 'cities.label', 'cities.latitude', 'cities.longitude', 'cities.address_count', 'cities.coordinate_source', 'cities.valid_from', 'cities.valid_to', 'cities.replaced_by_city_id')
                    ->orderBy('cities.id'),
            ],
            'commune_successions' => [
                'columns' => ['from_code', 'to_code', 'kind', 'effective_date'],
                'query'   => static fn (): Builder => DB::table('commune_successions')
                    ->select('from_code', 'to_code', 'kind', 'effective_date')
                    ->oldest('effective_date')
                    ->orderBy('id'),
            ],
            'reference_changes' => [
                'columns' => ['entity_type', 'entity_code', 'change_type', 'old_value', 'new_value', 'detected_at'],
                'query'   => static fn (): Builder => DB::table('reference_changes')
                    ->select('entity_type', 'entity_code', 'change_type', 'old_value', 'new_value', 'detected_at')
                    ->orderBy('id'),
            ],
        ];
    }

    /**
     * @param list<string> $columns
     */
    private function write(string $directory, string $name, array $columns, Builder $builder): int
    {
        foreach (['csv', 'json'] as $format) {
            if (! is_dir($directory . '/' . $format) && ! mkdir($directory . '/' . $format, 0o755, true) && ! is_dir($directory . '/' . $format)) {
                throw new RuntimeException(sprintf('Cannot create the directory "%s".', $directory . '/' . $format));
            }
        }

        $writer = Writer::from($directory . '/csv/' . $name . '.csv', 'w');
        $writer->insertOne($columns);

        $json = fopen($directory . '/json/' . $name . '.json', 'w');

        if (false === $json) {
            throw new RuntimeException(sprintf('Cannot write the file "%s".', $directory . '/json/' . $name . '.json'));
        }

        fwrite($json, "[\n");

        $count = 0;

        foreach ($builder->cursor() as $lazyCollection) {
            /** @var array<string, mixed> $values */
            $values = (array) $lazyCollection;

            $writer->insertOne(array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', array_values($values)));

            fwrite($json, (0 === $count ? '' : ",\n") . json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $count++;
        }

        fwrite($json, "\n]\n");
        fclose($json);

        return $count;
    }
}
