<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Integration;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Serializer;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CloudstackVirtualMachineDeploymentFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SshKeyFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\Actions\ResolveAndLinkSshKeyAction;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Exceptions\CloudstackNotFoundException;
use Waterfront\Domain\VPS\Exceptions\VirtualMachineNotFoundException;
use Waterfront\Domain\VPS\Interfaces\AdminClientFactoryInterface;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineDeploymentRepositoryInterface;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineServiceInterface;
use Waterfront\Domain\VPS\Jobs\DeployVirtualMachineJob;
use Waterfront\Domain\VPS\Models\Environment;
use Waterfront\Domain\VPS\Models\EnvironmentProduct;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Repositories\CloudstackJobRepository;
use Waterfront\Domain\VPS\Repositories\EnvironmentProductRepository;
use Waterfront\Domain\VPS\Repositories\EnvironmentRepository;
use Waterfront\Domain\VPS\Repositories\ManagerDomainDeploymentRepository;
use Waterfront\Domain\VPS\Services\AdminClientFactory;
use Waterfront\Domain\VPS\Services\ClientFactory;
use Waterfront\Domain\VPS\Services\ManagerDomainService;
use Waterfront\Domain\VPS\Services\VpsService;
use Waterfront\Domain\VPS\Services\VpsTemplateService;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\CloudStackPaginationIterator;
use Waterfront\Infra\CloudStackClient\DTO\Account;
use Waterfront\Infra\CloudStackClient\DTO\Domain;
use Waterfront\Infra\CloudStackClient\DTO\Network;
use Waterfront\Infra\CloudStackClient\DTO\Template;
use Waterfront\Infra\CloudStackClient\DTO\VirtualMachine;
use Waterfront\Infra\CloudStackClient\DTO\Zone;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;
use Waterfront\Infra\CloudStackClient\Mapper\AccountMapper;
use Waterfront\Infra\CloudStackClient\Mapper\DomainMapper;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;

#[CoversClass(VpsService::class)]
#[AllowMockObjectsWithoutExpectations]
class VpsServiceTest extends IntegrationTestCase
{
    private string $contactFirstName = 'Test';

    private string $contactLastName = 'Kees';

    private string $contactEmail = 'test@kees.nl';

    private string $templateSlug = 'Ubuntu-24.04';

    private Customer $otherCustomer;

    private Subscription $subscription;

    private Product $operatingSystemProduct;

    private Environment $environment;

    #[Test]
    public function missingEnvironment(): void
    {
        $subscriptionUuid = 'fd028b7c-7347-2903-2354-76df8938d893';

        $cloudStackOsProductGroup = new ProductGroupFactory()->createOne([
            'name' => 'VPS Operating System',
            'slug' => 'cloudstack-os',
            'ledger_code' => 7331,
        ]);

        $vpsProduct = ProductFactory::new()->vps()->createOne();

        $this->operatingSystemProduct = new ProductFactory()->for($cloudStackOsProductGroup)->createOne([
            'name' => 'Operating System',
            'slug' => 'cloudstack-os',
            'description' => 'VPS operating system',
            'orderable' => true,
            'weight' => 1,
        ]);

        new ProductPriceComponentFactory()
            ->for($vpsProduct)
            ->registration()
            ->createOne(['billing_period' => 1, 'contract_period' => 1, 'price' => 0]);

        $this->subscription = new SubscriptionFactory()->makeOne([
            'uuid' => $subscriptionUuid,
            'billing_period' => 1,
            'contract_period' => 1,
        ]);

        $osProductSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusActive()
            ->for($this->operatingSystemProduct, 'product')
            ->createOne();

        $customer = new CustomerFactory()->createOne([
            'organization' => 'cloudstack',
            'first_name' => $this->contactFirstName,
            'last_name' => $this->contactLastName,
            'email' => $this->contactEmail,
            'customer_number' => 5656,
        ]);

        $this->subscription
            ->product()
            ->associate($vpsProduct)
            ->customer()
            ->associate($customer)
            ->save();

        $this->subscription->children()->save($osProductSubscription);

        $clientAdminFactoryMock = self::createMock(AdminClientFactoryInterface::class);

        $this->app->bind(AdminClientFactoryInterface::class, fn () => $clientAdminFactoryMock);

        $vpsService = self::resolve(VpsService::class);

        $this->expectException(CloudstackNotFoundException::class);

        $vpsService->create(
            subscription: $this->subscription,
            sshKeyUuid: null,
        );
    }

