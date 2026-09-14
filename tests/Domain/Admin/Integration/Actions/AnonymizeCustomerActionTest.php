<?php

declare(strict_types=1);

namespace Tests\Domain\Admin\Integration\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use JsonException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerAddressFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\PaymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Exceptions\AnonymizeCustomerException;
use Waterfront\Domain\Admin\Actions\AnonymizeCustomerAction;
use Waterfront\Domain\Admin\Actions\AnonymizeIdentitiesForCustomerAction;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Lighthouse\Actions\GetIdentitiesForCustomerNumberAction;
use Waterfront\Domain\Lighthouse\Actions\RemoveCustomerNumberFromIdentityAction;
use Waterfront\Domain\Lighthouse\Exceptions\ResourceNotFoundException;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Payments\Enums\PaymentStatus;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(AnonymizeCustomerAction::class)]
#[AllowMockObjectsWithoutExpectations]
class AnonymizeCustomerActionTest extends IntegrationTestCase
{
    private AnonymizeIdentitiesForCustomerAction&MockObject $anonymizeIdentitiesForCustomerAction;

    private RemoveCustomerNumberFromIdentityAction&MockObject $removeCustomerNumberFromIdentityAction;

    private Customer $customer;

    private Subscription $subscription;

    private Invoice $invoice;

    private OrderLineItem $orderLineItem;

    private AnonymizeCustomerAction $anonymizeCustomerAction;

    public function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $this->customer = new CustomerFactory()->createOne([
            'first_name' => 'Tony',
            'last_name' => 'Hawk',
        ]);

        new CustomerAddressFactory()->createQuietly([
            'customer_id' => $this->customer->id,
        ]);

