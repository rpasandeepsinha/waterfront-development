<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use Illuminate\Support\Collection;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductAddonCoupling;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\ProductAddonCouplingRepository;
use Waterfront\Domain\Products\Services\PriceService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\GatewayHelper;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Webmozart\Assert\Assert;

readonly class SubscriptionAddonService
{
    public function __construct(
        private PriceResolver $priceResolver,
        private PriceService $priceService,
        private ProductAddonCouplingRepository $productAddonCouplingRepository,
        private GatewayHelper $gatewayHelper,
    ) {
    }

    /**
     * @return Collection<int, Product>
     */
    public function getPotentialAddons(Subscription $subscription): Collection
    {
        // Only parent subscriptions can have add-ons.
        Assert::null($subscription->parent_subscription_id);

        if (! $this->addonsAllowed($subscription)) {
            return new Collection();
        }

        // We need to filter out the add-ons the subscription already has.
        $addonProducts = $this->productAddonCouplingRepository->getOrderableAddonProductsForParentProduct($subscription->product);

        $subscription->loadMissing('children');
        $childSubscriptions = $subscription->children()->where('administrative_status', '!=', AdministrativeStatus::ARCHIVED->value)->get();
        $filterChildSubscriptions = fn (ProductAddonCoupling $coupling) => ! in_array($coupling->addonProduct->uuid, $childSubscriptions->pluck('product_uuid')->toArray(), true);
        $availableAddons = $addonProducts->filter($filterChildSubscriptions);

        /** @var Collection<int, Product> $productCollection */
        $productCollection = new Collection();
        $availableAddons->each(function (ProductAddonCoupling $productAddonCoupling) use ($productCollection) {
            $productCollection->push($productAddonCoupling->addonProduct);
        });

        $productPriceRequests = array_map(fn ($product) => new ProlongationPriceRequest($product), $productCollection->all());
        $priceList = $this->priceResolver->getPriceList(new PriceRequest($productPriceRequests, $subscription->customer));

        $calculatedAddons = new Collection();

        foreach ($productCollection as $addonProduct) {
            $priceForAddonProduct = $priceList->getProductPrice($addonProduct->slug, $subscription->contract_period, $subscription->billing_period);

            $calculatedAddons->push([
                'product' => $addonProduct->toArray(),
                'full_charge' => $priceForAddonProduct->regularPrice,
                'charge' => $this->priceService->calculateProRate(0, $priceForAddonProduct->regularPrice, $subscription->next_billing_date, $subscription->billing_period),
            ]);
        }

        return $calculatedAddons;
    }

    private function addonsAllowed(Subscription $subscription): bool
    {
        if (! $subscription->product->isSitebuilderProduct()) {
            return true;
        }

        return $this->gatewayHelper->hasSitebuilderDeploymentUsingGateway($subscription);
    }
}
