<?php

declare(strict_types=1);

namespace Waterfront\Domain\ResellerHosting\Factories;

use RuntimeException;
use Waterfront\Domain\Placeholder\Services\ResellerHostingPlaceholderService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\ResellerHosting\Interfaces\ResellerHostingServiceInterface;
use Waterfront\Domain\ResellerHosting\Services\DirectAdminResellerHostingService;

class ResellerHostingServiceFactory
{
    public function __construct(
        private readonly DirectAdminResellerHostingService $directAdminResellerHostingService,
        private readonly ResellerHostingPlaceholderService $resellerHostingPlaceholderService,
    ) {
    }

    public function driver(ProviderSlug $driver): ResellerHostingServiceInterface
    {
        return match ($driver) {
            ProviderSlug::DIRECTADMIN => $this->directAdminResellerHostingService,
            ProviderSlug::PLACEHOLDER => $this->resellerHostingPlaceholderService,
            default => throw new RuntimeException(sprintf(
                'Reseller hosting driver %s doesn\'t exist.',
                $driver->value,
            )),
        };
    }

    public function defaultDriver(): ResellerHostingServiceInterface
    {
        $default = Provider::where('type', ProviderType::HOSTING)
            ->where('enabled', true)
            ->where('default', true)
            ->firstOrFail();

        return $this->driver($default->slug);
    }
}
