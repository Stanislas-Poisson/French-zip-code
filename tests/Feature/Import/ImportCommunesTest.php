<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Actions\ImportCommunes;
use App\Data\Insee\CommuneRecord;
use App\Data\Insee\HistoricCommuneRecord;
use App\Enums\CommuneKind;
use App\Models\Commune;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ImportCommunesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_does_not_duplicate_an_overseas_commune_that_already_has_a_history(): void
    {
        $this->app->make(ImportCommunes::class)->execute(
            [$this->period('98714', 'Bora-Bora', '1943-01-01', null)],
            [],
            [
                new CommuneRecord('98714', CommuneKind::Commune, 'Bora-Bora', '987', null, null),
                new CommuneRecord('98801', CommuneKind::Commune, 'Nouméa', '988', null, null),
            ],
            [],
        );

        $this->assertSame(1, Commune::query()->where('insee_code', '98714')->count());
        $this->assertSame(1, Commune::query()->where('insee_code', '98801')->count());
    }

    #[Test]
    public function it_does_not_keep_the_delegated_and_associated_communes(): void
    {
        $this->import([
            $this->period('01015', 'Arbignieu', '1943-01-01', '2019-01-01', CommuneKind::Commune),
            $this->period('01015', 'Arbignieu', '2019-01-01', null, CommuneKind::Delegated),
        ], []);

        $this->assertSame(1, Commune::query()->count());
    }

    #[Test]
    public function it_keeps_one_row_when_a_commune_is_only_renamed(): void
    {
        $this->import([
            $this->period('28274', 'Moutiers', '1943-01-01', '2026-01-01'),
            $this->period('28274', 'Moutiers-en-Beauce', '2026-01-01', null),
        ], []);

        $commune = Commune::query()->where('insee_code', '28274')->sole();

        $this->assertSame('Moutiers-en-Beauce', $commune->name);
        $this->assertSame('1943-01-01', $commune->valid_from->toDateString());
        $this->assertNull($commune->valid_to);
    }

    #[Test]
    public function it_splits_the_rows_when_the_entity_behind_the_code_changes(): void
    {
        $this->import([
            $this->period('85213', 'Saint-Florent-des-Bois', '1943-01-01', '2016-01-01'),
            $this->period('85213', "Rives de l'Yon", '2016-01-01', null),
        ], ['85213' => ['2016-01-01' => true]]);

        $this->assertSame(2, Commune::query()->where('insee_code', '85213')->count());
    }

    #[Test]
    public function it_updates_the_validity_of_an_existing_row_on_a_new_import(): void
    {
        $this->import([$this->period('08227', 'Hocmont', '1943-01-01', null)], []);
        $this->import([$this->period('08227', 'Hocmont', '1943-01-01', '1968-03-02')], []);

        $commune = Commune::query()->where('insee_code', '08227')->sole();

        $this->assertSame('1968-03-02', $commune->valid_to?->toDateString());
    }

    /**
     * @param list<HistoricCommuneRecord>            $history
     * @param array<int|string, array<string, true>> $breaks
     */
    private function import(array $history, array $breaks): void
    {
        $this->app->make(ImportCommunes::class)->execute($history, [], [], $breaks);
    }

    private function period(string $code, string $name, string $from, ?string $to, CommuneKind $communeKind = CommuneKind::Commune): HistoricCommuneRecord
    {
        return new HistoricCommuneRecord(
            $code,
            $communeKind,
            $name,
            CarbonImmutable::parse($from),
            null === $to ? null : CarbonImmutable::parse($to),
        );
    }
}
