<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Services;

use Carbon\CarbonImmutable;
use Exception;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use SandwaveIo\Microsoft\Graph\GraphServiceClient;
use SandwaveIo\Microsoft\Graph\Models\DomainDnsCnameRecord;
use SandwaveIo\Microsoft\Graph\Models\DomainDnsMxRecord;
use SandwaveIo\Microsoft\Graph\Models\DomainDnsTxtRecord;
use SandwaveIo\Office365\Exception\Office365Exception;
use SandwaveIo\Office365\Office\OfficeClient;
use Tests\Factories\CustomerAddressFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\Microsoft365KpnProductFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\CnameRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\MxRecord;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryCustomerNotFoundException;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryException;
use Waterfront\Domain\Microsoft365\Exceptions\TenantNameTakenException;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365CustomerInfoRepository;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365KpnProductRepository;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365Repository;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Microsoft365\Services\Microsoft365TenantService;
use Waterfront\Domain\Products\Enums\ProductSlug;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365GetServiceDnsRecordsRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365TenantIdRequest;
use Waterfront\Domain\Provision\Microsoft365\Results\ServiceDnsRecordsResult;
use Waterfront\Domain\Provision\Microsoft365\Results\TenantIdResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Subscriptions\Actions\GetNextInvoicePriceAction;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(Microsoft365Service::class)]
#[AllowMockObjectsWithoutExpectations]
class Microsoft365ServiceTest extends IntegrationTestCase
{
    private const string DOMAIN = 'sandwave.io';

    private const string TENANT_ID = '8e4f6848-6efa-48f2-9cd7-343cab7de6ff';

    private UuidInterface $context;

    private Customer $customer;

    private ProductGroup $productGroup;

    private Product $product;

    private Microsoft365CustomerInfo $microsoft365CustomerInfo;

    private GetNextInvoicePriceAction $nextInvoicePriceAction;

    private Microsoft365Service $microsoft365Service;

    private GraphServiceClient&MockInterface $mockGraphServiceClient;

    private ProvisionGateway&MockInterface $mockProvisionGateway;

    private DnsService&MockObject $mockDnsService;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = Str::uuid();

        $this->customer = new CustomerFactory()->createOne();

        $this->productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $this->product = new ProductFactory()->for($this->productGroup)->createOne();

