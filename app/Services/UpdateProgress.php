<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Tells the person who runs an update what is going on: a line per step and a progress bar for the long ones.
 * It stays silent until a console output is attached, so updates queued on Horizon print nothing.
 */
final class UpdateProgress
{
    private ?OutputStyle $outputStyle = null;

    private ?ProgressBar $progressBar = null;

    public function advance(): void
    {
        $this->progressBar?->advance();
    }

    public function attach(OutputStyle $outputStyle): void
    {
        $this->outputStyle = $outputStyle;
    }

    /**
     * Ends the progress bar in progress, if any.
     */
    public function finish(): void
    {
        if ($this->progressBar instanceof ProgressBar) {
            $this->progressBar->finish();
            $this->outputStyle?->newLine(2);
        }

        $this->progressBar = null;
    }

    /**
     * Starts a progress bar for a long step made of a known number of units.
     */
    public function start(string $label, int $total): void
    {
        $this->step($label);

        if ($this->outputStyle instanceof OutputStyle) {
            $this->progressBar = $this->outputStyle->createProgressBar($total);
            $this->progressBar->start();
        }
    }

    /**
     * Announces the start of a step.
     */
    public function step(string $label): void
    {
        $this->finish();
        $this->outputStyle?->writeln('<info>→</info> ' . $label);
    }
}
