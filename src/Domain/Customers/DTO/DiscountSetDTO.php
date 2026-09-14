<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\DTO;

use ArrayIterator;
use Countable;
use Iterator;
use IteratorAggregate;
use Waterfront\Domain\Products\DTO\PriceList;

/**
 * @implements IteratorAggregate<DiscountDTO>
 */
readonly class DiscountSetDTO implements Countable, IteratorAggregate
{
    /**
     * @var DiscountDTO[]
     */
    private array $discounts;

    public function __construct(DiscountDTO ...$discounts)
    {
        $this->discounts = $discounts;
    }

    /**
     * @return Iterator<DiscountDTO>
     */
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->discounts);
    }

    public function count(): int
    {
        return count($this->discounts);
    }

    /**
     * @param array<int, array{slug: string, contract_period: int, billing_period: int, price: int}> $productsDiscounts
     */
    public static function fromArray(array $productsDiscounts, PriceList $priceList): self
    {
        $discountSet = [];

        foreach ($productsDiscounts as $productsDiscount) {
            $baseProductPrice = $priceList->getProductPrice(
                $productsDiscount['slug'],
                $productsDiscount['contract_period'],
                $productsDiscount['billing_period'],
            );

            $discountSet[] = new DiscountDTO(
                $productsDiscount['price'],
                $productsDiscount['contract_period'],
                $productsDiscount['billing_period'],
                $baseProductPrice,
            );
        }

        return new self(...$discountSet);
    }
}