        $productGroup = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::EXTENSION,
            'slug' => ProductGroupType::EXTENSION,
        ]);

        $domainProduct = new ProductFactory()->createOne([
            'product_group_id' => $productGroup->id,
            'name' => '.nl extension',
        ]);

        $this->subscription = new SubscriptionFactory()->for($this->customer)->createOneQuietly([
            'technical_status' => TechnicalStatus::OK->value,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'domain' => 'test-domain.nl',
            'product_uuid' => $domainProduct->uuid,
            'start_date' => CarbonImmutable::now(),
            'end_date' => CarbonImmutable::now()->addYear(),
            'contract_period' => 12,
            'next_billing_date' => CarbonImmutable::now()->addYear(),
        ]);

        $order = new OrderFactory()->createOne([
            'customer_id' => $this->customer->id,
        ]);

        $this->orderLineItem = new OrderLineItemFactory()->createOne([
            'order_id' => $order->id,
            'product_uuid' => $domainProduct->uuid,
            'product_name' => $domainProduct->name,
            'subscription_uuid' => $this->subscription->uuid,
        ]);
        $this->invoice = new InvoiceFactory()
            ->for($this->customer)
            ->for($this->subscription)
            ->for($domainProduct)
            ->createOne([
                'gross_price' => 100,
                'net_price' => 100,
                'sent_to_harbor_at' => null,
                'announced_by_harbor_at' => null,
            ]);

        CarbonImmutable::setTestNow();

        $this->anonymizeIdentitiesForCustomerAction = self::createMock(
            AnonymizeIdentitiesForCustomerAction::class,
        );

        $getIdentitiesForCustomerNumberAction = self::createMock(
            GetIdentitiesForCustomerNumberAction::class,
        );
        $this->app->bind(GetIdentitiesForCustomerNumberAction::class, fn () => $getIdentitiesForCustomerNumberAction);

        $this->removeCustomerNumberFromIdentityAction = self::createMock(
            RemoveCustomerNumberFromIdentityAction::class,
        );
        $this->app->bind(
            RemoveCustomerNumberFromIdentityAction::class,
            fn () => $this->removeCustomerNumberFromIdentityAction,
        );

        $this->app->bind(
            AnonymizeIdentitiesForCustomerAction::class,
            fn () => $this->anonymizeIdentitiesForCustomerAction,
        );

        $this->anonymizeCustomerAction = self::resolve(AnonymizeCustomerAction::class);
    }

    #[Test]
    public function activeSubscriptionException(): void
    {
        $this->expectException(AnonymizeCustomerException::class);
        $this->expectExceptionMessageIs("Customer {$this->customer->customer_number} still has active subscriptions");

        $this->anonymizeIdentitiesForCustomerAction->expects(self::never())->method('execute');

        $this->anonymizeCustomerAction->execute($this->customer);

        self::assertDatabaseHas('customers', [
            'customer_number' => $this->customer->customer_number,
            'first_name' => $this->customer->first_name,
        ]);
    }

    /**
     * @throws AnonymizeCustomerException
     * @throws JsonException
     */
    #[Test]
    public function openOrdersIsAllowedForProcessedOrderWithoutPayment(): void
    {
        $customer = new CustomerFactory()->withAddress()->createOne();
        $order = new OrderFactory()->for($customer)->createOne();
        new OrderLineItemFactory()->for($order)->createOne();
        $this->anonymizeIdentitiesForCustomerAction->expects(self::once())->method('execute');

        $this->anonymizeCustomerAction->execute($customer);
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function openOrdersIsNotAllowedForUnprocessedOrderWithoutPayment(): void
    {
        $order = new OrderFactory()->for($this->customer)->createOne();
        new OrderLineItemFactory()->for($order)->createOne([
            'subscription_uuid' => null,
        ]);
        $this->expectException(AnonymizeCustomerException::class);

        $this->anonymizeCustomerAction->execute($this->customer);
    }

    /**
     * @throws JsonException
     * @throws AnonymizeCustomerException
     */
    #[Test]
    public function openOrdersIsAllowedForProcessedOrderWithNoExpiredPayment(): void
    {
        $customer = new CustomerFactory()->withAddress()->createOne();
        $order = new OrderFactory()->for($customer)->createOne();
        new OrderLineItemFactory()->for($order)->createOne();
        new PaymentFactory()
            ->for($customer)
            ->for($order)
            ->createOne();

        $this->anonymizeIdentitiesForCustomerAction->expects(self::once())->method('execute');

        $this->anonymizeCustomerAction->execute($customer);
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function openOrdersIsNotAllowedForUnprocessedOrderWithNoExpiredPayment(): void
    {
        $order = new OrderFactory()->for($this->customer)->createOne();
        new OrderLineItemFactory()->for($order)->createOne([
            'subscription_uuid' => null,
        ]);
        new PaymentFactory()
            ->for($this->customer)
            ->for($order)
            ->createOne();

        $this->expectException(AnonymizeCustomerException::class);

        $this->anonymizeCustomerAction->execute($this->customer);
    }

    /**
     * @throws JsonException
     * @throws AnonymizeCustomerException
     */
    #[Test]
    public function openOrdersIsAllowedForUnprocessedOrderWithExpiredPayment(): void
    {
        $customer = new CustomerFactory()->withAddress()->createOne();
        $order = new OrderFactory()->for($customer)->createOne();
        new OrderLineItemFactory()->for($order)->createOne([
            'subscription_uuid' => null,
        ]);
        new PaymentFactory()
            ->for($customer)
            ->for($order)
            ->createOne([
                'status' => PaymentStatus::EXPIRED,
            ]);

        $this->anonymizeIdentitiesForCustomerAction->expects(self::once())->method('execute');

        $this->anonymizeCustomerAction->execute($customer);
    }

    #[Test]
    public function openInvoiceAmount(): void
    {
        $this->subscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
        $this->subscription->save();

        $this->orderLineItem->subscription_uuid = $this->subscription->uuid;
        $this->orderLineItem->save();

        $this->expectException(AnonymizeCustomerException::class);
        $this->expectExceptionMessageIs("Customer {$this->customer->customer_number} still has unpaid invoices");

        $this->anonymizeIdentitiesForCustomerAction->expects(self::never())->method('execute');

        $this->anonymizeCustomerAction->execute($this->customer);

        self::assertDatabaseHas('customers', [
            'customer_number' => $this->customer->customer_number,
            'first_name' => $this->customer->first_name,
        ]);
    }

    #[Test]
    public function anonymizeCustomerAddress(): void
    {
        $this->subscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
        $this->subscription->save();

        $this->invoice->announced_by_harbor_at = CarbonImmutable::now();
        $this->invoice->save();

        $this->anonymizeIdentitiesForCustomerAction->expects(self::once())->method('execute');

        $this->anonymizeCustomerAction->execute($this->customer);

        $address = $this->customer->address;

        self::assertNotNull($address);

        self::assertSame(
            'anonymized-street',
            $address->street_name,
        );
        self::assertSame(
            '1',
            $address->street_number,
        );
        self::assertSame(
            '1234AB',
            $address->zip_code,
        );
        self::assertSame(
            'anonymized-city',
            $address->city,
        );
        self::assertSame(
            'NL',
            $address->country_code,
        );
    }

    #[Test]
    public function anonymizeCustomer(): void
    {
        $this->subscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
        $this->subscription->save();

        $this->invoice->announced_by_harbor_at = CarbonImmutable::now();
        $this->invoice->save();

        $this->anonymizeIdentitiesForCustomerAction->expects(self::once())->method('execute');

        $this->anonymizeCustomerAction->execute($this->customer);

        self::assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'first_name' => "anonymized-first_name-{$this->customer->customer_number}",
            'last_name' => "anonymized-last_name-{$this->customer->customer_number}",
            'email' => "anonymized.customer.{$this->customer->customer_number}@sandwave.io",
            'organization' => 'anonymized-organisation',
            'department' => 'anonymized-department',
            'phone_country_code' => '31',
            'phone_area_code' => '6',
            'phone_subscriber_number' => '12345678',
            'coc_number' => '1234567890',
            'vat_number' => '1234567890',
        ]);
    }

    #[Test]
    public function anonymizeCustomerHasNoRelatedIdentitiesSoItSkipsTheCustomerNumberDetachPart(): void
    {
        $this->subscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
        $this->subscription->save();

        $this->invoice->announced_by_harbor_at = CarbonImmutable::now();
        $this->invoice->save();

        $this->removeCustomerNumberFromIdentityAction->expects(self::never())->method('execute');

        $this->anonymizeIdentitiesForCustomerAction
            ->expects(self::once())
            ->method('execute')
            ->willThrowException(new ResourceNotFoundException());

        $this->anonymizeCustomerAction->execute($this->customer);

        self::assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'first_name' => "anonymized-first_name-{$this->customer->customer_number}",
            'last_name' => "anonymized-last_name-{$this->customer->customer_number}",
            'email' => "anonymized.customer.{$this->customer->customer_number}@sandwave.io",
            'organization' => 'anonymized-organisation',
            'department' => 'anonymized-department',
            'phone_country_code' => '31',
            'phone_area_code' => '6',
            'phone_subscriber_number' => '12345678',
            'coc_number' => '1234567890',
            'vat_number' => '1234567890',
        ]);
    }

    #[Test]
    public function anonymizationSuccess(): void
    {
        $this->subscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
        $this->subscription->save();

        $this->invoice->announced_by_harbor_at = CarbonImmutable::now();
        $this->invoice->save();

        $this->customer->first_name = 'TriggerHistory';
        $this->customer->update();

        $this->anonymizeIdentitiesForCustomerAction->expects(self::once())->method('execute');

        $this->anonymizeCustomerAction->execute($this->customer);

        self::assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'first_name' => "anonymized-first_name-{$this->customer->customer_number}",
            'last_name' => "anonymized-last_name-{$this->customer->customer_number}",
            'email' => "anonymized.customer.{$this->customer->customer_number}@sandwave.io",
            'organization' => 'anonymized-organisation',
            'department' => 'anonymized-department',
            'phone_country_code' => '31',
            'phone_area_code' => '6',
            'phone_subscriber_number' => '12345678',
            'coc_number' => '1234567890',
            'vat_number' => '1234567890',
        ]);
    }

    #[Test]
    public function alreadyAnonymized(): void
    {
        $this->subscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
        $this->subscription->save();

        $this->invoice->announced_by_harbor_at = CarbonImmutable::now();
        $this->invoice->save();

        $this->customer->anonymized_at = CarbonImmutable::now();
        $this->customer->update();

        $this->anonymizeIdentitiesForCustomerAction->expects(self::never())->method('execute');

        $this->expectException(AnonymizeCustomerException::class);
        $this->expectExceptionMessageIs("Customer {$this->customer->customer_number} is already anonymized");

        $this->anonymizeCustomerAction->execute($this->customer);
    }
}
