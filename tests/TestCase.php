<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\PendingCommand;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    /**
     * The base path is not inferred from the Composer loader: Rector registers its own one first.
     */
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Runs an Artisan command and returns it ready for the expectations (artisan() may return an exit code).
     *
     * @param array<string, mixed> $parameters
     */
    protected function command(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);

        if (! $pending instanceof PendingCommand) {
            throw new LogicException('The command did not return a pending command.');
        }

        return $pending;
    }
}
