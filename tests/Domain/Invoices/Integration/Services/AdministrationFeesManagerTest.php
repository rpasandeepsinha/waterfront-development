<?php

declare(strict_types=1);

namespace Tests\Domain\Invoices\Integration\Services;

use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Services\CustomerVatService;
use Waterfront\Domain\Invoices\Events\InvoiceCreatedEvent;
use Waterfront\Domain\Invoices\Services\AdministrationFeesManager;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(AdministrationFeesManager::class)]
class AdministrationFeesManagerTest extends IntegrationTestCase
{
    #[Test]
    public function findAdminFeesProductPrice(): void
    {
        $productPrice = $this->createAdminFeesProductPrice(55);

        $service = new AdministrationFeesManager(
            self::resolve(ProductRepository::class),
            self::resolve(CustomerVatService::class),
            self::createStub(Dispatcher::class),
            $this->createConfigFeatureFlags(true, true, true),
            self::createStub(LoggerInterface::class),
            self::createStub(TranslatorInterface::class),
            self::resolve(PriceResolver::class),
        );

        $administrationFees = $service->getAdministrationFees(new Customer());

        self::assertNotNull($administrationFees);
        self::assertSame($productPrice->product->id, $administrationFees->productId);
        self::assertSame($productPrice->price, $administrationFees->price);
    }

    #[Test]
    public function findAdminFeesProductPriceReturnsNullIfNotConfigured(): void
    {
        $service = new AdministrationFeesManager(
            self::resolve(ProductRepository::class),
            self::resolve(CustomerVatService::class),
            self::createStub(Dispatcher::class),
            $this->createConfigFeatureFlags(true, true, true),
            self::createStub(LoggerInterface::class),
            self::createStub(TranslatorInterface::class),
            self::resolve(PriceResolver::class),
        );

        $administrationFees = $service->getAdministrationFees(new Customer());

        self::assertNull($administrationFees);
    }

    #[Test]
    public function findAdminFeesProductPriceReturnsNullIfConfiguredWithZeroPrice(): void
    {
        $this->createAdminFeesProductPrice(0);

        $service = new AdministrationFeesManager(
            self::resolve(ProductRepository::class),
            self::resolve(CustomerVatService::class),
            self::createStub(Dispatcher::class),
            $this->createConfigFeatureFlags(true, true, true),
            self::createStub(LoggerInterface::class),
            self::createStub(TranslatorInterface::class),
            self::resolve(PriceResolver::class),
        );

        $administrationFees = $service->getAdministrationFees(new Customer());

        self::assertNull($administrationFees);
    }

    #[Test]
    public function createsAdminFeesInvoiceCorrectly(): void
    {
        $now = CarbonImmutable::now();
        CarbonImmutable::setTestNow($now);

        $adminFeeProductPrice = $this->createAdminFeesProductPrice();
        $customer = CustomerFactory::new()->withAddress()->createOne();

        $eventDispatcher = self::createMock(Dispatcher::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(InvoiceCreatedEvent::class));

        $service = new AdministrationFeesManager(
            self::resolve(ProductRepository::class),
            self::resolve(CustomerVatService::class),
            $eventDispatcher,
            $this->createConfigFeatureFlags(true, true, true),
            self::createStub(LoggerInterface::class),
            self::createStub(TranslatorInterface::class),
            self::resolve(PriceResolver::class),
        );
        $administrationFees = $service->getAdministrationFees($customer);
        self::assertNotNull($administrationFees);

        $invoice = $service->createAdministrationFeesInvoice($customer, $administrationFees);

        self::assertSame($customer->id, $invoice->customer_id);
        self::assertNull($invoice->subscription_id);
        self::assertSame($adminFeeProductPrice->product->id, $invoice->product_id);
        self::assertSame($adminFeeProductPrice->price, $invoice->net_price);
        self::assertSame($adminFeeProductPrice->product->name, $invoice->title);
        self::assertSame(0, $invoice->period);
        self::assertSame($now->getTimestamp(), $invoice->start_date->getTimestamp());
        self::assertSame($now->getTimestamp(), $invoice->end_date->getTimestamp());
        self::assertFalse($invoice->paid);
    }

    #[Test]
    public function createsAdminFeesInvoiceCorrectlyWithIsPaid(): void
    {
        $this->createAdminFeesProductPrice();
        $customer = CustomerFactory::new()->withAddress()->createOne();

        $eventDispatcher = self::createMock(Dispatcher::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(InvoiceCreatedEvent::class));

        $service = new AdministrationFeesManager(
            self::resolve(ProductRepository::class),
            self::resolve(CustomerVatService::class),
            $eventDispatcher,
            $this->createConfigFeatureFlags(true, true, true),
            self::createStub(LoggerInterface::class),
            self::createStub(TranslatorInterface::class),
            self::resolve(PriceResolver::class),
        );

        $administrationFees = $service->getAdministrationFees($customer);
        self::assertNotNull($administrationFees);

        $invoice = $service->createAdministrationFeesInvoice(
            customer: $customer,
            administrationFees: $administrationFees,
            prepaidReference: 'test_prepaid_reference',
        );

        self::assertTrue($invoice->paid);
        self::assertSame('test_prepaid_reference', $invoice->prepaid_reference);
    }

