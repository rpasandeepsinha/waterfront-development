<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Providers;

use Waterfront\Support\Providers\BaseProvider;

class IntelligentCancellationProvider extends BaseProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/intelligent-cancellation.php' => $this->app->configPath('intelligent-cancellation.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/intelligent-cancellation.php',
            'intelligent-cancellation'
        );
    }
}
