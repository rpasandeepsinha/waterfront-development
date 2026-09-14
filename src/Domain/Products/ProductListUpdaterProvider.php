<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Waterfront\Apps\API\Atlantis\Resources\Products\ProductListResourceFactory;
use Waterfront\Domain\Pricing\Services\PriceExperimentService;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\HostingProductCompositionRepository;
use Waterfront\Domain\Products\Repositories\ProductPromotionsRepository;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Support\Providers\BaseProvider;

class ProductListUpdaterProvider extends BaseProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(
            function (): ProductListUpdater {
                $storage = ! App::runningUnitTests() ? Storage::disk('products') : Storage::fake();

                return new ProductListUpdater(
                    self::resolve(PriceResolver::class),
                    $storage,
                    self::resolve(ProductListResourceFactory::class),
                    self::resolve(ProductPromotionsRepository::class),
                    self::resolve(HostingProductCompositionRepository::class),
                    self::resolve(ProductRepository::class),
                    self::resolve(PriceExperimentService::class),
                );
            },
        );
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            ProductListUpdater::class,
        ];
    }
}
