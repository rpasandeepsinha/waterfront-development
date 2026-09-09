<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Repositories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductDiscountFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Repositories\ProductDiscountRepository;

#[CoversClass(ProductDiscountRepository::class)]
class ProductDiscountRepositoryTest extends IntegrationTestCase
{
    #[Test]
    public function getAllUnAssignedProductDiscountsWithVolumeDiscount(): void
    {
        $sut = new ProductDiscountRepository();

        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()
            ->createOne(['slug' => ProductGroupType::VOLUME_DISCOUNT->value]);
        $product = new ProductFactory()
            ->for($productGroup)
            ->createOne();
        new ProductDiscountFactory()
            ->for($product)
            ->createOne();

        $results = $sut->getAllUnassignedProductDiscountsWithVolumeDiscount($customer->id);

        self::assertCount(1, $results);
    }
}