    #[Test]
    public function successCreate(): void
    {
        Queue::fake();

        $this->setupDb();
        $serializer = CloudstackSerializerFactory::get();

        $baseClientMock = self::createMock(CloudStackBaseClient::class);
        $baseClientMock->expects(self::exactly(2))->method('execute')->willReturn([]);
        $this->app->bind(CloudStackBaseClient::class, fn () => $baseClientMock);

        $clientMock = self::createMock(CloudStackClient::class);

        /** @var array{network: array<mixed>} $response */
        $response = json_decode(
            (string) file_get_contents(__DIR__ . '/../data/networks/list-networks.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        /** @var array<Network> $networks */
        $networks = CloudstackSerializerFactory::get()->denormalize($response['network'], Network::class . '[]');
        $clientMock->expects(self::once())->method('listNetworks')->willReturn($networks);

        $clientMock
            ->expects(self::once())
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
                ),
            );

        $clientMock
            ->expects(self::once())
            ->method('createAccount')
            ->willReturn(new Account('1', 'test', 'test'));
        $clientMock
            ->expects(self::once())
            ->method('createDomain')
            ->willReturn(new Domain('1', 'test', 'test'));
        $clientMock->expects(self::once())->method('createSecurityGroup')->willReturn('1');
        $clientMock->expects(self::once())->method('authorizeSecurityGroupIngress');
        $clientMock
            ->expects(self::exactly(2))
            ->method('listDomainChildren')
            ->willReturn(
                new CloudStackPaginationIterator(
                    $baseClientMock,
                    'listDomainChildren',
                    [],
                    'domain',
                    new DomainMapper(),
                ),
            );
        $clientMock
            ->expects(self::once())
            ->method('listAccounts')
            ->willReturn(
                new CloudStackPaginationIterator($baseClientMock, 'listAccounts', [], 'account', new AccountMapper()),
            );

        $mockTemplate = self::createMock(Template::class);
        $mockTemplate->sshKeyEnabled = false;
        $mockTemplate->passwordEnabled = true;
        $mockTemplate->id = 'fa685028-1f5f-4acd-82a3-1693b91e605a';

        $clientMock
            ->expects(self::once())
            ->method('listTemplates')
            ->with($this->templateSlug)
            ->willReturn([$mockTemplate]);

        $cloudstackJobResponse = (string) file_get_contents(__DIR__ . '/../data/deployment/created_job.json');

        /** @var array<string, string> $cloudStackCreatedJob */
        $cloudStackCreatedJob = json_decode($cloudstackJobResponse, true, 512, JSON_THROW_ON_ERROR);
        $asyncJobResponse = $serializer->denormalize($cloudStackCreatedJob, AsynchronousCloudstackResponse::class);

        $clientMock->expects(self::once())->method('deployVirtualMachine')->willReturn($asyncJobResponse);

        $clientFactoryMock = self::createMock(ClientFactoryInterface::class);
        $clientFactoryMock->expects(self::exactly(2))->method('create')->willReturn($clientMock);

        $this->app->bind(ClientFactory::class, fn () => $clientFactoryMock);

        $clientAdminFactoryMockInterface = self::createMock(AdminClientFactoryInterface::class);
        $clientAdminFactoryMockInterface->method('create')->willReturn($clientMock);

        $clientAdminFactoryMock = self::createMock(AdminClientFactory::class);
        $clientAdminFactoryMock->method('create')->willReturn($clientMock);

        $this->app->bind(AdminClientFactoryInterface::class, fn () => $clientAdminFactoryMockInterface);
        $this->app->bind(AdminClientFactory::class, fn () => $clientAdminFactoryMock);

        $vpsService = self::resolve(VpsService::class);

        $sshKey = new SshKeyFactory()->for($this->otherCustomer)->createOne();

        $vpsService->create(
            subscription: $this->subscription,
            sshKeyUuid: (string) $sshKey->uuid,
        );

        Queue::assertPushed(DeployVirtualMachineJob::class);
    }

