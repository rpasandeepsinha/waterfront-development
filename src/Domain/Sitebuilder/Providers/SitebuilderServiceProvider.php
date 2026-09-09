<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Env;
use Illuminate\Support\ServiceProvider;
use Waterfront\Domain\Sitebuilder\Fakers\SitebuilderFaker;
use Waterfront\Domain\Sitebuilder\Services\BasekitFactory;
use Waterfront\Domain\Sitebuilder\Services\BasekitFactoryInterface;
use Waterfront\Domain\Sitebuilder\Services\BaseKitService;

class SitebuilderServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function boot(): void
    {
        $this->registerConfig();
    }

    public function register(): void
    {
        $this->app->bind(BasekitFactoryInterface::class, BasekitFactory::class);

        if ((bool) Env::get('APP_FAKE_SITEBUILDER_SERVICE', false)) {
            $this->app->singleton(BaseKitService::class, fn (): SitebuilderFaker => new SitebuilderFaker());
        }
    }

    /** @return array<int, string> */
    public function provides(): array
    {
        return [
            BasekitFactoryInterface::class,
            BaseKitService::class,
        ];
    }

    private function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => $this->app->configPath('SiteBuilder.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'sitebuilder'
        );
    }
}
