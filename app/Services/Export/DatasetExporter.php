<?php

declare(strict_types=1);

namespace App\Services\Export;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use League\Csv\Writer;
use SplFileObject;

/**
 * Exports the tables of the dataset to CSV and JSON files. The rows are streamed, never loaded all at once.
 * Foreign keys are replaced by the codes of the target rows so that a file can be read on its own.
 * The columns of a file are the aliases of its select.
 */
final class DatasetExporter
{
    /**
     * @return array<string, int> number of rows written by dataset
     */
    public function export(string $directory): array
    {
        File::ensureDirectoryExists($directory . '/csv');
        File::ensureDirectoryExists($directory . '/json');

        $counts = [];

        foreach ($this->datasets() as $name => $dataset) {
            $counts[$name] = $this->write(
                $directory,
                $name,
                array_map($this->columnName(...), $dataset['select']),
                $dataset['query']()->select($dataset['select']),
            );
        }

        return $counts;
    }

    /**
     * @return array{select: list<string>, query: callable(): Builder}
     */
    private function cities(): array
    {
        return [
            'select' => [
                'cities.id',
                'communes.insee_code as commune_insee_code',
                'communes.name as commune_name',
                'departments.code as department_code',
                'regions.code as region_code',
                'cities.postal_code',
                'cities.label',
                'cities.latitude',
                'cities.longitude',
                'cities.address_count',
                'cities.coordinate_source',
                'cities.valid_from',
                'cities.valid_to',
                'cities.replaced_by_city_id',
            ],
            'query' => static fn (): Builder => DB::table('cities')
                ->join('communes', 'communes.id', '=', 'cities.commune_id')
                ->leftJoin('departments', 'departments.id', '=', 'communes.department_id')
                ->leftJoin('regions', 'regions.id', '=', 'departments.region_id')
                ->orderBy('cities.id'),
        ];
    }

    private function columnName(string $select): string
    {
        return (string) preg_replace('/^.*(?: as |\.)/', '', $select);
    }

    /**
     * @return array{select: list<string>, query: callable(): Builder}
     */
    private function communes(): array
    {
        return [
            'select' => [
                'communes.id',
                'departments.code as department_code',
                'regions.code as region_code',
                'communes.insee_code',
                'communes.kind',
                'communes.name',
                'communes.slug',
                'communes.centre_latitude',
                'communes.centre_longitude',
                'communes.valid_from',
                'communes.valid_to',
            ],
            'query' => static fn (): Builder => DB::table('communes')
                ->leftJoin('departments', 'departments.id', '=', 'communes.department_id')
                ->leftJoin('regions', 'regions.id', '=', 'departments.region_id')
                ->orderBy('communes.id'),
        ];
    }

    /**
     * @return array{select: list<string>, query: callable(): Builder}
     */
    private function communeSuccessions(): array
    {
        return [
            'select' => ['from_code', 'to_code', 'kind', 'effective_date'],
            'query'  => static fn (): Builder => DB::table('commune_successions')
                ->oldest('effective_date')
                ->orderBy('id'),
        ];
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return list<string>
     */
    private function csvRow(array $values): array
    {
        return array_map(
            static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
            array_values($values),
        );
    }

    /**
     * @return array<string, array{select: list<string>, query: callable(): Builder}>
     */
    private function datasets(): array
    {
        return [
            'regions'             => $this->regions(),
            'departments'         => $this->departments(),
            'communes'            => $this->communes(),
            'cities'              => $this->cities(),
            'commune_successions' => $this->communeSuccessions(),
            'reference_changes'   => $this->referenceChanges(),
        ];
    }

    /**
     * @return array{select: list<string>, query: callable(): Builder}
     */
    private function departments(): array
    {
        return [
            'select' => [
                'departments.id',
                'regions.code as region_code',
                'departments.code',
                'departments.type',
                'departments.name',
                'departments.slug',
                'departments.valid_from',
                'departments.valid_to',
            ],
            'query' => static fn (): Builder => DB::table('departments')
                ->leftJoin('regions', 'regions.id', '=', 'departments.region_id')
                ->orderBy('departments.id'),
        ];
    }

    /**
     * @param array<string, mixed> $values
     */
    private function jsonRow(array $values): string
    {
        return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{select: list<string>, query: callable(): Builder}
     */
    private function referenceChanges(): array
    {
        return [
            'select' => ['entity_type', 'entity_code', 'change_type', 'old_value', 'new_value', 'detected_at'],
            'query'  => static fn (): Builder => DB::table('reference_changes')->orderBy('id'),
        ];
    }

    /**
     * @return array{select: list<string>, query: callable(): Builder}
     */
    private function regions(): array
    {
        return [
            'select' => ['id', 'code', 'name', 'slug', 'valid_from', 'valid_to'],
            'query'  => static fn (): Builder => DB::table('regions')->orderBy('id'),
        ];
    }

    /**
     * @param list<string> $columns
     */
    private function write(string $directory, string $name, array $columns, Builder $builder): int
    {
        $writer = Writer::from($directory . '/csv/' . $name . '.csv', 'w');
        $writer->insertOne($columns);

        $json = new SplFileObject($directory . '/json/' . $name . '.json', 'w');

        $json->fwrite("[\n");

        $count = 0;

        foreach ($builder->cursor() as $lazyCollection) {
            /** @var array<string, mixed> $values */
            $values = (array) $lazyCollection;

            $writer->insertOne($this->csvRow($values));

            $json->fwrite((0 < $count ? ",\n" : '') . $this->jsonRow($values));
            $count++;
        }

        $json->fwrite("\n]\n");

        return $count;
    }
}