    #[Test]
    public function createsAdminFeesInvoiceWithCustomPrice(): void
    {
        $productPrice = $this->createAdminFeesProductPrice();
        $customer = CustomerFactory::new()->withAddress()->createOne();

        $eventDispatcher = self::createMock(Dispatcher::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(InvoiceCreatedEvent::class));

        $service = new AdministrationFeesManager(
            self::resolve(ProductRepository::class),
            self::resolve(CustomerVatService::class),
            $eventDispatcher,
            $this->createConfigFeatureFlags(true, true, true),
            self::createStub(LoggerInterface::class),
            self::createStub(TranslatorInterface::class),
            self::resolve(PriceResolver::class),
        );

        $administrationFees = $service->getAdministrationFees($customer);
        self::assertNotNull($administrationFees);

        $invoice = $service->createAdministrationFeesInvoice(
            customer: $customer,
            administrationFees: $administrationFees,
            administrationFeesPrice: 111,
        );

        self::assertSame(111, $invoice->net_price);
        self::assertSame(111, $invoice->gross_price);
        self::assertSame($productPrice->product->id, $invoice->product_id);
    }

    #[Test]
    public function disableDispatchInvoiceCreatedEventWorks(): void
    {
        $this->createAdminFeesProductPrice();
        $customer = CustomerFactory::new()->withAddress()->createOne();

        $eventDispatcher = self::createMock(Dispatcher::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $service = new AdministrationFeesManager(
            self::resolve(ProductRepository::class),
            self::resolve(CustomerVatService::class),
            $eventDispatcher,
            $this->createConfigFeatureFlags(true, true, true),
            self::createStub(LoggerInterface::class),
            self::createStub(TranslatorInterface::class),
            self::resolve(PriceResolver::class),
        );

        $administrationFees = $service->getAdministrationFees($customer);
        self::assertNotNull($administrationFees);

        $service->createAdministrationFeesInvoice(
            customer: $customer,
            administrationFees: $administrationFees,
            dispatchInvoiceCreated: false,
        );
    }

    #[DataProvider('chargeWithOrderData')]
    #[Test]
    public function shouldChargeAdminFeesWithOrder(
        bool $featureFlagOnOrder,
        bool $customerAlreadyHasDirectDebit,
        ?string $paymentType,
        bool $customerApprovedForDirectDebitWithOrder,
        bool $expectedResult,
    ): void {
        $customer = CustomerFactory::new()->withAddress()->createOne([
            'has_direct_debit' => $customerAlreadyHasDirectDebit,
        ]);
        $this->createAdminFeesProductPrice();
        $config = $this->createConfigFeatureFlags(onOrder: $featureFlagOnOrder);

        $service = new AdministrationFeesManager(
            self::resolve(ProductRepository::class),
            self::resolve(CustomerVatService::class),
            self::createStub(Dispatcher::class),
            $config,
            self::createStub(LoggerInterface::class),
            self::createStub(TranslatorInterface::class),
            self::resolve(PriceResolver::class),
        );

        self::assertSame($expectedResult, $service->shouldBeChargedWithOrder(
            $customer,
            $paymentType,
            $customerApprovedForDirectDebitWithOrder,
        ));
    }

    public static function chargeWithOrderData(): Generator
    {
        yield 'If enabled and customer has no direct debit, no approve for direct debit with order; should be charged' =>
            [
                'featureFlagOnOrder' => true,
                'customerAlreadyHasDirectDebit' => false,
                'paymentType' => 'ideal',
                'customerApprovedForDirectDebitWithOrder' => false,
                'expectedResult' => true,
            ];

        yield 'If disabled and customer has no direct debit, no approve for direct debit with order; should not be charged' =>
            [
                'featureFlagOnOrder' => false,
                'customerAlreadyHasDirectDebit' => false,
                'paymentType' => 'ideal',
                'customerApprovedForDirectDebitWithOrder' => false,
                'expectedResult' => false,
            ];

        yield 'If enabled and customer has direct debit, no approve for direct debit with order; should not be charged' =>
            [
                'featureFlagOnOrder' => true,
                'customerAlreadyHasDirectDebit' => true,
                'paymentType' => 'ideal',
                'customerApprovedForDirectDebitWithOrder' => false,
                'expectedResult' => false,
            ];

        yield 'If enabled and customer has no direct debit, approve given for direct debit with order; should not be charged' =>
            [
                'featureFlagOnOrder' => true,
                'customerAlreadyHasDirectDebit' => false,
                'paymentType' => 'ideal',
                'customerApprovedForDirectDebitWithOrder' => true,
                'expectedResult' => false,
            ];

        yield 'If enabled and customer has no direct debit, no approve for direct debit with order but payment method is credit; should not be charged' =>
            [
                'featureFlagOnOrder' => true,
                'customerAlreadyHasDirectDebit' => false,
                'paymentType' => PaymentType::CREDIT->value,
                'customerApprovedForDirectDebitWithOrder' => false,
                'expectedResult' => false,
            ];

        yield 'If enabled and customer has no direct debit, no approve for direct debit with order and payment method null; should not be charged' =>
            [
                'featureFlagOnOrder' => true,
                'customerAlreadyHasDirectDebit' => false,
                'paymentType' => null,
                'customerApprovedForDirectDebitWithOrder' => false,
                'expectedResult' => false,
            ];
    }

    #[DataProvider('chargeWithDailyBillingData')]
    #[Test]
    public function shouldChargeAdminFeesWithDailyBilling(
        bool $featureFlagOnDailyBilling,
        bool $customerAlreadyHasDirectDebit,
        bool $expectedResult,
    ): void {
        $customer = CustomerFactory::new()->withAddress()->createOne([
            'has_direct_debit' => $customerAlreadyHasDirectDebit,
        ]);
        $this->createAdminFeesProductPrice();
        $config = $this->createConfigFeatureFlags(onDailyBilling: $featureFlagOnDailyBilling);

        $service = new AdministrationFeesManager(
            self::resolve(ProductRepository::class),
            self::resolve(CustomerVatService::class),
            self::createStub(Dispatcher::class),
            $config,
            self::createStub(LoggerInterface::class),
            self::createStub(TranslatorInterface::class),
            self::resolve(PriceResolver::class),
        );

        self::assertSame($expectedResult, $service->shouldBeChargedWithDailyBilling($customer));
    }

    public static function chargeWithDailyBillingData(): Generator
    {
        yield 'If enabled and customer has no direct debit; should be charged' => [
            'featureFlagOnDailyBilling' => true,
            'customerAlreadyHasDirectDebit' => false,
            'expectedResult' => true,
        ];

        yield 'If disabled and customer has no direct debit; should not be charged' => [
            'featureFlagOnDailyBilling' => false,
            'customerAlreadyHasDirectDebit' => false,
            'expectedResult' => false,
        ];

        yield 'If enabled and customer has direct debit; should not be charged' => [
            'featureFlagOnDailyBilling' => true,
            'customerAlreadyHasDirectDebit' => true,
            'expectedResult' => false,
        ];
    }

    #[DataProvider('chargeWithOneTimeServiceData')]
    #[Test]
    public function shouldChargeAdminFeesWithOneTimeService(
        bool $featureFlagOnOneTimeService,
        bool $customerAlreadyHasDirectDebit,
        bool $expectedResult,
    ): void {
        $customer = CustomerFactory::new()->withAddress()->createOne([
            'has_direct_debit' => $customerAlreadyHasDirectDebit,
        ]);
        $this->createAdminFeesProductPrice();
        $config = $this->createConfigFeatureFlags(onOts: $featureFlagOnOneTimeService);

        $service = new AdministrationFeesManager(
            self::resolve(ProductRepository::class),
            self::resolve(CustomerVatService::class),
            self::createStub(Dispatcher::class),
            $config,
            self::createStub(LoggerInterface::class),
            self::createStub(TranslatorInterface::class),
            self::resolve(PriceResolver::class),
        );

        self::assertSame($expectedResult, $service->shouldBeChargedWithOneTimeService($customer));
    }

    public static function chargeWithOneTimeServiceData(): Generator
    {
        yield 'If enabled and customer has no direct debit; should be charged' => [
            'featureFlagOnOneTimeService' => true,
            'customerAlreadyHasDirectDebit' => false,
            'expectedResult' => true,
        ];

        yield 'If disabled and customer has no direct debit; should not be charged' => [
            'featureFlagOnOneTimeService' => false,
            'customerAlreadyHasDirectDebit' => false,
            'expectedResult' => false,
        ];

        yield 'If enabled and customer has DD; should not be charged' => [
            'featureFlagOnOneTimeService' => true,
            'customerAlreadyHasDirectDebit' => true,
            'expectedResult' => false,
        ];
    }

    private function createAdminFeesProductPrice(int $price = 200): ProductPriceComponent
    {
        $product = ProductFactory::new()->administrationFees()->createOne();

        return ProductPriceComponentFactory::new()->administrationFee()->createOne([
            'product_id' => $product->id,
            'price' => $price,
        ]);
    }

    private function createConfigFeatureFlags(
        bool $onDailyBilling = false,
        bool $onOrder = false,
        bool $onOts = false,
    ): ConfigurationInterface&Stub {
        $config = self::createStub(ConfigurationInterface::class);
        $config
            ->method('getAsBoolean')
            ->willReturnCallback(
                fn (string $configKey): bool => match ($configKey) {
                    'financial.administration_fees_daily_billing_enabled' => $onDailyBilling,
                    'financial.administration_fees_order_billing_enabled' => $onOrder,
                    'financial.administration_fees_ots_enabled' => $onOts,
                    default => throw new RuntimeException('Unknown config key'),
                },
            );

        return $config;
    }
}
