<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use SandwaveIo\Office365\Exception\Office365Exception;
use Symfony\Component\HttpFoundation\Response;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\Microsoft365KpnProductFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\Microsoft365Controller;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryException;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Products\Models\Product;

#[CoversClass(Microsoft365Controller::class)]
class Microsoft365ControllerTest extends IntegrationTestCase
{
    private const string ROUTE = 'admin.microsoft365.customer-info.retry-create-kpn-customer';

    private const string ROUTE_ORDER = 'admin.microsoft365.deployment.retry-create-order';

    private const string ROUTE_CUSTOMER_OVERVIEW = 'admin.microsoft365.customer.overview';

    private const string ROUTE_SUBSCRIPTION_DEPLOYMENT = 'admin.microsoft365.subscription.deployment';

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
    }

    public function testRetryCreateKpnCustomerReturnsOkWhenCreationSucceeds(): void
    {
        $customerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => null,
        ]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())
            ->method('createKpnCustomer')
            ->willReturn(true);
        $this->app->bind(Microsoft365Service::class, fn (): Microsoft365Service => $microsoft365Service);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute(self::ROUTE, ['microsoft365CustomerInfo' => $customerInfo->id]))
            ->assertOk()
            ->assertJson(['message' => 'microsoft365.retry-create-kpn-customer.success']);
    }

    public function testRetryCreateKpnCustomerReturnsErrorWhenCreationFails(): void
    {
        $customerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => null,
            'technical_status' => Microsoft365ProcessStatus::ACTIVE,
        ]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())
            ->method('createKpnCustomer')
            ->willReturn(false);
        $this->app->bind(Microsoft365Service::class, fn (): Microsoft365Service => $microsoft365Service);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute(self::ROUTE, ['microsoft365CustomerInfo' => $customerInfo->id]))
            ->assertServerError()
            ->assertJson(['message' => 'microsoft365.retry-create-kpn-customer.failure']);

        self::assertDatabaseHas(Microsoft365CustomerInfo::class, [
            'id' => $customerInfo->id,
            'technical_status' => Microsoft365ProcessStatus::FAILED->value,
        ]);
    }

    public function testRetryCreateKpnCustomerReturnsErrorOnOffice365Exception(): void
    {
        $customerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => null,
        ]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())
            ->method('createKpnCustomer')
            ->willThrowException(new Office365Exception('KPN customer creation failed'));
        $this->app->bind(Microsoft365Service::class, fn (): Microsoft365Service => $microsoft365Service);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute(self::ROUTE, ['microsoft365CustomerInfo' => $customerInfo->id]))
            ->assertServerError()
            ->assertJson(['message' => 'microsoft365.retry-create-kpn-customer.failure']);
    }

    public function testRetryCreateKpnCustomerSkipsWhenKpnCustomerIdAlreadySet(): void
    {
        $customerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => 'CID12345',
        ]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::never())
            ->method('createKpnCustomer');
        $this->app->bind(Microsoft365Service::class, fn (): Microsoft365Service => $microsoft365Service);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute(self::ROUTE, ['microsoft365CustomerInfo' => $customerInfo->id]))
            ->assertOk()
            ->assertJson(['message' => 'microsoft365.retry-create-kpn-customer.success']);
    }

    public function testRetryCreateOrderReturnsOkWhenOrderCreated(): void
    {
        $deployment = $this->createDeployment(activeChildCount: 2);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())
            ->method('createOrder')
            ->with($this->callbackDeployment($deployment), '120A00179B', 2)
            ->willReturn(true);
        $this->app->bind(Microsoft365Service::class, fn (): Microsoft365Service => $microsoft365Service);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute(self::ROUTE_ORDER, ['microsoft365Deployment' => $deployment->id]))
            ->assertOk()
            ->assertJson(['message' => 'microsoft365.retry-create-order.success']);
    }

    public function testRetryCreateOrderReturnsOkWithTenantCreatedMessage(): void
    {
        $deployment = $this->createDeployment(activeChildCount: 1, customerInfoAttributes: ['tenant_order_id' => null]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->method('synchronizeTenantOrderIdFromOrderSummary')->willReturn(false);
        $microsoft365Service->expects(self::once())->method('createTenant')->willReturn(true);
        $microsoft365Service->expects(self::never())->method('createOrder');
        $this->app->bind(Microsoft365Service::class, fn (): Microsoft365Service => $microsoft365Service);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute(self::ROUTE_ORDER, ['microsoft365Deployment' => $deployment->id]))
            ->assertOk()
            ->assertJson(['message' => 'microsoft365.retry-create-order.tenant-created']);
    }

    public function testRetryCreateOrderReturnsUnprocessableWhenMcaNotSigned(): void
    {
        $deployment = $this->createDeployment(activeChildCount: 1, customerInfoAttributes: ['mca_signed_at' => null]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::never())->method('createOrder');
        $this->app->bind(Microsoft365Service::class, fn (): Microsoft365Service => $microsoft365Service);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute(self::ROUTE_ORDER, ['microsoft365Deployment' => $deployment->id]))
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['message' => 'microsoft365.retry-create-order.mca-not-signed']);
    }

    public function testRetryCreateOrderReturnsUnprocessableWhenNoSeats(): void
    {
        $deployment = $this->createDeployment(activeChildCount: 0);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::never())->method('createOrder');
        $this->app->bind(Microsoft365Service::class, fn (): Microsoft365Service => $microsoft365Service);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute(self::ROUTE_ORDER, ['microsoft365Deployment' => $deployment->id]))
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['message' => 'microsoft365.retry-create-order.no-seats']);
    }

    public function testRetryCreateOrderReturnsServerErrorWhenOrderSummaryRetrievalFails(): void
    {
        $deployment = $this->createDeployment(activeChildCount: 1, customerInfoAttributes: ['tenant_order_id' => null]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())
            ->method('synchronizeTenantOrderIdFromOrderSummary')
            ->willThrowException(new OrderSummaryException('Something went wrong while retrieving order summary.'));
        $microsoft365Service->expects(self::never())->method('createOrder');
        $this->app->bind(Microsoft365Service::class, fn (): Microsoft365Service => $microsoft365Service);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute(self::ROUTE_ORDER, ['microsoft365Deployment' => $deployment->id]))
            ->assertServerError()
            ->assertJson(['message' => 'microsoft365.retry-create-order.order-summary-retrieval-failed']);
    }

    public function testRetryCreateOrderReturnsServerErrorWhenOrderCreationFails(): void
    {
        $deployment = $this->createDeployment(activeChildCount: 1);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())
            ->method('createOrder')
            ->willThrowException(new Office365Exception('Order creation failed'));
        $this->app->bind(Microsoft365Service::class, fn (): Microsoft365Service => $microsoft365Service);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute(self::ROUTE_ORDER, ['microsoft365Deployment' => $deployment->id]))
            ->assertServerError()
            ->assertJson(['message' => 'microsoft365.retry-create-order.failure']);
    }

    public function testCustomerOverviewReturnsEachTenantWithItsDeployments(): void
    {
        $product = $this->createMicrosoft365Product();
        $firstTenant = $this->createTenantWithDeployment($product, ['tenant_name' => 'tenant-one']);
        $secondTenant = $this->createTenantWithDeployment($product, ['tenant_name' => 'tenant-two']);

        $firstDeployment = $firstTenant->microsoft365Deployments->first();
        self::assertInstanceOf(Microsoft365Deployment::class, $firstDeployment);

        /** @var array<int, array<string, mixed>> $response */
        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute(self::ROUTE_CUSTOMER_OVERVIEW, ['customer' => $this->customer->customer_number]))
            ->assertOk()
            ->json();

        /** @var array<int, array<string, mixed>> $firstTenantDeployments */
        $firstTenantDeployments = $response[0]['deployments'];

        self::assertCount(2, $response);
        self::assertSame($firstTenant->id, $response[0]['id']);
        self::assertSame('tenant-one', $response[0]['tenant_name']);
        self::assertCount(1, $firstTenantDeployments);
        self::assertSame($firstDeployment->id, $firstTenantDeployments[0]['id']);
        self::assertSame($secondTenant->id, $response[1]['id']);
        self::assertSame('tenant-two', $response[1]['tenant_name']);

        self::assertArrayNotHasKey('uuid', $response[0]);
        self::assertArrayNotHasKey('uuid', $firstTenantDeployments[0]);
    }

    public function testCustomerOverviewReturnsEmptyArrayWhenCustomerHasNoTenants(): void
    {
        /** @var array<int, array<string, mixed>> $response */
        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute(self::ROUTE_CUSTOMER_OVERVIEW, ['customer' => $this->customer->customer_number]))
            ->assertOk()
            ->json();

        self::assertSame([], $response);
    }

    public function testSubscriptionDeploymentReturnsDeploymentDetails(): void
    {
        $tenant = $this->createTenantWithDeployment($this->createMicrosoft365Product());
        $deployment = $tenant->microsoft365Deployments->first();
        self::assertInstanceOf(Microsoft365Deployment::class, $deployment);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute(self::ROUTE_SUBSCRIPTION_DEPLOYMENT, ['subscription' => $deployment->subscription->uuid]))
            ->assertOk()
            ->assertJson([
                'id' => $deployment->id,
                'subscription_uuid' => $deployment->subscription->uuid,
                'tenant_name' => $tenant->tenant_name,
                'kpn_order_id' => $deployment->kpn_order_id,
            ]);
    }

    public function testSubscriptionDeploymentReturnsNotFoundWhenSubscriptionHasNoDeployment(): void
    {
        $subscription = new SubscriptionFactory()->for($this->customer)->for($this->createMicrosoft365Product())->createOne();

        $this->actingAsEmployee()
            ->getJson($this->generateRoute(self::ROUTE_SUBSCRIPTION_DEPLOYMENT, ['subscription' => $subscription->uuid]))
            ->assertNotFound()
            ->assertJson(['message' => 'microsoft365.deployment.not-found']);
    }

    /**
     * @param array<string, mixed> $customerInfoAttributes
     */
    private function createTenantWithDeployment(Product $product, array $customerInfoAttributes = []): Microsoft365CustomerInfo
    {
        $subscription = new SubscriptionFactory()->for($this->customer)->for($product)->createOne();

        $customerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne($customerInfoAttributes);

        new Microsoft365DeploymentFactory()
            ->for($customerInfo)
            ->for($subscription)
            ->createOne();

        return $customerInfo->refresh()->load('microsoft365Deployments.subscription');
    }

    private function createMicrosoft365Product(): Product
    {
        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();

        return new ProductFactory()->for($productGroup)->createOne();
    }

    private function callbackDeployment(Microsoft365Deployment $expected): callable
    {
        return self::callback(
            static fn (Microsoft365Deployment $deployment): bool => $deployment->id === $expected->id
        );
    }

    /**
     * @param array<string, mixed> $customerInfoAttributes
     * @param array<string, mixed> $deploymentAttributes
     */
    private function createDeployment(
        int $activeChildCount,
        array $customerInfoAttributes = [],
        array $deploymentAttributes = [],
    ): Microsoft365Deployment {
        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $parentProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);
        $childProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard',
        ]);

        new Microsoft365KpnProductFactory()->for($parentProduct)->createOne([
            'contract_period' => 12,
            'kpn_product_code' => '120A00179B',
        ]);

        $parentSubscription = new SubscriptionFactory()->for($this->customer)->for($parentProduct)->createOne([
            'contract_period' => 12,
        ]);

        for ($i = 0; $i < $activeChildCount; $i++) {
            new SubscriptionFactory()
                ->for($this->customer)
                ->for($childProduct)
                ->parentSubscription($parentSubscription)
                ->administrativeStatusActive()
                ->createOne([
                    'contract_period' => 12,
                ]);
        }

        $customerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => 'CID12345',
            ...$customerInfoAttributes,
        ]);

        return new Microsoft365DeploymentFactory()
            ->for($customerInfo)
            ->for($parentSubscription)
            ->createOne([
                'kpn_order_id' => null,
                ...$deploymentAttributes,
            ]);
    }
}
