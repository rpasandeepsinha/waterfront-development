<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use SandwaveIo\Office365\Exception\Office365Exception;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\Microsoft365KpnProductFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryException;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Microsoft365\Services\Microsoft365SubscriptionService;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(Microsoft365SubscriptionService::class)]
#[AllowMockObjectsWithoutExpectations]
class Microsoft365SubscriptionServiceTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $childSubscription;

    private Product $parentProduct;

    private Product $childProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->withAddress()->createOne();
        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $this->childProduct = new ProductFactory()->for($productGroup)->createOne();

        $this->childSubscription = new SubscriptionFactory()->for($this->customer)->for($this->childProduct)->createOne([
            'net_price' => 100,
            'gross_price' => 200,
            'billing_period' => 1,
            'contract_period' => 1,
        ]);

        $this->parentProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => $this->childProduct->slug . '-parent',
        ]);
    }

    #[Test]
    public function createFirstTimeCustomerInfo(): void
    {
        $mockMicrosoftModuleMicrosoftService = self::createMock(Microsoft365Service::class);
        $mockMicrosoftModuleMicrosoftService->expects(self::once())
            ->method('createKpnCustomer')
            ->willReturn(true);

        $this->app->bind(Microsoft365Service::class, fn () => $mockMicrosoftModuleMicrosoftService);
        $microsoft365Service = self::resolve(Microsoft365SubscriptionService::class);

        self::assertDatabaseMissing('microsoft365_customer_info', ['customer_id' => $this->customer->id]);
        $microsoft365Service->create(new Collection([$this->childSubscription]), null, null);
        self::assertDatabaseHas('microsoft365_customer_info', ['customer_id' => $this->customer->id]);

        $microsoft365CustomerInfo = Microsoft365CustomerInfo::where('customer_id', $this->customer->id)->firstOrFail();
        self::assertSame(Microsoft365ProcessStatus::INITIATED, $microsoft365CustomerInfo->technical_status);
    }

    #[Test]
    public function createReturningCustomerInfo(): void
    {
        $microsoft365Service = self::resolve(Microsoft365SubscriptionService::class);
        new Microsoft365CustomerInfoFactory()->createOne([
            'customer_id' => $this->customer->id,
            'kpn_customer_id' => null,
            'tenant_access_verified' => true,
        ]);

        $microsoft365Service->create(new Collection([$this->childSubscription]), null, null);
        $subscriptionAmount = Microsoft365CustomerInfo::where('customer_id', $this->customer->id)->count();

        self::assertSame(1, $subscriptionAmount);
    }

    #[Test]
    public function createParentForChildIfNotPresent(): void
    {
        $mockMicrosoftModuleMicrosoftService = self::createMock(Microsoft365Service::class);
        $this->app->bind(Microsoft365Service::class, fn () => $mockMicrosoftModuleMicrosoftService);

        $microsoft365Service = self::resolve(Microsoft365SubscriptionService::class);
        $microsoft365Service->create(new Collection([$this->childSubscription]), null, null);
        $this->childSubscription->refresh();

        self::assertNotNull($this->childSubscription->parent_subscription_id);

        self::assertDatabaseHas('subscriptions', [
            'customer_id' => $this->customer->id,
            'end_date' => new CarbonImmutable($this->childSubscription->end_date),
            'contract_period' => $this->childSubscription->contract_period,
            'start_date' => new CarbonImmutable($this->childSubscription->start_date),
            'product_uuid' => $this->parentProduct->uuid,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'technical_status' => TechnicalStatus::REGISTRATION->value,
            'net_price' => 0,
            'gross_price' => 0,
        ]);

        self::assertDatabaseHas('microsoft365_deployments', [
            'subscription_id' => $this->childSubscription->parent_subscription_id,
        ]);
    }

    #[Test]
    public function dontCreateParentForChildIfPresent(): void
    {
        $microsoft365Service = self::resolve(Microsoft365SubscriptionService::class);
        $anotherChildSubscription = new SubscriptionFactory()->withCustomer()->createOne([
            'customer_id' => $this->childSubscription->customer->id,
            'product_uuid' => $this->childSubscription->product->uuid,
            'contract_period' => $this->childSubscription->contract_period,
        ]);
        new Microsoft365CustomerInfoFactory()->createOne([
            'customer_id' => $this->customer->id,
            'kpn_customer_id' => null,
            'tenant_access_verified' => true,
            'tenant_name' => 'test@onmicrosoft.com',
        ]);

        $microsoft365Service->create(new Collection([$this->childSubscription]), null, null);
        $microsoft365Service->create(new Collection([$anotherChildSubscription]), null, null);

        self::assertSame(1, Subscription::where('product_uuid', $this->parentProduct->uuid)->count());
        self::assertSame(1, Microsoft365Deployment::where('subscription_id', $this->childSubscription->parent_subscription_id)->count());
    }

    #[Test]
    public function resellerCreate(): void
    {
        $mockMicrosoftModuleMicrosoftService = self::createMock(Microsoft365Service::class);
        $this->app->bind(Microsoft365Service::class, fn () => $mockMicrosoftModuleMicrosoftService);

        $microsoft365Service = self::resolve(Microsoft365SubscriptionService::class);

        $resellerSub = new SubscriptionFactory()->for($this->customer)->for($this->childProduct)->createOne([
            'contract_period' => 1,
            'billing_period' => 1,
        ]);

        // setup existing customer
        $existingCustomerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'technical_status' => Microsoft365ProcessStatus::ACTIVE,
            'kpn_customer_id' => 'CID12345',
            'tenant_access_verified' => true,
            'tenant_name' => 'test@onmicrosoft.com',
        ]);
        $existingParentSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->parentProduct)
            ->createOne([
                'contract_period' => 1,
                'billing_period' => 1,
            ]);

        new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->childProduct)
            ->for($existingParentSubscription, 'parent')
            ->createOne([
                'contract_period' => 1,
                'billing_period' => 1,
            ]);

        new Microsoft365DeploymentFactory()->for($existingCustomerInfo)->for($existingParentSubscription)->createOne([
            'kpn_status' => Microsoft365OrderStatus::ACTIVE,
            'kpn_order_id' => '12345',
        ]);

        $tenantName = '1.onmicrosoft.com';
        $microsoft365Service->create(new Collection([$resellerSub]), $tenantName, '1234567890');

        self::assertCount(2, Subscription::where('product_uuid', $this->parentProduct->uuid)->whereNull('parent_subscription_id')->get());
        self::assertCount(1, Microsoft365Deployment::where('kpn_status', Microsoft365OrderStatus::PLACED)->get());

        self::assertCount(2, Microsoft365CustomerInfo::where('customer_id', $this->customer->id)->get());
        self::assertCount(1, Microsoft365CustomerInfo::where('customer_id', $this->customer->id)->where('tenant_name', $tenantName)->get());

        $microsoft365Deployment = Microsoft365Deployment::where('kpn_status', Microsoft365OrderStatus::PLACED)->firstOrFail();
        $subscription = $microsoft365Deployment->subscription;

        self::assertCount(1, $existingParentSubscription->children);
        self::assertCount(1, $subscription->children);
    }

    #[Test]
    public function createExtraSeatsBillingDateFromParent(): void
    {
        $mockMicrosoftModuleMicrosoftService = self::createMock(Microsoft365Service::class);
        $this->app->bind(Microsoft365Service::class, fn () => $mockMicrosoftModuleMicrosoftService);

        $this->travelTo(CarbonImmutable::create(2023));

        $microsoft365Service = self::resolve(Microsoft365SubscriptionService::class);
        $microsoft365Service->create(new Collection([$this->childSubscription]), null, null);
        $this->childSubscription->refresh();

        self::assertNotNull($this->childSubscription->parent_subscription_id);

        $parentSubscription = $this->childSubscription->parent;
        self::assertInstanceOf(Subscription::class, $parentSubscription);

        self::assertDatabaseHas('subscriptions', [
            'id' => $this->childSubscription->id,
            'parent_subscription_id' => $parentSubscription->id,
            'next_billing_date' => $parentSubscription->next_billing_date,
        ]);

        $this->travel(2)->months();

        $newChildSubscription = new SubscriptionFactory()->for($this->customer)->for($this->childProduct)->createOne([
            'net_price' => 100,
            'gross_price' => 200,
            'billing_period' => 1,
            'contract_period' => 1,
        ]);

        $microsoft365Service->create(new Collection([$newChildSubscription]), null, null);
        $newChildSubscription->refresh();

        self::assertDatabaseHas('subscriptions', [
            'id' => $newChildSubscription->id,
            'parent_subscription_id' => $parentSubscription->id,
            'next_billing_date' => $parentSubscription->next_billing_date,
        ]);
    }

    #[Test]
    public function createOrderWhileTenantHasNotBeenCreatedYet(): void
    {
        $microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => 'CID12345',
            'technical_status' => Microsoft365ProcessStatus::ACTIVE,
            'tenant_order_id' => null,
            'tenant_name' => 'test.onmicrosoft.com',
        ]);

        new Microsoft365KpnProductFactory()->for($this->parentProduct)->createOne([
            'contract_period' => $this->childSubscription->contract_period,
        ]);

        $mockMicrosoftModuleMicrosoftService = self::createMock(Microsoft365Service::class);
        $mockMicrosoftModuleMicrosoftService->expects(self::once())
            ->method('synchronizeTenantOrderIdFromOrderSummary')
            ->with(self::callback(
                fn (Microsoft365CustomerInfo $customerInfo): bool => $customerInfo->id === $microsoft365CustomerInfo->id
            ))
            ->willReturn(false);
        $mockMicrosoftModuleMicrosoftService->expects(self::once())
            ->method('createTenant')
            ->willReturn(true);
        $mockMicrosoftModuleMicrosoftService->expects(self::never())
            ->method('createOrder');
        $mockMicrosoftModuleMicrosoftService->expects(self::never())
            ->method('modifyOrder');

        $this->app->bind(Microsoft365Service::class, fn () => $mockMicrosoftModuleMicrosoftService);
        $microsoft365Service = self::resolve(Microsoft365SubscriptionService::class);

        $microsoft365Service->create(new Collection([$this->childSubscription]), null, null);

        $this->childSubscription->refresh();
        self::assertNotNull($this->childSubscription->parent_subscription_id);

        $microsoft365Deployment = Microsoft365Deployment::where('subscription_id', $this->childSubscription->parent_subscription_id)->firstOrFail();
        self::assertSame($microsoft365CustomerInfo->id, $microsoft365Deployment->microsoft365_customer_info_id);
        self::assertSame(Microsoft365OrderStatus::PLACED, $microsoft365Deployment->kpn_status);
    }

    #[Test]
    public function createOrderWhenExistingTenantOrderIsFound(): void
    {
        $microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => 'CID12345',
            'technical_status' => Microsoft365ProcessStatus::ACTIVE,
            'tenant_order_id' => null,
            'tenant_name' => 'test.onmicrosoft.com',
        ]);

        $kpnProduct = new Microsoft365KpnProductFactory()->for($this->parentProduct)->createOne([
            'contract_period' => $this->childSubscription->contract_period,
        ]);

        $mockMicrosoftModuleMicrosoftService = self::createMock(Microsoft365Service::class);
        $mockMicrosoftModuleMicrosoftService->expects(self::once())
            ->method('synchronizeTenantOrderIdFromOrderSummary')
            ->with(self::callback(
                fn (Microsoft365CustomerInfo $customerInfo): bool => $customerInfo->id === $microsoft365CustomerInfo->id
            ))
            ->willReturn(true);
        $mockMicrosoftModuleMicrosoftService->expects(self::never())
            ->method('createTenant');
        $mockMicrosoftModuleMicrosoftService->expects(self::once())
            ->method('createOrder')
            ->with(
                self::isInstanceOf(Microsoft365Deployment::class),
                $kpnProduct->kpn_product_code,
                1,
            )
            ->willReturn(true);
        $mockMicrosoftModuleMicrosoftService->expects(self::never())
            ->method('modifyOrder');

        $this->app->bind(Microsoft365Service::class, fn () => $mockMicrosoftModuleMicrosoftService);
        $microsoft365Service = self::resolve(Microsoft365SubscriptionService::class);

        $microsoft365Service->create(new Collection([$this->childSubscription]), null, null);

        $this->childSubscription->refresh();
        self::assertNotNull($this->childSubscription->parent_subscription_id);

        $microsoft365Deployment = Microsoft365Deployment::where('subscription_id', $this->childSubscription->parent_subscription_id)->firstOrFail();
        self::assertSame($microsoft365CustomerInfo->id, $microsoft365Deployment->microsoft365_customer_info_id);
        self::assertSame(Microsoft365OrderStatus::PLACED, $microsoft365Deployment->kpn_status);
    }

    #[Test]
    public function createOrModifyOrderLogsContextWhenKpnCustomerIsMissing(): void
    {
        $microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => null,
            'technical_status' => Microsoft365ProcessStatus::INITIATED,
            'tenant_name' => 'test.onmicrosoft.com',
        ]);

        $microsoft365Deployment = $this->createDeploymentForChild($microsoft365CustomerInfo, [
            'kpn_status' => Microsoft365OrderStatus::PLACED,
            'kpn_order_id' => null,
        ]);

        $mockMicrosoft365Service = self::createMock(Microsoft365Service::class);
        $mockMicrosoft365Service->expects(self::never())->method('createTenant');
        $mockMicrosoft365Service->expects(self::never())->method('createOrder');
        $mockMicrosoft365Service->expects(self::never())->method('modifyOrder');

        $logger = $this->createLoggerMock();
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'M365 order missing KPN customer',
                self::callback(function (array $context) use ($microsoft365Deployment): bool {
                    $this->assertM365Context($context);
                    self::assertSame($this->customer->id, $context[LoggingContextKeys::CUSTOMER_ID]);
                    self::assertSame($microsoft365Deployment->id, $context[LoggingContextKeys::PROVISIONING_ID]);
                    self::assertSame($microsoft365Deployment->subscription_id, $context[LoggingContextKeys::SUBSCRIPTION_ID]);

                    return true;
                })
            );

        $this->app->bind(Microsoft365Service::class, fn () => $mockMicrosoft365Service);
        $this->app->bind(LoggerInterface::class, fn () => $logger);
        $microsoft365Service = self::resolve(Microsoft365SubscriptionService::class);

        $microsoft365Service->createOrModifyOrder(new Collection([$this->childSubscription]), $this->customer);
    }

    #[Test]
    public function createOrModifyOrderLogsContextWhenOrderModificationFails(): void
    {
        $microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => 'CID12345',
            'technical_status' => Microsoft365ProcessStatus::ACTIVE,
            'tenant_name' => 'test.onmicrosoft.com',
        ]);

        $microsoft365Deployment = $this->createDeploymentForChild($microsoft365CustomerInfo, [
            'kpn_status' => Microsoft365OrderStatus::ACTIVE,
            'kpn_order_id' => 12345,
        ]);
        $exception = new Office365Exception('Modification failed');

        $mockMicrosoft365Service = self::createMock(Microsoft365Service::class);
        $mockMicrosoft365Service->expects(self::once())
            ->method('modifyOrder')
            ->with(12345, 1)
            ->willThrowException($exception);
        $mockMicrosoft365Service->expects(self::never())->method('createTenant');
        $mockMicrosoft365Service->expects(self::never())->method('createOrder');

        $logger = $this->createLoggerMock();
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'M365 order modification failed',
                self::callback(function (array $context) use ($microsoft365Deployment, $microsoft365CustomerInfo, $exception): bool {
                    $this->assertM365Context($context);
                    self::assertSame($this->customer->id, $context[LoggingContextKeys::CUSTOMER_ID]);
                    self::assertSame($microsoft365Deployment->id, $context[LoggingContextKeys::PROVISIONING_ID]);
                    self::assertSame($microsoft365Deployment->subscription_id, $context[LoggingContextKeys::SUBSCRIPTION_ID]);
                    self::assertSame($exception, $context[LoggingContextKeys::EXCEPTION]);

                    $meta = $this->contextMeta($context);
                    self::assertSame(12345, $meta['kpn_order_id']);
                    self::assertSame($microsoft365CustomerInfo->id, $meta['microsoft365_customer_info_id']);
                    self::assertSame(1, $meta['amount']);

                    return true;
                })
            );

        $this->app->bind(Microsoft365Service::class, fn () => $mockMicrosoft365Service);
        $this->app->bind(LoggerInterface::class, fn () => $logger);
        $microsoft365Service = self::resolve(Microsoft365SubscriptionService::class);

        $microsoft365Service->createOrModifyOrder(new Collection([$this->childSubscription]), $this->customer);
    }

    #[Test]
    public function createOrModifyOrderLogsContextWhenTenantOrderSummaryFails(): void
    {
        $microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => 'CID12345',
            'technical_status' => Microsoft365ProcessStatus::ACTIVE,
            'tenant_order_id' => null,
            'tenant_name' => 'test.onmicrosoft.com',
        ]);

        $microsoft365Deployment = $this->createDeploymentForChild($microsoft365CustomerInfo, [
            'kpn_status' => Microsoft365OrderStatus::PLACED,
            'kpn_order_id' => null,
        ]);
        new Microsoft365KpnProductFactory()->for($this->parentProduct)->createOne([
            'contract_period' => $this->childSubscription->contract_period,
            'kpn_product_code' => 'KPN-CODE',
        ]);
        $exception = new OrderSummaryException('Summary failed');

        $mockMicrosoft365Service = self::createMock(Microsoft365Service::class);
        $mockMicrosoft365Service->expects(self::once())
            ->method('synchronizeTenantOrderIdFromOrderSummary')
            ->with(self::callback(
                fn (Microsoft365CustomerInfo $customerInfo): bool => $customerInfo->id === $microsoft365CustomerInfo->id
            ))
            ->willThrowException($exception);
        $mockMicrosoft365Service->expects(self::never())->method('createTenant');
        $mockMicrosoft365Service->expects(self::never())->method('createOrder');
        $mockMicrosoft365Service->expects(self::never())->method('modifyOrder');

        $logger = $this->createLoggerMock();
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'M365 tenant order summary failed',
                self::callback(function (array $context) use ($microsoft365Deployment, $microsoft365CustomerInfo, $exception): bool {
                    $this->assertM365Context($context);
                    self::assertSame($this->customer->id, $context[LoggingContextKeys::CUSTOMER_ID]);
                    self::assertSame($microsoft365Deployment->id, $context[LoggingContextKeys::PROVISIONING_ID]);
                    self::assertSame($microsoft365Deployment->subscription_id, $context[LoggingContextKeys::SUBSCRIPTION_ID]);
                    self::assertSame($exception, $context[LoggingContextKeys::EXCEPTION]);

                    $meta = $this->contextMeta($context);
                    self::assertSame($microsoft365CustomerInfo->id, $meta['microsoft365_customer_info_id']);

                    return true;
                })
            );

        $this->app->bind(Microsoft365Service::class, fn () => $mockMicrosoft365Service);
        $this->app->bind(LoggerInterface::class, fn () => $logger);
        $microsoft365Service = self::resolve(Microsoft365SubscriptionService::class);

        $microsoft365Service->createOrModifyOrder(new Collection([$this->childSubscription]), $this->customer);
    }

    #[Test]
    public function createOrModifyOrderLogsContextWhenOrderCreationFails(): void
    {
        $microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => 'CID12345',
            'technical_status' => Microsoft365ProcessStatus::ACTIVE,
            'tenant_order_id' => 98765,
            'tenant_name' => 'test.onmicrosoft.com',
        ]);

        $microsoft365Deployment = $this->createDeploymentForChild($microsoft365CustomerInfo, [
            'kpn_status' => Microsoft365OrderStatus::PLACED,
            'kpn_order_id' => null,
        ]);
        $kpnProduct = new Microsoft365KpnProductFactory()->for($this->parentProduct)->createOne([
            'contract_period' => $this->childSubscription->contract_period,
            'kpn_product_code' => 'KPN-CODE',
        ]);
        $exception = new Office365Exception('Creation failed');

        $mockMicrosoft365Service = self::createMock(Microsoft365Service::class);
        $mockMicrosoft365Service->expects(self::never())->method('synchronizeTenantOrderIdFromOrderSummary');
        $mockMicrosoft365Service->expects(self::never())->method('createTenant');
        $mockMicrosoft365Service->expects(self::once())
            ->method('createOrder')
            ->with(
                self::isInstanceOf(Microsoft365Deployment::class),
                $kpnProduct->kpn_product_code,
                1,
            )
            ->willThrowException($exception);
        $mockMicrosoft365Service->expects(self::never())->method('modifyOrder');

        $logger = $this->createLoggerMock();
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'M365 order creation failed',
                self::callback(function (array $context) use ($microsoft365Deployment, $microsoft365CustomerInfo, $exception): bool {
                    $this->assertM365Context($context);
                    self::assertSame($this->customer->id, $context[LoggingContextKeys::CUSTOMER_ID]);
                    self::assertSame($microsoft365Deployment->id, $context[LoggingContextKeys::PROVISIONING_ID]);
                    self::assertSame($microsoft365Deployment->subscription_id, $context[LoggingContextKeys::SUBSCRIPTION_ID]);
                    self::assertSame($exception, $context[LoggingContextKeys::EXCEPTION]);

                    $meta = $this->contextMeta($context);
                    self::assertSame($microsoft365CustomerInfo->id, $meta['microsoft365_customer_info_id']);
                    self::assertSame(98765, $meta['tenant_order_id']);
                    self::assertSame('test.onmicrosoft.com', $meta['tenant_name']);

                    return true;
                })
            );

        $this->app->bind(Microsoft365Service::class, fn () => $mockMicrosoft365Service);
        $this->app->bind(LoggerInterface::class, fn () => $logger);
        $microsoft365Service = self::resolve(Microsoft365SubscriptionService::class);

        $microsoft365Service->createOrModifyOrder(new Collection([$this->childSubscription]), $this->customer);
    }

    #[Test]
    public function createLogsContextWhenKpnCustomerCreationFails(): void
    {
        $exception = new Office365Exception('KPN customer creation failed');

        $mockMicrosoft365Service = self::createMock(Microsoft365Service::class);
        $mockMicrosoft365Service->expects(self::once())
            ->method('createKpnCustomer')
            ->with(
                self::callback(fn (Customer $customer): bool => $customer->id === $this->customer->id),
                self::callback(fn (string $customerInfoId): bool => $customerInfoId !== ''),
            )
            ->willThrowException($exception);

        $logger = $this->createLoggerMock();
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'M365 KPN customer creation failed',
                self::callback(function (array $context) use ($exception): bool {
                    $this->assertM365Context($context);
                    self::assertSame($this->customer->id, $context[LoggingContextKeys::CUSTOMER_ID]);
                    self::assertSame($exception, $context[LoggingContextKeys::EXCEPTION]);

                    $meta = $this->contextMeta($context);
                    self::assertIsInt($meta['microsoft365_customer_info_id']);
                    self::assertSame('tenant-id', $meta['tenant_id']);

                    return true;
                })
            );

        $this->app->bind(Microsoft365Service::class, fn () => $mockMicrosoft365Service);
        $this->app->bind(LoggerInterface::class, fn () => $logger);
        $microsoft365Service = self::resolve(Microsoft365SubscriptionService::class);

        $microsoft365Service->create(new Collection([$this->childSubscription]), 'first.onmicrosoft.com', 'tenant-id');

        self::assertDatabaseHas('microsoft365_customer_info', [
            'customer_id' => $this->customer->id,
            'technical_status' => Microsoft365ProcessStatus::FAILED->value,
            'tenant_id' => 'tenant-id',
            'tenant_name' => 'first.onmicrosoft.com',
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createDeploymentForChild(Microsoft365CustomerInfo $customerInfo, array $attributes): Microsoft365Deployment
    {
        $parentSubscription = new SubscriptionFactory()->for($this->customer)->for($this->parentProduct)->createOne([
            'billing_period' => $this->childSubscription->billing_period,
            'contract_period' => $this->childSubscription->contract_period,
        ]);

        $this->childSubscription->parent_subscription_id = $parentSubscription->id;
        $this->childSubscription->save();

        return new Microsoft365DeploymentFactory()->for($customerInfo)->for($parentSubscription)->createOne($attributes);
    }

    private function createLoggerMock(): LoggerInterface&MockObject
    {
        return self::createMock(LoggerInterface::class);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function assertM365Context(array $context): void
    {
        self::assertSame(ProvisionType::M365, $context[LoggingContextKeys::PROVISIONING_TYPE]);
        self::assertSame(ProvisionProvider::MICROSOFT_IRMA, $context[LoggingContextKeys::PROVISIONING_PROVIDER]);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function contextMeta(array $context): array
    {
        $meta = $context[LoggingContextKeys::META] ?? null;

        self::assertIsArray($meta);

        $stringKeyedMeta = [];
        foreach ($meta as $key => $value) {
            self::assertIsString($key);
            $stringKeyedMeta[$key] = $value;
        }

        return $stringKeyedMeta;
    }
}
