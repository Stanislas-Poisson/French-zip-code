<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * The project has no web interface: the dashboard is closed, Horizon is used through its console commands.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', static fn (): bool => false);
    }
}
