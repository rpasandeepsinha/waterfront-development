<?php

declare(strict_types=1);

namespace Waterfront\Domain\ManualProvisioning\Providers;

use Illuminate\Support\ServiceProvider;

class ManualProvisioningServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
    }

    private function registerConfig(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'manual-provisioning'
        );
    }
}
