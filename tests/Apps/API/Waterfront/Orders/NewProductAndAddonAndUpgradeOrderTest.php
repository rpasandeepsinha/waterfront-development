<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Orders;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductAddonCouplingFactory;
use Tests\Factories\ProductAllowedChangeFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Orders\Enums\OrderLineItemStatus;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCreated;

#[CoversNothing]
class NewProductAndAddonAndUpgradeOrderTest extends IntegrationTestCase
{
    #[Test]
    public function addonAndUpgradeAndNewProductInOneOrder(): void
    {
        $startDate = CarbonImmutable::create(2023);
        $nextBillingDate = CarbonImmutable::create(2023, 12, 31);
        CarbonImmutable::setTestNow(CarbonImmutable::create(2023, 2, 10));

        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $sslGroup = new ProductGroupFactory()->ssl()->createOne();
        $addonProductGroup = new ProductGroupFactory()->addon()->createOne();

        $addonProduct = new ProductFactory()->for($addonProductGroup)->createOne(['slug' => 'booking']);
        $product = new ProductFactory()->for($productGroup)->createOne(['slug' => 'basic']);
        $sslProduct = new ProductFactory()->for($sslGroup)->createOne(['slug' => 'ssl']);
        $upgradeProduct = new ProductFactory()->for($productGroup)->createOne(['slug' => 'groot']);

        ProductAllowedChangeFactory::new()->upgradeChange()->create([
            'from_product_id' => $product->id,
            'to_product_id' =>  $upgradeProduct->id,
            'display_order' => 1,
        ]);

        new ProductAddonCouplingFactory()->createOne([
            'parent_product_id' => $product->id,
            'addon_product_id' => $addonProduct->id,
        ]);

        new ProductPriceComponentFactory()->for($product)->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 1000]);

        new ProductPriceComponentFactory()->for($addonProduct)->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 1000]);
        new ProductPriceComponentFactory()->for($addonProduct)->prolongation()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 2580]);

        new ProductPriceComponentFactory()->for($upgradeProduct)->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 1000]);
        new ProductPriceComponentFactory()->for($upgradeProduct)->prolongation()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 2580]);

        new ProductPriceComponentFactory()->for($sslProduct)->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 1000]);

        new TemplateFactory()->createOne(['slug' => MailSubscriptionCreated::getTemplateSlug()]);

        $json = (string) file_get_contents(__DIR__ . '/data/order_new_product_addon_and_upgrade_payload.json');

        $customer = new CustomerFactory()->createOne(['payment_type' => 'direct', 'has_direct_debit' => true, 'credit_limit' => 500000]);

        $subscription = new SubscriptionFactory()->for($customer)->for($product)->createOne([
            'uuid' => '24ab093c-d742-4637-b3f4-fc4c4823e70f',
            'billing_period' => 12,
            'contract_period' => 12,
            'gross_price' => 1000,
            'net_price' => 1000,
            'start_date' => $startDate,
            'next_billing_date' => $nextBillingDate,
        ]);
        $dispatcherMock = self::createStub(Dispatcher::class);
        $this->app->bind(Dispatcher::class, fn () => $dispatcherMock);

        /** @var array<int, array<string>> $orderPayload */
        $orderPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(0, OrderLineItem::all());

        $this->withoutExceptionHandling()->actingAsCustomer($customer)->postJson(
            $this->generateRoute('partners.order.order'),
            $orderPayload
        )->assertOk();

        self::assertCount(3, OrderLineItem::all());

        /*
         * We're doing the following for an existing hosting subscription after 41 days;
         * - an upgrade for the product from 'basic' to 'groot'
         * - activating a new addon 'booking'
         *
         * And we're also ordering a new SSL product with it's own period
         *
         * Keeping in mind that the customer has already been invoiced for 12 months on the 'basic' product
         *
         * We're expecting:
         * - order line item with a prorata price based the additional 324 days for the upgraded 'groot' product
         * - order line item with a prorata price based the additional 324 days for the new 'booking' addon product
         *
         * Based on the calculation (for the upgrade) of
         * - Initially paid amount for the original product divided by 365 days times 41 days actually used
         * - plus the full price of the new product divided by 365 days times 324 days remaining for the period
         * - minus the initial amount which is invoiced already (as we don't credit previously invoiced amount)
         */

        // order line item of the addon
        self::assertDatabaseHas(OrderLineItem::class, [
            'product_uuid' => $addonProduct->uuid,
            'subscription_uuid' => null,
            'parent_subscription_uuid' => $subscription->uuid,
            'gross_price' => 888,
            'net_price' => 888,
            'billing_period' => 12,
            'contract_period' => 12,
            'status' => OrderLineItemStatus::REGISTRATION,
        ]);
        // order line item of the upgrade
        self::assertDatabaseHas(OrderLineItem::class, [
            'product_uuid' => $upgradeProduct->uuid,
            'subscription_uuid' => $subscription->uuid,
            'parent_subscription_uuid' => null,
            'gross_price' => 1403,
            'net_price' => 1403,
            'billing_period' => 12,
            'contract_period' => 12,
            'status' => OrderLineItemStatus::PROLONGATION,
        ]);
        // order line item of the new SSL
        self::assertDatabaseHas(OrderLineItem::class, [
            'product_uuid' => $sslProduct->uuid,
            'gross_price' => 1000,
            'net_price' => 1000,
            'billing_period' => 12,
            'contract_period' => 12,
            'status' => OrderLineItemStatus::REGISTRATION,
        ]);
    }
}
