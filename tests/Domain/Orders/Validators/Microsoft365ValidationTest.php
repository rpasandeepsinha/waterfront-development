<?php

declare(strict_types=1);

namespace Tests\Domain\Orders\Validators;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Cart\Validators\CartValidatorFactory;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;

#[CoversClass(CartValidatorFactory::class)]
class Microsoft365ValidationTest extends IntegrationTestCase
{
    private Customer $customer;

    private Product $product;

    private CartValidatorFactory $validatorFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
        $this->actingAsCustomer($this->customer);

        $productGroupMicrosoft = new ProductGroupFactory()->microsoft365()->createOne();

        $this->product = new ProductFactory()->createOne([
            'slug' => 'microsoft-business-standard',
            'product_group_id' => $productGroupMicrosoft->id,
        ]);

        new ProductSpecFactory()
            ->for($this->product)
            ->createOne([
                'name' => ProductSpecName::MICROSOFT365_ALLOW_COPILOT->value,
                'value' => true,
            ]);

        new ProductFactory()->createOne([
            'slug' => 'microsoft-business-premium',
            'product_group_id' => $productGroupMicrosoft->id,
        ]);

        new ProductFactory()->createOne([
            'slug' => 'microsoft-copilot-for-microsoft-365',
            'product_group_id' => $productGroupMicrosoft->id,
        ]);

        $this->validatorFactory = self::resolve(CartValidatorFactory::class);
    }

    #[Test]
    public function equalWithNonCopilotSpecProduct(): void
    {
        $orderEqualCopilotAndMicrosoftProducts = include __DIR__ . '/data/order_microsoft_copilot_equal_with_no_spec_product.php';
        $validator = $this->validatorFactory->make($orderEqualCopilotAndMicrosoftProducts, []);
        self::assertTrue($validator->passes());
    }

    #[Test]
    public function unequalCopilotAndMicrosoftProducts(): void
    {
        $orderUnequalCopilotAndMicrosoftProducts = include __DIR__ . '/data/order_microsoft_copilot_unequal_products.php';
        $validator = $this->validatorFactory->make($orderUnequalCopilotAndMicrosoftProducts, []);
        self::assertFalse($validator->passes());
        self::assertSame('validation.m365.more-copilot-then-products', $validator->messages()->toArray()['subscriptions.microsoft-365'][0]);
    }

    #[Test]
    public function copilotWithProductWithNoSpec(): void
    {
        $orderEqualCopilotAndMicrosoftProducts = include __DIR__ . '/data/order_microsoft_copilot_only_product_with_no_spec.php';
        $validator = $this->validatorFactory->make($orderEqualCopilotAndMicrosoftProducts, []);
        self::assertFalse($validator->passes());
        self::assertSame('validation.m365.more-copilot-then-products', $validator->messages()->toArray()['subscriptions.microsoft-365'][0]);
    }

    #[Test]
    public function alreadyHasMicrosoftProduct(): void
    {
        new SubscriptionFactory()->state([
            'customer_id' => $this->customer->id,
            'product_uuid' => $this->product->uuid,
        ])->createMany(3);

        $orderEqualCopilotAndMicrosoftProducts = include __DIR__ . '/data/order_microsoft_copilot_only.php';
        $validator = $this->validatorFactory->make($orderEqualCopilotAndMicrosoftProducts, []);
        self::assertTrue($validator->passes());
    }

    #[Test]
    public function noCopilot(): void
    {
        $orderEqualCopilotAndMicrosoftProducts = include __DIR__ . '/data/order_microsoft_no_copilot.php';
        $validator = $this->validatorFactory->make($orderEqualCopilotAndMicrosoftProducts, []);
        self::assertTrue($validator->passes());
    }
}
