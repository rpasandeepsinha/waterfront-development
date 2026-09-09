<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\JsonResponse;
use Waterfront\Apps\API\Compass\Requests\SearchTermRequest;
use Waterfront\Apps\API\Compass\Resources\Search\SearchCustomerResource;
use Waterfront\Apps\API\Compass\Resources\Search\SearchDomainResource;
use Waterfront\Apps\API\Compass\Resources\Search\SearchMigrationCustomerResource;
use Waterfront\Apps\API\Compass\Resources\Search\SearchMigrationSubscriptionResource;
use Waterfront\Apps\API\Compass\Resources\Search\SearchProductResource;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Repositories\CustomerRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

class SearchController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    /**
     * Returns a list of customers matching the search term.
     */
    public function customerSearch(SearchTermRequest $request): string
    {
        $customerCollection = Customer::search($request->searchterm)->get();

        return SearchCustomerResource::collection(
            $customerCollection
        )->toJson();
    }

    /**
     * Returns a list of domain subscriptions matching the search term.
     */
    public function domainSearch(SearchTermRequest $request): string
    {
        $subscriptionCollection = Subscription::query()->whereProductGroupType(ProductGroupType::EXTENSION)
            ->whereLikeDomain($request->searchterm)->get();

        return SearchDomainResource::collection(
            $subscriptionCollection
        )->toJson();
    }

    public function productSearch(SearchTermRequest $request): string
    {
        $products = $this->productRepository->productSearchBasedOnNameOrSlug($request->searchterm);

        return SearchProductResource::collection(
            $products
        )->toJson();
    }

    public function searchMigrationReference(SearchTermRequest $request): JsonResponse
    {
        $customerCollection = $this->customerRepository->findByMigratedCustomerReference($request->searchterm);
        $subscriptionCollection = $this->subscriptionRepository->findByMigratedSubscriptionReference($request->searchterm);

        return new JsonResponse([
            ...SearchMigrationCustomerResource::collection($customerCollection)->resolve(),
            ...SearchMigrationSubscriptionResource::collection($subscriptionCollection)->resolve(),
        ]);
    }
}
