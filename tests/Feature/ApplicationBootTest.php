<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ApplicationBootTest extends TestCase
{
    #[Test]
    public function it_boots_the_console_application(): void
    {
        $this->assertSame(0, Artisan::call('list'));
    }
}
