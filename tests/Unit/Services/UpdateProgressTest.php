<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\UpdateProgress;
use Illuminate\Console\OutputStyle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class UpdateProgressTest extends TestCase
{
    #[Test]
    public function it_draws_a_progress_bar_that_ends_with_the_step(): void
    {
        $bufferedOutput = new BufferedOutput;
        $updateProgress = new UpdateProgress;
        $updateProgress->attach(new OutputStyle(new ArrayInput([]), $bufferedOutput));

        $updateProgress->start('Computing the points', 2);
        $updateProgress->advance();
        $updateProgress->advance();
        $updateProgress->finish();

        $output = $bufferedOutput->fetch();

        $this->assertStringContainsString('→ Computing the points', $output);
        $this->assertStringContainsString('2/2', $output);
    }

    #[Test]
    public function it_ends_a_progress_bar_when_the_next_step_starts(): void
    {
        $bufferedOutput = new BufferedOutput;
        $updateProgress = new UpdateProgress;
        $updateProgress->attach(new OutputStyle(new ArrayInput([]), $bufferedOutput));

        $updateProgress->start('Computing the points', 1);
        $updateProgress->advance();
        $updateProgress->step('Checking that nothing is missing');

        $this->assertStringContainsString('1/1', $bufferedOutput->fetch());
    }

    #[Test]
    public function it_prints_a_line_for_each_step(): void
    {
        $bufferedOutput = new BufferedOutput;
        $updateProgress = new UpdateProgress;
        $updateProgress->attach(new OutputStyle(new ArrayInput([]), $bufferedOutput));

        $updateProgress->step('Importing the postal codes');

        $this->assertStringContainsString('→ Importing the postal codes', $bufferedOutput->fetch());
    }

    #[Test]
    public function it_stays_silent_without_a_console_output(): void
    {
        $updateProgress = new UpdateProgress;

        $updateProgress->step('Importing');
        $updateProgress->start('Computing', 3);
        $updateProgress->advance();
        $updateProgress->finish();

        $this->addToAssertionCount(1);
    }
}
