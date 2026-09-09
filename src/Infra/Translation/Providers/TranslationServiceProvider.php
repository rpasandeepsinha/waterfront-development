<?php

declare(strict_types=1);

namespace Waterfront\Infra\Translation\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Waterfront\Infra\Translation\TranslationUpdater;
use Waterfront\Support\Providers\BaseProvider;

class TranslationServiceProvider extends BaseProvider implements DeferrableProvider
{
    public function register(): void
    {
        if (! App::runningUnitTests()) {
            $storage = Storage::disk('translations-s3');

            $this->app->singleton(
                TranslationUpdater::class,
                fn () => new TranslationUpdater(
                    self::resolve(Loader::class),
                    $storage
                )
            );
        }
    }

    /** @return array<int, string> */
    public function provides(): array
    {
        return [
            TranslationUpdater::class,
        ];
    }
}
