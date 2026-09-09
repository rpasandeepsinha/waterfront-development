<?php

declare(strict_types=1);

namespace Waterfront\Support\Providers;

use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function authorization(): void
    {
        // Authentication set through the ingress container.
        // No Authentication needed through waterfront.
        Horizon::auth(fn (): bool => true);
    }
}
