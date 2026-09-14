<?php

declare(strict_types=1);

namespace Tests\Domain\Invoices\Integration\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Repositories\MigrationCustomerRepository;
use Waterfront\Domain\Invoices\Actions\CreateInvoiceAndSetNextBillingDateForSubscriptionAction;
use Waterfront\Domain\Invoices\Events\InvoiceCreatedEvent;
use Waterfront\Domain\Invoices\Jobs\DispatchConsolidatedInvoicesForCustomer;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Services\AdministrationFeesManager;
use Waterfront\Domain\Invoices\Services\ComesWithFreeProductInvoiceManager;
use Waterfront\Domain\Invoices\Services\ConsolidatedInvoiceCreator;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Actions\IsSubscriptionPeriodEntirelyInvoicedAction;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(ConsolidatedInvoiceCreator::class)]
#[AllowMockObjectsWithoutExpectations]
class ConsolidatedInvoiceCreatorTest extends IntegrationTestCase
{
    private Product $product;

    private Customer $customer;

    private MockObject&Dispatcher $bus;

    private ProductPriceComponent $prolongationPriceYearly;

    private ProductPriceComponent $registrationPriceYearly;

    private ProductPriceComponent $prolongationPriceMonthly;

    private ProductPriceComponent $registrationPriceMonthly;

    private ConfigurationInterface $configuration;

    private int $renewalDays;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $this->product = new ProductFactory()->for($productGroup)->createOne();

