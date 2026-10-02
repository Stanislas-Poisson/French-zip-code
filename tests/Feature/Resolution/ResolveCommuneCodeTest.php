<?php

declare(strict_types=1);

namespace Tests\Feature\Resolution;

use App\Actions\ResolveCommuneCode;
use App\Data\Resolution\ResolutionStep;
use App\Enums\CommuneKind;
use App\Enums\DepartmentType;
use App\Enums\SuccessionKind;
use App\Models\Commune;
use App\Models\CommuneSuccession;
use App\Models\Department;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CogFixtures;
use Tests\TestCase;

final class ResolveCommuneCodeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_follows_a_chain_of_successions(): void
    {
        $this->createCommune('30003');
        $this->succession('30001', '30002', SuccessionKind::Replaced, '2000-01-01');
        $this->succession('30002', '30003', SuccessionKind::Absorbed, '2010-01-01');

        $codeResolution = $this->app->make(ResolveCommuneCode::class)->execute('30001');

        $this->assertSame(['30003'], $codeResolution->currentCodes);
        $this->assertCount(2, $codeResolution->steps);
    }

    #[Test]
    public function it_ignores_the_changes_that_happened_before_the_given_date(): void
    {
        CogFixtures::import($this->app);

        $codeResolution = $this->app->make(ResolveCommuneCode::class)->execute('85213', CarbonImmutable::parse('2020-01-01'));

        $this->assertSame([], $codeResolution->steps);
        $this->assertSame(['85213'], $codeResolution->currentCodes);
    }

    #[Test]
    public function it_points_an_absorbed_commune_to_the_merged_commune(): void
    {
        CogFixtures::import($this->app);

        $codeResolution = $this->app->make(ResolveCommuneCode::class)->execute('85043');

        $this->assertSame(['85213'], $codeResolution->currentCodes);
        $this->assertFalse($codeResolution->disappeared);
        $this->assertSame(SuccessionKind::Absorbed, $codeResolution->steps[0]->kind);
        $this->assertSame('2016-01-01', $codeResolution->steps[0]->effectiveDate->toDateString());
    }

    #[Test]
    public function it_reports_a_commune_that_disappeared_without_successor(): void
    {
        $this->succession('97123', null, SuccessionKind::Deleted, '2007-02-23');

        $codeResolution = $this->app->make(ResolveCommuneCode::class)->execute('97123');

        $this->assertTrue($codeResolution->disappeared);
        $this->assertSame([], $codeResolution->currentCodes);
        $this->assertSame(SuccessionKind::Deleted, $codeResolution->steps[0]->kind);
    }

    #[Test]
    public function it_reports_an_unknown_code_as_not_existing(): void
    {
        $codeResolution = $this->app->make(ResolveCommuneCode::class)->execute('00000');

        $this->assertTrue($codeResolution->disappeared);
        $this->assertSame([], $codeResolution->steps);
    }

    #[Test]
    public function it_reports_that_a_code_was_reused_by_the_merged_commune(): void
    {
        CogFixtures::import($this->app);

        $codeResolution = $this->app->make(ResolveCommuneCode::class)->execute('85213', CarbonImmutable::parse('2000-01-01'));

        $this->assertSame(['85213'], $codeResolution->currentCodes);
        $this->assertContains(SuccessionKind::CodeReused, array_map(static fn (ResolutionStep $resolutionStep): SuccessionKind => $resolutionStep->kind, $codeResolution->steps));
    }

    #[Test]
    public function it_returns_every_target_of_a_split(): void
    {
        $this->createCommune('15141');
        $this->createCommune('15031');
        $this->succession('15141', '15031', SuccessionKind::Split, '2025-01-01');

        $codeResolution = $this->app->make(ResolveCommuneCode::class)->execute('15141');

        $this->assertEqualsCanonicalizing(['15141', '15031'], $codeResolution->currentCodes);
    }

    #[Test]
    public function it_walks_a_code_reached_by_two_paths_only_once(): void
    {
        $this->createCommune('40004');
        $this->succession('40001', '40002', SuccessionKind::Split, '2020-01-01');
        $this->succession('40001', '40003', SuccessionKind::Split, '2020-01-01');
        $this->succession('40002', '40004', SuccessionKind::Absorbed, '2021-01-01');
        $this->succession('40003', '40004', SuccessionKind::Absorbed, '2021-01-01');

        $codeResolution = $this->app->make(ResolveCommuneCode::class)->execute('40001');

        $this->assertSame(['40004'], $codeResolution->currentCodes);
        $this->assertCount(4, $codeResolution->steps);
    }

    private function createCommune(string $code): void
    {
        $department = Department::query()->firstOrCreate(
            ['code' => substr($code, 0, 2)],
            ['type' => DepartmentType::Department, 'name' => 'Test', 'slug' => 'test', 'valid_from' => '1943-01-01'],
        );

        Commune::query()->create([
            'department_id' => $department->id,
            'insee_code'    => $code,
            'kind'          => CommuneKind::Commune,
            'name'          => 'Commune ' . $code,
            'slug'          => 'commune-' . $code,
            'valid_from'    => '1943-01-01',
        ]);
    }

    private function succession(string $from, ?string $to, SuccessionKind $successionKind, string $date): void
    {
        CommuneSuccession::query()->create([
            'from_code'      => $from,
            'to_code'        => $to,
            'kind'           => $successionKind,
            'effective_date' => $date,
        ]);
    }
}
