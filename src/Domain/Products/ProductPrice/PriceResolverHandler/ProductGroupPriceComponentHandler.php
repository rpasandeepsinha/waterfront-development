<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\ProductPrice\PriceResolverHandler;

use Illuminate\Support\Collection;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProductGroupPriceComponent;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\ProductPriceRequest;
use Waterfront\Domain\Products\Exceptions\PriceResolvingException;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Repositories\DiscountRepository;

readonly class ProductGroupPriceComponentHandler
{
    public function __construct(
        private DiscountRepository $discountRepository,
    ) {
    }

    /**
     * Some customers have a permanent discount on a product group. For example: "10% off on all hosting".
     * In such a case, we apply that discount on all prices for which it is relevant, and return the updated
     * list of prices.
     *
     * @param Collection<int, Price>          $prices
     * @param array<int, ProductPriceRequest> $productMap
     *
     * @return Collection<int, Price>
     */
    public function handle(Collection $prices, array $productMap, Customer $customer): Collection
    {
        if (! $this->discountRepository->hasGroupDiscounts($customer)) {
            return $prices;
        }

        /**
         * Map with discounts.
         * Key: Product group slug
         * Value: Discount percentage (as int, 12 would be 12%).
         *
         * @var array<string, int> $groupDiscounts
         */
        $groupDiscounts = $customer->productGroups->mapWithKeys(function (ProductGroup $group, int $key): array {
            /** @var float|string|null $discount */
            $discount = $group->pivot?->getAttribute('discount');
            if ($discount === null) {
                throw new PriceResolvingException("ProductGroup does not have expected pivot value 'discount' group={$group->slug->value}");
            }

            return [$group->slug->value => (float) $discount];
        })->toArray();

        return $prices->map(function (Price $price) use ($productMap, $groupDiscounts): Price {
            /** @var ProductPriceRequest|null $product */
            $product = $productMap[$price->productId] ?? null;
            $group = $product?->product->productGroup->slug->value;

            if ($group === null || ! array_key_exists($group, $groupDiscounts)) {
                // No group discounts are available for this product.
                return $price;
            }

            $price->possiblePriceComponents[] = new ProductGroupPriceComponent(null, $groupDiscounts[$group], null);

            return $price;
        });
    }
}