        $this->microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => 1,
        ]);

        new Microsoft365DeploymentFactory()
            ->for(
                new SubscriptionFactory()
                    ->for($this->customer)
                    ->for($this->product)
                    ->createOne()
            )
            ->for($this->microsoft365CustomerInfo)
            ->createOne();

        $this->nextInvoicePriceAction = self::resolve(GetNextInvoicePriceAction::class);

        $mockProvisionGateway = self::mock(ProvisionGateway::class);
        $this->mockProvisionGateway = $mockProvisionGateway;

        $mockGraphServiceClient = self::mock(GraphServiceClient::class);
        $this->mockGraphServiceClient = $mockGraphServiceClient;

        $this->mockDnsService = self::createMock(DnsService::class);

        $officeClient = new OfficeClient(
            'fake_url',
            'fake_username',
            'fake_password',
        );

        $this->microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            $this->getConfiguration(),
            self::createMock(LoggerInterface::class),
            $mockProvisionGateway,
            self::createMock(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createMock(Microsoft365CustomerInfoRepository::class),
            self::createMock(Microsoft365KpnProductRepository::class),
            self::createMock(Microsoft365Repository::class),
            self::createMock(DomainDeploymentRepository::class),
        );
    }

    #[Test]
    public function getTenantIdByNameNotFound(): void
    {
        $tenantName = 'tenant-test-name';
        $tenantNameWithHost = $tenantName . '.onmicrosoft.com';

        $mockException = new Exception('an exception');
        $mockLogger = self::createMock(LoggerInterface::class);
        $mockGateway = self::createMock(ProvisionGateway::class);

        $mockNotFoundResult = new TenantIdResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::FAILED,
            exception: $mockException,
        );

        $mockGateway->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (Microsoft365TenantIdRequest $request) => $request->tenantName === $tenantNameWithHost
                )
            )
            ->willReturn($mockNotFoundResult);

        $mockLogger->expects(self::once())
            ->method('warning')
            ->with(
                'Retrieving tenant id from name [meta.tenant_name] failed.',
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_ONLINE,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::META => [
                        'tenant_name' => $tenantNameWithHost,
                    ],
                    LoggingContextKeys::EXCEPTION => $mockException,
                ]
            );

        $mockHandler = new MockHandler();
        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $m365 = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            $mockLogger,
            $mockGateway,
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $tenant = $m365->getTenantIdByName($tenantName);
        self::assertNull($tenant);
    }

    #[Test]
    public function getTenantByIdSuccess(): void
    {
        $tenantName = 'tenant-test-name';
        $tenantNameWithHost = $tenantName . '.onmicrosoft.com';
        $expectedTenantId = '123';
        $mockGateway = self::createMock(ProvisionGateway::class);

        $mockNotFoundResult = new TenantIdResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::SUCCESS,
            tenantId: $expectedTenantId,
        );

        $mockGateway->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (Microsoft365TenantIdRequest $request) => $request->tenantName === $tenantNameWithHost
                )
            )
            ->willReturn($mockNotFoundResult);

        $mockHandler = new MockHandler();
        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $m365 = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            $mockGateway,
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $tenant = $m365->getTenantIdByName($tenantName);
        self::assertSame($expectedTenantId, $tenant);
    }

    #[Test]
    public function getTenantByIdSuccessWithHostname(): void
    {
        $tenantName = 'tenant-test-name.onmicrosoft.com';
        $expectedTenantId = '123';
        $mockGateway = self::createMock(ProvisionGateway::class);

        $mockNotFoundResult = new TenantIdResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::SUCCESS,
            tenantId: $expectedTenantId,
        );

        $mockGateway->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (Microsoft365TenantIdRequest $request) => $request->tenantName === $tenantName
                )
            )
            ->willReturn($mockNotFoundResult);

        $mockHandler = new MockHandler();
        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $m365 = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            $mockGateway,
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $tenant = $m365->getTenantIdByName($tenantName);
        self::assertSame($expectedTenantId, $tenant);
    }

    #[Test]
    public function tenantExists(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/MicrosoftTenantExistsCheckSuccessResponse_V1.xml')),
        ]);
        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $tenantExists = $microsoft365Service->isTenantNameTaken(tenantName: 'test.onmicrosoft.com');

        self::assertTrue($tenantExists);
    }

    #[Test]
    public function tenantExistsFailure(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/MicrosoftTenantExistsCheckFailureResponse_V1.xml')),
        ]);
        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $tenantExists = $microsoft365Service->isTenantNameTaken(tenantName: 'test.onmicrosoft.com');

        self::assertFalse($tenantExists);
    }

    #[Test]
    public function generateTenantName(): void
    {
        $generated_tenant = $this->microsoft365Service->generateTenantName(customer: $this->customer);

        self::assertSame($this->getConfiguration()->getAsString('microsoft365.tenant_prefix') . $this->customer->id . '.onmicrosoft.com', $generated_tenant);
        self::assertSame(18 + strlen((string) $this->customer->id), strlen($generated_tenant));

        $retry_generated_tenant = $this->microsoft365Service->generateTenantName(customer: $this->customer, retry: true);

        //If retry is true it will add a random 5 char string to it.
        self::assertSame(24 + strlen((string) $this->customer->id), strlen($retry_generated_tenant));
    }

    #[Test]
    public function orderSummary(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/OrderSummaryResponse_V1.xml')),
        ]);
        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $orderSummary = $microsoft365Service->orderSummary(customer: (int) $this->microsoft365CustomerInfo->kpn_customer_id, productName: $this->product->name);

        self::assertCount(3, $orderSummary);
        self::assertSame('Activate', $orderSummary[0]->getOrderState());
        self::assertSame(10, $orderSummary[0]->getQuantity());
    }

    #[Test]
    public function synchronizeTenantOrderIdFromOrderSummaryStoresExistingTenantOrderId(): void
    {
        $this->microsoft365CustomerInfo->tenant_order_id = null;
        $this->microsoft365CustomerInfo->save();

        $microsoft365Service = $this->createMicrosoft365Service('/Data/Response/OrderSummaryResponse_V1.xml');

        $tenantOrderIdSynchronized = $microsoft365Service->synchronizeTenantOrderIdFromOrderSummary($this->microsoft365CustomerInfo);

        self::assertTrue($tenantOrderIdSynchronized);
        self::assertSame(3, $this->microsoft365CustomerInfo->refresh()->tenant_order_id);
    }

    #[Test]
    public function synchronizeTenantOrderIdFromOrderSummaryReturnsFalseWhenNoTenantOrderExists(): void
    {
        $this->microsoft365CustomerInfo->tenant_order_id = null;
        $this->microsoft365CustomerInfo->save();

        $microsoft365Service = $this->createMicrosoft365Service('/Data/Response/OrderSummaryResponse_NoTenant.xml');

        $tenantOrderIdSynchronized = $microsoft365Service->synchronizeTenantOrderIdFromOrderSummary($this->microsoft365CustomerInfo);

        self::assertFalse($tenantOrderIdSynchronized);
        self::assertNull($this->microsoft365CustomerInfo->refresh()->tenant_order_id);
    }

    #[Test]
    public function synchronizeTenantOrderIdFromOrderSummaryReturnsFalseWhenTenantOrderIsNotActive(): void
    {
        $this->microsoft365CustomerInfo->tenant_order_id = null;
        $this->microsoft365CustomerInfo->save();

        $microsoft365Service = $this->createMicrosoft365Service('/Data/Response/OrderSummaryResponse_TenantNotActive.xml');

        $tenantOrderIdSynchronized = $microsoft365Service->synchronizeTenantOrderIdFromOrderSummary($this->microsoft365CustomerInfo);

        self::assertFalse($tenantOrderIdSynchronized);
        self::assertNull($this->microsoft365CustomerInfo->refresh()->tenant_order_id);
    }

    #[Test]
    public function prepareOrdersCreatesTenantWhenNoTenantOrderExists(): void
    {
        $this->microsoft365CustomerInfo->tenant_order_id = null;
        $this->microsoft365CustomerInfo->save();

        $microsoft365Service = $this->getMockBuilder(Microsoft365Service::class)
            ->setConstructorArgs([
                new OfficeClient(
                    'fake_url',
                    'fake_username',
                    'fake_password',
                ),
                $this->nextInvoicePriceAction,
                self::createMock(ConfigurationInterface::class),
                self::createStub(LoggerInterface::class),
                self::createMock(ProvisionGateway::class),
                self::createMock(Microsoft365TenantService::class),
                $this->mockGraphServiceClient,
                $this->mockDnsService,
                self::createMock(Microsoft365CustomerInfoRepository::class),
                self::resolve(Microsoft365KpnProductRepository::class),
                self::createMock(Microsoft365Repository::class),
                self::createMock(DomainDeploymentRepository::class),
            ])
            ->onlyMethods(['synchronizeTenantOrderIdFromOrderSummary', 'createTenant', 'createOrder'])
            ->getMock();
        $microsoft365Service->expects(self::once())
            ->method('synchronizeTenantOrderIdFromOrderSummary')
            ->with($this->microsoft365CustomerInfo)
            ->willReturn(false);
        $microsoft365Service->expects(self::once())
            ->method('createTenant')
            ->with($this->microsoft365CustomerInfo)
            ->willReturn(true);
        $microsoft365Service->expects(self::never())
            ->method('createOrder');

        $microsoft365Service->prepareOrders($this->microsoft365CustomerInfo);
    }

    #[Test]
    public function prepareOrdersCreatesOrdersWhenExistingTenantOrderIsFound(): void
    {
        $this->microsoft365CustomerInfo->tenant_order_id = null;
        $this->microsoft365CustomerInfo->save();

        $microsoft365Deployment = $this->microsoft365CustomerInfo->microsoft365Deployments()->firstOrFail();
        $parentSubscription = $microsoft365Deployment->subscription;
        $kpnProduct = new Microsoft365KpnProductFactory()->for($this->product)->createOne([
            'contract_period' => $parentSubscription->contract_period,
            'kpn_product_code' => '120A00179B',
        ]);
        new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->parentSubscription($parentSubscription)
            ->administrativeStatusActive()
            ->count(2)
            ->create();

        $microsoft365Service = $this->getMockBuilder(Microsoft365Service::class)
            ->setConstructorArgs([
                new OfficeClient(
                    'fake_url',
                    'fake_username',
                    'fake_password',
                ),
                $this->nextInvoicePriceAction,
                self::createMock(ConfigurationInterface::class),
                self::createStub(LoggerInterface::class),
                self::createMock(ProvisionGateway::class),
                self::createMock(Microsoft365TenantService::class),
                $this->mockGraphServiceClient,
                $this->mockDnsService,
                self::createMock(Microsoft365CustomerInfoRepository::class),
                self::resolve(Microsoft365KpnProductRepository::class),
                self::createMock(Microsoft365Repository::class),
                self::createMock(DomainDeploymentRepository::class),
            ])
            ->onlyMethods(['synchronizeTenantOrderIdFromOrderSummary', 'createTenant', 'createOrder'])
            ->getMock();
        $microsoft365Service->expects(self::once())
            ->method('synchronizeTenantOrderIdFromOrderSummary')
            ->with($this->microsoft365CustomerInfo)
            ->willReturn(true);
        $microsoft365Service->expects(self::never())
            ->method('createTenant');
        $microsoft365Service->expects(self::once())
            ->method('createOrder')
            ->with(
                self::isInstanceOf(Microsoft365Deployment::class),
                $kpnProduct->kpn_product_code,
                2,
            )
            ->willReturn(true);

        $microsoft365Service->prepareOrders($this->microsoft365CustomerInfo);
    }

    #[Test]
    public function prepareOrdersDoesNotCreateTenantWhenTenantOrderIdSynchronizationFails(): void
    {
        $this->microsoft365CustomerInfo->tenant_order_id = null;
        $this->microsoft365CustomerInfo->save();
        $orderSummaryException = new OrderSummaryException('Something went wrong while retrieving order summary.');

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Tenant order summary retrieval failed',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::M365,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::MICROSOFT_IRMA,
                    LoggingContextKeys::CUSTOMER_ID => $this->microsoft365CustomerInfo->customer->id,
                    LoggingContextKeys::EXCEPTION => $orderSummaryException,
                ],
            );

        $microsoft365Service = $this->getMockBuilder(Microsoft365Service::class)
            ->setConstructorArgs([
                new OfficeClient(
                    'fake_url',
                    'fake_username',
                    'fake_password',
                ),
                $this->nextInvoicePriceAction,
                self::createMock(ConfigurationInterface::class),
                $logger,
                self::createMock(ProvisionGateway::class),
                self::createMock(Microsoft365TenantService::class),
                $this->mockGraphServiceClient,
                $this->mockDnsService,
                self::createMock(Microsoft365CustomerInfoRepository::class),
                self::resolve(Microsoft365KpnProductRepository::class),
                self::createMock(Microsoft365Repository::class),
                self::createMock(DomainDeploymentRepository::class),
            ])
            ->onlyMethods(['synchronizeTenantOrderIdFromOrderSummary', 'createTenant', 'createOrder'])
            ->getMock();
        $microsoft365Service->expects(self::once())
            ->method('synchronizeTenantOrderIdFromOrderSummary')
            ->with($this->microsoft365CustomerInfo)
            ->willThrowException($orderSummaryException);
        $microsoft365Service->expects(self::never())
            ->method('createTenant');
        $microsoft365Service->expects(self::never())
            ->method('createOrder');

        $microsoft365Service->prepareOrders($this->microsoft365CustomerInfo);
    }

    #[Test]
    public function prepareOrdersSkipsDeploymentsWithAdministrativelyEndedSubscriptions(): void
    {
        $this->microsoft365CustomerInfo->tenant_order_id = 999;
        $this->microsoft365CustomerInfo->save();

        $activeDeployment = $this->microsoft365CustomerInfo->microsoft365Deployments()->firstOrFail();
        $kpnProduct = new Microsoft365KpnProductFactory()->for($this->product)->createOne([
            'contract_period' => $activeDeployment->subscription->contract_period,
            'kpn_product_code' => '120A00179B',
        ]);

        foreach ([AdministrativeStatus::ARCHIVED, AdministrativeStatus::EXPIRED, AdministrativeStatus::ARCHIVING] as $status) {
            new Microsoft365DeploymentFactory()
                ->for(
                    new SubscriptionFactory()
                        ->for($this->customer)
                        ->for($this->product)
                        ->administrativeStatus($status->value)
                        ->createOne()
                )
                ->for($this->microsoft365CustomerInfo)
                ->createOne();
        }

        $microsoft365Service = $this->getMockBuilder(Microsoft365Service::class)
            ->setConstructorArgs([
                new OfficeClient(
                    'fake_url',
                    'fake_username',
                    'fake_password',
                ),
                $this->nextInvoicePriceAction,
                self::createMock(ConfigurationInterface::class),
                self::createStub(LoggerInterface::class),
                self::createMock(ProvisionGateway::class),
                self::createMock(Microsoft365TenantService::class),
                $this->mockGraphServiceClient,
                $this->mockDnsService,
                self::createMock(Microsoft365CustomerInfoRepository::class),
                self::resolve(Microsoft365KpnProductRepository::class),
                self::createMock(Microsoft365Repository::class),
                self::createMock(DomainDeploymentRepository::class),
            ])
            ->onlyMethods(['synchronizeTenantOrderIdFromOrderSummary', 'createTenant', 'createOrder'])
            ->getMock();

        $microsoft365Service->expects(self::never())
            ->method('synchronizeTenantOrderIdFromOrderSummary');
        $microsoft365Service->expects(self::never())
            ->method('createTenant');
        $microsoft365Service->expects(self::once())
            ->method('createOrder')
            ->with(
                self::callback(fn (Microsoft365Deployment $deployment) => $deployment->id === $activeDeployment->id),
                $kpnProduct->kpn_product_code,
                0,
            )
            ->willReturn(true);

        $microsoft365Service->prepareOrders($this->microsoft365CustomerInfo->refresh());
    }

    #[Test]
    public function orderSummaryMissingCustomer(): void
    {
        $this->expectException(OrderSummaryCustomerNotFoundException::class);

        $mockHandler = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/OrderSummaryMissingCustomer.xml')),
        ]);
        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $microsoft365Service->orderSummary(customer: (int) $this->microsoft365CustomerInfo->kpn_customer_id, productName: $this->product->name);
    }

    #[Test]
    public function getTenantOrderIdReturnsTenantOrderId(): void
    {
        $microsoft365Service = $this->createMicrosoft365Service('/Data/Response/OrderSummaryResponse_V1.xml');

        $tenantOrderId = $microsoft365Service->getTenantOrderId((int) $this->microsoft365CustomerInfo->kpn_customer_id);

        self::assertSame(3, $tenantOrderId);
    }

    #[Test]
    public function getTenantOrderIdReturnsNullWhenNoTenantOrder(): void
    {
        $microsoft365Service = $this->createMicrosoft365Service('/Data/Response/OrderSummaryResponse_NoTenant.xml');

        $tenantOrderId = $microsoft365Service->getTenantOrderId((int) $this->microsoft365CustomerInfo->kpn_customer_id);

        self::assertNull($tenantOrderId);
    }

    #[Test]
    public function getTenantOrderIdThrowsExceptionWhenCustomerNotFound(): void
    {
        $this->expectException(OrderSummaryCustomerNotFoundException::class);

        $microsoft365Service = $this->createMicrosoft365Service('/Data/Response/OrderSummaryMissingCustomer.xml');

        $microsoft365Service->getTenantOrderId((int) $this->microsoft365CustomerInfo->kpn_customer_id);
    }

    #[Test]
    public function getTenantOrderIdReturnsNullWhenTenantOrderIsNotActive(): void
    {
        $microsoft365Service = $this->createMicrosoft365Service('/Data/Response/OrderSummaryResponse_TenantNotActive.xml');

        $tenantOrderId = $microsoft365Service->getTenantOrderId((int) $this->microsoft365CustomerInfo->kpn_customer_id);

        self::assertNull($tenantOrderId);
    }

    #[Test]
    public function modifyKpnCustomer(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/NinaResponse_Success.xml')),
        ]);
        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $modifyCustomer = $microsoft365Service->modifyKpnCustomer(
            'CID1322911',
            'Sandwave test',
            'StraatNaam',
            38,
            '',
            '1234AB',
            'Amsterdam',
            'NLD',
            '0612345678',
            'klant@email.nl',
            '134534659043869034809635435',
        );

        self::assertTrue($modifyCustomer);
    }

    #[Test]
    public function modifyKpnCustomerDeclined(): void
    {
        $loggerMock = self::createMock(LoggerInterface::class);
        $loggerMock->expects(self::once())
            ->method('error')
            ->with('ModifyKpnCustomer - KPN error code: 108, message: Failed, details: []');

        $mockHandler = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/NinaResponse_Fail.xml')),
        ]);
        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            $loggerMock,
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $modifyCustomer = $microsoft365Service->modifyKpnCustomer(
            'CID1322911',
            'Sandwave test',
            'StraatNaam',
            38,
            '',
            '1234AB',
            'Amsterdam',
            'NLD',
            '0612345678',
            'klant@fail',
            '134534659043869034809635435',
        );

        self::assertFalse($modifyCustomer);
    }

    #[DataProvider('streetNumberProvider')]
    #[Test]
    public function streetNumberLetters(string $street_number_full, int $street_number, ?string $street_number_letter): void
    {
        new CustomerAddressFactory()->for($this->customer)->create([
            'street_number' => $street_number_full,
        ]);

        self::assertInstanceOf(CustomerAddress::class, $this->customer->address);

        self::assertSame($street_number, intval($this->customer->address->street_number));
        self::assertSame($street_number_letter, $this->customer->address->getStreetNumberLetters());
    }

    /**
     * @return array<int, array<int, int|string|null>>
     */
    public static function streetNumberProvider(): array
    {
        return [
            ['42d', 42, 'd'],
            ['33', 33, null],
        ];
    }

    #[Test]
    public function kpnCustomerCreateEmailRemovedPlus(): void
    {
        self::assertSame('qa@sandwave.io', $this->microsoft365Service->removePlusFromEmail('qa+124@sandwave.io'));
        self::assertSame('qa124@sandwave.io', $this->microsoft365Service->removePlusFromEmail('qa124@sandwave.io'));
    }

    #[Test]
    public function hasDomainOwnershipReturnsTrue(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/MicrosoftTenantDomainOwnershipCheckResponse_V1.xml')),
        ]);
        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $hasDomainOwnership = $microsoft365Service->hasDomainOwnership('661e6518-891e-40e8-9735-f9a0d9518c5e');

        self::assertTrue($hasDomainOwnership);
    }

    #[Test]
    public function hasDomainOwnershipReturnsFalse(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/MicrosoftTenantDomainOwnershipCheckResponse_V1_returns_false.xml')),
        ]);
        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $hasDomainOwnership = $microsoft365Service->hasDomainOwnership('661e6518-891e-40e8-9735-f9a0d9518c5e');

        self::assertFalse($hasDomainOwnership);
    }

    #[Test]
    public function setServiceConfigurationRecordsForPrimaryDomainRecordsAlreadyExist(): void
    {
        $domainDnsTxtRecord = new DomainDnsTxtRecord();
        $domainDnsTxtRecord->setIsOptional(false);
        $domainDnsTxtRecord->setLabel('email');
        $domainDnsTxtRecord->setRecordType('Txt');
        $domainDnsTxtRecord->setSupportedService('Email');
        $domainDnsTxtRecord->setTtl(600);
        $domainDnsTxtRecord->setOdataType('microsoft.graph.domainDnsTxtRecord');
        $domainDnsTxtRecord->setText('v=spf1 include: spf.protection.outlook.com ~all');

        $domainDnsMxRecord = new DomainDnsMxRecord();
        $domainDnsMxRecord->setIsOptional(false);
        $domainDnsMxRecord->setLabel('email');
        $domainDnsMxRecord->setRecordType('Mx');
        $domainDnsMxRecord->setSupportedService('Email');
        $domainDnsMxRecord->setTtl(600);
        $domainDnsMxRecord->setOdataType('microsoft.graph.domainDnsMxRecord');
        $domainDnsMxRecord->setMailExchange('contoso-com.mail.protection.outlook.com');

        $records = [$domainDnsTxtRecord, $domainDnsMxRecord];

        $provisionRequest = new Microsoft365GetServiceDnsRecordsRequest(
            domainName: self::DOMAIN,
            context: Uuid::fromString(self::TENANT_ID),
            tagUuid: $this->context,
        );

        $this->mockProvisionGateway
            ->shouldReceive('request')
            ->once()
            ->withArgs(fn (ProvisionRequestInterface $request) => $request instanceof Microsoft365GetServiceDnsRecordsRequest)
            ->andReturn(new ServiceDnsRecordsResult($provisionRequest, ProvisionStatus::SUCCESS, $records));

        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $deleteMxRecord = new MxRecord('emailhosting', 'hosting.yourhosting.nl', 10, 600);
        self::assertNotNull($domainDnsTxtRecord->getLabel());
        self::assertNotNull($domainDnsTxtRecord->getText());
        self::assertNotNull($domainDnsTxtRecord->getTtl());
        self::assertNotNull($domainDnsMxRecord->getLabel());
        self::assertNotNull($domainDnsMxRecord->getMailExchange());
        self::assertNotNull($domainDnsMxRecord->getTtl());
        $zone->setRecords([
            new DefaultRecord(DnsRecordType::TXT->value, $domainDnsTxtRecord->getLabel(), $domainDnsTxtRecord->getText(), $domainDnsTxtRecord->getTtl()),
            new MxRecord($domainDnsMxRecord->getLabel(), $domainDnsMxRecord->getMailExchange(), 10, $domainDnsMxRecord->getTtl()),
            $deleteMxRecord,
        ]);
        $this->mockDnsService->expects(self::exactly(2))->method('getDnsRecordsForDomain')->willReturn(new Collection($zone->getRecords()));
        $this->mockDnsService->expects(self::never())->method('addRecordFromObject');
        $this->mockDnsService->expects(self::once())
            ->method('removeDnsRecord')
            ->with(
                self::DOMAIN,
                new MxRecord(
                    name: $deleteMxRecord->getName(),
                    content: $deleteMxRecord->getContent(),
                    priority: $deleteMxRecord->getPriority(),
                    ttl: $deleteMxRecord->getTtl(),
                ),
            );

        $hasDomainOwnership = $this->microsoft365Service->setServiceConfigurationRecordsForPrimaryDomain(
            domain: self::DOMAIN,
            tenantId: self::TENANT_ID,
            subscription: SubscriptionFactory::new()->makeOne(),
        );

        self::assertTrue($hasDomainOwnership);
    }

    #[Test]
    public function setServiceConfigurationRecordsForPrimaryDomainRecordsDoesNotExist(): void
    {
        $domainDnsTxtRecord = new DomainDnsTxtRecord();
        $domainDnsTxtRecord->setIsOptional(false);
        $domainDnsTxtRecord->setLabel(self::DOMAIN);
        $domainDnsTxtRecord->setRecordType('Txt');
        $domainDnsTxtRecord->setSupportedService('Email');
        $domainDnsTxtRecord->setTtl(600);
        $domainDnsTxtRecord->setOdataType('microsoft.graph.domainDnsTxtRecord');
        $domainDnsTxtRecord->setText('v=spf1 include: spf.protection.outlook.com ~all');

        $domainDnsMxRecord = new DomainDnsMxRecord();
        $domainDnsMxRecord->setIsOptional(false);
        $domainDnsMxRecord->setLabel(self::DOMAIN);
        $domainDnsMxRecord->setRecordType('Mx');
        $domainDnsMxRecord->setSupportedService('Email');
        $domainDnsMxRecord->setTtl(600);
        $domainDnsMxRecord->setPreference(32767);
        $domainDnsMxRecord->setOdataType('microsoft.graph.domainDnsMxRecord');
        $domainDnsMxRecord->setMailExchange('contoso-com.mail.protection.outlook.com');

        $domainDnsCnameRecord = new DomainDnsCnameRecord();
        $domainDnsCnameRecord->setIsOptional(false);
        $domainDnsCnameRecord->setLabel('autodiscover.' . self::DOMAIN);
        $domainDnsCnameRecord->setRecordType('CName');
        $domainDnsCnameRecord->setSupportedService('Email');
        $domainDnsCnameRecord->setTtl(600);
        $domainDnsCnameRecord->setOdataType('microsoft.graph.domainDnsCnameRecord');
        $domainDnsCnameRecord->setCanonicalName('autodiscover.outlook.com');

        $domainDnsCnameRecordIgnore = new DomainDnsCnameRecord();
        $domainDnsCnameRecordIgnore->setIsOptional(false);
        $domainDnsCnameRecordIgnore->setLabel('lyncdiscover.' . self::DOMAIN);
        $domainDnsCnameRecordIgnore->setRecordType('CName');
        $domainDnsCnameRecordIgnore->setSupportedService('OfficeCommunicationsOnline');
        $domainDnsCnameRecordIgnore->setTtl(600);
        $domainDnsCnameRecordIgnore->setOdataType('microsoft.graph.domainDnsCnameRecord');
        $domainDnsCnameRecordIgnore->setCanonicalName('autodiscover.outlook.com');

        $records = [$domainDnsTxtRecord, $domainDnsMxRecord, $domainDnsCnameRecord, $domainDnsCnameRecordIgnore];

        $provisionRequest = new Microsoft365GetServiceDnsRecordsRequest(
            domainName: self::DOMAIN,
            context: Uuid::fromString(self::TENANT_ID),
            tagUuid: $this->context,
        );

        $this->mockProvisionGateway
            ->shouldReceive('request')
            ->once()
            ->withArgs(fn (ProvisionRequestInterface $request) => $request instanceof Microsoft365GetServiceDnsRecordsRequest)
            ->andReturn(new ServiceDnsRecordsResult($provisionRequest, ProvisionStatus::SUCCESS, $records));

        self::assertNotNull($domainDnsTxtRecord->getRecordType());
        self::assertNotNull($domainDnsTxtRecord->getLabel());
        self::assertNotNull($domainDnsTxtRecord->getText());
        self::assertNotNull($domainDnsTxtRecord->getTtl());

        self::assertNotNull($domainDnsMxRecord->getLabel());
        self::assertNotNull($domainDnsMxRecord->getMailExchange());
        self::assertNotNull($domainDnsMxRecord->getPreference());
        self::assertNotNull($domainDnsMxRecord->getTtl());

        self::assertNotNull($domainDnsCnameRecord->getLabel());
        self::assertNotNull($domainDnsCnameRecord->getCanonicalName());
        self::assertNotNull($domainDnsCnameRecord->getTtl());

        $this->mockDnsService->expects(self::exactly(2))
            ->method('getDnsRecordsForDomain')
            ->willReturn(
                new Collection(
                    [
                        $existingMxRecord = new MxRecord(
                            name: self::DOMAIN,
                            content: 'primary.yourfilter.nl',
                            priority: 10,
                            ttl: 3600
                        ),
                        new DefaultRecord(
                            type: DnsRecordType::TXT->value,
                            name: self::DOMAIN,
                            content: 'SPF',
                            ttl: 3600
                        ),
                    ]
                )
            );
        $this->mockDnsService->expects(self::once())
            ->method('removeDnsRecord')
            ->with(self::DOMAIN, $existingMxRecord);
        $this->mockDnsService->expects(self::exactly(3))
            ->method('addRecordFromObject')
            ->with(
                ...self::withConsecutive(
                    [
                        self::DOMAIN,
                        new DefaultRecord(
                            type: strtoupper($domainDnsTxtRecord->getRecordType()),
                            name: $domainDnsTxtRecord->getLabel(),
                            content: $domainDnsTxtRecord->getText(),
                            ttl: $domainDnsTxtRecord->getTtl(),
                        ),
                    ],
                    [
                        self::DOMAIN,
                        new MxRecord(
                            name: $domainDnsMxRecord->getLabel(),
                            content: $domainDnsMxRecord->getMailExchange(),
                            priority: $domainDnsMxRecord->getPreference(),
                            ttl: $domainDnsMxRecord->getTtl(),
                        ),
                    ],
                    [
                        self::DOMAIN,
                        new CnameRecord(
                            name: $domainDnsCnameRecord->getLabel(),
                            content: $domainDnsCnameRecord->getCanonicalName(),
                            ttl: $domainDnsCnameRecord->getTtl(),
                        ),
                    ],
                )
            );

        $hasDomainOwnership = $this->microsoft365Service->setServiceConfigurationRecordsForPrimaryDomain(
            domain: self::DOMAIN,
            tenantId: self::TENANT_ID,
            subscription: SubscriptionFactory::new()->makeOne(),
        );

        self::assertTrue($hasDomainOwnership);
    }

    #[Test]
    public function getMicrosoftCustomerAgreementUrl(): void
    {
        $attestationId = '49a69a39-8244-4c51-9805-4f5aa3adac6b'; // from XML
        $attestationUrl = 'https://cdn.partner.microsoft.com/mca/?attestationid=49a69a39-8244-4c51-9805-4f5aa3adac6b'; // from XML
        $attestationStatus = 'Pending'; // from XML

        $microsoft365Service = $this->createMicrosoft365Service('/Data/Response/MicrosoftCustomerAgreementAttestationResponse_V1.xml');

        $response = $microsoft365Service->getMicrosoftCustomerAgreementUrl(
            $this->customer
        );

        self::assertSame($attestationId, $response->attestationId);
        self::assertSame($attestationUrl, $response->attestationUrl);
        self::assertSame($attestationStatus, $response->attestationStatus);
    }

    #[Test]
    public function getMicrosoftCustomerAgreement(): void
    {
        $dateSigned = '2025-09-10 00:00:00'; // from XML

        $microsoft365Service = $this->createMicrosoft365Service('/Data/Response/MicrosoftCustomerAgreementResponse_V1.xml');

        $response = $microsoft365Service->getMicrosoftCustomerAgreement(
            $this->customer
        );

        self::assertTrue($response->mcaSigned);
        self::assertEmpty($response->attestationId);
        self::assertNull($response->attestationUrl);
        self::assertNotNull($response->dateAgreed);
        self::assertSame($dateSigned, $response->dateAgreed->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function createTenantSuccessfully(): void
    {
        $microsoft365Service = $this->createMicrosoft365Service('/Data/Response/NinaResponse_Success.xml');

        $result = $microsoft365Service->createTenant(
            microsoft365CustomerInfo: $this->microsoft365CustomerInfo
        );
        self::assertTrue($result);
    }

    #[Test]
    public function createTenantFails(): void
    {
        $microsoft365Service = $this->createMicrosoft365Service('/Data/Response/NinaResponse_Fail.xml');

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                sprintf(
                    'CreateTenant - KPN error code: %d, message: %s, details: %s',
                    108,
                    'Failed',
                    '[]',
                )
            );

        $result = $microsoft365Service->createTenant(
            microsoft365CustomerInfo: $this->microsoft365CustomerInfo
        );
        self::assertFalse($result);
    }

    #[Test]
    public function createTenantThrowsTenantNameTakenException(): void
    {
        self::expectException(TenantNameTakenException::class);
        self::expectExceptionMessageIs('Something went wrong while checking tenant names.');

        $this->microsoft365CustomerInfo->tenant_name = null;

        $mockHandler = new MockHandler([new Response(500)]);
        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $microsoft365Service->createTenant($this->microsoft365CustomerInfo);
    }

    #[Test]
    public function createTenantThrowsOffice365Exception(): void
    {
        $mockHandler = new MockHandler(
            [new Response(500, [])]
        );

        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createMock(ConfigurationInterface::class),
            $this->logger = self::createMock(LoggerInterface::class),
            self::createMock(ProvisionGateway::class),
            self::createMock(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        self::expectException(Office365Exception::class);
        $microsoft365Service->createTenant($this->microsoft365CustomerInfo);
    }

    #[Test]
    public function createTenantWithTenantNameGenerationWhenTenantNameTaken(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/MicrosoftTenantExistsCheckSuccessResponse_V1.xml')),
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/MicrosoftTenantExistsCheckFailureResponse_V1.xml')),
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/NinaResponse_Success.xml')),
        ]);

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())
            ->method('error');

        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            $logger,
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $this->microsoft365CustomerInfo->tenant_name = null;
        $this->microsoft365CustomerInfo->tenant_id = null;
        $this->microsoft365CustomerInfo->save();

        $result = $microsoft365Service->createTenant(
            microsoft365CustomerInfo: $this->microsoft365CustomerInfo,
        );

        self::assertTrue($result);
    }

    #[Test]
    public function createOrderSuccessfully(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/NinaResponse_Success.xml')),
        ]);
        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $result = $microsoft365Service->createOrder(
            microsoft365Deployment: $this->createMicrosoft365DeploymentForCreateOrder(),
            productCode: 'CFQ7TTC0LH18',
            amount: 1,
        );

        self::assertTrue($result);
    }

    #[Test]
    public function createOrderFails(): void
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'CreateOrder - KPN error code: 108, message: Failed, details: []',
                self::callback(fn (array $context): bool => $context[LoggingContextKeys::CUSTOMER_ID] === $this->customer->id
                    && $context[LoggingContextKeys::PROVISIONING_TYPE] === ProvisionType::M365
                    && $context[LoggingContextKeys::PROVISIONING_PROVIDER] === ProvisionProvider::MICROSOFT_IRMA
                    && array_key_exists('microsoft365_customer_info', $context[LoggingContextKeys::META]))
            );

        $mockHandler = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/NinaResponse_Fail.xml')),
        ]);
        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            $logger,
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $result = $microsoft365Service->createOrder(
            microsoft365Deployment: $this->createMicrosoft365DeploymentForCreateOrder(),
            productCode: 'CFQ7TTC0LH18',
            amount: 1,
        );

        self::assertFalse($result);
    }

    #[Test]
    public function createOrderThrowsOffice365Exception(): void
    {
        $mockHandler = new MockHandler([
            new Response(500, []),
        ]);
        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        self::expectException(Office365Exception::class);

        $microsoft365Service->createOrder(
            microsoft365Deployment: $this->createMicrosoft365DeploymentForCreateOrder(),
            productCode: 'CFQ7TTC0LH18',
            amount: 1,
        );
    }

    #[Test]
    public function retryPendingCopilotOrderSendsPendingOrderToKpn(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/NinaResponse_Success.xml')),
        ]);

        [$microsoft365CustomerInfo, $copilotDeployment] = $this->createPendingCopilotRetryContext();
        $copilotChildProduct = $copilotDeployment->subscription->children()->firstOrFail()->product;

        new SubscriptionFactory()
            ->for($this->customer)
            ->for($copilotChildProduct)
            ->parentSubscription($copilotDeployment->subscription)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::REGISTRATION->value)
            ->createOne();

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'Retrying pending Copilot order',
                self::callback(
                    fn (array $context): bool => ($context[LoggingContextKeys::META]['seat_count'] ?? null) === 2
                )
            );

        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            $logger,
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::resolve(Microsoft365KpnProductRepository::class),
            self::resolve(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $microsoft365Service->retryPendingCopilotOrder($microsoft365CustomerInfo);

        self::assertCount(0, $mockHandler);
        self::assertSame(Microsoft365OrderStatus::ACCEPTED, $copilotDeployment->refresh()->kpn_status);
        self::assertNull($copilotDeployment->kpn_order_id);
    }

    #[Test]
    public function retryPendingCopilotOrderDoesNotRetryAcceptedOrder(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/NinaResponse_Success.xml')),
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/NinaResponse_Success.xml')),
        ]);

        [$microsoft365CustomerInfo] = $this->createPendingCopilotRetryContext();
        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::resolve(Microsoft365KpnProductRepository::class),
            self::resolve(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $microsoft365Service->retryPendingCopilotOrder($microsoft365CustomerInfo);
        $microsoft365Service->retryPendingCopilotOrder($microsoft365CustomerInfo);

        self::assertCount(1, $mockHandler);
    }

    #[Test]
    public function retryPendingCopilotOrderDoesNothingWithoutPendingOrder(): void
    {
        $mockHandler = new MockHandler();

        $microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => 'CID123456',
            'tenant_name' => 'test.onmicrosoft.com',
        ]);

        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::resolve(Microsoft365KpnProductRepository::class),
            self::resolve(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $microsoft365Service->retryPendingCopilotOrder($microsoft365CustomerInfo);

        self::assertCount(0, $mockHandler);
    }

    #[Test]
    public function retryPendingCopilotOrderDoesNotRetryOrderWithKpnOrderId(): void
    {
        $mockHandler = new MockHandler();

        [$microsoft365CustomerInfo, $copilotDeployment] = $this->createPendingCopilotRetryContext(
            copilotKpnOrderId: 987654,
        );

        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::resolve(Microsoft365KpnProductRepository::class),
            self::resolve(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $microsoft365Service->retryPendingCopilotOrder($microsoft365CustomerInfo);

        self::assertCount(0, $mockHandler);
        self::assertSame(Microsoft365OrderStatus::PLACED, $copilotDeployment->refresh()->kpn_status);
    }

    #[Test]
    public function retryPendingCopilotOrderSkipsRetryWithoutActivePrerequisite(): void
    {
        $mockHandler = new MockHandler();

        [$microsoft365CustomerInfo, $copilotDeployment] = $this->createPendingCopilotRetryContext(
            hasActivePrerequisite: false,
        );

        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::resolve(Microsoft365KpnProductRepository::class),
            self::resolve(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $microsoft365Service->retryPendingCopilotOrder($microsoft365CustomerInfo);

        self::assertCount(0, $mockHandler);
        self::assertSame(Microsoft365OrderStatus::PLACED, $copilotDeployment->refresh()->kpn_status);
    }

    #[Test]
    public function retryPendingCopilotOrdersLogsFailureAndContinues(): void
    {
        $mockHandler = new MockHandler();
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Pending Copilot retry failed',
                self::callback(fn (array $context): bool => array_key_exists(LoggingContextKeys::EXCEPTION, $context))
            );

        [$microsoft365CustomerInfo, $copilotDeployment] = $this->createPendingCopilotRetryContext();
        $microsoft365KpnProductRepository = self::createMock(Microsoft365KpnProductRepository::class);
        $microsoft365KpnProductRepository->expects(self::once())
            ->method('getBySubscription')
            ->willThrowException(new ModelNotFoundException());

        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            $logger,
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            $microsoft365KpnProductRepository,
            self::resolve(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $microsoft365Service->retryPendingCopilotOrder($microsoft365CustomerInfo);

        self::assertCount(0, $mockHandler);
        self::assertSame(Microsoft365OrderStatus::PLACED, $copilotDeployment->refresh()->kpn_status);
    }

    #[Test]
    public function retryPendingCopilotOrderLogsUnsuccessfulResponseAndContinues(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/NinaResponse_Fail.xml')),
        ]);
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))
            ->method('error')
            ->with(...self::withConsecutive(
                [
                    'CreateOrder - KPN error code: 108, message: Failed, details: []',
                    self::anything(),
                ],
                [
                    'Pending Copilot retry failed unsuccessful response',
                    self::callback(fn (array $context): bool => array_key_exists(LoggingContextKeys::SUBSCRIPTION_ID, $context)),
                ],
            ));

        [$microsoft365CustomerInfo, $copilotDeployment] = $this->createPendingCopilotRetryContext();

        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            $logger,
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::resolve(Microsoft365KpnProductRepository::class),
            self::resolve(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $microsoft365Service->retryPendingCopilotOrder($microsoft365CustomerInfo);

        self::assertCount(0, $mockHandler);
        self::assertSame(Microsoft365OrderStatus::PLACED, $copilotDeployment->refresh()->kpn_status);
    }

    #[Test]
    public function terminateOrderSendsFourDaysAgoAsDesiredTerminateDate(): void
    {
        CarbonImmutable::setTestNow('2026-06-02 10:00:00');

        $mockHandler = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/Data/Response/NinaResponse_Success.xml')),
        ]);
        $capturedRequests = [];
        $history = Middleware::history($capturedRequests);

        $stack = HandlerStack::create($mockHandler);
        $stack->push($history);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $microsoft365Deployment = new Microsoft365DeploymentFactory()
            ->for(
                new SubscriptionFactory()
                    ->for($this->customer)
                    ->for($this->product)
                    ->createOne()
            )
            ->for($this->microsoft365CustomerInfo)
            ->createOne([
                'kpn_status' => Microsoft365OrderStatus::PLACED,
                'kpn_order_id' => 123456,
            ]);

        $microsoft365Service = new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        );

        $result = $microsoft365Service->terminateOrder($microsoft365Deployment);

        self::assertTrue($result);
        self::assertIsArray($capturedRequests);
        self::assertCount(1, $capturedRequests);

        $body = (string) $capturedRequests[0]['request']->getBody();
        self::assertStringContainsString(
            '<DesiredTerminateDate><![CDATA[2026-05-29]]></DesiredTerminateDate>',
            $body,
        );
    }

    private function createMicrosoft365Service(string $filename): Microsoft365Service
    {
        $mockHandler = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . $filename)),
        ]);

        $stack = HandlerStack::create($mockHandler);
        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        return new Microsoft365Service(
            $officeClient,
            $this->nextInvoicePriceAction,
            self::createMock(ConfigurationInterface::class),
            $this->logger = self::createMock(LoggerInterface::class),
            self::createMock(ProvisionGateway::class),
            self::createMock(Microsoft365TenantService::class),
            $this->mockGraphServiceClient,
            $this->mockDnsService,
            self::createMock(Microsoft365CustomerInfoRepository::class),
            self::createMock(Microsoft365KpnProductRepository::class),
            self::createMock(Microsoft365Repository::class),
            self::createMock(DomainDeploymentRepository::class),
        );
    }

    private function createMicrosoft365DeploymentForCreateOrder(): Microsoft365Deployment
    {
        $this->microsoft365CustomerInfo->kpn_customer_id = 'CID123456';
        $this->microsoft365CustomerInfo->save();

        return new Microsoft365DeploymentFactory()
            ->for(
                new SubscriptionFactory()
                    ->for($this->customer)
                    ->for($this->product)
                    ->createOne()
            )
            ->for($this->microsoft365CustomerInfo)
            ->createOne([
                'kpn_status' => Microsoft365OrderStatus::PLACED,
                'kpn_order_id' => null,
            ]);
    }

    /**
     * @return array{0: Microsoft365CustomerInfo, 1: Microsoft365Deployment}
     */
    private function createPendingCopilotRetryContext(
        bool $hasActivePrerequisite = true,
        ?int $copilotKpnOrderId = null,
        bool $hasKpnProduct = true,
    ): array {
        $microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => 'CID123456',
            'tenant_name' => 'test.onmicrosoft.com',
        ]);

        if ($hasActivePrerequisite) {
            $baseParentProduct = new ProductFactory()->for($this->productGroup)->createOne();
            $baseChildProduct = new ProductFactory()
                ->for($this->productGroup)
                ->has(ProductSpecFactory::new()->enable(ProductSpecName::MICROSOFT365_ALLOW_COPILOT))
                ->createOne();

            $baseParentSubscription = new SubscriptionFactory()
                ->for($this->customer)
                ->for($baseParentProduct)
                ->administrativeStatusActive()
                ->technicalStatusOk()
                ->createOne();

            new SubscriptionFactory()
                ->for($this->customer)
                ->for($baseChildProduct)
                ->parentSubscription($baseParentSubscription)
                ->administrativeStatusActive()
                ->technicalStatusOk()
                ->createOne();

            new Microsoft365DeploymentFactory()
                ->for($baseParentSubscription)
                ->for($microsoft365CustomerInfo)
                ->createOne();
        }

        $copilotParentProduct = new ProductFactory()->for($this->productGroup)->createOne([
            'slug' => ProductSlug::MICROSOFT_COPILOT_PARENT->value,
        ]);
        $copilotChildProduct = new ProductFactory()->for($this->productGroup)->createOne([
            'slug' => ProductSlug::MICROSOFT_COPILOT->value,
        ]);

        $copilotParentSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($copilotParentProduct)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::REGISTRATION->value)
            ->createOne();

        new SubscriptionFactory()
            ->for($this->customer)
            ->for($copilotChildProduct)
            ->parentSubscription($copilotParentSubscription)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::REGISTRATION->value)
            ->createOne();

        $copilotDeployment = new Microsoft365DeploymentFactory()
            ->for($copilotParentSubscription)
            ->for($microsoft365CustomerInfo)
            ->createOne([
                'kpn_order_id' => $copilotKpnOrderId,
                'kpn_status' => Microsoft365OrderStatus::PLACED,
            ]);

        if ($hasKpnProduct) {
            new Microsoft365KpnProductFactory()
                ->for($copilotParentProduct)
                ->createOne([
                    'contract_period' => $copilotParentSubscription->contract_period,
                    'kpn_product_code' => '120A01070B',
                ]);
        }

        return [$microsoft365CustomerInfo, $copilotDeployment];
    }
}
