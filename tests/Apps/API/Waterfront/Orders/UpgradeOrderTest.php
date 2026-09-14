<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Orders;

use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductAddonCouplingFactory;
use Tests\Factories\ProductAllowedChangeFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCreated;

#[CoversNothing]
class UpgradeOrderTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private Product $basicProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $addonGroup = new ProductGroupFactory()->addon()->createOne();
        $addonProduct = new ProductFactory()->for($addonGroup)->createOne([
            'slug' => 'booking',
        ]);

        $upgradeProduct = new ProductFactory()->for($hostingGroup)->createOne([
            'slug' => 'groot',
        ]);

        $this->basicProduct = new ProductFactory()->for($hostingGroup)->createOne([
            'slug' => 'basic',
        ]);

        new ProductAllowedChangeFactory()
            ->upgradeChange()
            ->createOne([
                'from_product_id' => $this->basicProduct->id,
                'to_product_id' => $upgradeProduct->id,
            ]);

        new ProductAddonCouplingFactory()->createOne([
            'parent_product_id' => $upgradeProduct->id,
            'addon_product_id' => $addonProduct->id,
        ]);

        new ProductPriceComponentFactory()
            ->for($this->basicProduct)
            ->registration()
            ->createOne([
                'price' => 120,
                'billing_period' => 12,
                'contract_period' => 12,
            ]);

        new ProductPriceComponentFactory()
            ->for($this->basicProduct)
            ->prolongation()
            ->createOne([
                'price' => 120,
                'billing_period' => 12,
                'contract_period' => 12,
            ]);

        new ProductPriceComponentFactory()
            ->for($upgradeProduct)
            ->registration()
            ->createOne(['price' => 120]);

        new ProductPriceComponentFactory()
            ->for($upgradeProduct)
            ->prolongation()
            ->createOne([
                'price' => 120,
                'billing_period' => 12,
                'contract_period' => 12,
            ]);
        new ProductPriceComponentFactory()
            ->for($addonProduct)
            ->registration()
            ->createOne([
                'price' => 120,
                'billing_period' => 12,
                'contract_period' => 12,
            ]);

        new ProductPriceComponentFactory()
            ->for($addonProduct)
            ->prolongation()
            ->createOne([
                'price' => 120,
                'billing_period' => 12,
                'contract_period' => 12,
            ]);

        new TemplateFactory()->createOne(['slug' => MailSubscriptionCreated::getTemplateSlug()]);
    }

    #[Test]
    public function orderUpgrade(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/order_upgrade_payload.json');

        $customer = new CustomerFactory()->createOne(['payment_type' => 'direct', 'has_direct_debit' => true]);
        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($this->basicProduct)
            ->createOne([
                'uuid' => '24ab093c-d742-4637-b3f4-fc4c4823e70f',
                'billing_period' => 12,
                'contract_period' => 12,
            ]);
        $order = new OrderFactory()->createOne([
            'customer_id' => $customer->id,
        ]);
        new OrderLineItemFactory()->for($order)->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $dispatcherMock = self::createStub(Dispatcher::class);
        $this->app->bind(Dispatcher::class, fn () => $dispatcherMock);

        /** @var array<int, array<string>> $orderPayload */
        $orderPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->actingAsCustomer($customer)
            ->postJson(
                $this->generateRoute('partners.order.order'),
                $orderPayload,
            )
            ->assertOk();

        self::assertCount(3, OrderLineItem::all());
    }
}
