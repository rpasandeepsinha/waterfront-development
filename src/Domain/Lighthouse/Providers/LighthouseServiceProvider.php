<?php

declare(strict_types=1);

namespace Waterfront\Domain\Lighthouse\Providers;

use Waterfront\Support\Providers\BaseProvider;

class LighthouseServiceProvider extends BaseProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/lighthouse.php',
            'lighthouse',
        );
    }
}
