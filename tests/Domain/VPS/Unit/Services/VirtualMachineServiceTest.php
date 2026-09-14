<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Unit\Services;

use Generator;
use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\CloudstackEnvironmentProductFactory;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CloudstackVirtualMachineDeploymentFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\ProductAllowedChangeRepository;
use Waterfront\Domain\VPS\Actions\ResolveAndLinkSshKeyAction;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Enums\VirtualMachineState;
use Waterfront\Domain\VPS\Enums\VpsActionStatus;
use Waterfront\Domain\VPS\Exceptions\CloudstackException;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineServiceInterface;
use Waterfront\Domain\VPS\Jobs\DestroyVirtualMachineJob;
use Waterfront\Domain\VPS\Jobs\ReinstallVirtualMachineJob;
use Waterfront\Domain\VPS\Jobs\ResetPasswordJob;
use Waterfront\Domain\VPS\Models\CloudstackJob;
use Waterfront\Domain\VPS\Models\Environment;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Services\AdminClientFactory;
use Waterfront\Domain\VPS\Services\VirtualMachineService;
use Waterfront\Domain\VPS\Services\VpsTemplateService;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\DTO\Nic;
use Waterfront\Infra\CloudStackClient\DTO\Template;
use Waterfront\Infra\CloudStackClient\DTO\VirtualMachine;
use Waterfront\Infra\CloudStackClient\Enums\CloudstackMachineState;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(VirtualMachineService::class)]
#[AllowMockObjectsWithoutExpectations]
class VirtualMachineServiceTest extends IntegrationTestCase
{
    private VirtualMachineDeployment $virtualMachineDeployment;

    private Subscription $virtualMachineSubscription;

    private LoggerInterface&MockObject $loggerMock;

    private VirtualMachineService $virtualMachineService;

    private ClientFactoryInterface&MockObject $clientFactoryMock;

    private Dispatcher&MockObject $dispatcherMock;

    private VpsTemplateService&MockObject $vpsTemplateServiceMock;

    private ProductRepository&MockObject $productRepository;

    private ResolveAndLinkSshKeyAction&MockObject $linkSshKeyAction;

    private ProductAllowedChangeRepository&Stub $productAllowedChangeRepository;

    private ProductSpecRepository&MockObject $productSpecRepository;

    private Product $newOsProduct;

    private AdminClientFactory&MockObject $adminClientFactoryMock;

    private Environment $environment;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne([
            'organization' => 'cloudstack',
        ]);

        $this->environment = new CloudstackEnvironmentFactory()->createOne([
            'name' => 'Test Environment',
            'ui_url' => 'https://ui',
            'domain_name' => 'test/vps',
        ]);

        $managerDomainDeployment = new CloudstackManagerDomainDeploymentFactory()->for($this->environment)->createOne([
            'customer_id' => $customer->id,
            'domain_name' => 'cs84583951',
            'account' => 'cs84583951',
            'username' => 'cs84583951',
        ]);

        $virtualMachineProductGroup = new ProductGroupFactory()->cloudstackVirtualMachine()->createOne();
        $virtualMachineProduct = new ProductFactory()->for($virtualMachineProductGroup)->createOne();

        $this->newOsProduct = new ProductFactory()->ubuntu()->createOne();

        new CloudstackEnvironmentProductFactory()
            ->for($virtualMachineProduct)
            ->for($this->environment)
            ->create();

