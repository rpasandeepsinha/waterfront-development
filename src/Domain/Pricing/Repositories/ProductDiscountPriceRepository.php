<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;

class ProductDiscountPriceRepository
{
    /**
     * @return Collection<int, ProductPriceComponent>
     */
    public function getActiveStaffelPrices(int $productDiscountId): Collection
    {
        $now = CarbonImmutable::now();

        $sql = <<<SQL
        select distinct on (product_price_components.product_id, product_price_components.type, product_price_components.contract_period, product_price_components.billing_period)
            product_price_components.*
        from product_price_components
        join product_discount_prices on product_discount_prices.price_id = product_price_components.id
        where product_discount_prices.product_discount_id = ?
            and product_price_components.starts_at <= ?
            and (product_price_components.expires_at is null or product_price_components.expires_at > ?)
            and product_price_components.type in (?, ?)
        order by product_price_components.product_id, product_price_components.type, product_price_components.contract_period, product_price_components.billing_period, product_price_components.starts_at desc
        SQL;

        return ProductPriceComponent::query()->fromQuery($sql, [
            $productDiscountId,
            $now,
            $now,
            PriceComponentType::REGISTRATION_STAFFEL->value,
            PriceComponentType::PROLONGATION_STAFFEL->value,
        ]);
    }
}
