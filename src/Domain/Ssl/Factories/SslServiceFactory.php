<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Factories;

use RuntimeException;
use Waterfront\Domain\Placeholder\Services\SslPlaceholderService;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Ssl\Interfaces\SslDriverInterface;
use Waterfront\Domain\Ssl\Services\SslService as OpenproviderSslService;
use Waterfront\Infra\RtrClient\Services\RtrSslService;

class SslServiceFactory
{
    public function __construct(
        private readonly RtrSslService $rtrSslService,
        private readonly SslPlaceholderService $placeholderSslService,
        private readonly OpenproviderSslService $openproviderSslService,
    ) {
    }

    public function driver(ProviderSlug $driver): SslDriverInterface
    {
        return match ($driver) {
            ProviderSlug::REALTIME_REGISTER => $this->rtrSslService,
            ProviderSlug::OPEN_PROVIDER => $this->openproviderSslService,
            ProviderSlug::PLACEHOLDER => $this->placeholderSslService,
            // Should never happen, if it does blow up everything.
            ProviderSlug::BASEKIT,
            ProviderSlug::ACRONIS,
            ProviderSlug::DIRECTADMIN,
            ProviderSlug::XOLPHIN,
            ProviderSlug::PLESK,
                => throw new RuntimeException(),
        };
    }

    public function defaultDriver(): SslDriverInterface
    {
        return $this->driver($this->defaultProvider()->slug);
    }

    public function resolveProviderByProduct(Product $product): Provider
    {
        return $product->productsSslProvider->sslProvider ?? $this->defaultProvider();
    }

    public function defaultProvider(): Provider
    {
        return Provider::where('type', ProviderType::SSL)->where('default', true)->firstOrFail();
    }
}
