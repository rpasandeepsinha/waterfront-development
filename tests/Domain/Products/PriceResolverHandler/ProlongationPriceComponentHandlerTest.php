<?php

declare(strict_types=1);

namespace Tests\Domain\Products\PriceResolverHandler;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProlongationPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\ProductPrice\PriceResolverHandler\ProlongationPriceComponentHandler;

#[CoversClass(ProlongationPriceComponentHandler::class)]
class ProlongationPriceComponentHandlerTest extends IntegrationTestCase
{
    #[Test]
    public function addsProlongationPriceComponent(): void
    {
        $handler = new ProlongationPriceComponentHandler();
        $prices = new Collection([
            new Price(
                type: ProductPriceType::REGISTRATION,
                billingPeriod: 12,
                productId: 1,
                productGroupUuid: 'uuid',
                regularPrice: 1234,
                contractPeriod: 12,
                orderable: true,
                is_default: false,
            ),
            new Price(
                type: ProductPriceType::PROLONGATION,
                billingPeriod: 12,
                productId: 1,
                productGroupUuid: 'uuid',
                regularPrice: 789,
                contractPeriod: 12,
                orderable: true,
                is_default: false,
            ),
        ]);

        $prices = $handler->handle($prices);

        $basePrice = $prices->where('type', ProductPriceType::REGISTRATION)->firstOrFail();
        self::assertCount(2, $prices);
        self::assertCount(1, $basePrice->possiblePriceComponents);
        $priceComponent = $basePrice->possiblePriceComponents[0];
        self::assertInstanceOf(ProlongationPriceComponent::class, $priceComponent);
    }

    #[Test]
    public function addsNothingAndDoesntBreakWhenThereIsNoRegistrationPrice(): void
    {
        $handler = new ProlongationPriceComponentHandler();
        $prices = new Collection([
            new Price(
                type: ProductPriceType::PROLONGATION,
                billingPeriod: 12,
                productId: 1,
                productGroupUuid: 'uuid',
                regularPrice: 789,
                contractPeriod: 12,
                orderable: true,
                is_default: false,
            ),
        ]);

        $prices = $handler->handle($prices);

        $price = $prices->where('type', ProductPriceType::PROLONGATION)->firstOrFail();
        self::assertCount(1, $prices);
        self::assertCount(0, $price->possiblePriceComponents);
    }

    #[Test]
    public function createsProlongationPriceWhenOnlyRegistrationExists(): void
    {
        $handler = new ProlongationPriceComponentHandler();
        $prices = new Collection([
            new Price(
                type: ProductPriceType::REGISTRATION,
                billingPeriod: 12,
                productId: 1,
                productGroupUuid: 'uuid',
                regularPrice: 1234,
                contractPeriod: 12,
                orderable: true,
                is_default: false,
            ),
        ]);

        $prices = $handler->handle($prices);

        self::assertCount(2, $prices);
        $prices->where('type', ProductPriceType::PROLONGATION)->firstOrFail();
    }

    #[Test]
    public function attachesProlongationToCorrectBasePrice(): void
    {
        $handler = new ProlongationPriceComponentHandler();
        $prices = new Collection([
            new Price(
                type: ProductPriceType::REGISTRATION,
                billingPeriod: 12,
                productId: 1,
                productGroupUuid: 'uuid',
                regularPrice: 1234,
                contractPeriod: 12,
                orderable: true,
                is_default: false,
            ),
            new Price(
                type: ProductPriceType::PROLONGATION,
                billingPeriod: 1,
                productId: 1,
                productGroupUuid: 'uuid',
                regularPrice: 456,
                contractPeriod: 12,
                orderable: true,
                is_default: false,
            ),
            new Price(
                type: ProductPriceType::PROLONGATION,
                billingPeriod: 12,
                productId: 1,
                productGroupUuid: 'uuid',
                regularPrice: 789,
                contractPeriod: 12,
                orderable: true,
                is_default: false,
            ),
            new Price(
                type: ProductPriceType::REGISTRATION,
                billingPeriod: 1,
                productId: 1,
                productGroupUuid: 'uuid',
                regularPrice: 534,
                contractPeriod: 12,
                orderable: true,
                is_default: false,
            ),
        ]);

        $prices = $handler->handle($prices);

        $basePriceYearlyBilling = $prices
            ->where('type', ProductPriceType::REGISTRATION)
            ->where('contractPeriod', 12)
            ->where('billingPeriod', 12)
            ->firstOrFail();
        $prolongationYearlyBilling = array_find(
            $basePriceYearlyBilling->possiblePriceComponents,
            fn (PriceComponent $priceComponent) => $priceComponent->type === PriceComponentType::PROLONGATION,
        );
        self::assertInstanceOf(ProlongationPriceComponent::class, $prolongationYearlyBilling);
        self::assertSame(789, $prolongationYearlyBilling->newPrice);

        $basePriceMonthlyBilling = $prices
            ->where('type', ProductPriceType::REGISTRATION)
            ->where('contractPeriod', 12)
            ->where('billingPeriod', 1)
            ->firstOrFail();
        $prolongationMonthlyBilling = array_find(
            $basePriceMonthlyBilling->possiblePriceComponents,
            fn (PriceComponent $priceComponent) => $priceComponent->type === PriceComponentType::PROLONGATION,
        );
        self::assertInstanceOf(ProlongationPriceComponent::class, $prolongationMonthlyBilling);
        self::assertSame(456, $prolongationMonthlyBilling->newPrice);
    }
}
