<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Orders;

use Illuminate\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductAllowedChangeFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Orders\Enums\OrderLineItemStatus;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;

#[CoversNothing]
class OrderMutationTest extends IntegrationTestCase
{
    #[Test]
    public function orderContractExtension(): void
    {
        $domainProvider = new ProviderFactory()->domainOpenProvider()->createOne();
        $extensionGroup = new ProductGroupFactory()->extension()->createOne();

        $comProduct = new ProductFactory()->for($extensionGroup)->createOne([
            'name' => '.com',
            'slug' => 'extension_com',
        ]);

        new ProductSpecFactory()->for($comProduct)->createOne([
            'name' => 'domain.provider_id',
            'value' => $domainProvider->id,
        ]);

        new ProductPriceComponentFactory()
            ->for($comProduct)
            ->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 120]);
        new ProductPriceComponentFactory()
            ->for($comProduct)
            ->prolongation()
            ->createOne(['billing_period' => 24, 'contract_period' => 24, 'price' => 120]);

        $productAdminFees = new ProductFactory()->administrationFees()->createOne();
        new ProductPriceComponentFactory()
            ->registration()
            ->administrationFee()
            ->createOne(['product_id' => $productAdminFees->id]);

        $json = (string) file_get_contents(__DIR__ . '/data/order_mutation_payload.json');

        $customer = new CustomerFactory()->createOne(['payment_type' => 'credit', 'has_direct_debit' => true]);
        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($comProduct)
            ->createOne([
                'uuid' => 'fda0da0b-4678-4716-ae8b-b30c62139137',
                'billing_period' => 12,
                'contract_period' => 12,
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
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        self::assertCount(1, OrderLineItem::all());

        self::assertDatabaseHas(OrderLineItem::class, [
            'product_uuid' => $comProduct->uuid,
            'subscription_uuid' => $subscription->uuid,
            'gross_price' => 120,
            'net_price' => 120,
            'billing_period' => 24,
            'contract_period' => 24,
            'status' => OrderLineItemStatus::PROLONGATION,
        ]);
    }

    #[Test]
    public function orderDowngrade(): void
    {
        $group = new ProductGroupFactory()->hosting()->createOne();
        $smallProduct = new ProductFactory()->for($group)->createOne(['slug' => 'small']);
        $grootProduct = new ProductFactory()->for($group)->createOne(['slug' => 'groot']);

        new ProductPriceComponentFactory()
            ->for($grootProduct)
            ->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 120]);
        new ProductPriceComponentFactory()
            ->for($grootProduct)
            ->prolongation()
            ->createOne(['billing_period' => 24, 'contract_period' => 24, 'price' => 120]);

        new ProductPriceComponentFactory()
            ->for($smallProduct)
            ->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 120]);
        new ProductPriceComponentFactory()
            ->for($smallProduct)
            ->prolongation()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 120]);

        new ProductAllowedChangeFactory()
            ->downgradeChange()
            ->create(['from_product_id' => $grootProduct, 'to_product_id' => $smallProduct]);

        $json = (string) file_get_contents(__DIR__ . '/data/order_downgrade_payload.json');

        $customer = new CustomerFactory()
            ->withAddress()
            ->createOne(['payment_type' => 'credit', 'has_direct_debit' => true]);
        $sub = new SubscriptionFactory()
            ->for($customer)
            ->for($grootProduct)
            ->createOne([
                'uuid' => 'fda0da0b-4678-4716-ae8b-b30c62139137',
                'billing_period' => 12,
                'contract_period' => 12,
            ]);

        /** @var array<int, array<string>> $orderPayload */
        $orderPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->actingAsCustomer($customer)
            ->postJson(
                $this->generateRoute('partners.order.order'),
                $orderPayload,
            )
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        self::assertCount(1, OrderLineItem::all());
        self::assertDatabaseHas(SubscriptionMutation::class, [
            'subscription_id' => $sub->id,
            'product_id' => $smallProduct->id,
        ]);
    }

    #[Test]
    public function orderDowngradeFailsWhenNoChangePathIsSet(): void
    {
        $group = new ProductGroupFactory()->hosting()->createOne();
        $smallProduct = new ProductFactory()->for($group)->createOne(['slug' => 'small']);
        $grootProduct = new ProductFactory()->for($group)->createOne(['slug' => 'groot']);

        new ProductPriceComponentFactory()
            ->for($grootProduct)
            ->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 120]);
        new ProductPriceComponentFactory()
            ->for($grootProduct)
            ->prolongation()
            ->createOne(['billing_period' => 24, 'contract_period' => 24, 'price' => 120]);

        new ProductPriceComponentFactory()
            ->for($smallProduct)
            ->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 120]);
        new ProductPriceComponentFactory()
            ->for($smallProduct)
            ->prolongation()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 120]);

        $json = (string) file_get_contents(__DIR__ . '/data/order_downgrade_payload.json');

        $customer = new CustomerFactory()
            ->withAddress()
            ->createOne(['payment_type' => 'credit', 'has_direct_debit' => true]);
        new SubscriptionFactory()
            ->for($customer)
            ->for($grootProduct)
            ->createOne([
                'uuid' => 'fda0da0b-4678-4716-ae8b-b30c62139137',
                'billing_period' => 12,
                'contract_period' => 12,
            ]);

        /** @var array<int, array<string>> $orderPayload */
        $orderPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->actingAsCustomer($customer)
            ->postJson(
                $this->generateRoute('partners.order.order'),
                $orderPayload,
            )
            ->assertUnprocessable();

        self::assertCount(0, OrderLineItem::all());
        self::assertCount(0, SubscriptionMutation::all());
    }

    #[Test]
    public function orderDowngradeAndContractExtension(): void
    {
        $domainProvider = new ProviderFactory()->domainOpenProvider()->createOne();
        $extensionGroup = new ProductGroupFactory()->extension()->createOne();

        $comProduct = new ProductFactory()->for($extensionGroup)->createOne([
            'name' => '.com',
            'slug' => 'extension_com',
        ]);

        new ProductSpecFactory()->for($comProduct)->createOne([
            'name' => 'domain.provider_id',
            'value' => $domainProvider->id,
        ]);

        new ProductPriceComponentFactory()
            ->for($comProduct)
            ->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 120]);
        new ProductPriceComponentFactory()
            ->for($comProduct)
            ->prolongation()
            ->createOne(['billing_period' => 24, 'contract_period' => 24, 'price' => 120]);
        $group = new ProductGroupFactory()->hosting()->createOne();
        $smallProduct = new ProductFactory()->for($group)->createOne(['slug' => 'small']);
        $grootProduct = new ProductFactory()->for($group)->createOne(['slug' => 'groot']);

        new ProductPriceComponentFactory()
            ->for($grootProduct)
            ->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 120]);
        new ProductPriceComponentFactory()
            ->for($grootProduct)
            ->prolongation()
            ->createOne(['billing_period' => 24, 'contract_period' => 24, 'price' => 120]);

        new ProductPriceComponentFactory()
            ->for($smallProduct)
            ->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 120]);
        new ProductPriceComponentFactory()
            ->for($smallProduct)
            ->prolongation()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 120]);

        new ProductAllowedChangeFactory()
            ->downgradeChange()
            ->createOne([
                'from_product_id' => $grootProduct->id,
                'to_product_id' => $smallProduct->id,
            ]);

        $productAdminFees = ProductFactory::new()->administrationFees()->createOne();
        new ProductPriceComponentFactory()
            ->administrationFee()
            ->createOne(['product_id' => $productAdminFees->id]);

        $json = (string) file_get_contents(__DIR__ . '/data/order_downgrade_and_contract_extension_payload.json');

        $customer = new CustomerFactory()
            ->withAddress()
            ->createOne(['payment_type' => 'credit', 'has_direct_debit' => true]);
        $hostingSub = new SubscriptionFactory()
            ->for($customer)
            ->for($grootProduct)
            ->createOne([
                'uuid' => 'fda0da0b-4678-4716-ae8b-b30c62139137',
                'domain' => 'grootSub.com',
                'billing_period' => 12,
                'contract_period' => 12,
            ]);

        $extensionSub = new SubscriptionFactory()
            ->for($customer)
            ->for($comProduct)
            ->createOne([
                'uuid' => 'fda0da0b-4678-4716-ae8b-b30c62139138',
                'domain' => 'test.com',
                'billing_period' => 12,
                'contract_period' => 12,
            ]);

        /** @var array<int, array<string>> $orderPayload */
        $orderPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->actingAsCustomer($customer)
            ->postJson(
                $this->generateRoute('partners.order.order'),
                $orderPayload,
            )
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        self::assertCount(2, OrderLineItem::all());

        self::assertDatabaseHas(SubscriptionMutation::class, [
            'subscription_id' => $hostingSub->id,
            'product_id' => $smallProduct->id,
        ]);

        self::assertDatabaseHas(SubscriptionMutation::class, [
            'subscription_id' => $extensionSub->id,
            'product_id' => $comProduct->id,
        ]);
    }
}
