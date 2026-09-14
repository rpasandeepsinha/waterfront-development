<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Waterfront\Apps\API\Atlantis\Resources\Products\ProductListResourceFactory;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Pricing\Services\PriceExperimentService;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\HostingProductCompositionRepository;
use Waterfront\Domain\Products\Repositories\ProductPromotionsRepository;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Infra\Authentication\AuthenticationManager;

class ProductController
{
    private const int TTL = 600;

    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly PriceResolver $priceResolver,
        private readonly ProductListResourceFactory $productListResourceFactory,
        private readonly ProductPromotionsRepository $productPromotionsRepository,
        private readonly HostingProductCompositionRepository $hostingProductCompositionsRepository,
        private readonly ProductRepository $productRepository,
        private readonly PriceExperimentService $priceExperimentService,
    ) {
    }

    /**
     * @throws AuthenticationException
     */
    public function index(): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        /**
         * Let's see if there is an order for the active customer within the timespan of the TTL from the cache.
         */
        $latestOrder = $customer->orders()->latest()->first();
        if (CarbonImmutable::now()->diffInMinutes($latestOrder?->created_at, true) <= (self::TTL / 60)) {
            return $this->getResponse($customer);
        }

        return Cache::remember(
            'price_list_' . $customer->uuid,
            self::TTL,
            fn (): JsonResponse => $this->getResponse($customer),
        );
    }

    private function getResponse(?Customer $customer): JsonResponse
    {
        $products = $this->productRepository->getProductsWithMetaData();
        $productPriceRequests = array_map(fn ($product) => new RegistrationPriceRequest($product), $products->all());
        $prices = $this->priceResolver->getPriceList(new PriceRequest($productPriceRequests, $customer));

        $priceList = $prices->onlyOrderableProducts()->onlyProductsWithPrices();

        $productGroups = ProductGroup::all();

        $productPromotions = $this->productPromotionsRepository->findAllActiveProductPromotions();

        $hostingProductCompositions = $this->hostingProductCompositionsRepository->findAllHostingProductCompositions();

        $experiments = $this->priceExperimentService->getExperiments();

        return $this->productListResourceFactory
            ->makeResource(
                $priceList,
                $productGroups,
                $productPromotions,
                $hostingProductCompositions,
                $experiments,
            )
            ->response();
    }
}
