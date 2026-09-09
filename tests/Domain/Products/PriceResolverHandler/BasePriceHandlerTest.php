<?php

declare(strict_types=1);

namespace Tests\Domain\Products\PriceResolverHandler;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\ProductPrice\PriceResolverHandler\BasePriceHandler;

#[CoversClass(BasePriceHandler::class)]
class BasePriceHandlerTest extends IntegrationTestCase
{
    #[Test]
    public function createsRegistrationPriceWhenOnlyProlongationExists(): void
    {
        $handler = new BasePriceHandler();
        $prices = new Collection([
            new Price(
                type: ProductPriceType::PROLONGATION,
                billingPeriod: 12,
                productId: 1,
                productGroupUuid: 'uuid',
                regularPrice: 1234,
                contractPeriod: 12,
                orderable: true,
                is_default: false
            ),
            new Price(
                type: ProductPriceType::REGISTRATION,
                billingPeriod: 1,
                productId: 2,
                productGroupUuid: 'uuid',
                regularPrice: 5678,
                contractPeriod: 12,
                orderable: true,
                is_default: false
            ),
        ]);

        $prices = $handler->handle($prices);

        self::assertCount(3, $prices);

        $price = $prices
            ->where('type', ProductPriceType::REGISTRATION)
            ->where('productId', 1)
            ->firstOrFail();

        self::assertSame(12, $price->contractPeriod);
        self::assertSame(12, $price->billingPeriod);
        self::assertSame(1234, $price->regularPrice);
    }
}
