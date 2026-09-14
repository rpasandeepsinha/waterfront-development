<?php

declare(strict_types=1);

namespace Tests\Domain\Products\PriceResolverHandler;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\CustomerProductDiscountFactory;
use Tests\Factories\ProductDiscountFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProlongationStaffelPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\RegistrationStaffelPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\ProductPrice\PriceResolverHandler\ProductDiscountPriceComponentHandler;
use Waterfront\Domain\Products\VolumeDiscountService;

#[CoversClass(ProductDiscountPriceComponentHandler::class)]
class StaffelPriceComponentHandlerTest extends IntegrationTestCase
{
    private readonly ProductDiscountPriceComponentHandler $handler;

    private readonly VolumeDiscountService $productDiscountService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = self::resolve(ProductDiscountPriceComponentHandler::class);
        $this->productDiscountService = self::resolve(VolumeDiscountService::class);
    }

    #[Test]
    public function correctlyAddsStaffelPriceComponents(): void
    {
        $customer = new CustomerFactory()->createOne();
        $group = new ProductGroupFactory()->createOne();
        $product1 = new ProductFactory()->for($group)->createOne();
        $product2 = new ProductFactory()->for($group)->createOne();
        $product3 = new ProductFactory()->for($group)->createOne();

        $discount1 = new ProductDiscountFactory()->for($product1)->createOne();
        $discount2 = new ProductDiscountFactory()->for($product2)->createOne();
        $discount3 = new ProductDiscountFactory()->for($product3)->createOne();
        new CustomerProductDiscountFactory()
            ->for($customer)
            ->for($discount1)
            ->createOne();
        new CustomerProductDiscountFactory()
            ->for($customer)
            ->for($discount2)
            ->createOne();
        new CustomerProductDiscountFactory()
            ->for($customer)
            ->for($discount3)
            ->createOne();

        $prices = new ProductPriceComponentFactory()->createMany([
            [
                'product_id' => $product1->id,
                'type' => PriceComponentType::REGISTRATION_STAFFEL,
                'price' => 444,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::yesterday(),
            ],
            [
                'product_id' => $product1->id,
                'type' => PriceComponentType::REGISTRATION_STAFFEL,
                'price' => 123,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::now(),
            ],
            [
                'product_id' => $product1->id,
                'type' => PriceComponentType::REGISTRATION_STAFFEL,
                'price' => 777,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::tomorrow(),
            ],
            [
                'product_id' => $product1->id,
                'type' => PriceComponentType::PROLONGATION_STAFFEL,
                'price' => 321,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::now(),
            ],
            [
                'product_id' => $product2->id,
                'type' => PriceComponentType::REGISTRATION_STAFFEL,
                'price' => 333,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::yesterday(),
            ],
            [
                'product_id' => $product2->id,
                'type' => PriceComponentType::REGISTRATION,
                'price' => 654,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::now(),
            ],
            [
                'product_id' => $product3->id,
                'type' => PriceComponentType::PROLONGATION_STAFFEL,
                'price' => 111,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::now()->subMonth(),
            ],
            [
                'product_id' => $product3->id,
                'type' => PriceComponentType::PROLONGATION_STAFFEL,
                'price' => 222,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::now()->subDays(3),
                'expires_at' => CarbonImmutable::now()->subHour(),
            ],
        ]);

        self::assertNotNull($prices[0]);
        self::assertNotNull($prices[1]);
        self::assertNotNull($prices[2]);
        self::assertNotNull($prices[3]);
        self::assertNotNull($prices[4]);
        self::assertNotNull($prices[5]);
        self::assertNotNull($prices[6]);
        self::assertNotNull($prices[7]);

        $this->productDiscountService->attachPrice($discount1, $prices[0]);
        $this->productDiscountService->attachPrice($discount1, $prices[1]);
        $this->productDiscountService->attachPrice($discount1, $prices[3]);
        $this->productDiscountService->attachPrice($discount1, $prices[4]);
        $this->productDiscountService->attachPrice($discount2, $prices[5]);
        $this->productDiscountService->attachPrice($discount2, $prices[6]);
        $this->productDiscountService->attachPrice($discount2, $prices[7]);

        $prices = new Collection([
            new Price(
                type: ProductPriceType::REGISTRATION,
                billingPeriod: 12,
                productId: $product1->id,
                productGroupUuid: $product1->productGroup->uuid,
                regularPrice: 1234,
                contractPeriod: 12,
                orderable: true,
                is_default: false,
            ),
            new Price(
                type: ProductPriceType::REGISTRATION,
                billingPeriod: 12,
                productId: $product2->id,
                productGroupUuid: $product2->productGroup->uuid,
                regularPrice: 789,
                contractPeriod: 12,
                orderable: true,
                is_default: false,
            ),
            new Price(
                type: ProductPriceType::REGISTRATION,
                billingPeriod: 12,
                productId: $product3->id,
                productGroupUuid: $product3->productGroup->uuid,
                regularPrice: 789,
                contractPeriod: 12,
                orderable: true,
                is_default: false,
            ),
        ]);

        $prices = $this->handler->handle($prices, $customer);

        $product1Price = $prices->where('productId', $product1->id)->firstOrFail();
        self::assertCount(2, $product1Price->possiblePriceComponents);
        self::assertInstanceOf(ProlongationStaffelPriceComponent::class, $product1Price->possiblePriceComponents[0]);
        self::assertSame(321, $product1Price->possiblePriceComponents[0]->newPrice);
        self::assertInstanceOf(RegistrationStaffelPriceComponent::class, $product1Price->possiblePriceComponents[1]);
        self::assertSame(123, $product1Price->possiblePriceComponents[1]->newPrice);

        $product2Price = $prices->where('productId', $product2->id)->firstOrFail();
        self::assertCount(1, $product2Price->possiblePriceComponents);
        self::assertInstanceOf(RegistrationStaffelPriceComponent::class, $product2Price->possiblePriceComponents[0]);
        self::assertSame(333, $product2Price->possiblePriceComponents[0]->newPrice);

        $product3Price = $prices->where('productId', $product3->id)->firstOrFail();
        self::assertCount(1, $product3Price->possiblePriceComponents);
        self::assertInstanceOf(ProlongationStaffelPriceComponent::class, $product3Price->possiblePriceComponents[0]);
        self::assertSame(111, $product3Price->possiblePriceComponents[0]->newPrice);
    }

    #[Test]
    public function duplicateStaffelPriceIsDeduplicated(): void
    {
        $customer = new CustomerFactory()->createOne();
        $group = new ProductGroupFactory()->createOne();
        $product1 = new ProductFactory()->for($group)->createOne();

        $discount1 = new ProductDiscountFactory()->for($product1)->createOne();
        $discount2 = new ProductDiscountFactory()->for($product1)->createOne();
        $discount3 = new ProductDiscountFactory()->for($product1)->createOne();
        new CustomerProductDiscountFactory()
            ->for($customer)
            ->for($discount1)
            ->createOne();
        new CustomerProductDiscountFactory()
            ->for($customer)
            ->for($discount2)
            ->createOne();
        new CustomerProductDiscountFactory()
            ->for($customer)
            ->for($discount3)
            ->createOne();

        $prices = new ProductPriceComponentFactory()->createMany([
            [
                'product_id' => $product1->id,
                'type' => PriceComponentType::REGISTRATION_STAFFEL,
                'price' => 444,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::yesterday(),
            ],
            [
                'product_id' => $product1->id,
                'type' => PriceComponentType::REGISTRATION_STAFFEL,
                'price' => 999,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::now(),
            ],
            [
                'product_id' => $product1->id,
                'type' => PriceComponentType::REGISTRATION_STAFFEL,
                'price' => 777,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::tomorrow(),
            ],
            [
                'product_id' => $product1->id,
                'type' => PriceComponentType::PROLONGATION_STAFFEL,
                'price' => 321,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::now(),
            ],
            [
                'product_id' => $product1->id,
                'type' => PriceComponentType::REGISTRATION_STAFFEL,
                'price' => 333,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::yesterday(),
            ],
            [
                'product_id' => $product1->id,
                'type' => PriceComponentType::REGISTRATION,
                'price' => 654,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::now(),
            ],
            [
                'product_id' => $product1->id,
                'type' => PriceComponentType::PROLONGATION_STAFFEL,
                'price' => 111,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::now()->subMonth(),
            ],
            [
                'product_id' => $product1->id,
                'type' => PriceComponentType::PROLONGATION_STAFFEL,
                'price' => 222,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::now()->subDays(3),
                'expires_at' => CarbonImmutable::now()->subHour(),
            ],
            [
                'product_id' => $product1->id,
                'type' => PriceComponentType::REGISTRATION_STAFFEL,
                'price' => 123,
                'contract_period' => 12,
                'billing_period' => 12,
                'orderable' => true,
                'starts_at' => CarbonImmutable::now(),
            ],
        ]);

        self::assertNotNull($prices[0]);
        self::assertNotNull($prices[1]);
        self::assertNotNull($prices[2]);
        self::assertNotNull($prices[3]);
        self::assertNotNull($prices[4]);
        self::assertNotNull($prices[5]);
        self::assertNotNull($prices[6]);
        self::assertNotNull($prices[7]);
        self::assertNotNull($prices[8]);

        $this->productDiscountService->attachPrice($discount1, $prices[0]);
        $this->productDiscountService->attachPrice($discount1, $prices[1]);
        $this->productDiscountService->attachPrice($discount1, $prices[2]);
        $this->productDiscountService->attachPrice($discount1, $prices[3]);
        $this->productDiscountService->attachPrice($discount1, $prices[4]);
        $this->productDiscountService->attachPrice($discount2, $prices[5]);
        $this->productDiscountService->attachPrice($discount2, $prices[6]);
        $this->productDiscountService->attachPrice($discount2, $prices[7]);
        $this->productDiscountService->attachPrice($discount3, $prices[8]);

        $prices = new Collection([
            new Price(
                type: ProductPriceType::REGISTRATION,
                billingPeriod: 12,
                productId: $product1->id,
                productGroupUuid: $product1->productGroup->uuid,
                regularPrice: 1234,
                contractPeriod: 12,
                orderable: true,
                is_default: false,
            ),
        ]);

        $prices = $this->handler->handle($prices, $customer);

        $product1Price = $prices->where('productId', $product1->id)->firstOrFail();

        self::assertCount(2, $product1Price->possiblePriceComponents);
        self::assertInstanceOf(ProlongationStaffelPriceComponent::class, $product1Price->possiblePriceComponents[0]);
        self::assertSame(111, $product1Price->possiblePriceComponents[0]->newPrice);
        self::assertInstanceOf(RegistrationStaffelPriceComponent::class, $product1Price->possiblePriceComponents[1]);
        self::assertSame(123, $product1Price->possiblePriceComponents[1]->newPrice);
    }
}
