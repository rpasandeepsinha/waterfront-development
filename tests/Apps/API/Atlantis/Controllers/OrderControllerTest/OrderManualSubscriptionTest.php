<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers\OrderControllerTest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;

#[CoversClass(OrderController::class)]
class OrderManualSubscriptionTest extends IntegrationTestCase
{
    private Customer $customer;

    private Product $product;

    /** @var array<mixed, mixed> * */
    private array $orderData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $productGroup = new ProductGroupFactory()
            ->manualSubscription()
            ->createOne([
                'slug' => ProductGroupType::MANUAL_SUBSCRIPTION,
            ]);

        $this->product = new ProductFactory()->createOne([
            'slug' => 'manual-testproduct',
            'name' => 'ManualTestProduct',
            'product_group_id' => $productGroup->id,
        ]);

        new ProductPriceComponentFactory()
            ->registration()
            ->createOne([
                'product_id' => $this->product->id,
                'price' => 96,
            ]);

        $this->orderData = (array) json_decode(
            (string) file_get_contents(__DIR__ . '/data/order_payload_manual_subscription.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    #[Test]
    public function orderSuccess(): void
    {
        $response = $this->actingAsCustomer($this->customer)->json(
            'post',
            $this->generateRoute('partners.order.order'),
            $this->orderData,
        );

        $response->assertOk();
        $response->assertJsonFragment([
            'status' => 'ok',
        ]);

        self::assertDatabaseHas(
            'order_line_items',
            [
                'product_name' => $this->product->name,
            ],
        );
    }

    #[Test]
    public function orderFailedDueProductMismatch(): void
    {
        /** @phpstan-ignore-next-line */
        $this->orderData['subscriptions']['manual-subscription'][0]['slug'] = 'wrong-product';

        $response = $this->actingAsCustomer($this->customer)->json(
            'post',
            $this->generateRoute('partners.order.order'),
            $this->orderData,
        );

        $response->assertUnprocessable();

        self::assertDatabaseMissing(
            'order_line_items',
            [
                'product_name' => $this->product->name,
            ],
        );
    }

    #[Test]
    public function combinationOrderSuccess(): void
    {
        new ServerFactory()->directadmin()->createOne();
        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);
        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();

        $hostingProduct = new ProductFactory()->createOne([
            'slug' => 'hosting_premium',
            'name' => 'premium',
            'product_group_id' => $hostingGroup->id,
        ]);

        new ProductPriceComponentFactory()
            ->for($hostingProduct)
            ->registration()
            ->createOne(['price' => 96]);

        $this->orderData = (array) json_decode(
            (string) file_get_contents(__DIR__ . '/data/order_payload_combination_subscriptions.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $response = $this->actingAsCustomer($this->customer)->json(
            'post',
            $this->generateRoute('partners.order.order'),
            $this->orderData,
        );

        $response->assertOk();
        $response->assertJsonFragment([
            'status' => 'ok',
        ]);

        self::assertDatabaseHas(
            'order_line_items',
            [
                'product_name' => $this->product->name,
            ],
        );

        self::assertDatabaseHas(
            'order_line_items',
            [
                'product_name' => $hostingProduct->name,
            ],
        );
    }
}