        $this->virtualMachineSubscription = new SubscriptionFactory()
            ->for($virtualMachineProduct)
            ->for($customer)
            ->createOne([
                'uuid' => '45ff89c4-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ]);

        $this->virtualMachineDeployment = new CloudstackVirtualMachineDeploymentFactory()->for(
            $managerDomainDeployment,
        )->createOne([
            'subscription_uuid' => $this->virtualMachineSubscription->uuid,
            'cloudstack_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ]);

        $this->loggerMock = self::createMock(LoggerInterface::class);

        $this->clientFactoryMock = self::createMock(ClientFactoryInterface::class);
        $this->dispatcherMock = self::createMock(Dispatcher::class);
        $this->vpsTemplateServiceMock = self::createMock(VpsTemplateService::class);
        $this->productRepository = self::createMock(ProductRepository::class);
        $this->linkSshKeyAction = self::createMock(ResolveAndLinkSshKeyAction::class);
        $this->productAllowedChangeRepository = self::createStub(ProductAllowedChangeRepository::class);
        $this->productSpecRepository = self::createMock(ProductSpecRepository::class);
        $this->adminClientFactoryMock = self::createMock(AdminClientFactory::class);

        $this->virtualMachineService = new VirtualMachineService(
            clientFactory: $this->clientFactoryMock,
            logger: $this->loggerMock,
            bus: $this->dispatcherMock,
            serializer: CloudstackSerializerFactory::getCamelCaseSerializer(),
            vpsTemplateService: $this->vpsTemplateServiceMock,
            productRepository: $this->productRepository,
            linkSshKeyAction: $this->linkSshKeyAction,
            productAllowedChangeRepository: $this->productAllowedChangeRepository,
            productSpecRepository: $this->productSpecRepository,
            adminClientFactory: $this->adminClientFactoryMock,
        );
    }

    #[Test]
    public function findBySubscription(): void
    {
        $nic = new Nic(
            id: '3f5a059a-76e1-4bb0-9a2d-316c6ce55f93',
            networkId: 'da1d7155-a503-4f43-a9e7-fc97238077af',
            networkName: '59210 - Provider Private 1',
            netmask: '255.255.255.128',
            gateway: '185.159.242.3',
            ipAddress: '127.0.0.1',
            isolationUri: 'vxlan://503',
            broadcastUri: 'vxlan://503',
            trafficType: 'Guest',
            type: 'Shared',
            isDefault: true,
            macAddress: '1e:00:f3:00:03:46',
            ip6Gateway: '2a03:3060:c::1',
            ip6Cidr: '2a03:3060:c::/64',
            ip6Address: '2600:1801:1::1',
            secondaryIp: [],
            extraDhcpOption: [],
            deviceId: '0',
        );

        $virtualMachine = new VirtualMachine(
            id: 'abc',
            name: 'Name of VM',
            domainId: '1234',
            account: 'account',
            username: 'username',
            nic: [$nic],
            state: CloudstackMachineState::RUNNING,
            serviceOfferingId: '1234',
        );

        $service = self::createMock(VirtualMachineServiceInterface::class);

        $service->expects(self::once())->method('findByDeployment')->willReturn($virtualMachine);

        $subscription = $this->virtualMachineSubscription;
        self::assertNotNull($subscription->cloudStackVirtualMachineDeployment);

        $actualResult = $service->findByDeployment($subscription->cloudStackVirtualMachineDeployment);

        self::assertInstanceOf(VirtualMachine::class, $actualResult);
        self::assertSame($virtualMachine->id, $actualResult->id);
    }

    #[DataProvider('virtualMachineStates')]
    #[Test]
    public function stateVirtualMachine(VirtualMachineState $state, bool $expectedResult): void
    {
        $service = self::createMock(VirtualMachineServiceInterface::class);

        $service->expects(self::once())->method($state->value)->willReturn($expectedResult);

        $subscription = $this->virtualMachineSubscription;

        /** @phpstan-ignore-next-line */
        $actualResult = $service->{$state->value}($subscription->cloudStackVirtualMachineDeployment);

        self::assertEquals($expectedResult, $actualResult);
    }

    #[Test]
    public function reinstallingStateVirtualMachine(): void
    {
        $service = self::createMock(VirtualMachineServiceInterface::class);

        $service->expects(self::once())->method('reinstall')->willReturn(true);

        $subscription = $this->virtualMachineSubscription;

        self::assertNotNull($subscription->cloudStackVirtualMachineDeployment);
        $actualResult = $service->reinstall($subscription->cloudStackVirtualMachineDeployment, $this->newOsProduct);

        self::assertTrue($actualResult);
    }

    #[Test]
    public function postReinstallSuccessWithSshKeyRequired(): void
    {
        $job = new CloudstackJob([
            'id' => 100,
            'template_uuid' => 'template-uuid',
            'ssh_key_uuid' => 'ssh-key-uuid',
        ]);

        $osGroup = $this->newOsProduct->productGroup;
        $product = new ProductFactory()
            ->for($osGroup)
            ->state(['uuid' => 'template-uuid'])
            ->has(
                new ProductSpecFactory()->state([
                    'name' => ProductSpecName::SSH_KEY_REQUIRED->value,
                    'value' => 'true',
                ]),
                'productSpecs',
            )
            ->createOne();

        $this->productRepository->method('findProductByUuid')->willReturn($product);

        $this->productSpecRepository
            ->expects(self::once())
            ->method('booleanSpecificationIsTrue')
            ->with($product, ProductSpecName::SSH_KEY_REQUIRED)
            ->willReturn(true);

        $this->linkSshKeyAction
            ->expects(self::once())
            ->method('execute')
            ->with(
                'ssh-key-uuid',
                $this->virtualMachineDeployment->id,
                $this->virtualMachineDeployment->managerDomainDeployment,
            )
            ->willReturn('cloud-key-name');

        $service = $this->getMockBuilder(VirtualMachineService::class)
            ->onlyMethods(['resetSshKey'])
            ->setConstructorArgs([
                $this->clientFactoryMock,
                $this->loggerMock,
                $this->dispatcherMock,
                CloudstackSerializerFactory::getCamelCaseSerializer(),
                $this->vpsTemplateServiceMock,
                $this->productRepository,
                $this->linkSshKeyAction,
                $this->productAllowedChangeRepository,
                $this->productSpecRepository,
                $this->adminClientFactoryMock,
            ])
            ->getMock();

        $service
            ->expects(self::once())
            ->method('resetSshKey')
            ->with(
                self::assertCallbackIsModel($this->virtualMachineDeployment),
                'cloud-key-name',
            );

        $service->postReinstall($this->virtualMachineDeployment, $job);

        $this->virtualMachineDeployment->subscription->refresh();
        $this->virtualMachineDeployment->refresh();
        self::assertSame(TechnicalStatus::OK->value, $this->virtualMachineDeployment->subscription->technical_status);
        self::assertSame(VpsActionStatus::REINSTALL_SUCCESS, $this->virtualMachineDeployment->last_action_status);
    }

    #[Test]
    public function postReinstallSuccessWithoutSshKey(): void
    {
        $job = new CloudstackJob([
            'id' => 101,
            'template_uuid' => 'template-uuid',
        ]);

        $osGroup = $this->newOsProduct->productGroup;
        $product = new ProductFactory()
            ->for($osGroup)
            ->state(['uuid' => 'template-uuid'])
            ->has(
                new ProductSpecFactory()->state([
                    'name' => ProductSpecName::SSH_KEY_REQUIRED->value,
                    'value' => 'false',
                ]),
                'productSpecs',
            )
            ->createOne();

        $this->productRepository->method('findProductByUuid')->willReturn($product);

        $this->productSpecRepository
            ->expects(self::once())
            ->method('booleanSpecificationIsTrue')
            ->with($product, ProductSpecName::SSH_KEY_REQUIRED)
            ->willReturn(false);

        $service = $this->getMockBuilder(VirtualMachineService::class)
            ->onlyMethods(['stop'])
            ->setConstructorArgs([
                $this->clientFactoryMock,
                $this->loggerMock,
                $this->dispatcherMock,
                CloudstackSerializerFactory::getCamelCaseSerializer(),
                $this->vpsTemplateServiceMock,
                $this->productRepository,
                $this->linkSshKeyAction,
                $this->productAllowedChangeRepository,
                $this->productSpecRepository,
                $this->adminClientFactoryMock,
            ])
            ->getMock();

        $service->expects(self::never())->method('stop');
        $this->linkSshKeyAction->expects(self::never())->method('execute');

        $service->postReinstall($this->virtualMachineDeployment, $job);

        $this->virtualMachineDeployment->subscription->refresh();
        $this->virtualMachineDeployment->refresh();
        self::assertSame(TechnicalStatus::OK->value, $this->virtualMachineDeployment->subscription->technical_status);
        self::assertSame(VpsActionStatus::REINSTALL_SUCCESS, $this->virtualMachineDeployment->last_action_status);
    }

    #[Test]
    public function postReinstallThrowsExceptionOnMissingTemplateUuid(): void
    {
        $jobId = 200;

        $job = new CloudstackJob([
            'job_id' => $jobId,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs("Missing template_uuid on job {$jobId}");

        $this->virtualMachineService->postReinstall(
            $this->virtualMachineDeployment,
            $job,
        );
    }

    #[Test]
    public function postReinstallThrowsExceptionOnMissingSshKeyUuid(): void
    {
        $jobId = 201;

        $job = new CloudstackJob([
            'job_id' => $jobId,
            'template_uuid' => 'template-uuid',
        ]);

        $product = new Product();
        $product->uuid = 'template-uuid';
        $spec = (object) [
            'name' => ProductSpecName::SSH_KEY_REQUIRED->value,
            'value' => 'true',
        ];
        $product->setRelation('productSpecs', new Collection([$spec]));

        $this->productRepository
            ->expects(self::once())
            ->method('findProductByUuid')
            ->with('template-uuid')
            ->willReturn($product);

        $this->productSpecRepository
            ->expects(self::once())
            ->method('booleanSpecificationIsTrue')
            ->with($product, ProductSpecName::SSH_KEY_REQUIRED)
            ->willReturn(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('No ssh_key_uuid on job ');

        $this->virtualMachineService->postReinstall(
            $this->virtualMachineDeployment,
            $job,
        );
    }

    #[Test]
    public function resetSshKey(): void
    {
        $newKeyNameMockString = sha1('This is the hash of a key');
        $jobIdMockString = 'aaaa-aaaa-aaaa-aaaa';

        $cloudstackClientMock = self::createMock(CloudStackClient::class);
        $cloudstackClientMock
            ->expects(self::once())
            ->method('resetSshKeyForVirtualMachine')
            ->with($this->virtualMachineDeployment->cloudstack_id, $newKeyNameMockString)
            ->willReturn(new AsynchronousCloudstackResponse(jobId: $jobIdMockString));

        $this->clientFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with($this->virtualMachineDeployment->managerDomainDeployment)
            ->willReturn($cloudstackClientMock);

        $logMessage = sprintf(
            'Cloudstack resetting VMs [%s] ssh key with job ID %s for subscription %s.',
            $this->virtualMachineDeployment->cloudstack_id,
            $jobIdMockString,
            $this->virtualMachineDeployment->subscription->uuid,
        );

        $this->loggerMock->expects(self::once())->method('info')->with($logMessage);

        $this->dispatcherMock->expects(self::once())->method('dispatch');

        $serviceResult = $this->virtualMachineService->resetSshKey(
            deployment: $this->virtualMachineDeployment,
            newKeyName: $newKeyNameMockString,
        );

        self::assertTrue($serviceResult);
        self::assertSame(
            TechnicalStatus::PENDING->value,
            $this->virtualMachineDeployment->subscription->technical_status,
        );
        self::assertSame(VpsActionStatus::RESETTING_SSH_KEY, $this->virtualMachineDeployment->last_action_status);
    }

    #[Test]
    public function resetSshKeyFailedCloudstackClientException(): void
    {
        $newKeyNameMockString = sha1('This is the hash of a key');

        $cloudstackClientMock = self::createMock(CloudStackClient::class);

        $expectedException = new CloudStackException('This is a test message');
        $cloudstackClientMock
            ->expects(self::once())
            ->method('resetSshKeyForVirtualMachine')
            ->with($this->virtualMachineDeployment->cloudstack_id, $newKeyNameMockString)
            ->willThrowException($expectedException);

        $this->clientFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with($this->virtualMachineDeployment->managerDomainDeployment)
            ->willReturn($cloudstackClientMock);

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                $expectedException->getMessage(),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->virtualMachineDeployment->subscription->uuid,
                    LoggingContextKeys::PROVISIONING_ID => $this->virtualMachineDeployment->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                    LoggingContextKeys::EXCEPTION => $expectedException,
                ],
            );

        $serviceResult = $this->virtualMachineService->resetSshKey(
            deployment: $this->virtualMachineDeployment,
            newKeyName: $newKeyNameMockString,
        );

        Assert::assertFalse($serviceResult);
    }

    #[Test]
    public function virtualMachineReinstall(): void
    {
        $cloudstackBaseClientMock = self::createMock(CloudStackBaseClient::class);
        $startedReinstallResponse = json_decode(
            (string) file_get_contents(__DIR__ . '/../../data/reinstall/created_job.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $jobId = 'fbhdt34e-bb25-40hj-bd5f-320b9cq27151'; // matches json file

        $mockTemplate = self::createMock(Template::class);
        $mockTemplate->sshKeyEnabled = false;
        $mockTemplate->passwordEnabled = true;
        $mockTemplate->id = 'fa685028-1f5f-4acd-82a3-1693b91e605a';
        $mockTemplate->name = 'mock-template';

        $cloudstackBaseClientMock
            ->expects(self::once())
            ->method('execute')
            ->with('restoreVirtualMachine', [
                'virtualmachineid' => $this->virtualMachineDeployment->cloudstack_id,
                'templateid' => $mockTemplate->id,
            ])
            ->willReturn($startedReinstallResponse);

        $this->clientFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with($this->virtualMachineDeployment->managerDomainDeployment)
            ->willReturn(new CloudStackClient($cloudstackBaseClientMock, CloudstackSerializerFactory::get()));

        $this->vpsTemplateServiceMock
            ->expects(self::once())
            ->method('getTemplateByProduct')
            ->with(
                self::assertCallbackIsModel($this->newOsProduct),
                self::assertCallbackIsModel($this->environment),
            )
            ->willReturn($mockTemplate);

        $this->dispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn ($job) => $job instanceof ReinstallVirtualMachineJob));

        $this->virtualMachineService->reinstall($this->virtualMachineDeployment, $this->newOsProduct);

        $this->virtualMachineDeployment->refresh();
        self::assertSame(
            TechnicalStatus::PENDING->value,
            $this->virtualMachineDeployment->subscription->technical_status,
        );
        self::assertSame(VpsActionStatus::REINSTALLING, $this->virtualMachineDeployment->last_action_status);
        self::assertDatabaseHas('cloudstack_jobs', [
            'job_id' => $jobId,
            'vm_deployment_id' => $this->virtualMachineDeployment->id,
        ]);
    }

    #[Test]
    public function virtualMachineDestroy(): void
    {
        $cloudstackBaseClientMock = self::createMock(CloudStackBaseClient::class);
        $startedDestroyResponse = json_decode(
            (string) file_get_contents(__DIR__ . '/../../data/destroy/created_job.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $jobId = '741c702b-8857-4e3c-8b88-0157e1321cad'; // matches json file

        $cloudstackBaseClientMock
            ->expects(self::once())
            ->method('execute')
            ->with('destroyVirtualMachine', [
                'id' => $this->virtualMachineDeployment->cloudstack_id,
                'expunge' => true,
            ])
            ->willReturn($startedDestroyResponse);

        $this->clientFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with($this->virtualMachineDeployment->managerDomainDeployment)
            ->willReturn(new CloudStackClient($cloudstackBaseClientMock, CloudstackSerializerFactory::get()));

        $this->loggerMock
            ->expects(self::once())
            ->method('info')
            ->with(sprintf(
                'Cloudstack destroying VM [%s] with job ID %s for subscription %s.',
                $this->virtualMachineDeployment->cloudstack_id,
                $jobId,
                $this->virtualMachineDeployment->subscription_uuid,
            ));

        $this->dispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn ($job) => $job instanceof DestroyVirtualMachineJob));

        $this->virtualMachineService->destroy($this->virtualMachineDeployment);

        $subscription = $this->virtualMachineDeployment->subscription->refresh();
        self::assertSame(TechnicalStatus::DELETING->value, $subscription->technical_status);

        self::assertDatabaseHas('cloudstack_jobs', [
            'job_id' => $jobId,
            'vm_deployment_id' => $this->virtualMachineDeployment->id,
        ]);
    }

    #[Test]
    public function virtualMachineResetPassword(): void
    {
        $cloudstackBaseClientMock = self::createMock(CloudStackBaseClient::class);
        $startedDestroyResponse = json_decode(
            (string) file_get_contents(__DIR__ . '/../../data/reset_password/created_job.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $jobId = '741c702b-8857-4e3c-8b88-0157e1321cad'; // matches json file
        $newPassword = 'hunter2';

        $cloudstackBaseClientMock
            ->expects(self::once())
            ->method('execute')
            ->with('resetPasswordForVirtualMachine', [
                'id' => $this->virtualMachineDeployment->cloudstack_id,
                'password' => $newPassword,
            ])
            ->willReturn($startedDestroyResponse);

        $this->clientFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with($this->virtualMachineDeployment->managerDomainDeployment)
            ->willReturn(new CloudStackClient($cloudstackBaseClientMock, CloudstackSerializerFactory::get()));

        $this->loggerMock
            ->expects(self::once())
            ->method('info')
            ->with(sprintf(
                'Cloudstack resetting VMs [%s] password with job ID %s for subscription %s.',
                $this->virtualMachineDeployment->cloudstack_id,
                $jobId,
                $this->virtualMachineDeployment->subscription_uuid,
            ));

        $this->dispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn ($job) => $job instanceof ResetPasswordJob));

        $resetPasswordSuccess = $this->virtualMachineService->resetPassword(
            $this->virtualMachineDeployment,
            $newPassword,
        );

        $subscription = $this->virtualMachineDeployment->subscription->refresh();
        self::assertSame(TechnicalStatus::PENDING->value, $subscription->technical_status);
        self::assertSame(VpsActionStatus::RESETTING_CREDENTIALS, $this->virtualMachineDeployment->last_action_status);

        self::assertTrue($resetPasswordSuccess);
        self::assertDatabaseHas('cloudstack_jobs', [
            'job_id' => $jobId,
            'vm_deployment_id' => $this->virtualMachineDeployment->id,
        ]);
    }

    #[Test]
    public function findVirtualMachinesTest(): void
    {
        $cloudstackBaseClientMock = self::createMock(CloudStackBaseClient::class);
        $listVirtualMachineResponse = json_decode(
            (string) file_get_contents(__DIR__ . '/../../data/virtualmachines/listVirtualMachines.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $cloudstackBaseClientMock
            ->expects(self::once())
            ->method('execute')
            ->with('listVirtualMachines', ['listall' => 'true'])
            ->willReturn($listVirtualMachineResponse);

        $this->clientFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with($this->virtualMachineDeployment->managerDomainDeployment)
            ->willReturn(new CloudStackClient($cloudstackBaseClientMock, CloudstackSerializerFactory::get()));

        $vms = $this->virtualMachineService->findVirtualMachines($this->virtualMachineDeployment->managerDomainDeployment);

        self::assertCount(4, $vms);
        self::assertContainsOnlyInstancesOf(VirtualMachine::class, $vms);
    }

    #[Test]
    public function getConsole(): void
    {
        $cloudstackBaseClientMock = self::createMock(CloudStackBaseClient::class);

        // matches json
        $expectedUrl = 'https://13.37.13.37.infra.cldin.net/resource/noVNC/vnc.html?autoconnect=true&port=8443&token=legit-token';
        $createConsoleResponse = json_decode(
            (string) file_get_contents(__DIR__ . '/../../data/console/create-console-endpoint.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->adminClientFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with(self::assertCallbackIsModel($this->environment))
            ->willReturn(new CloudStackClient($cloudstackBaseClientMock, CloudstackSerializerFactory::get()));

        $cloudstackBaseClientMock
            ->expects(self::once())
            ->method('execute')
            ->with('createConsoleEndpoint', ['virtualmachineid' => $this->virtualMachineDeployment->cloudstack_id])
            ->willReturn($createConsoleResponse);

        $consoleUrl = $this->virtualMachineService->getConsole($this->virtualMachineDeployment);

        self::assertSame($expectedUrl, $consoleUrl);
    }

    #[Test]
    public function consoleErrorThrowsException(): void
    {
        $cloudstackBaseClientMock = self::createMock(CloudStackBaseClient::class);

        // matches json
        $expectedError = 'The console endpoint is not available yet.';
        $createConsoleResponse = json_decode(
            (string) file_get_contents(__DIR__ . '/../../data/console/create-console-endpoint-error.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->adminClientFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with(self::assertCallbackIsModel($this->environment))
            ->willReturn(new CloudStackClient($cloudstackBaseClientMock, CloudstackSerializerFactory::get()));

        $cloudstackBaseClientMock
            ->expects(self::once())
            ->method('execute')
            ->with('createConsoleEndpoint', ['virtualmachineid' => $this->virtualMachineDeployment->cloudstack_id])
            ->willReturn($createConsoleResponse);

        self::expectException(CloudstackException::class);
        self::expectExceptionMessageIs(sprintf(
            'Failed to get console URL for VM %s: %s',
            $this->virtualMachineDeployment->id,
            $expectedError,
        ));

        $this->virtualMachineService->getConsole($this->virtualMachineDeployment);
    }

    #[Test]
    public function resetSshKeyFailedMissingCloudStackId(): void
    {
        $this->virtualMachineDeployment->cloudstack_id = null;

        $logMessage = sprintf(
            'Trying to reset sshkey for VM deployment (%d) without a cloudstack ID.',
            $this->virtualMachineDeployment->id,
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('warning')
            ->with(
                $logMessage,
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->virtualMachineDeployment->subscription->uuid,
                    LoggingContextKeys::PROVISIONING_ID => $this->virtualMachineDeployment->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                ],
            );

        $serviceResult = $this->virtualMachineService->resetSshKey(
            deployment: $this->virtualMachineDeployment,
            newKeyName: 'sha1-from-the-key',
        );

        Assert::assertFalse($serviceResult);
    }

    /**
     * @return Generator<mixed>
     */
    public static function virtualMachineStates(): Generator
    {
        yield [VirtualMachineState::START, true];
        yield [VirtualMachineState::REBOOT, true];
        yield [VirtualMachineState::STOP, true];
        yield [VirtualMachineState::DESTROY, true];

        // REINSTALL is tested in 'testReinstallingStateVirtualMachine'
    }
}