    #[Test]
    public function unableToCreateManagerDomain(): void
    {
        Queue::fake();

        $this->setupDb();

        $this->subscription->technical_status = TechnicalStatus::PENDING->value;
        $this->subscription->save();

        $mockManagerDomainService = self::createMock(ManagerDomainService::class);
        $clientFactory = self::resolve(ClientFactoryInterface::class);
        $mailer = self::resolve(MailerInterface::class);
        $bus = self::resolve(Dispatcher::class);
        $virtualMachineDeploymentRepository = self::resolve(VirtualMachineDeploymentRepositoryInterface::class);
        $serializer = self::resolve(Serializer::class);
        $resolveAndLinkSshKeyActionMock = self::createMock(ResolveAndLinkSshKeyAction::class);
        $mockTemplateService = self::createMock(VpsTemplateService::class);
        $cloudstackJobRepository = self::resolve(CloudstackJobRepository::class);
        $virtualMachineServiceInterface = self::resolve(VirtualMachineServiceInterface::class);

        $mockTemplate = self::createMock(Template::class);
        $mockTemplate->id = 'fa685028-1f5f-4acd-82a3-1693b91e605a';
        $mockTemplateService->method('getTemplateByProduct')->willReturn($mockTemplate);

        $vpsService = new VpsService(
            logger: self::createStub(LoggerInterface::class),
            virtualMachineDeploymentRepository: $virtualMachineDeploymentRepository,
            environmentRepository: self::resolve(EnvironmentRepository::class),
            managerDomainDeploymentRepository: self::resolve(ManagerDomainDeploymentRepository::class),
            managerDomainService: $mockManagerDomainService,
            clientFactory: $clientFactory,
            vpsTemplateService: $mockTemplateService,
            environmentProductRepository: self::resolve(EnvironmentProductRepository::class),
            resolveAndLinkSshKeyAction: $resolveAndLinkSshKeyActionMock,
            serializer: $serializer,
            bus: $bus,
            mailer: $mailer,
            cloudstackJobRepository: $cloudstackJobRepository,
            virtualMachineService: $virtualMachineServiceInterface,
        );

        $mockManagerDomainService->expects(self::once())->method('create')->willThrowException(new ClientException());

        self::expectException(ClientException::class);

        $vpsService->create(
            subscription: $this->subscription,
            sshKeyUuid: null,
        );

        self::assertSame(TechnicalStatus::ERROR->value, $this->subscription->refresh()->technical_status);

        Queue::assertNotPushed(DeployVirtualMachineJob::class);
    }

