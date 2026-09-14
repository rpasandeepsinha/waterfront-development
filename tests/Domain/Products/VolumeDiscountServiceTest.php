<?php

declare(strict_types=1);

namespace Tests\Domain\Products;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductDiscountFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\VolumeDiscountService;

#[CoversClass(VolumeDiscountService::class)]
class VolumeDiscountServiceTest extends IntegrationTestCase
{
    #[Test]
    public function attachVolumeDiscountForSubscription(): void
    {
        $customer = new CustomerFactory()->createOne();
        $volumeDiscountProductGroup = new ProductGroupFactory()->volumeDiscount()->createOne();
        $volumeDiscountProduct = new ProductFactory()->for($volumeDiscountProductGroup)->createOne();
        $volumeDiscount = new ProductDiscountFactory()->for($volumeDiscountProduct)->createOne();
        new ProductPriceComponentFactory()
            ->for($volumeDiscountProduct)
            ->registration()
            ->createOne();

        $volumeDiscountService = self::resolve(VolumeDiscountService::class);
        $volumeDiscountService->attach(
            $customer,
            $volumeDiscount,
            $volumeDiscountProduct,
            12,
        );

        self::assertCount(1, $volumeDiscount->customers);
        self::assertSame(1, $customer->subscriptions()->count());
    }

    #[Test]
    public function detachDiscountForSubscriptionDetachesVolumeDiscount(): void
    {
        $customer = new CustomerFactory()->createOne();
        $volumeDiscountProductGroup = new ProductGroupFactory()->volumeDiscount()->createOne();
        $volumeDiscountProduct = new ProductFactory()->for($volumeDiscountProductGroup)->createOne();
        $volumeDiscount = new ProductDiscountFactory()->for($volumeDiscountProduct)->createOne();

        $volumeDiscount->customers()->attach($customer);

        self::assertCount(1, $volumeDiscount->customers);

        $service = self::resolve(VolumeDiscountService::class);
        $service->detach($customer, $volumeDiscountProduct);

        $volumeDiscount->refresh();

        self::assertCount(0, $volumeDiscount->customers);
    }

    #[Test]
    public function detachDiscountForSubscriptionDoesNotDetachOtherVolumeDiscounts(): void
    {
        $customer = new CustomerFactory()->createOne();
        $volumeDiscountProductGroup = new ProductGroupFactory()->volumeDiscount()->createOne();
        $volumeDiscountProduct = new ProductFactory()->for($volumeDiscountProductGroup)->createOne();
        $otherVolumeDiscountProduct = new ProductFactory()->for($volumeDiscountProductGroup)->createOne();
        $volumeDiscount = new ProductDiscountFactory()->for($volumeDiscountProduct)->createOne();

        $volumeDiscount->customers()->attach($customer);

        self::assertCount(1, $volumeDiscount->customers);

        $service = self::resolve(VolumeDiscountService::class);
        $service->detach($customer, $otherVolumeDiscountProduct);

        $volumeDiscount->refresh();

        self::assertCount(1, $volumeDiscount->customers);
    }
}
