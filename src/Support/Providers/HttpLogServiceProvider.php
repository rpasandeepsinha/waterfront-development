<?php

declare(strict_types=1);

namespace Waterfront\Support\Providers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskerInterface;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskKeysInterface;
use Waterfront\Infra\Logging\Masker\JsonLogMasker;
use Waterfront\Infra\Logging\Masker\MaskKeys;

class HttpLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(function (Container $app): MaskerInterface {
            $logger = $app->make(LoggerInterface::class);

            return new JsonLogMasker($logger);
        });

        $this->app->singleton(MaskKeysInterface::class, fn () => new MaskKeys());
    }

    public function boot(): void
    {
    }
}
