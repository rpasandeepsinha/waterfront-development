<?php

declare(strict_types=1);

namespace Tests\Domain\Orders\Validators;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Cart\Validators\CartValidatorFactory;
use Waterfront\Domain\Products\Enums\ProductGroupType;

#[CoversClass(CartValidatorFactory::class)]
class VpsOrderValidationTest extends IntegrationTestCase
{
    private CartValidatorFactory $validatorFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();
        $this->actingAsCustomer($customer);

        $this->validatorFactory = self::resolve(CartValidatorFactory::class);
    }

    #[Test]
    public function vpsChildrenValidation(): void
    {
        $this->setUpVpsProducts();

        $orderVpsWithChild = include __DIR__ . '/data/order_vps_with_child.php';
        $validator = $this->validatorFactory->make($orderVpsWithChild, []);
        self::assertFalse($validator->fails());
    }

    #[Test]
    public function vpsChildrenShouldBeCloudstackOsValidation(): void
    {
        $this->setUpVpsProducts();

        $orderVpsWithChild = include __DIR__ . '/data/order_vps_with_invalid_child.php';
        $validator = $this->validatorFactory->make($orderVpsWithChild, []);
        self::assertTrue($validator->fails());
        self::assertArrayHasKey('subscriptions.vps.0.children', $validator->messages()->toArray());
    }

    private function setUpVpsProducts(): void
    {
        $productGroupVps = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::VPS,
            'slug' => ProductGroupType::VPS,
        ]);

        $productGroupVpsOs = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::CLOUDSTACK_OS,
            'slug' => ProductGroupType::CLOUDSTACK_OS,
        ]);

        new ProductFactory()->createOne([
            'product_group_id' => $productGroupVps->id,
            'name' => 'Cloud I',
            'slug' => 'cloud-i',
        ]);

        new ProductFactory()->createOne([
            'product_group_id' => $productGroupVpsOs->id,
            'name' => 'Ubuntu 22.04',
            'slug' => 'ubuntu-2204',
        ]);
    }
}
