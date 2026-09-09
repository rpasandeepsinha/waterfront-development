<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\Factories;

use RuntimeException;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Sitebuilder\Interfaces\SitebuilderDriverInterface;
use Waterfront\Domain\Sitebuilder\Services\BaseKitService;

class SitebuilderServiceFactory
{
    public function __construct(private readonly BaseKitService $baseKitService)
    {
    }

    public function driver(?string $driverSlug = null): SitebuilderDriverInterface
    {
        $query = Provider::where([
            'type' => ProviderType::SITEBUILDER,
            'enabled' => true,
        ]);

        if ($driverSlug !== null) {
            $query->where('slug', $driverSlug);
        } else {
            $query->where('default', true);
        }

        $driver = $query->firstOrFail();

        return match ($driver->slug) {
            ProviderSlug::BASEKIT => $this->baseKitService,
            default => throw new RuntimeException(sprintf('Sitebuilder driver %s doesn\'t exist.', $driver->slug->value)),
        };
    }

    public function getDefaultSitebuilderProvider(): Provider
    {
        return Provider::where([
            'type' => ProviderType::SITEBUILDER,
            'default' => true,
            'enabled' => true,
        ])->firstOrFail();
    }
}
