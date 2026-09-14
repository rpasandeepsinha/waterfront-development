<?php

declare(strict_types=1);

namespace Waterfront\Domain\Translations\Providers;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Translation\TranslationServiceProvider as ServiceProvider;
use Illuminate\Translation\Translator;
use Waterfront\Domain\Translations\Loaders\TranslationLoader;
use Waterfront\Domain\Translations\Models\TranslationKey;
use Waterfront\Domain\Translations\Models\TranslationLanguage;
use Waterfront\Domain\Translations\Models\TranslationString;
use Waterfront\Domain\Translations\Observers\LanguageObserver;
use Waterfront\Domain\Translations\Observers\TranslationKeyObserver;
use Waterfront\Domain\Translations\Observers\TranslationStringObserver;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class TranslationProvider extends ServiceProvider implements DeferrableProvider
{
    public function boot(): void
    {
        TranslationKey::observe(TranslationKeyObserver::class);
        TranslationString::observe(TranslationStringObserver::class);
        TranslationLanguage::observe(LanguageObserver::class);
    }

    public function register(): void
    {
        $this->app->singleton('translation.loader', fn ($app): Loader => new TranslationLoader(
            $app['files'],
            $this->app->make(CacheManager::class)->store(),
            $this->app->make(ConfigurationInterface::class),
            $app['path.lang'],
        ));

        $this->app->alias('translation.loader', Loader::class);

        $this->app->singleton('translator', static function ($app): Translator {
            $loader = $app['translation.loader'];

            // When registering the translator component, we'll need to set the default
            // locale as well as the fallback locale. So, we'll grab the application
            // configuration so we can easily get both of these values from there.
            $locale = $app['config']['app.locale'];
            $trans = new Translator($loader, $locale);
            $trans->setFallback($app['config']['app.fallback_locale']);

            return $trans;
        });
    }
}
