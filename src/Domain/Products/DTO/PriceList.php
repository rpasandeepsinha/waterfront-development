<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO;

use Deprecated;
use Illuminate\Support\Collection;
use Illuminate\Support\ItemNotFoundException;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Exceptions\PriceResolvingException;

/**
 * @extends  Collection<int, Product>
 */
class PriceList extends Collection
{
    /**
     * @throws PriceResolvingException
     */
    #[Deprecated(
        message: "Don't use this function, it's only here for legacy reasons. You want `getProductPrice()` below.",
    )]
    public function getPrice(
        string $productSlug,
        int $contractPeriod,
        int $billingPeriod,
        ProductPriceType $type,
    ): Price {
        /** @var Product|null $product */
        $product = $this->filter(fn (Product $product) => $product->slug === $productSlug)->first();
        /** @var Price|null $price */
        $price = $product
            ?->prices
            ->filter(
                fn (Price $price) => (
                    $price->contractPeriod === $contractPeriod
                    && $price->billingPeriod === $billingPeriod
                    && $price->type === $type
                ),
            )
            ->first();

        if ($price === null) {
            throw new PriceResolvingException(
                "Cannot resolve price for product: slug={$productSlug} contract_period={$contractPeriod} billing_period={$billingPeriod} type={$type->value}",
            );
        }

        return $price;
    }

    /** @throws ItemNotFoundException */
    public function getProductPrice(string $productSlug, int $contractPeriod, int $billingPeriod): Price
    {
        try {
            $product = $this->where('slug', $productSlug)->firstOrFail();

            return $product
                ->prices
                ->where('contractPeriod', $contractPeriod)
                ->where('billingPeriod', $billingPeriod)
                ->where('type', ProductPriceType::REGISTRATION)
                ->firstOrFail();
        } catch (ItemNotFoundException $e) {
            throw new ItemNotFoundException(
                sprintf(
                    'No base price found for product with slug %s, contract period %d, billing period %d',
                    $productSlug,
                    $contractPeriod,
                    $billingPeriod,
                ),
                $e->getCode(),
                $e,
            );
        }
    }

    /**
     * @return array<Price>
     */
    public function getPrices(): array
    {
        return array_merge(...array_values($this->pluck('prices')->toArray()));
    }

    public function onlyOrderableProducts(): self
    {
        return $this->filter(fn (Product $product) => $product->orderable);
    }

    public function onlyProductsWithPrices(): self
    {
        return $this->filter(fn (Product $product) => $product->prices->isNotEmpty());
    }
}
