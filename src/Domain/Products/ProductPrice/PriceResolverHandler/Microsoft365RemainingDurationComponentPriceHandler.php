<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\ProductPrice\PriceResolverHandler;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365Repository;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProRatePriceComponent;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\ProductPriceRequest;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Infra\Common\DateTimeFormat;

readonly class Microsoft365RemainingDurationComponentPriceHandler
{
    public function __construct(
        private Microsoft365Repository $repository,
    ) {
    }

    /**
     * When a customer owns a Microsoft 365 product license, and wishes to order a new seat on that license, the new seat
     * should be billed pro rata (for the remainder of the license period).
     *
     * @param Collection<int, Price>          $prices
     * @param array<int, ProductPriceRequest> $productMap
     *
     * @return Collection<int, Price>
     */
    public function handle(Collection $prices, array $productMap, Customer $customer): Collection
    {
        if (! $this->containsMicrosoft365Products($productMap)) {
            return $prices;
        }

        if ($this->repository->hasMicrosoft365Subscriptions($customer)) {
            $activeSubscriptionsInfo = $this->repository->getActiveSubscriptionsInfo(
                $customer,
                array_keys($productMap),
            );

            $prices = $prices->map(function (Price $price) use ($activeSubscriptionsInfo): Price {
                /**
                 * Pro rata should only be calculated if you already have at least one instance of the same product,
                 * for the same contract and billing period.
                 * And calculating pro rata for anything other than registration prices doesn't make sense.
                 */
                if (
                    $price->type !== ProductPriceType::REGISTRATION
                    || ($subscriptionInfo = $activeSubscriptionsInfo->get(
                        $price->productId . $price->contractPeriod . $price->billingPeriod,
                    )) === null
                ) {
                    return $price;
                }

                $nextBillingDate = CarbonImmutable::createFromFormat(
                    DateTimeFormat::DATE,
                    $subscriptionInfo->next_billing_date,
                );
                assert($nextBillingDate instanceof CarbonImmutable);

                $price->possiblePriceComponents[] = new ProRatePriceComponent($nextBillingDate);

                return $price;
            });
        }

        return $prices;
    }

    /**
     * @param ProductPriceRequest[] $productMap
     */
    private function containsMicrosoft365Products(array $productMap): bool
    {
        return array_any(
            $productMap,
            fn ($productRequest) => $productRequest->product->productGroup->slug === ProductGroupType::MICROSOFT_365,
        );
    }
}
