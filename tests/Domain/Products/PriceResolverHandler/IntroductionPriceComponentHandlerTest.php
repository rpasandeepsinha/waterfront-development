<?php

declare(strict_types=1);

namespace Tests\Domain\Products\PriceResolverHandler;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductIntroductionDiscountsFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Pricing\DTO\PriceComponents\IntroductionPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\ProductPrice\PriceResolverHandler\IntroDiscountPricePriceHandler;

#[CoversClass(IntroDiscountPricePriceHandler::class)]
class IntroductionPriceComponentHandlerTest extends IntegrationTestCase
{
    #[Test]
    public function correctlyAddsIntroductionPriceComponents(): void
    {
        $group = new ProductGroupFactory()->createOne();
        $product1 = new ProductFactory()->for($group)->createOne();
        $product2 = new ProductFactory()->for($group)->createOne();
        $product3 = new ProductFactory()->for($group)->createOne();

        new ProductPriceComponentFactory()->createMany([
            ['product_id' => $product1->id, 'type' => PriceComponentType::REGISTRATION, 'price' => 654],
            ['product_id' => $product2->id, 'type' => PriceComponentType::REGISTRATION, 'price' => 654],
            ['product_id' => $product2->id, 'type' => PriceComponentType::INTRODUCTION, 'price' => 123],
            ['product_id' => $product3->id, 'type' => PriceComponentType::REGISTRATION, 'price' => 654],
        ]);

        new ProductIntroductionDiscountsFactory()->createOne([
            'product_id' => $product2->id,
            'max_uses_per_customer' => 5,
            'contract_period' => 12,
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

        $prices = self::resolve(IntroDiscountPricePriceHandler::class)->handle($prices, null);

        $product1Price = $prices->where('productId', $product1->id)->firstOrFail();
        self::assertCount(0, $product1Price->possiblePriceComponents);

        $product2Price = $prices->where('productId', $product2->id)->firstOrFail();
        self::assertCount(1, $product2Price->possiblePriceComponents);
        self::assertInstanceOf(IntroductionPriceComponent::class, $product2Price->possiblePriceComponents[0]);
        self::assertSame(123, $product2Price->possiblePriceComponents[0]->newPrice);

        $product3Price = $prices->where('productId', $product3->id)->firstOrFail();
        self::assertCount(0, $product3Price->possiblePriceComponents);
    }

    #[Test]
    public function multipleIntroductionDiscountsForTheSameProductDoNotGroupDiscounts(): void
    {
        $group = new ProductGroupFactory()->createOne();
        $product1 = new ProductFactory()->for($group)->createOne();
        $product2 = new ProductFactory()->for($group)->createOne();
        $product3 = new ProductFactory()->for($group)->createOne();

        new ProductPriceComponentFactory()->createMany([
            ['product_id' => $product1->id, 'type' => PriceComponentType::REGISTRATION, 'price' => 654],
            ['product_id' => $product2->id, 'type' => PriceComponentType::REGISTRATION, 'price' => 654],
            ['product_id' => $product2->id, 'type' => PriceComponentType::INTRODUCTION, 'price' => 123],
            ['product_id' => $product3->id, 'type' => PriceComponentType::REGISTRATION, 'price' => 654],
            ['product_id' => $product3->id, 'type' => PriceComponentType::INTRODUCTION, 'price' => 234],
        ]);

        new ProductIntroductionDiscountsFactory()->createOne([
            'product_id' => $product2->id,
            'max_uses_per_customer' => 5,
            'contract_period' => 12,
        ]);
        new ProductIntroductionDiscountsFactory()->createOne([
            'product_id' => $product3->id,
            'max_uses_per_customer' => 5,
            'contract_period' => 12,
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

        $prices = self::resolve(IntroDiscountPricePriceHandler::class)->handle($prices, null);

        $product1Price = $prices->where('productId', $product1->id)->firstOrFail();
        self::assertCount(0, $product1Price->possiblePriceComponents);

        $product2Price = $prices->where('productId', $product2->id)->firstOrFail();
        self::assertCount(1, $product2Price->possiblePriceComponents);
        self::assertInstanceOf(IntroductionPriceComponent::class, $product2Price->possiblePriceComponents[0]);
        self::assertSame(123, $product2Price->possiblePriceComponents[0]->newPrice);

        $product3Price = $prices->where('productId', $product3->id)->firstOrFail();
        self::assertCount(1, $product3Price->possiblePriceComponents);
        self::assertInstanceOf(IntroductionPriceComponent::class, $product3Price->possiblePriceComponents[0]);
        self::assertSame(234, $product3Price->possiblePriceComponents[0]->newPrice);
    }
}
