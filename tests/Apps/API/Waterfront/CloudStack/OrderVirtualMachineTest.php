<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\CloudStack;

use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Serializer\Serializer;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Interfaces\AdminClientFactoryInterface;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Models\Environment;
use Waterfront\Domain\VPS\Models\EnvironmentProduct;
use Waterfront\Domain\VPS\Services\AdminClientFactory;
use Waterfront\Domain\VPS\Services\ClientFactory;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\CloudStackPaginationIterator;
use Waterfront\Infra\CloudStackClient\DTO\Account;
use Waterfront\Infra\CloudStackClient\DTO\Domain;
use Waterfront\Infra\CloudStackClient\DTO\Network;
use Waterfront\Infra\CloudStackClient\DTO\Template;
use Waterfront\Infra\CloudStackClient\DTO\Zone;
use Waterfront\Infra\CloudStackClient\Mapper\AccountMapper;
use Waterfront\Infra\CloudStackClient\Mapper\DomainMapper;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;

#[CoversClass(OrderController::class)]
class OrderVirtualMachineTest extends IntegrationTestCase
{
    protected Customer $customer;

    /** @var array<string, array<int|string, mixed>|int|string> */
    private array $cloudStackFinishedJob;

    private Product $vps32;

    private Product $ubuntuLTS;

    private Serializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $vpsGroup = new ProductGroupFactory()->createOne([
            'name'        => 'VPS',
            'slug'        => ProductGroupType::VPS,
            'ledger_code' => 1337,
        ]);

        $this->vps32 = new ProductFactory()->createOne([
            'product_group_id' => $vpsGroup->id,
            'name'             => 'VPS 32 Redundant',
            'slug'             => 'vps-32-red',
            'description'      => 'VPS 32 reduntant omschrijving',
            'orderable'        => true,
            'weight'           => 1,
        ]);