    #[Test]
    public function createSkipsWhenAlreadyProvisioned(): void
    {
        Queue::fake();

        $this->setupDb();

        $managerDomainDeployment = CloudstackManagerDomainDeploymentFactory::new()->createOne([
            'customer_id' => $this->subscription->customer_id,
            'environment_id' => $this->environment->id,
        ]);

        $deployment = CloudstackVirtualMachineDeploymentFactory::new()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'manager_domain_deployment_id' => $managerDomainDeployment->id,
            'cloudstack_id' => 'cs-vm-id-123',
        ]);

        $virtualMachineServiceMock = self::createMock(VirtualMachineServiceInterface::class);
        $virtualMachineServiceMock
            ->expects(self::once())
            ->method('findByDeployment')
            ->with(self::callback(fn (VirtualMachineDeployment $d) => $d->id === $deployment->id))
            ->willReturn(self::createStub(VirtualMachine::class));
        $this->app->bind(VirtualMachineServiceInterface::class, fn () => $virtualMachineServiceMock);

        $cloudstackJobRepoMock = self::createMock(CloudstackJobRepository::class);
        $cloudstackJobRepoMock->method('hasActiveJobForVmDeployment')->willReturn(false);
        $this->app->bind(CloudstackJobRepository::class, fn () => $cloudstackJobRepoMock);

        $vpsService = self::resolve(VpsService::class);

        $vpsService->create(subscription: $this->subscription, sshKeyUuid: null);

        Queue::assertNotPushed(DeployVirtualMachineJob::class);
        self::assertSame(TechnicalStatus::OK->value, $this->subscription->refresh()->technical_status);
    }

    #[Test]
    public function createSkipsWhenActiveCloudstackJobExists(): void
    {
        Queue::fake();

        $this->setupDb();

        $managerDomainDeployment = CloudstackManagerDomainDeploymentFactory::new()->createOne([
            'customer_id' => $this->subscription->customer_id,
            'environment_id' => $this->environment->id,
        ]);

        $deployment = CloudstackVirtualMachineDeploymentFactory::new()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'manager_domain_deployment_id' => $managerDomainDeployment->id,
            'cloudstack_id' => null,
        ]);

        $virtualMachineServiceMock = self::createMock(VirtualMachineServiceInterface::class);
        $virtualMachineServiceMock->expects(self::never())->method('findByDeployment');
        $this->app->bind(VirtualMachineServiceInterface::class, fn () => $virtualMachineServiceMock);

        $cloudstackJobRepoMock = self::createMock(CloudstackJobRepository::class);
        $cloudstackJobRepoMock
            ->expects(self::once())
            ->method('hasActiveJobForVmDeployment')
            ->with($deployment->id)
            ->willReturn(true);
        $this->app->bind(CloudstackJobRepository::class, fn () => $cloudstackJobRepoMock);

        $vpsService = self::resolve(VpsService::class);

        $vpsService->create(subscription: $this->subscription, sshKeyUuid: null);

        Queue::assertNotPushed(DeployVirtualMachineJob::class);
        self::assertSame(TechnicalStatus::PENDING->value, $this->subscription->refresh()->technical_status);
    }

    #[Test]
    public function retryWithoutDeploymentDeletesEmptyManagerDomainThenCallsCreate(): void
    {
        $this->setupDb();

        $managerDomainDeployment = CloudstackManagerDomainDeploymentFactory::new()->createOne([
            'customer_id' => $this->subscription->customer_id,
            'environment_id' => $this->environment->id,
            'account' => 'acc',
            'domain_name' => 'dm',
            'domain_id' => 'domain-1',
        ]);

        $vmRepo = self::createMock(VirtualMachineDeploymentRepositoryInterface::class);
        $vmRepo
            ->expects(self::once())
            ->method('findBySubscriptionUuid')
            ->willThrowException(new VirtualMachineNotFoundException());

        $envRepo = self::createMock(EnvironmentRepository::class);
        $envRepo->method('getPreferredEnvironment')->willReturn($this->environment);

        $managerRepo = self::createMock(ManagerDomainDeploymentRepository::class);
        $managerRepo->method('getManagerDomainDeploymentByEnvironment')->willReturn($managerDomainDeployment);

        $managerService = self::createMock(ManagerDomainService::class);
        $managerService->expects(self::once())->method('deleteDomain')->with($managerDomainDeployment);

        /** @var VpsService&MockObject $service */
        $service = $this->getMockBuilder(VpsService::class)
            ->onlyMethods(['create'])
            ->setConstructorArgs([
                self::createStub(LoggerInterface::class),
                $vmRepo,
                $envRepo,
                $managerRepo,
                $managerService,
                self::createStub(ClientFactoryInterface::class),
                self::createStub(VpsTemplateService::class),
                self::createStub(EnvironmentProductRepository::class),
                self::createStub(ResolveAndLinkSshKeyAction::class),
                self::createStub(Serializer::class),
                self::createStub(Dispatcher::class),
                self::createStub(MailerInterface::class),
                self::createStub(CloudstackJobRepository::class),
                self::createStub(VirtualMachineServiceInterface::class),
            ])
            ->getMock();

        $service->expects(self::once())->method('create')->with($this->subscription, 'ssh-uuid');

        $service->retry(subscription: $this->subscription, sshKeyUuid: 'ssh-uuid', deleteVmFirst: false);
    }

    #[Test]
    public function retrySkipsWhenActiveCloudstackJobExists(): void
    {
        $this->setupDb();

        $managerDomainDeployment = CloudstackManagerDomainDeploymentFactory::new()->createOne([
            'customer_id' => $this->subscription->customer_id,
            'environment_id' => $this->environment->id,
        ]);

        $deployment = CloudstackVirtualMachineDeploymentFactory::new()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'manager_domain_deployment_id' => $managerDomainDeployment->id,
            'cloudstack_id' => 'cs-vm-id-123',
        ]);

        $vmRepo = self::createMock(VirtualMachineDeploymentRepositoryInterface::class);
        $vmRepo->method('findBySubscriptionUuid')->willReturn($deployment);

        $cloudstackJobRepo = self::createMock(CloudstackJobRepository::class);
        $cloudstackJobRepo
            ->expects(self::once())
            ->method('hasActiveJobForVmDeployment')
            ->with($deployment->id)
            ->willReturn(true);

        $virtualMachineService = self::createMock(VirtualMachineServiceInterface::class);
        $virtualMachineService->expects(self::never())->method('findByDeployment');
        $virtualMachineService->expects(self::never())->method('reinstall');
        $virtualMachineService->expects(self::never())->method('destroy');

        /** @var VpsService&MockObject $service */
        $service = $this->getMockBuilder(VpsService::class)
            ->onlyMethods(['create'])
            ->setConstructorArgs([
                self::createStub(LoggerInterface::class),
                $vmRepo,
                self::createStub(EnvironmentRepository::class),
                self::createStub(ManagerDomainDeploymentRepository::class),
                self::createStub(ManagerDomainService::class),
                self::createStub(ClientFactoryInterface::class),
                self::createStub(VpsTemplateService::class),
                self::createStub(EnvironmentProductRepository::class),
                self::createStub(ResolveAndLinkSshKeyAction::class),
                self::createStub(Serializer::class),
                self::createStub(Dispatcher::class),
                self::createStub(MailerInterface::class),
                $cloudstackJobRepo,
                $virtualMachineService,
            ])
            ->getMock();

        $service->expects(self::never())->method('create');

        $service->retry(subscription: $this->subscription, sshKeyUuid: null, deleteVmFirst: false);

        self::assertSame(TechnicalStatus::PENDING->value, $this->subscription->refresh()->technical_status);
    }

    #[Test]
    public function retryVmExistsDeleteFalseReinstalls(): void
    {
        $this->setupDb();

        $managerDomainDeployment = CloudstackManagerDomainDeploymentFactory::new()->createOne([
            'customer_id' => $this->subscription->customer_id,
            'environment_id' => $this->environment->id,
        ]);

        $deployment = CloudstackVirtualMachineDeploymentFactory::new()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'manager_domain_deployment_id' => $managerDomainDeployment->id,
            'cloudstack_id' => 'cs-id',
        ]);

        $vmRepo = self::createMock(VirtualMachineDeploymentRepositoryInterface::class);
        $vmRepo->method('findBySubscriptionUuid')->willReturn($deployment);
        $vmRepo
            ->method('getOsSubscriptionChildFromSubscriptionUuid')
            ->willReturn(
                $this->subscription->children()->firstOrFail(),
            );

        $cloudstackJobRepo = self::createMock(CloudstackJobRepository::class);
        $cloudstackJobRepo->method('hasActiveJobForVmDeployment')->willReturn(false);

        $virtualMachineService = self::createMock(VirtualMachineServiceInterface::class);
        $virtualMachineService->method('findByDeployment')->willReturn(self::createStub(VirtualMachine::class));
        $virtualMachineService
            ->expects(self::once())
            ->method('reinstall')
            ->with(
                deployment: $deployment,
                newOs: self::isInstanceOf(Product::class),
                sshKeyUuid: 'ssh',
            );

        /** @var VpsService&MockObject $service */
        $service = $this->getMockBuilder(VpsService::class)
            ->onlyMethods(['create'])
            ->setConstructorArgs([
                self::createStub(LoggerInterface::class),
                $vmRepo,
                self::createStub(EnvironmentRepository::class),
                self::createStub(ManagerDomainDeploymentRepository::class),
                self::createStub(ManagerDomainService::class),
                self::createStub(ClientFactoryInterface::class),
                self::createStub(VpsTemplateService::class),
                self::createStub(EnvironmentProductRepository::class),
                self::createStub(ResolveAndLinkSshKeyAction::class),
                self::createStub(Serializer::class),
                self::createStub(Dispatcher::class),
                self::createStub(MailerInterface::class),
                $cloudstackJobRepo,
                $virtualMachineService,
            ])
            ->getMock();

        $service->expects(self::never())->method('create');

        $service->retry(subscription: $this->subscription, sshKeyUuid: 'ssh', deleteVmFirst: false);

        self::assertSame(TechnicalStatus::PENDING->value, $this->subscription->refresh()->technical_status);
    }

    #[Test]
    public function retryVmExistsDeleteTrueDestroys(): void
    {
        $this->setupDb();

        $managerDomainDeployment = CloudstackManagerDomainDeploymentFactory::new()->createOne([
            'customer_id' => $this->subscription->customer_id,
            'environment_id' => $this->environment->id,
        ]);

        $deployment = CloudstackVirtualMachineDeploymentFactory::new()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'manager_domain_deployment_id' => $managerDomainDeployment->id,
            'cloudstack_id' => 'cs-id',
        ]);

        $vmRepo = self::createMock(VirtualMachineDeploymentRepositoryInterface::class);
        $vmRepo->method('findBySubscriptionUuid')->willReturn($deployment);

        $cloudstackJobRepo = self::createMock(CloudstackJobRepository::class);
        $cloudstackJobRepo->method('hasActiveJobForVmDeployment')->willReturn(false);

        $virtualMachineService = self::createMock(VirtualMachineServiceInterface::class);
        $virtualMachineService->method('findByDeployment')->willReturn(self::createStub(VirtualMachine::class));
        $virtualMachineService->expects(self::once())->method('destroy')->with($deployment);

        /** @var VpsService&MockObject $service */
        $service = $this->getMockBuilder(VpsService::class)
            ->onlyMethods(['create'])
            ->setConstructorArgs([
                self::createStub(LoggerInterface::class),
                $vmRepo,
                self::createStub(EnvironmentRepository::class),
                self::createStub(ManagerDomainDeploymentRepository::class),
                self::createStub(ManagerDomainService::class),
                self::createStub(ClientFactoryInterface::class),
                self::createStub(VpsTemplateService::class),
                self::createStub(EnvironmentProductRepository::class),
                self::createStub(ResolveAndLinkSshKeyAction::class),
                self::createStub(Serializer::class),
                self::createStub(Dispatcher::class),
                self::createStub(MailerInterface::class),
                $cloudstackJobRepo,
                $virtualMachineService,
            ])
            ->getMock();

        $service->expects(self::never())->method('create');

        $service->retry(subscription: $this->subscription, sshKeyUuid: null, deleteVmFirst: true);

        self::assertSame(TechnicalStatus::DELETING->value, $this->subscription->refresh()->technical_status);
    }

    #[Test]
    public function retryVmDoesNotExistClearsCloudstackIdAndCallsCreate(): void
    {
        $this->setupDb();

        $managerDomainDeployment = CloudstackManagerDomainDeploymentFactory::new()->createOne([
            'customer_id' => $this->subscription->customer_id,
            'environment_id' => $this->environment->id,
        ]);

        $deployment = CloudstackVirtualMachineDeploymentFactory::new()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'manager_domain_deployment_id' => $managerDomainDeployment->id,
            'cloudstack_id' => 'stale-id',
        ]);

        $vmRepo = self::createMock(VirtualMachineDeploymentRepositoryInterface::class);
        $vmRepo->method('findBySubscriptionUuid')->willReturn($deployment);

        $cloudstackJobRepo = self::createMock(CloudstackJobRepository::class);
        $cloudstackJobRepo->method('hasActiveJobForVmDeployment')->willReturn(false);

        $virtualMachineService = self::createMock(VirtualMachineServiceInterface::class);
        $virtualMachineService->method('findByDeployment')->willReturn(null);

        /** @var VpsService&MockObject $service */
        $service = $this->getMockBuilder(VpsService::class)
            ->onlyMethods(['create'])
            ->setConstructorArgs([
                self::createStub(LoggerInterface::class),
                $vmRepo,
                self::createStub(EnvironmentRepository::class),
                self::createStub(ManagerDomainDeploymentRepository::class),
                self::createStub(ManagerDomainService::class),
                self::createStub(ClientFactoryInterface::class),
                self::createStub(VpsTemplateService::class),
                self::createStub(EnvironmentProductRepository::class),
                self::createStub(ResolveAndLinkSshKeyAction::class),
                self::createStub(Serializer::class),
                self::createStub(Dispatcher::class),
                self::createStub(MailerInterface::class),
                $cloudstackJobRepo,
                $virtualMachineService,
            ])
            ->getMock();

        $service->expects(self::once())->method('create')->with($this->subscription, 'ssh');

        $service->retry(subscription: $this->subscription, sshKeyUuid: 'ssh', deleteVmFirst: false);

        self::assertNull($deployment->refresh()->cloudstack_id);
        self::assertNotNull($deployment->refresh()->last_result_received);
    }

    private function setupDb(): void
    {
        $subscriptionUuid = 'fd028b7c-7347-2903-2354-76df8938d893';

        $cloudStackOsProductGroup = new ProductGroupFactory()->createOne([
            'name' => 'VPS Operating System',
            'slug' => 'cloudstack-os',
            'ledger_code' => 7331,
        ]);

        $vpsProduct = ProductFactory::new()->vps()->createOne();

        $this->operatingSystemProduct = new ProductFactory()->for($cloudStackOsProductGroup)->createOne([
            'name' => 'Operating System',
            'slug' => 'cloudstack-os',
            'description' => 'VPS operating system',
            'orderable' => true,
            'weight' => 1,
            'product_group_id' => $cloudStackOsProductGroup->id,
        ]);

        new ProductSpecFactory()->for($this->operatingSystemProduct)->createOne([
            'name' => ProductSpecName::VPS_CLOUDSTACK_TEMPLATE_SLUG->value,
            'value' => $this->templateSlug,
        ]);

        $osProductSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusActive()
            ->for($this->operatingSystemProduct, 'product')
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($vpsProduct)
            ->prolongation()
            ->createOne([
                'billing_period' => 1,
                'contract_period' => 1,
                'price' => 0,
            ]);

        $this->environment = Environment::create([
            'slug' => 'TEST01',
            'name' => 'Test Environment',
            'api_url' => 'https://api',
            'ui_url' => 'https://ui',
            'domain_id' => Str::uuid()->toString(),
            'domain_name' => 'test/vps',
            'preferred' => true,
            'default_email_address' => 'support@example.com',
            'default_role_id' => Str::uuid()->toString(),
            'api_key' => 'api-test-key',
            'secret_key' => 'secret-test-key',
        ]);

        new EnvironmentProduct([
            'product_identifier' => 'd8a23fc6-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        ])
            ->environment()
            ->associate($this->environment)
            ->product()
            ->associate($vpsProduct)
            ->save();

        new EnvironmentProduct([
            'product_identifier' => 'd8a23fc6-bbbb-bbbb-bbbb-bbbbbbbbbbbc',
        ])
            ->environment()
            ->associate($this->environment)
            ->product()
            ->associate($this->operatingSystemProduct)
            ->save();

        $this->subscription = new SubscriptionFactory()->makeOne([
            'uuid' => $subscriptionUuid,
            'billing_period' => 1,
            'contract_period' => 1,
        ]);

        $customer = new CustomerFactory()->createOne([
            'organization' => 'cloudstack',
            'customer_number' => 6767,
        ]);

        $this->otherCustomer = new CustomerFactory()->createOne([
            'organization' => 'other company',
            'first_name' => $this->contactFirstName,
            'last_name' => $this->contactLastName,
            'email' => $this->contactEmail,
        ]);

        $this->subscription
            ->product()
            ->associate($vpsProduct)
            ->customer()
            ->associate($customer)
            ->save();

        $this->subscription->children()->save($osProductSubscription);
    }
}
