<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Partners\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class PartnersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerViews();
    }

    private function registerViews(): void
    {
        $viewPath = $this->app->resourcePath('views/modules/partners');

        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $viewPaths = Config::get('view.paths');
        assert(is_array($viewPaths));

        $modulePaths = array_map(
            fn (string $path): string => $path . '/modules/partners',
            array_filter($viewPaths, 'is_string')
        );

        $this->loadViewsFrom(array_merge($modulePaths, [$sourcePath]), 'partners');
    }

    private function registerTranslations(): void
    {
        $langPath = $this->app->resourcePath('lang/modules/partners');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'partners');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'partners');
        }
    }
}
