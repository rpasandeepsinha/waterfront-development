<?php

declare(strict_types=1);

namespace Tests\Domain\Products\PriceResolverHandler;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PromotionPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\ProductPrice\PriceResolverHandler\PromotionPriceComponentHandler;

#[CoversClass(PromotionPriceComponentHandler::class)]
class PromotionPriceComponentHandlerTest extends IntegrationTestCase
{
    private readonly PromotionPriceComponentHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new PromotionPriceComponentHandler();
    }

    #[Test]
    public function correctlyAddsPromotionPriceComponents(): void
    {
        $group = new ProductGroupFactory()->createOne();
        $product1 = new ProductFactory()->for($group)->createOne();
        $product2 = new ProductFactory()->for($group)->createOne();
        $product3 = new ProductFactory()->for($group)->createOne();

        new ProductPriceComponentFactory()->createMany([
            ['product_id' => $product1->id, 'type' => PriceComponentType::PROMOTION, 'price' => 123, 'contract_period' => 12, 'billing_period' => 12, 'orderable' => true, 'starts_at' => CarbonImmutable::yesterday()],
            ['product_id' => $product1->id, 'type' => PriceComponentType::PROMOTION, 'price' => 312, 'contract_period' => 12, 'billing_period' => 12, 'orderable' => true, 'starts_at' => CarbonImmutable::now()],
            ['product_id' => $product1->id, 'type' => PriceComponentType::PROMOTION, 'price' => 890, 'contract_period' => 12, 'billing_period' => 12, 'orderable' => true, 'starts_at' => CarbonImmutable::tomorrow()],
            ['product_id' => $product2->id, 'type' => PriceComponentType::PROMOTION, 'price' => 456, 'contract_period' => 12, 'billing_period' => 12, 'orderable' => true, 'starts_at' => CarbonImmutable::yesterday()],
            ['product_id' => $product2->id, 'type' => PriceComponentType::REGISTRATION, 'price' => 654, 'contract_period' => 12, 'billing_period' => 12, 'orderable' => true, 'starts_at' => CarbonImmutable::now()],
            ['product_id' => $product3->id, 'type' => PriceComponentType::PROMOTION, 'price' => 111, 'contract_period' => 12, 'billing_period' => 12, 'orderable' => true, 'starts_at' => CarbonImmutable::now()->subMonth()],
            ['product_id' => $product3->id, 'type' => PriceComponentType::PROMOTION, 'price' => 222, 'contract_period' => 12, 'billing_period' => 12, 'orderable' => true, 'starts_at' => CarbonImmutable::now()->subDays(3), 'expires_at' => CarbonImmutable::now()->subHour()],
        ]);

        $prices = new Collection([
            new Price(
                type: ProductPriceType::REGISTRATION,
                billingPeriod: 12,
                productId: $product1->id,
                productGroupUuid: 'uuid',
                regularPrice: 1234,
                contractPeriod: 12,
                orderable: true,
                is_default: false
            ),
            new Price(
                type: ProductPriceType::REGISTRATION,
                billingPeriod: 12,
                productId: $product2->id,
                productGroupUuid: 'uuid',
                regularPrice: 789,
                contractPeriod: 12,
                orderable: true,
                is_default: false
            ),

            new Price(
                type: ProductPriceType::REGISTRATION,
                billingPeriod: 12,
                productId: $product3->id,
                productGroupUuid: 'uuid',
                regularPrice: 3423,
                contractPeriod: 12,
                orderable: true,
                is_default: false
            ),
        ]);

        $prices = $this->handler->handle($prices);

        $product1Price = $prices->where('productId', $product1->id)->firstOrFail();
        self::assertCount(1, $product1Price->possiblePriceComponents);
        self::assertInstanceOf(PromotionPriceComponent::class, $product1Price->possiblePriceComponents[0]);
        self::assertSame(312, $product1Price->possiblePriceComponents[0]->newPrice);

        $product2Price = $prices->where('productId', $product2->id)->firstOrFail();
        self::assertCount(1, $product2Price->possiblePriceComponents);
        self::assertInstanceOf(PromotionPriceComponent::class, $product2Price->possiblePriceComponents[0]);
        self::assertSame(456, $product2Price->possiblePriceComponents[0]->newPrice);

        $product3Price = $prices->where('productId', $product3->id)->firstOrFail();
        self::assertCount(1, $product3Price->possiblePriceComponents);
        self::assertInstanceOf(PromotionPriceComponent::class, $product3Price->possiblePriceComponents[0]);
        self::assertSame(111, $product3Price->possiblePriceComponents[0]->newPrice);
    }
}
