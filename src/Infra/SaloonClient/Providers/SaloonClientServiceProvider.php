<?php

declare(strict_types=1);

namespace Waterfront\Infra\SaloonClient\Providers;

use Illuminate\Support\ServiceProvider;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;

class SaloonClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RetryConfig::class, fn (): RetryConfig => new RetryConfig());
    }
}
