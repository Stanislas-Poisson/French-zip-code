<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\FileDownloader;
use App\Services\Sources\HttpFileDownloader;
use App\Services\UpdateProgress;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FileDownloader::class, HttpFileDownloader::class);
        $this->app->singleton(UpdateProgress::class);
    }
}