        new ProductPriceComponentFactory()->for($this->vps32)->registration()->createOne(['price'   => 96]);
        new ProductPriceComponentFactory()->for($this->vps32)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 96]);

        $cloudstackOS = new ProductGroupFactory()
            ->createOne([
                'name'           => 'VPS OSs',
                'slug'           => ProductGroupType::CLOUDSTACK_OS,
                'ledger_code' => 1337,
            ]);

        $ubuntuLTS = new ProductFactory()
            ->has(
                ProductSpecFactory::new(
                    [
                        'name'  => ProductSpecName::VPS_CLOUDSTACK_TEMPLATE_SLUG->value,
                        'value' => 'Ubuntu-20.04',
                    ]
                ),
                'productSpecs'
            )
            ->createOne([
                'product_group_id' => $cloudstackOS->id,
                'name'             => 'Ubuntu LTS 20.04',
                'slug'             => 'ubuntu-lts-20.04',
                'description'      => 'Ubuntu LTS 20.04',
                'orderable'        => true,
                'weight'           => 1,
            ]);

        $ubuntuLTS->uuid = 'e0e82a4f-3028-41b3-8928-5d040ea182c2';
        $ubuntuLTS->save();

        $this->ubuntuLTS = $ubuntuLTS;

        $environment = Environment::create([
            'slug'                  => 'TEST01',
            'name'                  => 'Test Environment',
            'api_url'               => 'https://api',
            'ui_url'                => 'https://ui',
            'domain_id'             => Str::uuid()->toString(),
            'domain_name'           => 'test/vps',
            'preferred'             => true,
            'default_email_address' => 'support@example.com',
            'default_role_id'       => Str::uuid()->toString(),
            'api_key'               => 'api-test-key',
            'secret_key'            => 'secret-test-key',
        ]);

        new EnvironmentProduct([
            'product_identifier' => 'd8a23fc6-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        ])
            ->environment()->associate($environment)
            ->product()->associate($this->vps32)
            ->save();

        new EnvironmentProduct([
            'product_identifier' => 'd8a23fc6-bbbb-bbbb-bbbb-bbbbbbbbbbbc',
        ])
            ->environment()->associate($environment)
            ->product()->associate($ubuntuLTS)
            ->save();

        $cloudstackJobFinishedResponse = (string) file_get_contents(__DIR__ . '/data/finished_job.json');

        /** @var array<string, array<int|string, mixed>|int|string> $cloudStackFinishedJob */
        $cloudStackFinishedJob = json_decode($cloudstackJobFinishedResponse, true, 512, JSON_THROW_ON_ERROR);
        $this->cloudStackFinishedJob = $cloudStackFinishedJob;

        $this->serializer = CloudstackSerializerFactory::get();
    }

    #[Test]
    public function orderWithFreeOsChild(): void
    {
        new ProductPriceComponentFactory()->for($this->ubuntuLTS)->registration()->createOne([
            'billing_period'  => 1,
            'contract_period' => 1,
            'price'   => 0,
        ]);

        /**
         * We validate that our job is dispatched by checking if the 'execute'
         * method is used in the client to call queryAsyncJobResult on the
         * cloudstack API. This is done by our DeployVirtualMachine job.
         */
        $baseClientMock = self::mock(CloudStackBaseClient::class);
        $cloudStackFinishedJob = $this->cloudStackFinishedJob;

        $baseClientMock->shouldReceive('execute')
            ->once()
            ->with('listDomainChildren', self::anything())
            ->andReturn([]);

        $baseClientMock->shouldReceive('execute')
            ->once()
            ->with('listAccounts', self::anything())
            ->andReturn([]);

        $baseClientMock->shouldReceive('execute')
            ->once()
            ->with('queryAsyncJobResult', ['jobid' => '83372e9a-1041-4842-a7a8-68ea8c4b78a4'])
            ->andReturn($cloudStackFinishedJob);

        $this->app->bind(CloudStackBaseClient::class, fn () => $baseClientMock);

        $clientMock = self::createMock(CloudStackClient::class);

        /** @var array{network: array<mixed>} $response */
        $response = json_decode((string) file_get_contents(__DIR__ . '/data/list-networks.json'), true, 512, JSON_THROW_ON_ERROR);

        /** @var array<Network> $networks */
        $networks = CloudstackSerializerFactory::get()->denormalize($response['network'], Network::class . '[]');
        $clientMock->expects(self::once())
            ->method('listNetworks')
            ->willReturn($networks);

        $clientMock->expects(self::once())
            ->method('listZones')
            ->willReturn(
                new Zone(
                    id: '1',
                    name: 'test',
                    networktype: 'Advanced',
                    securitygroupsenabled: true,
                    allocationstate: 'Enabled',
                    zonetoken: 'test',
                    dhcpprovider: 'DhcpProvider',
                    localstorageenabled: true,
                )
            );

        $clientMock->expects(self::once())
            ->method('createAccount')
            ->willReturn(new Account('1', 'test', 'test'));
        $clientMock->expects(self::once())
            ->method('createDomain')
            ->willReturn(new Domain('1', 'test', 'test'));
        $clientMock->expects(self::once())
            ->method('createSecurityGroup')
            ->willReturn('1');
        $clientMock->expects(self::once())
            ->method('authorizeSecurityGroupIngress');
        $clientMock->expects(self::exactly(2))
            ->method('listDomainChildren')
            ->willReturn(
                new CloudStackPaginationIterator(
                    $baseClientMock,
                    'listDomainChildren',
                    [],
                    'domain',
                    new DomainMapper()
                )
            );
        $clientMock->expects(self::once())
            ->method('listAccounts')
            ->willReturn(
                new CloudStackPaginationIterator($baseClientMock, 'listAccounts', [], 'account', new AccountMapper())
            );

        $mockTemplate = self::createStub(Template::class);
        $mockTemplate->sshKeyEnabled = false;
        $mockTemplate->passwordEnabled = true;
        $mockTemplate->id = 'fa685028-1f5f-4acd-82a3-1693b91e605a';

        $clientMock->expects(self::once())
            ->method('listTemplates')
            ->with('Ubuntu-20.04')
            ->willReturn([$mockTemplate]);

        $cloudstackJobResponse = (string) file_get_contents(__DIR__ . '/data/created_job.json');

        /** @var array<string, string> $cloudStackCreatedJob */
        $cloudStackCreatedJob = json_decode($cloudstackJobResponse, true, 512, JSON_THROW_ON_ERROR);
        $asyncJobResponse = $this->serializer->denormalize(
            $cloudStackCreatedJob,
            AsynchronousCloudstackResponse::class
        );

        $clientMock->expects(self::once())
            ->method('deployVirtualMachine')
            ->willReturn($asyncJobResponse);

        $clientMock->expects(self::once())
            ->method('getBaseClient')
            ->willReturn($baseClientMock);

        $clientFactoryMock = self::createStub(ClientFactoryInterface::class);
        $clientFactoryMock->method('create')
            ->willReturn($clientMock);

        $this->app->bind(ClientFactory::class, fn () => $clientFactoryMock);

        $clientAdminFactoryMockInterface = self::createStub(AdminClientFactoryInterface::class);
        $clientAdminFactoryMockInterface->method('create')
            ->willReturn($clientMock);

        $clientAdminFactoryMock = self::createStub(AdminClientFactory::class);
        $clientAdminFactoryMock->method('create')
            ->willReturn($clientMock);

        $this->app->bind(AdminClientFactoryInterface::class, fn () => $clientAdminFactoryMockInterface);
        $this->app->bind(AdminClientFactory::class, fn () => $clientAdminFactoryMock);

        self::assertFalse(Subscription::where('product_uuid', $this->vps32->uuid)->exists());

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_virtual_machine_with_free_os.json');

        $payload = (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.order.order'),
            $payload
        )->assertOk();

        $subscription = Subscription::where('product_uuid', $this->vps32->uuid)->firstOrFail();
        $subscriptionChild = $subscription->children->firstOrFail();
        $parentOrderLineItem = $subscription->orderLineItem;
        self::assertInstanceOf(OrderLineItem::class, $parentOrderLineItem);
        self::assertNull($parentOrderLineItem->parent_id);
        self::assertCount(1, $parentOrderLineItem->children);
        $childOrderLineItem = $parentOrderLineItem->children->firstOrFail();
        self::assertSame($this->ubuntuLTS->name, $childOrderLineItem->product_name);
        self::assertSame($subscriptionChild->uuid, $childOrderLineItem->subscription_uuid);
        self::assertDatabaseHas('subscriptions', [
            'product_uuid'          => $this->vps32->uuid,
            'customer_id'           => $this->customer->id,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ]);
    }

    #[Test]
    public function orderWithOsChild(): void
    {
        new ProductPriceComponentFactory()->for($this->ubuntuLTS)->registration()->createOne([
            'billing_period'  => 1,
            'contract_period' => 1,
            'price'   => 10,
        ]);

        /**
         * We validate that our job is dispatched by checking if the 'execute'
         * method is used in the client to call queryAsyncJobResult on the
         * cloudstack API. This is done by our DeployVirtualMachine job.
         *
         */
        $baseClientMock = self::mock(CloudStackBaseClient::class);
        $cloudStackFinishedJob = $this->cloudStackFinishedJob;

        $baseClientMock->shouldReceive('execute')
            ->once()
            ->with('listDomainChildren', self::anything())
            ->andReturn([]);

        $baseClientMock->shouldReceive('execute')
            ->once()
            ->with('listAccounts', self::anything())
            ->andReturn([]);

        $baseClientMock->shouldReceive('execute')
            ->once()
            ->with('queryAsyncJobResult', ['jobid' => '83372e9a-1041-4842-a7a8-68ea8c4b78a4'])
            ->andReturn($cloudStackFinishedJob);

        $this->app->bind(CloudStackBaseClient::class, fn () => $baseClientMock);

        $clientMock = self::createMock(CloudStackClient::class);

        /** @var array{network: array<mixed>} $response */
        $response = json_decode((string) file_get_contents(__DIR__ . '/data/list-networks.json'), true, 512, JSON_THROW_ON_ERROR);

        /** @var array<Network> $networks */
        $networks = CloudstackSerializerFactory::get()->denormalize($response['network'], Network::class . '[]');
        $clientMock->expects(self::once())
            ->method('listNetworks')
            ->willReturn($networks);

        $clientMock->expects(self::once())
            ->method('listZones')
            ->willReturn(
                new Zone(
                    id: '1',
                    name: 'test',
                    networktype: 'Advanced',
                    securitygroupsenabled: true,
                    allocationstate: 'Enabled',
                    zonetoken: 'test',
                    dhcpprovider: 'DhcpProvider',
                    localstorageenabled: true,
                )
            );

        $clientMock->expects(self::once())
            ->method('createAccount')
            ->willReturn(new Account('1', 'test', 'test'));
        $clientMock->expects(self::once())
            ->method('createDomain')
            ->willReturn(new Domain('1', 'test', 'test'));
        $clientMock->expects(self::once())
            ->method('createSecurityGroup')
            ->willReturn('1');
        $clientMock->expects(self::once())
            ->method('authorizeSecurityGroupIngress');
        $clientMock->expects(self::exactly(2))
            ->method('listDomainChildren')
            ->willReturn(
                new CloudStackPaginationIterator(
                    $baseClientMock,
                    'listDomainChildren',
                    [],
                    'domain',
                    new DomainMapper()
                )
            );
        $clientMock->expects(self::once())
            ->method('listAccounts')
            ->willReturn(
                new CloudStackPaginationIterator($baseClientMock, 'listAccounts', [], 'account', new AccountMapper())
            );

        $mockTemplate = self::createStub(Template::class);
        $mockTemplate->sshKeyEnabled = false;
        $mockTemplate->passwordEnabled = true;
        $mockTemplate->id = 'fa685028-1f5f-4acd-82a3-1693b91e605a';

        $clientMock->expects(self::once())
            ->method('listTemplates')
            ->with('Ubuntu-20.04')
            ->willReturn([$mockTemplate]);

        $cloudstackJobResponse = (string) file_get_contents(__DIR__ . '/data/created_job.json');

        /** @var array<string, string> $cloudStackCreatedJob */
        $cloudStackCreatedJob = json_decode($cloudstackJobResponse, true, 512, JSON_THROW_ON_ERROR);
        $asyncJobResponse = $this->serializer->denormalize(
            $cloudStackCreatedJob,
            AsynchronousCloudstackResponse::class
        );

        $clientMock->expects(self::once())
            ->method('deployVirtualMachine')
            ->willReturn($asyncJobResponse);

        $clientMock->expects(self::once())
            ->method('getBaseClient')
            ->willReturn($baseClientMock);

        $clientFactoryMock = self::createStub(ClientFactoryInterface::class);
        $clientFactoryMock->method('create')
            ->willReturn($clientMock);

        $this->app->bind(ClientFactory::class, fn () => $clientFactoryMock);

        $clientAdminFactoryMockInterface = self::createStub(AdminClientFactoryInterface::class);
        $clientAdminFactoryMockInterface->method('create')
            ->willReturn($clientMock);

        $clientAdminFactoryMock = self::createStub(AdminClientFactory::class);
        $clientAdminFactoryMock->method('create')
            ->willReturn($clientMock);

        $this->app->bind(AdminClientFactoryInterface::class, fn () => $clientAdminFactoryMockInterface);
        $this->app->bind(AdminClientFactory::class, fn () => $clientAdminFactoryMock);

        self::assertFalse(Subscription::where('product_uuid', $this->vps32->uuid)->exists());

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_virtual_machine_with_os.json');

        $payload = (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.order.order'),
            $payload
        )->assertOk();

        $subscription = Subscription::where('product_uuid', $this->vps32->uuid)->firstOrFail();
        $subscriptionChild = $subscription->children->firstOrFail();
        $parentOrderLineItem = $subscription->orderLineItem;
        self::assertInstanceOf(OrderLineItem::class, $parentOrderLineItem);
        self::assertNull($parentOrderLineItem->parent_id);
        self::assertCount(1, $parentOrderLineItem->children);
        $childOrderLineItem = $parentOrderLineItem->children->firstOrFail();
        self::assertSame($this->ubuntuLTS->name, $childOrderLineItem->product_name);
        self::assertSame($subscriptionChild->uuid, $childOrderLineItem->subscription_uuid);
        self::assertDatabaseHas('subscriptions', [
            'product_uuid'          => $this->vps32->uuid,
            'customer_id'           => $this->customer->id,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ]);
    }
}
