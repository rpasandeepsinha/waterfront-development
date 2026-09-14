<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO;

use InvalidArgumentException;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Translations\Models\TranslationKey;

class Price
{
    /**
     * @param non-negative-int      $regularPrice
     * @param ?non-negative-int     $discountPrice
     * @param array<int,int>        $alternativeGrossPrices
     * @param array<PriceComponent> $possiblePriceComponents
     * @param array<PriceComponent> $appliedPriceComponents
     * @param ?non-negative-int     $calculatedPrice
     */
    public function __construct(
        public ProductPriceType $type,
        public int $billingPeriod,
        public int $productId,
        public string $productGroupUuid,
        public int $regularPrice,
        public int $contractPeriod,
        public bool $orderable,
        public bool $is_default,
        public ?TranslationKey $priceExplanation = null,
        public ?int $discountPrice = null,
        public ?int $actionPeriod = null,
        public ?int $actionPeriodPrice = null,
        public array $alternativeGrossPrices = [],
        public array $appliedPriceComponents = [],
        public array $possiblePriceComponents = [],
        public ?int $calculatedPrice = null,
    ) {
    }

    public static function fromPrice(ProductPriceComponent $price): self
    {
        if (! in_array($price->type, [PriceComponentType::PROLONGATION, PriceComponentType::REGISTRATION], true)) {
            throw new InvalidArgumentException(sprintf('Price type "%s" is not valid.', $price->type->value));
        }

        return new Price(
            type: $price->type === PriceComponentType::REGISTRATION
                ? ProductPriceType::REGISTRATION
                : ProductPriceType::PROLONGATION,
            billingPeriod: $price->billing_period,
            productId: $price->product_id,
            productGroupUuid: $price->product->productGroup->uuid,
            regularPrice: $price->price,
            contractPeriod: $price->contract_period,
            orderable: $price->orderable,
            is_default: false,
        );
    }

    /**
     * @return int<0, max>
     */
    public function getNetPrice(): int
    {
        $promotion = array_find(
            $this->possiblePriceComponents,
            fn (PriceComponent $priceComponent): bool => $priceComponent->type === PriceComponentType::PROMOTION,
        );

        return $promotion->newPrice ?? $this->discountPrice ?? $this->regularPrice;
    }

    public function getAlternativeGrossPrice(int $productId): ?int
    {
        return $this->alternativeGrossPrices[$productId] ?? null;
    }
}
