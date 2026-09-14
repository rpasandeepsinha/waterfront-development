<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products;

use Illuminate\Contracts\Filesystem\Filesystem;
use Waterfront\Apps\API\Atlantis\Resources\Products\ProductListResourceFactory;
use Waterfront\Domain\Pricing\Services\PriceExperimentService;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\HostingProductCompositionRepository;
use Waterfront\Domain\Products\Repositories\ProductPromotionsRepository;
use Waterfront\Domain\Products\Repositories\ProductRepository;

class ProductListUpdater
{
    public function __construct(
        private readonly PriceResolver $priceResolver,
        private readonly Filesystem $filesystem,
        private readonly ProductListResourceFactory $productListResourceFactory,
        private readonly ProductPromotionsRepository $productPromotionsRepository,
        private readonly HostingProductCompositionRepository $hostingProductCompositionsRepository,
        private readonly ProductRepository $productRepository,
        private readonly PriceExperimentService $priceExperimentService,
    ) {
    }

    public function update(): void
    {
        $products = $this->productRepository->getProductsWithMetaData();
        $productPriceRequests = array_map(fn ($product) => new RegistrationPriceRequest($product), $products->all());
        $priceList = $this->priceResolver->getPriceList(new PriceRequest($productPriceRequests, null));
        $productGroups = ProductGroup::all();

        $productPromotions = $this->productPromotionsRepository->findAllActiveProductPromotions();

        $hostingProductCompositions = $this->hostingProductCompositionsRepository->findAllHostingProductCompositions();

        $experiments = $this->priceExperimentService->getExperiments();

        if ($priceList->count() === 0) {
            return;
        }

        $list = $priceList->onlyOrderableProducts()->onlyProductsWithPrices();
        $json = $this->productListResourceFactory
            ->makeResource(
                $list,
                $productGroups,
                $productPromotions,
                $hostingProductCompositions,
                $experiments,
            )
            ->toJson();

        file_put_contents(__DIR__ . '/bla.json', $json);

        $this->filesystem->put('product-list.json', $json);
    }
}
