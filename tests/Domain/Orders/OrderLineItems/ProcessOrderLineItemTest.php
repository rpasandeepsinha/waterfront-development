<?php

declare(strict_types=1);

namespace Tests\Domain\Orders\OrderLineItems;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\OrderLinePriceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\DirectAdmin\Mailer\MailDirectAdminDetails;
use Waterfront\Domain\Orders\Enums\OrderLineItemStatus;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;

#[CoversClass(SubscriptionService::class)]
class ProcessOrderLineItemTest extends IntegrationTestCase
{
    #[Test]
    public function createSubscriptionFromOrderLineItemWithoutBackends(): void
    {
        $orderLineItem = $this->buildBasicOrderLineItem();

        $product = $orderLineItem->product;

        $subscriptionService = self::resolve(SubscriptionService::class);

        $subscription = $subscriptionService->createSubscriptionFromOrderLineItem($orderLineItem, false);

        self::assertSame($orderLineItem->status->value, $subscription->technical_status);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $subscription->administrative_status);
        self::assertSame($orderLineItem->net_price, $subscription->net_price);
        self::assertSame($orderLineItem->contract_period, $subscription->contract_period);
        self::assertSame($orderLineItem->billing_period, $subscription->billing_period);
        self::assertSame($orderLineItem->domain, $subscription->domain);

        self::assertNull($subscription->resellerHostingDeployment);

        $invoices = $subscription->invoices;

        self::assertCount(0, $invoices);

        self::assertInstanceOf(Product::class, $product);
    }

    #[Test]
    public function createSubscriptionFromOrderLineItemWithBackends(): void
    {
        new TemplateFactory()->createMany([
            [
                'slug' => MailDirectAdminDetails::getTemplateSlug(),
            ],
        ]);
        $orderLineItem = $this->buildBasicOrderLineItem();

        $product = $orderLineItem->product;

        $subscriptionService = self::resolve(SubscriptionService::class);

        $subscription = $subscriptionService->createSubscriptionFromOrderLineItem($orderLineItem, true);

        self::assertSame($orderLineItem->status->value, $subscription->technical_status);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $subscription->administrative_status);
        self::assertSame($orderLineItem->net_price, $subscription->net_price);
        self::assertSame($orderLineItem->contract_period, $subscription->contract_period);
        self::assertSame($orderLineItem->billing_period, $subscription->billing_period);
        self::assertSame($orderLineItem->domain, $subscription->domain);

        self::assertInstanceOf(ResellerHostingDeployment::class, $subscription->resellerHostingDeployment);

        $invoices = $subscription->invoices;

        self::assertCount(0, $invoices);

        self::assertInstanceOf(Product::class, $product);
    }

    private function buildBasicOrderLineItem(): OrderLineItem
    {
        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);
        new ServerFactory()
            ->directadmin()
            ->createOne([
                'type' => ServerType::DIRECTADMIN,
            ]);

        $customer = new CustomerFactory()->createOne();

        $resellerProductGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::RESELLER_HOSTING,
            'name' => 'Reseller Hosting',
        ]);

        $resellerProduct = new ProductFactory()->createOne([
            'product_group_id' => $resellerProductGroup->id,
            'name' => 'Reseller Brons',
            'slug' => 'hosting_reseller_brons',
        ]);

        $regularPrice = 25;
        $period = 12;
        new ProductPriceComponentFactory()
            ->for($resellerProduct)
            ->registration()
            ->createOne(['price' => $regularPrice]);

        new ProductSpecFactory()->for($resellerProduct)->createOne([
            'name' => 'connections',
            'value' => 10,
        ]);

        $order = new OrderFactory()->for($customer)->createOne();
        $orderLineItem = new OrderLineItemFactory()->makeOne([
            'product_uuid' => $resellerProduct->uuid,
            'subscription_uuid' => null,
            'gross_price' => $regularPrice,
            'product_name' => $resellerProduct->name,
            'billing_period' => $period,
            'contract_period' => $period,
            'net_price' => $regularPrice,
            'status' => OrderLineItemStatus::REGISTRATION,
        ]);

        $orderLineItem->order()->associate($order);

        $orderLineItem->save();
        $order->save();
        new OrderLinePriceFactory()->createOne(['order_line_item_id' => $orderLineItem->id]);

        return $orderLineItem;
    }
}
