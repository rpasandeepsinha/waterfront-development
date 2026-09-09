<?php

declare(strict_types=1);

namespace Tests\Apps\Console\Commands;

use Illuminate\Foundation\Console\ViewCacheCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

#[CoversClass(ViewCacheCommand::class)]
class ArtisanViewCacheTest extends IntegrationTestCase
{
    #[Test]
    public function viewCacheCommand(): void
    {
        $this->artisan(ViewCacheCommand::class)->assertExitCode(0);
    }
}
