<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Providers;

use Illuminate\Support\ServiceProvider;

class FerryDomainServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/ferry-domain.php',
            'ferry-domain',
        );
    }
}