        $this->prolongationPriceYearly = new ProductPriceComponentFactory()
            ->for($this->product)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'price' => 100,
            ]);

        $this->registrationPriceYearly = new ProductPriceComponentFactory()
            ->for($this->product)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'price' => 90,
            ]);

        $this->prolongationPriceMonthly = new ProductPriceComponentFactory()
            ->for($this->product)
            ->prolongation()
            ->createOne([
                'billing_period' => 1,
                'price' => 8,
            ]);

        $this->registrationPriceMonthly = new ProductPriceComponentFactory()
            ->for($this->product)
            ->registration()
            ->createOne([
                'billing_period' => 1,
                'price' => 7,
            ]);

        $this->bus = self::createMock(Dispatcher::class);

        $this->configuration = self::resolve(ConfigurationInterface::class);
        $this->renewalDays = $this->configuration->getAsInteger('constants.invoice-ahead-days');
    }

    public function getInvoiceCreator(): ConsolidatedInvoiceCreator
    {
        return new ConsolidatedInvoiceCreator(
            self::resolve(SubscriptionRepository::class),
            self::resolve(LoggerInterface::class),
            self::resolve(CreateInvoiceAndSetNextBillingDateForSubscriptionAction::class),
            self::resolve(IsSubscriptionPeriodEntirelyInvoicedAction::class),
            $this->bus,
            self::resolve(AdministrationFeesManager::class),
            self::resolve(MigrationCustomerRepository::class),
            $this->configuration,
            self::resolve(ComesWithFreeProductInvoiceManager::class),
        );
    }

    #[Test]
    public function noSubscriptionsFoundWillResultInNoInvoices(): void
    {
        $billingDate = CarbonImmutable::today()->addDays($this->renewalDays);
        $this->bus->expects(self::never())->method('dispatch');
        $this->getInvoiceCreator()->invoiceCustomer(new Customer(), $billingDate);
        self::assertCount(0, Invoice::all());
    }

    /**
     * Note: this works only because in `.env.testing` the `ADMINISTRATION_FEES_DAILY_BILLING_ENABLED` is set to `true`.
     */
    #[Test]
    public function subscriptionForCustomerWithoutMandateWillResultInAdminFeesInvoice(): void
    {
        $customer = CustomerFactory::new()->withAddress()->createOne([
            'has_direct_debit' => false,
        ]);
        $product = ProductFactory::new()->administrationFees()->createOne();
        ProductPriceComponentFactory::new()->administrationFee()->createOne([
            'product_id' => $product->id,
        ]);

        $this->customer = $customer;

        CarbonImmutable::setTestNow($now = CarbonImmutable::now());
        $today = $now::today();
        $billingPeriod = 12;
        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($customer)
            ->for($this->product)
            ->createOne([
                'end_date' => $today,
                'next_billing_date' => $today,
                'net_price' => 30,
                'billing_period' => $billingPeriod,
            ]);

        self::assertCount(0, Invoice::all());
        Event::fake([InvoiceCreatedEvent::class]);
        $billingDate = CarbonImmutable::today()->addDays($this->renewalDays);
        $this->bus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DispatchConsolidatedInvoicesForCustomer::class));

        $this->getInvoiceCreator()->invoiceCustomer($customer, $billingDate);

        $subscription->refresh();

        $newInvoice = Invoice::query()->latest()->first();
        self::assertInstanceOf(Invoice::class, $newInvoice);

        self::assertCount(2, Invoice::all());
        self::assertSame($today->addMonths($billingPeriod)->timestamp, $subscription->next_billing_date->timestamp);
        Event::assertNotDispatched(InvoiceCreatedEvent::class);

        self::assertDatabaseHas('invoices', [
            'customer_id' => $this->customer->id,
            'subscription_id' => $subscription->id,
            'start_date' => $today,
            'end_date' => $subscription->next_billing_date,
        ]);
        self::assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'subscription_id' => null,
            'start_date' => $today,
            'end_date' => $today,
            'product_id' => $product->id,
            'title' => $product->name,
        ]);
    }

    #[Test]
    public function subscriptionWhichComesWithFreeProductWillBeInvoicedForBoth(): void
    {
        $productGroupAddon = new ProductGroupFactory()->addon()->createOne();
        $productServicePlus = new ProductFactory()->for($productGroupAddon)->createOne();

        $prolongationPriceYearly = new ProductPriceComponentFactory()
            ->for($productServicePlus)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'price' => 100,
            ]);

        new ProductPriceComponentFactory()
            ->for($productServicePlus)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'price' => 90,
            ]);

        $productSpecComesWithFreeProduct = ProductSpecFactory::new()->createOne(
            [
                'name' => ProductSpecName::COMES_WITH_FREE_PRODUCT_SLUG,
                'value' => $productServicePlus->slug,
                'product_id' => $this->product->id,
            ],
        );

        $this->product->productSpecs()->save($productSpecComesWithFreeProduct);

        $today = CarbonImmutable::today();
        $billingPeriod = 12;
        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'end_date' => $today,
                'next_billing_date' => $today,
                'net_price' => 0,
                'billing_period' => $billingPeriod,
            ]);

        self::assertCount(0, Invoice::all());
        Event::fake([InvoiceCreatedEvent::class]);
        $billingDate = CarbonImmutable::today()->addDays($this->renewalDays);
        $this->bus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DispatchConsolidatedInvoicesForCustomer::class));

        $this->getInvoiceCreator()->invoiceCustomer($this->customer, $billingDate);

        $subscription->refresh();

        $subscriptionProductInvoice = Invoice::query()->where(['product_id' => $this->product->id])->first();
        $comesWithFreeProductInvoice = Invoice::query()->where(['product_id' => $productServicePlus->id])->first();

        $translator = self::resolve(TranslatorInterface::class);
        $expectedDescription = sprintf(
            '%s %s',
            $productServicePlus->name,
            $translator->translate('invoice.description.for') . " {$subscription->domain}",
        );

        self::assertInstanceOf(Invoice::class, $subscriptionProductInvoice);
        self::assertInstanceOf(Invoice::class, $comesWithFreeProductInvoice);

        self::assertSame($subscription->id, $comesWithFreeProductInvoice->subscription_id);
        self::assertSame($this->customer->id, $comesWithFreeProductInvoice->customer_id);
        self::assertSame($productGroupAddon->ledger_code, $comesWithFreeProductInvoice->ledger_code);
        self::assertSame($productServicePlus->name, $comesWithFreeProductInvoice->title);
        self::assertSame($subscription->domain, $comesWithFreeProductInvoice->group_label);
        self::assertFalse($comesWithFreeProductInvoice->paid);
        self::assertNull($comesWithFreeProductInvoice->prepaid_reference);
        self::assertEquals($subscriptionProductInvoice->start_date, $comesWithFreeProductInvoice->start_date);
        self::assertEquals($subscriptionProductInvoice->end_date, $comesWithFreeProductInvoice->end_date);
        self::assertSame($prolongationPriceYearly->price, $comesWithFreeProductInvoice->gross_price);
        self::assertSame(0, $comesWithFreeProductInvoice->net_price);
        self::assertSame($expectedDescription, $comesWithFreeProductInvoice->description);

        self::assertCount(2, Invoice::all());
        self::assertSame($today->addMonths($billingPeriod)->timestamp, $subscription->next_billing_date->timestamp);
        Event::assertNotDispatched(InvoiceCreatedEvent::class);
    }

    #[Test]
    public function subscriptionWithNetPriceZeroWillBeInvoiced(): void
    {
        $today = CarbonImmutable::today();
        $billingPeriod = 12;
        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'end_date' => $today,
                'next_billing_date' => $today,
                'net_price' => 0,
                'billing_period' => $billingPeriod,
            ]);

        self::assertCount(0, Invoice::all());
        Event::fake([InvoiceCreatedEvent::class]);
        $billingDate = CarbonImmutable::today()->addDays($this->renewalDays);
        $this->bus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DispatchConsolidatedInvoicesForCustomer::class));

        $this->getInvoiceCreator()->invoiceCustomer($this->customer, $billingDate);

        $subscription->refresh();

        $newInvoice = Invoice::query()->latest()->first();
        self::assertInstanceOf(Invoice::class, $newInvoice);

        self::assertCount(1, Invoice::all());
        self::assertSame($today->addMonths($billingPeriod)->timestamp, $subscription->next_billing_date->timestamp);
        Event::assertNotDispatched(InvoiceCreatedEvent::class);
    }

    #[Test]
    public function subscriptionWithOnlyNetPriceZeroWillNotResultInAdminFeesInvoice(): void
    {
        /**
         * Note: this works only because in `.env.testing` the `ADMINISTRATION_FEES_DAILY_BILLING_ENABLED` is set to `true`.
         */
        $customer = CustomerFactory::new()->withAddress()->createOne([
            'has_direct_debit' => false,
        ]);
        $productAdminFees = ProductFactory::new()->administrationFees()->createOne();
        ProductPriceComponentFactory::new()->administrationFee()->createOne([
            'product_id' => $productAdminFees->id,
        ]);

        $productGroup = new ProductGroupFactory()->cloudstackVirtualMachine()->createOne();
        $freeProduct = new ProductFactory()->for($productGroup)->createOne();

        new ProductPriceComponentFactory()
            ->for($freeProduct)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'price' => 0,
            ]);

        new ProductPriceComponentFactory()
            ->for($freeProduct)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'price' => 0,
            ]);

        $this->customer = $customer;

        CarbonImmutable::setTestNow($now = CarbonImmutable::now());
        $today = $now::today();
        $billingPeriod = 12;
        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($customer)
            ->for($freeProduct)
            ->createOne([
                'end_date' => $today,
                'next_billing_date' => $today,
                'net_price' => 0,
                'billing_period' => $billingPeriod,
            ]);

        self::assertCount(0, Invoice::all());
        Event::fake([InvoiceCreatedEvent::class]);
        $billingDate = CarbonImmutable::today()->addDays($this->renewalDays);
        $this->bus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DispatchConsolidatedInvoicesForCustomer::class));

        $this->getInvoiceCreator()->invoiceCustomer($customer, $billingDate);

        $subscription->refresh();

        $newInvoice = Invoice::query()->latest()->first();
        self::assertInstanceOf(Invoice::class, $newInvoice);

        self::assertCount(1, Invoice::all());
        self::assertSame($today->addMonths($billingPeriod)->timestamp, $subscription->next_billing_date->timestamp);
        Event::assertNotDispatched(InvoiceCreatedEvent::class);

        self::assertDatabaseHas('invoices', [
            'customer_id' => $this->customer->id,
            'subscription_id' => $subscription->id,
            'start_date' => $today,
            'end_date' => $subscription->next_billing_date,
        ]);
        self::assertDatabaseMissing('invoices', [
            'customer_id' => $customer->id,
            'subscription_id' => null,
            'start_date' => $today,
            'end_date' => $today,
            'product_id' => $productAdminFees->id,
            'title' => $productAdminFees->name,
        ]);
    }

    #[Test]
    public function suspendedSubscriptionWillBeInvoiced(): void
    {
        $today = CarbonImmutable::today();
        $subscription = new SubscriptionFactory()
            ->administrativeStatusSuspended()
            ->withPrice()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'next_billing_date' => $today,
                'end_date' => $today->addYear(),
                'net_price' => 1,
                'billing_period' => 12,
            ]);

        self::assertCount(0, Invoice::all());

        Event::fake([InvoiceCreatedEvent::class]);
        $billingDate = CarbonImmutable::today()->addDays($this->renewalDays);
        $this->bus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DispatchConsolidatedInvoicesForCustomer::class));
        $this->getInvoiceCreator()->invoiceCustomer($this->customer, $billingDate);

        $subscription->refresh();

        self::assertCount(1, Invoice::all());
        self::assertSame($today->addYear()->timestamp, $subscription->next_billing_date->timestamp);
        Event::assertNotDispatched(InvoiceCreatedEvent::class);
    }

    #[Test]
    public function cancelledSubscriptionWithEndDateNotSurpassedWillBeInvoiced(): void
    {
        $today = CarbonImmutable::today();
        $subscription = new SubscriptionFactory()
            ->administrativeStatusCancelled()
            ->withPrice()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'next_billing_date' => $today,
                'end_date' => $today->addYear(),
                'net_price' => 1,
                'billing_period' => 12,
            ]);

        self::assertCount(0, Invoice::all());

        Event::fake([InvoiceCreatedEvent::class]);
        $billingDate = CarbonImmutable::today()->addDays($this->renewalDays);
        $this->bus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DispatchConsolidatedInvoicesForCustomer::class));
        $this->getInvoiceCreator()->invoiceCustomer($this->customer, $billingDate);

        $subscription->refresh();

        self::assertCount(1, Invoice::all());
        self::assertSame($today->addYear()->timestamp, $subscription->next_billing_date->timestamp);
        Event::assertNotDispatched(InvoiceCreatedEvent::class);
    }

    #[Test]
    public function cancelledSubscriptionWillBeInvoicedIfContractPeriodHasNotBeenEntirelyInvoicedYet(): void
    {
        $today = CarbonImmutable::today();

        // Should be invoiced, next billing date in range for consolidating
        $cancelledSubscription = new SubscriptionFactory()
            ->administrativeStatusCancelled()
            ->withPrice()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'next_billing_date' => $today->subMonth(),
                'end_date' => $today,
                'net_price' => 1,
                'billing_period' => 1,
                'contract_period' => 12,
            ]);

        self::assertCount(0, Invoice::all());

        Event::fake([InvoiceCreatedEvent::class]);
        $this->bus->expects(self::once())->method('dispatch');

        $billingDate = CarbonImmutable::today()->addDays($this->renewalDays);
        $this->getInvoiceCreator()->invoiceCustomer($this->customer, $billingDate);

        $cancelledSubscription->refresh();

        self::assertCount(1, Invoice::all());
        self::assertSame($today->timestamp, $cancelledSubscription->next_billing_date->timestamp);
    }

    #[Test]
    public function cancelledSubscriptionWillNotBeInvoicedIfContractPeriodHasBeenEntirelyInvoiced(): void
    {
        $today = CarbonImmutable::today();

        // Should not be invoiced, although next billing date in range for consolidating
        $cancelledSubscription = new SubscriptionFactory()
            ->administrativeStatusCancelled()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'next_billing_date' => $today,
                'end_date' => $today,
                'net_price' => 1,
                'billing_period' => 12,
                'contract_period' => 12,
            ]);

        self::assertCount(0, Invoice::all());

        Event::fake([InvoiceCreatedEvent::class]);
        $this->bus->expects(self::never())->method('dispatch');

        $billingDate = CarbonImmutable::today()->addDays($this->renewalDays);
        $this->getInvoiceCreator()->invoiceCustomer($this->customer, $billingDate);

        $cancelledSubscription->refresh();

        self::assertCount(0, Invoice::all());
        self::assertSame($today->timestamp, $cancelledSubscription->next_billing_date->timestamp);
    }

    #[Test]
    public function inactiveSubscriptionShouldNotBeInvoiced(): void
    {
        $today = CarbonImmutable::today();
        $subscription = new SubscriptionFactory()
            ->administrativeStatusInactive()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'next_billing_date' => $today,
                'end_date' => $today,
                'net_price' => 1,
            ]);

        self::assertCount(0, Invoice::all());
        $this->bus->expects(self::never())->method('dispatch');

        $billingDate = CarbonImmutable::today()->addDays($this->renewalDays);
        $this->getInvoiceCreator()->invoiceCustomer($this->customer, $billingDate);

        $subscription->refresh();

        self::assertCount(0, Invoice::all());
        self::assertSame($today->timestamp, $subscription->next_billing_date->timestamp);
    }

    #[Test]
    public function deletedSubscriptionShouldNotBeInvoiced(): void
    {
        $today = CarbonImmutable::today();
        $subscription = new SubscriptionFactory()
            ->administrativeStatusArchived()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'next_billing_date' => $today,
                'end_date' => $today,
                'net_price' => 1,
            ]);

        self::assertCount(0, Invoice::all());
        $this->bus->expects(self::never())->method('dispatch');

        $billingDate = CarbonImmutable::today()->addDays($this->renewalDays);
        $this->getInvoiceCreator()->invoiceCustomer($this->customer, $billingDate);

        $subscription->refresh();

        self::assertCount(0, Invoice::all());
        self::assertSame($today->timestamp, $subscription->next_billing_date->timestamp);
    }

    #[Test]
    public function subscriptionWithChildrenShouldOnlyInvoiceInvoicableChildsubscription(): void
    {
        $today = CarbonImmutable::today();
        $parent = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->withPrice()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'next_billing_date' => $today,
                'end_date' => $today->addYear(),
                'net_price' => 1,
                'billing_period' => 12,
            ]);

        $firstChild = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->withPrice()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'next_billing_date' => $today,
                'end_date' => $today->addYear(),
                'net_price' => 1,
            ]);
        $secondChild = new SubscriptionFactory()
            ->administrativeStatusSuspended()
            ->withPrice()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'next_billing_date' => $today,
                'end_date' => $today->addYear(),
                'net_price' => 1,
            ]);

        $cancelledChild = new SubscriptionFactory()
            ->administrativeStatusCancelled()
            ->withPrice()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'net_price' => 1,
                'next_billing_date' => $today->addDays($this->renewalDays),
                'end_date' => $today->addDays($this->renewalDays),
            ]);

        $parent->children()->save($firstChild);
        $parent->children()->save($secondChild);
        $parent->children()->save($cancelledChild);

        self::assertCount(0, Invoice::all());

        $billingDate = CarbonImmutable::today()->addDays($this->renewalDays);
        $this->bus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DispatchConsolidatedInvoicesForCustomer::class));
        $this->getInvoiceCreator()->invoiceCustomer($this->customer, $billingDate);

        $parent->refresh();
        $firstChild->refresh();
        $secondChild->refresh();
        $cancelledChild->refresh();

        self::assertCount(3, Invoice::all());
        self::assertSame($today->addYear()->timestamp, $parent->next_billing_date->timestamp);
        self::assertSame($today->addYear()->timestamp, $firstChild->next_billing_date->timestamp);
        self::assertSame($today->addYear()->timestamp, $secondChild->next_billing_date->timestamp);
        self::assertSame($today->addDays($this->renewalDays)->timestamp, $cancelledChild->next_billing_date->timestamp);
    }

    #[Test]
    public function invoiceCustomerWithSubscriptionAhead(): void
    {
        $netPrice = 180;
        $billingPeriod = 12;

        $renewedAndToBeInvoiced = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->withPrice()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'contract_period' => 12,
                'billing_period' => 12,
                'start_date' => CarbonImmutable::today()->subYear(),
                'next_billing_date' => CarbonImmutable::today(),
                'end_date' => CarbonImmutable::today()->addYear(),
                'net_price' => $netPrice,
                'domain' => 'test.com',
            ]);
        $notRenewedButToBeInvoicedAlready = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->withPrice()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'contract_period' => 12,
                'billing_period' => $billingPeriod,
                'start_date' => CarbonImmutable::today()->subYear()->addDays(12),
                'next_billing_date' => CarbonImmutable::today()->addDays(12),
                'end_date' => CarbonImmutable::today()->addDays(12),
                'net_price' => $netPrice,
                'domain' => 'test2.com',
            ]);
        $notRenewedButToBeInvoicedAlreadyActiveChild = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->withPrice()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'contract_period' => 12,
                'billing_period' => $billingPeriod,
                'start_date' => CarbonImmutable::today()->subYear()->addDays(12),
                'next_billing_date' => CarbonImmutable::today()->addDays(12),
                'end_date' => CarbonImmutable::today()->addDays(12),
                'net_price' => $netPrice,
                'domain' => 'test2.com',
                'parent_subscription_id' => $notRenewedButToBeInvoicedAlready->id,
            ]);

        self::assertDatabaseCount('invoices', 0);

        $billingDate = CarbonImmutable::today()->addDays($this->renewalDays);
        $this->bus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DispatchConsolidatedInvoicesForCustomer::class));
        $this->getInvoiceCreator()->invoiceCustomer($this->customer, $billingDate);

        $renewedAndToBeInvoiced->refresh();
        $notRenewedButToBeInvoicedAlready->refresh();
        $notRenewedButToBeInvoicedAlreadyActiveChild->refresh();

        self::assertSame(
            CarbonImmutable::today()->addMonths(12)->timestamp,
            $renewedAndToBeInvoiced->next_billing_date->timestamp,
        );
        self::assertSame(
            CarbonImmutable::today()->addDays(12)->addMonths($billingPeriod)->timestamp,
            $notRenewedButToBeInvoicedAlready->next_billing_date->timestamp,
        );
        self::assertSame(
            CarbonImmutable::today()->addDays(12)->addMonths($billingPeriod)->timestamp,
            $notRenewedButToBeInvoicedAlreadyActiveChild->next_billing_date->timestamp,
        );

        self::assertDatabaseCount('invoices', 3);

        self::assertDatabaseHas('invoices', [
            'customer_id' => $this->customer->id,
            'subscription_id' => $renewedAndToBeInvoiced->id,
            'start_date' => CarbonImmutable::today(),
            'end_date' => $renewedAndToBeInvoiced->next_billing_date,
        ]);
    }

    #[Test]
    public function invoiceCustomerWithRenewalPriceForSubscriptionAhead(): void
    {
        $renewedAndToBeInvoiced = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->withPrice()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'contract_period' => $this->prolongationPriceYearly->contract_period,
                'billing_period' => $this->prolongationPriceYearly->billing_period,
                'gross_price' => $this->prolongationPriceYearly->price,
                'net_price' => $this->prolongationPriceYearly->price,
                'start_date' => CarbonImmutable::today()->subYear(),
                'next_billing_date' => CarbonImmutable::today(),
                'end_date' => CarbonImmutable::today()->addYear(),
                'domain' => 'test.com',
            ]);
        $notRenewedButToBeInvoicedAlready = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->withPrice()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'contract_period' => $this->registrationPriceYearly->contract_period,
                'billing_period' => $this->registrationPriceYearly->billing_period,
                'gross_price' => $this->registrationPriceYearly->price,
                'net_price' => $this->registrationPriceYearly->price,
                'start_date' => CarbonImmutable::today()->subYear()->addDays(12),
                'next_billing_date' => CarbonImmutable::today()->addDays(12),
                'end_date' => CarbonImmutable::today()->addDays(12),
                'domain' => 'test2.com',
            ]);

        self::assertDatabaseCount('invoices', 0);

        $billingDate = CarbonImmutable::today()->addDays($this->renewalDays);
        $this->bus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DispatchConsolidatedInvoicesForCustomer::class));
        $this->getInvoiceCreator()->invoiceCustomer($this->customer, $billingDate);

        $renewedAndToBeInvoiced->refresh();
        $notRenewedButToBeInvoicedAlready->refresh();

        self::assertDatabaseCount('invoices', 2);

        // new invoice for renewed subscription
        self::assertDatabaseHas('invoices', [
            'customer_id' => $this->customer->id,
            'subscription_id' => $renewedAndToBeInvoiced->id,
            'start_date' => CarbonImmutable::today(),
            'end_date' => $renewedAndToBeInvoiced->next_billing_date,
            'gross_price' => $this->prolongationPriceYearly->price,
            'net_price' => $this->prolongationPriceYearly->price,
        ]);

        // new invoice for subscription that still needs te be renewed
        // having the prolongation price
        self::assertDatabaseHas('invoices', [
            'customer_id' => $this->customer->id,
            'subscription_id' => $notRenewedButToBeInvoicedAlready->id,
            'start_date' => CarbonImmutable::today()->addDays(12),
            'end_date' => $notRenewedButToBeInvoicedAlready->next_billing_date,
            'gross_price' => $this->prolongationPriceYearly->price,
            'net_price' => $this->prolongationPriceYearly->price,
        ]);
    }

    #[Test]
    public function monthlyBilledContractPeriodIsNotReachedInvoiceHasOriginalPrice(): void
    {
        $today = CarbonImmutable::now();
        CarbonImmutable::setTestNow($today);

        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->withPrice()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'contract_period' => 12,
                'billing_period' => 1,
                'gross_price' => $this->registrationPriceMonthly->price,
                'net_price' => $this->registrationPriceMonthly->price,
                'start_date' => $today->subMonths(6),
                'end_date' => $today->addMonths(6),
                'next_billing_date' => $today,
            ]);

        $this->getInvoiceCreator()->invoiceCustomer($this->customer, $today->addDays(12));

        self::assertDatabaseHas('invoices', [
            'customer_id' => $this->customer->id,
            'subscription_id' => $subscription->id,
            'start_date' => $today,
            'end_date' => $today->addMonth(),
            'gross_price' => $this->registrationPriceMonthly->price,
            'net_price' => $this->registrationPriceMonthly->price,
        ]);
    }

    #[Test]
    public function yearlyBilledContractPeriodIsReachedInvoiceHasRenewalPrice(): void
    {
        $today = CarbonImmutable::now();
        CarbonImmutable::setTestNow($today);

        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'contract_period' => 12,
                'billing_period' => 12,
                'gross_price' => $this->registrationPriceMonthly->price,
                'net_price' => $this->registrationPriceMonthly->price,
                'start_date' => $today->subMonths(12),
                'end_date' => $today,
                'next_billing_date' => $today,
            ]);

        $this->getInvoiceCreator()->invoiceCustomer($this->customer, $today->addDays(12));

        self::assertDatabaseHas('invoices', [
            'customer_id' => $this->customer->id,
            'subscription_id' => $subscription->id,
            'start_date' => $today,
            'end_date' => $today->addYear(),
            'gross_price' => $this->prolongationPriceYearly->price,
            'net_price' => $this->prolongationPriceYearly->price,
        ]);
    }

    #[Test]
    public function monthlyBilledContractPeriodWillBeReachedInvoiceHasRenewalPrice(): void
    {
        $today = CarbonImmutable::now();
        CarbonImmutable::setTestNow($today);

        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'contract_period' => 12,
                'billing_period' => 1,
                'gross_price' => $this->registrationPriceMonthly->price,
                'net_price' => $this->registrationPriceMonthly->price,
                'start_date' => $today->subMonths(12),
                'end_date' => $today,
                'next_billing_date' => $today,
            ]);

        $this->getInvoiceCreator()->invoiceCustomer($this->customer, $today->addDays(12));

        self::assertDatabaseHas('invoices', [
            'customer_id' => $this->customer->id,
            'subscription_id' => $subscription->id,
            'start_date' => $today,
            'end_date' => $today->addMonth(),
            'gross_price' => $this->prolongationPriceMonthly->price,
            'net_price' => $this->prolongationPriceMonthly->price,
        ]);
    }
}
