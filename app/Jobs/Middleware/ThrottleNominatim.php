<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

/**
 * Nominatim usage policy: at most one request per second.
 * The job waits for its turn instead of being released, so it also works with the sync queue.
 * It runs on a dedicated queue handled by one single process, so waiting blocks nobody else.
 */
final class ThrottleNominatim
{
    private const string KEY = 'nominatim';

    /**
     * @param Closure(object): mixed $next
     */
    public function handle(object $job, Closure $next): mixed
    {
        while (! RateLimiter::attempt(self::KEY, 1, static fn (): bool => true, 1)) {
            Sleep::usleep(max(RateLimiter::availableIn(self::KEY), 1) * 100_000);
        }

        return $next($job);
    }
}
