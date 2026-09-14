<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\CloudStack;

use Generator;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\CloudstackEnvironmentProductFactory;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CloudstackVirtualMachineDeploymentFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SshKeyFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\CloudStack\VirtualMachineController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Domain\VPS\Actions\ResetVirtualMachineSshKeyAction;
use Waterfront\Domain\VPS\Enums\VirtualMachineState;
use Waterfront\Domain\VPS\Exceptions\VirtualMachineNotFoundException;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineDeploymentRepositoryInterface;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineServiceInterface;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Services\ClientFactory;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\DTO\Nic;
use Waterfront\Infra\CloudStackClient\DTO\VirtualMachine;
use Waterfront\Infra\CloudStackClient\Enums\CloudstackMachineState;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;

#[CoversClass(VirtualMachineController::class)]
class VirtualMachineTest extends IntegrationTestCase
{
    private Customer $customer;

    private Product $virtualMachineProduct;

    private VirtualMachineDeployment $virtualMachineDeployment;

    private Subscription $osSubscription;

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading(false);

        $this->customer =
            $customer = new CustomerFactory()->createOne([
                'organization' => 'cloudstack',
            ]);

        $environment = new CloudstackEnvironmentFactory()->createOne();

        $managerDomainDeployment = new CloudstackManagerDomainDeploymentFactory()
            ->for($environment)
            ->for($this->customer)
            ->createOne();

        $virtualMachineProductGroup = new ProductGroupFactory()->vps()->createOne();
        $this->virtualMachineProduct = new ProductFactory()->for($virtualMachineProductGroup)->createOne();

        new ProductPriceComponentFactory()
            ->for($this->virtualMachineProduct)
            ->registration()
            ->createOne();
        new ProductPriceComponentFactory()
            ->for($this->virtualMachineProduct)
            ->prolongation()
            ->createOne();

        new CloudstackEnvironmentProductFactory()
            ->for($this->virtualMachineProduct)
            ->for($environment)
            ->create();

        $volumeProductGroup = new ProductGroupFactory()->cloudstackVolume()->createOne();
        $volumeProduct = new ProductFactory()->for($volumeProductGroup)->createOne();
        new ProductPriceComponentFactory()
            ->for($volumeProduct)
            ->registration()
            ->createOne();
        new ProductPriceComponentFactory()
            ->for($volumeProduct)
            ->prolongation()
            ->createOne();
        new CloudstackEnvironmentProductFactory()
            ->for($volumeProduct)
            ->for($environment)
            ->create();

        $vpsSubscription = new SubscriptionFactory()
            ->for($this->virtualMachineProduct)
            ->for($customer)
            ->createOne(['uuid' => '45ff89c4-aaaa-aaaa-aaaa-aaaaaaaaaaaa']);

        $this->virtualMachineDeployment = new CloudstackVirtualMachineDeploymentFactory()->for(
            $managerDomainDeployment,
        )->createOne([
            'subscription_uuid' => $vpsSubscription->uuid,
            'cloudstack_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ]);

        $osProduct = new ProductFactory()->ubuntu()->createOne();

        $this->osSubscription = new SubscriptionFactory()
            ->for($osProduct, 'product')
            ->for($customer)
            ->parentSubscription($vpsSubscription)
            ->createOne();
    }

    /**
     * @param string[] $expectedContent
     */
    #[DataProvider('stateProvider')]
    #[Test]
    public function virtualMachineEndpoint(
        string $uuid,
        string $state,
        int $calls,
        string $method,
        array $expectedContent,
        int $expectedStatusCode,
    ): void {
        $this->setupMockingVps($method, $calls);

        $resetSshKeyActionMock = self::createStub(ResetVirtualMachineSshKeyAction::class);
        $this->app->bind(ResetVirtualMachineSshKeyAction::class, fn () => $resetSshKeyActionMock);

        $response = $this->actingAsCustomer($this->customer)
            ->patchJson(
                $this->generateRoute('partners.cloudstack.virtual-machine.state', [
                    'subscription' => $uuid,
                ]),
                [
                    'state' => $state,
                ],
            )
            ->assertStatus($expectedStatusCode);

        self::assertJson($response->content(), json_encode($expectedContent, JSON_THROW_ON_ERROR));
    }

    /**
     * @return Generator<mixed>
     */
    public static function stateProvider(): Generator
    {
        yield [
            '45ff89c4-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            VirtualMachineState::STOP->value,
            1,
            'stopVirtualMachine',
            ['status' => 'ok'],
            Response::HTTP_OK,
        ];
        yield [
            '45ff89c4-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            VirtualMachineState::START->value,
            1,
            'startVirtualMachine',
            ['status' => 'ok'],
            Response::HTTP_OK,
        ];
        yield [
            '45ff89c4-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            VirtualMachineState::REBOOT->value,
            1,
            'rebootVirtualMachine',
            ['status' => 'ok'],
            Response::HTTP_OK,
        ];
        yield [
            '45ff89c4-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            '420-nice-69',
            0,
            'stopVirtualMachine',
            ['status' => 'ok'],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ];
        yield [
            '45ff89c4-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            '',
            0,
            'startVirtualMachine',
            ['status' => 'ok'],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ];
        yield [
            'does-not-exist',
            VirtualMachineState::START->value,
            0,
            'startVirtualMachine',
            ['error' => 'Virtual machine not found.'],
            Response::HTTP_NOT_FOUND,
        ];
    }

    #[Test]
    public function reinstallEndpoint(): void
    {
        $vpsService = self::mock(VirtualMachineServiceInterface::class);
        $vpsService
            ->shouldReceive('reinstall')
            ->once()
            ->withArgs(
                fn (
                    VirtualMachineDeployment $deployment,
                    string $newProductUuid,
                    ?string $sshKeyUuid = null,
                ) => $deployment->is($this->virtualMachineDeployment),
            )
            ->andReturnTrue();
        $this->app->bind(VirtualMachineServiceInterface::class, fn () => $vpsService);

        $changeService = self::createMock(SubscriptionChangeService::class);
        $changeService
            ->expects(self::once())
            ->method('change')
            ->with(
                ProductChangeType::REINSTALL,
                self::assertCallbackIsModel($this->osSubscription),
                self::callback(fn (Product $prod) => $prod->uuid === $this->osSubscription->product->uuid),
                false,
                false,
            );
        $this->app->bind(SubscriptionChangeService::class, fn () => $changeService);

        $this->app->bind(ResetVirtualMachineSshKeyAction::class, fn () => self::createStub(ResetVirtualMachineSshKeyAction::class));

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.cloudstack.virtual-machine.reinstall', [
                    'subscription' => $this->virtualMachineDeployment->subscription->uuid,
                ]),
                ['os_product_uuid' => $this->osSubscription->product->uuid],
            )
            ->assertOk()
            ->assertJsonFragment(['status' => 'success']);
    }

    #[Test]
    public function reinstallEndpointReinstallError(): void
    {
        $this->app->bind(ResetVirtualMachineSshKeyAction::class, fn () => self::createStub(ResetVirtualMachineSshKeyAction::class));

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.cloudstack.virtual-machine.reinstall', [
                    'subscription' => $this->virtualMachineDeployment->subscription->uuid,
                ]),
                ['os_product_uuid' => $this->osSubscription->product->uuid],
            )
            ->assertJsonFragment(['error' => 'vps.reinstall-failed']);
    }

    #[Test]
    public function resetPasswordEndpoint(): void
    {
        $password = 'SUPER-secret-password1';
        $vm = new VirtualMachine(
            id: '1',
            name: 'name',
            domainId: 'domain-id',
            account: 'account',
            username: 'username',
            nic: [],
            state: CloudstackMachineState::STOPPED,
            serviceOfferingId: '',
        );

        $virtualMachineDeploymentRepository = self::createMock(VirtualMachineDeploymentRepositoryInterface::class);
        $virtualMachineDeploymentRepository
            ->expects(self::once())
            ->method('findBySubscriptionUuid')
            ->with($this->virtualMachineDeployment->subscription_uuid, $this->customer->id)
            ->willReturn($this->virtualMachineDeployment);
        $this->app->bind(
            VirtualMachineDeploymentRepositoryInterface::class,
            fn () => $virtualMachineDeploymentRepository,
        );

        $vpsService = self::createMock(VirtualMachineServiceInterface::class);
        $vpsService->expects(self::once())->method('findByDeployment')->willReturn($vm);

        $vpsService
            ->expects(self::once())
            ->method('resetPassword')
            ->with($this->virtualMachineDeployment, $password)
            ->willReturn(true);

        $this->app->bind(VirtualMachineServiceInterface::class, fn () => $vpsService);

        $resetSshKeyActionMock = self::createStub(ResetVirtualMachineSshKeyAction::class);
        $this->app->bind(ResetVirtualMachineSshKeyAction::class, fn () => $resetSshKeyActionMock);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.cloudstack.virtual-machine.reset-password', [
                    'subscription' => $this->virtualMachineDeployment->subscription_uuid,
                    'password' => $password,
                    'password_confirmation' => $password,
                ]),
            )
            ->assertOk()
            ->assertJsonFragment(['status' => 'success']);
    }

    #[Test]
    public function resetPasswordEndpointFailed(): void
    {
        $password = 'SUPER-secret-password1';
        $vm = new VirtualMachine(
            id: '1',
            name: 'name',
            domainId: 'domain-id',
            account: 'account',
            username: 'username',
            nic: [],
            state: CloudstackMachineState::STOPPED,
            serviceOfferingId: '',
        );

        $virtualMachineDeploymentRepository = self::createMock(VirtualMachineDeploymentRepositoryInterface::class);
        $virtualMachineDeploymentRepository
            ->expects(self::once())
            ->method('findBySubscriptionUuid')
            ->with($this->virtualMachineDeployment->subscription_uuid, $this->customer->id)
            ->willReturn($this->virtualMachineDeployment);
        $this->app->bind(
            VirtualMachineDeploymentRepositoryInterface::class,
            fn () => $virtualMachineDeploymentRepository,
        );

        $vpsService = self::createMock(VirtualMachineServiceInterface::class);
        $vpsService->expects(self::once())->method('findByDeployment')->willReturn($vm);

        $vpsService
            ->expects(self::once())
            ->method('resetPassword')
            ->with($this->virtualMachineDeployment, $password)
            ->willReturn(false);

        $this->app->bind(VirtualMachineServiceInterface::class, fn () => $vpsService);

        $resetSshKeyActionMock = self::createStub(ResetVirtualMachineSshKeyAction::class);
        $this->app->bind(ResetVirtualMachineSshKeyAction::class, fn () => $resetSshKeyActionMock);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.cloudstack.virtual-machine.reset-password', [
                    'subscription' => $this->virtualMachineDeployment->subscription_uuid,
                    'password' => $password,
                    'password_confirmation' => $password,
                ]),
            )
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'vps.reset-password-failed']);
    }

    #[Test]
    public function resetPasswordEndpointVpsNotFound(): void
    {
        $password = 'SUPER-secret-password1';

        $virtualMachineDeploymentRepository = self::createMock(VirtualMachineDeploymentRepositoryInterface::class);
        $virtualMachineDeploymentRepository
            ->expects(self::once())
            ->method('findBySubscriptionUuid')
            ->with($this->virtualMachineDeployment->subscription_uuid, $this->customer->id)
            ->willThrowException(new VirtualMachineNotFoundException());
        $this->app->bind(
            VirtualMachineDeploymentRepositoryInterface::class,
            fn () => $virtualMachineDeploymentRepository,
        );

        $vpsService = self::createMock(VirtualMachineServiceInterface::class);
        $vpsService->expects(self::never())->method('findByDeployment');

        $vpsService->expects(self::never())->method('resetPassword');

        $this->app->bind(VirtualMachineServiceInterface::class, fn () => $vpsService);

        $resetSshKeyActionMock = self::createStub(ResetVirtualMachineSshKeyAction::class);
        $this->app->bind(ResetVirtualMachineSshKeyAction::class, fn () => $resetSshKeyActionMock);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.cloudstack.virtual-machine.reset-password', [
                    'subscription' => $this->virtualMachineDeployment->subscription_uuid,
                    'password' => $password,
                    'password_confirmation' => $password,
                ]),
            )
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'vps.not-found']);
    }

    #[Test]
    public function resetPasswordEndpointVpsNotStopped(): void
    {
        $password = 'SUPER-secret-password1';
        $vm = new VirtualMachine(
            id: '1',
            name: 'name',
            domainId: 'domain-id',
            account: 'account',
            username: 'username',
            nic: [],
            state: CloudstackMachineState::RUNNING,
            serviceOfferingId: '',
        );

        $virtualMachineDeploymentRepository = self::createMock(VirtualMachineDeploymentRepositoryInterface::class);
        $virtualMachineDeploymentRepository
            ->expects(self::once())
            ->method('findBySubscriptionUuid')
            ->with($this->virtualMachineDeployment->subscription_uuid, $this->customer->id)
            ->willReturn($this->virtualMachineDeployment);
        $this->app->bind(
            VirtualMachineDeploymentRepositoryInterface::class,
            fn () => $virtualMachineDeploymentRepository,
        );

        $vpsService = self::createMock(VirtualMachineServiceInterface::class);
        $vpsService->expects(self::once())->method('findByDeployment')->willReturn($vm);

        $vpsService->expects(self::never())->method('resetPassword');

        $this->app->bind(VirtualMachineServiceInterface::class, fn () => $vpsService);

        $resetSshKeyActionMock = self::createStub(ResetVirtualMachineSshKeyAction::class);
        $this->app->bind(ResetVirtualMachineSshKeyAction::class, fn () => $resetSshKeyActionMock);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.cloudstack.virtual-machine.reset-password', [
                    'subscription' => $this->virtualMachineDeployment->subscription_uuid,
                    'password' => $password,
                    'password_confirmation' => $password,
                ]),
            )
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'vps.not-stopped']);
    }

    #[Test]
    public function resetSshKeyEndpointSuccess(): void
    {
        $sshKey = new SshKeyFactory()->for($this->customer)->createOne();

        $resetSshKeyActionMock = self::createMock(ResetVirtualMachineSshKeyAction::class);
        $resetSshKeyActionMock->expects(self::once())->method('execute')->willReturn(true);

        $this->app->bind(ResetVirtualMachineSshKeyAction::class, fn () => $resetSshKeyActionMock);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.cloudstack.virtual-machine.reset-vm-sshkey', [
                    'subscription' => $this->virtualMachineDeployment->subscription_uuid,
                    'ssh_uuid' => $sshKey->uuid->toString(),
                ]),
            )
            ->assertStatus(Response::HTTP_NO_CONTENT);
    }

    #[Test]
    public function resetSshKeyEndpointFailedDueGeneralError(): void
    {
        $sshKey = new SshKeyFactory()->for($this->customer)->createOne();

        $resetSshKeyActionMock = self::createMock(ResetVirtualMachineSshKeyAction::class);
        $resetSshKeyActionMock->expects(self::once())->method('execute')->willReturn(false);

        $this->app->bind(ResetVirtualMachineSshKeyAction::class, fn () => $resetSshKeyActionMock);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.cloudstack.virtual-machine.reset-vm-sshkey', [
                    'subscription' => $this->virtualMachineDeployment->subscription_uuid,
                    'ssh_uuid' => $sshKey->uuid->toString(),
                ]),
            )
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'ssh-key.general-reset.error']);
    }

    #[Test]
    public function resetSshKeyEndpointFailedDueVmNotFound(): void
    {
        $sshKey = new SshKeyFactory()->for($this->customer)->createOne();

        $resetSshKeyActionMock = self::createMock(ResetVirtualMachineSshKeyAction::class);
        $resetSshKeyActionMock
            ->expects(self::once())
            ->method('execute')
            ->willThrowException(new VirtualMachineNotFoundException('test message'));

        $this->app->bind(ResetVirtualMachineSshKeyAction::class, fn () => $resetSshKeyActionMock);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.cloudstack.virtual-machine.reset-vm-sshkey', [
                    'subscription' => $this->virtualMachineDeployment->subscription_uuid,
                    'ssh_uuid' => $sshKey->uuid->toString(),
                ]),
            )
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'ssh-key.notfound-reset.error']);
    }

    #[Test]
    public function indexEndpoint(): void
    {
        $this->virtualMachineDeployment = new CloudstackVirtualMachineDeploymentFactory()->for($this->virtualMachineDeployment->managerDomainDeployment)->createOne([
            'subscription_uuid' => new SubscriptionFactory()
                ->for($this->virtualMachineProduct)
                ->for($this->customer)
                ->createOne()
                ->uuid,
            'cloudstack_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        ]);

        $this->virtualMachineDeployment = new CloudstackVirtualMachineDeploymentFactory()->for($this->virtualMachineDeployment->managerDomainDeployment)->createOne([
            'subscription_uuid' => new SubscriptionFactory()
                ->for($this->virtualMachineProduct)
                ->for($this->customer)
                ->createOne()
                ->uuid,
            'cloudstack_id' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
        ]);

        $mockVirtualMachineService = self::createMock(VirtualMachineServiceInterface::class);
        $this->app->bind(VirtualMachineServiceInterface::class, fn () => $mockVirtualMachineService);

        $mockNic = $this->createMockNic();
        $mockVirtualMachineService
            ->expects(self::once())
            ->method('findVirtualMachines')
            ->with($this->virtualMachineDeployment->managerDomainDeployment)
            ->willReturn([
                new VirtualMachine(
                    id: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                    name: 'name',
                    domainId: 'domain-id',
                    account: 'account',
                    username: 'username',
                    nic: [$mockNic],
                    state: CloudstackMachineState::RUNNING,
                    serviceOfferingId: '',
                ),
                new VirtualMachine(
                    id: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                    name: 'name2',
                    domainId: 'domain-id',
                    account: 'account',
                    username: 'username',
                    nic: [$mockNic],
                    state: CloudstackMachineState::STOPPED,
                    serviceOfferingId: '',
                ),
                new VirtualMachine(
                    id: 'cccccccc-cccc-cccc-cccc-cccccccccccc',
                    name: 'name',
                    domainId: 'domain-id',
                    account: 'account',
                    username: 'username',
                    nic: [$mockNic],
                    state: CloudstackMachineState::PRESENT,
                    serviceOfferingId: '',
                ),
            ]);

        $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.cloudstack.virtual-machine.index'))
            ->assertOk()
            ->assertJsonFragment([
                'cloudstack_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'vps_status' => 'Running',
            ])
            ->assertJsonFragment([
                'cloudstack_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                'vps_status' => 'Stopped',
            ])
            ->assertJsonFragment([
                'cloudstack_id' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
                'vps_status' => 'Present',
            ]);
    }

    #[Test]
    public function indexEndpointWithMultipleEnvironments(): void
    {
        // First 3 vm deployments (1 in setup, 2 in this test) are for the first environment.
        $this->virtualMachineDeployment = new CloudstackVirtualMachineDeploymentFactory()->for($this->virtualMachineDeployment->managerDomainDeployment)->createOne([
            'subscription_uuid' => new SubscriptionFactory()
                ->for($this->virtualMachineProduct)
                ->for($this->customer)
                ->createOne()
                ->uuid,
            'cloudstack_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        ]);

        $this->virtualMachineDeployment = new CloudstackVirtualMachineDeploymentFactory()->for($this->virtualMachineDeployment->managerDomainDeployment)->createOne([
            'subscription_uuid' => new SubscriptionFactory()
                ->for($this->virtualMachineProduct)
                ->for($this->customer)
                ->createOne()
                ->uuid,
            'cloudstack_id' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
        ]);

        // Creating a second environment with manager domain.
        $secondEnvironment = new CloudstackEnvironmentFactory()->createOne();

        $secondManagerDomainDeployment = new CloudstackManagerDomainDeploymentFactory()
            ->for($secondEnvironment)
            ->for($this->customer)
            ->createOne();

        // Add 2 new Virtual machines to the second environment
        new CloudstackVirtualMachineDeploymentFactory()->for($secondManagerDomainDeployment)->createOne([
            'subscription_uuid' => new SubscriptionFactory()
                ->for($this->virtualMachineProduct)
                ->for($this->customer)
                ->createOne()
                ->uuid,
            'cloudstack_id' => 'cccccccc-cccc-2222-cccc-cccccccccccc',
        ]);

        new CloudstackVirtualMachineDeploymentFactory()->for($secondManagerDomainDeployment)->createOne([
            'subscription_uuid' => new SubscriptionFactory()
                ->for($this->virtualMachineProduct)
                ->for($this->customer)
                ->createOne()
                ->uuid,
            'cloudstack_id' => 'bbbbbbbb-bbbb-2222-bbbb-bbbbbbbbbbbb',
        ]);

        // vps without manager domain
        new SubscriptionFactory()
            ->for($this->virtualMachineProduct)
            ->for($this->customer)
            ->createOne();

        $mockVirtualMachineService = self::mock(VirtualMachineServiceInterface::class);
        $this->app->bind(VirtualMachineServiceInterface::class, fn () => $mockVirtualMachineService);

        $mockNic = $this->createMockNic();
        // find virtual machines for the first manager domain (on the first env).
        $mockVirtualMachineService
            ->shouldReceive('findVirtualMachines')
            ->once()
            ->withArgs(fn (ManagerDomainDeployment $managerDomainDeployment) => $managerDomainDeployment->is($this->virtualMachineDeployment->managerDomainDeployment))
            ->andReturn([
                new VirtualMachine(
                    id: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                    name: 'name',
                    domainId: 'domain-id',
                    account: 'account',
                    username: 'username',
                    nic: [$mockNic],
                    state: CloudstackMachineState::RUNNING,
                    serviceOfferingId: '',
                ),
                new VirtualMachine(
                    id: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                    name: 'name2',
                    domainId: 'domain-id',
                    account: 'account',
                    username: 'username',
                    nic: [$mockNic],
                    state: CloudstackMachineState::STOPPED,
                    serviceOfferingId: '',
                ),
                new VirtualMachine(
                    id: 'cccccccc-cccc-cccc-cccc-cccccccccccc',
                    name: 'name',
                    domainId: 'domain-id',
                    account: 'account',
                    username: 'username',
                    nic: [$mockNic],
                    state: CloudstackMachineState::PRESENT,
                    serviceOfferingId: '',
                ),
            ]);

        // find virtual machines for the second manager domain (on the second env).
        $mockVirtualMachineService
            ->shouldReceive('findVirtualMachines')
            ->once()
            ->withArgs(
                fn (ManagerDomainDeployment $managerDomainDeployment) => $managerDomainDeployment->is(
                    $secondManagerDomainDeployment,
                ),
            )
            ->andReturn([
                new VirtualMachine(
                    id: 'cccccccc-cccc-2222-cccc-cccccccccccc',
                    name: 'name-b2222',
                    domainId: 'domain-id',
                    account: 'account',
                    username: 'username',
                    nic: [$mockNic],
                    state: CloudstackMachineState::RUNNING,
                    serviceOfferingId: '',
                ),
                new VirtualMachine(
                    id: 'bbbbbbbb-bbbb-2222-bbbb-bbbbbbbbbbbb',
                    name: 'name-c2222',
                    domainId: 'domain-id',
                    account: 'account',
                    username: 'username',
                    nic: [$mockNic],
                    state: CloudstackMachineState::STOPPED,
                    serviceOfferingId: '',
                ),
            ]);

        $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.cloudstack.virtual-machine.index'))
            ->assertOk()
            ->assertJsonFragment([
                'cloudstack_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'vps_status' => 'Running',
            ])
            ->assertJsonFragment([
                'cloudstack_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                'vps_status' => 'Stopped',
            ])
            ->assertJsonFragment([
                'cloudstack_id' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
                'vps_status' => 'Present',
            ])
            ->assertJsonFragment([
                'cloudstack_id' => 'cccccccc-cccc-2222-cccc-cccccccccccc',
                'vps_status' => 'Running',
            ])
            ->assertJsonFragment([
                'cloudstack_id' => 'bbbbbbbb-bbbb-2222-bbbb-bbbbbbbbbbbb',
                'vps_status' => 'Stopped',
            ]);
    }

    #[Test]
    public function getConsoleUrl(): void
    {
        $expectedUrl = 'https://console.example.com/&someToken=xxx';

        $mockVirtualMachineService = self::createMock(VirtualMachineServiceInterface::class);
        $this->app->bind(VirtualMachineServiceInterface::class, fn () => $mockVirtualMachineService);

        $mockVirtualMachineService
            ->expects(self::once())
            ->method('getConsole')
            ->with(self::assertCallbackIsModel($this->virtualMachineDeployment))
            ->willReturn($expectedUrl);

        $this->actingAsCustomer($this->customer)
            ->get(
                $this->generateRoute('partners.cloudstack.virtual-machine.console', [
                    'subscription' => $this->virtualMachineDeployment->subscription_uuid,
                ]),
            )
            ->assertOk()
            ->assertJsonFragment([
                'message' => 'success',
                'console_url' => $expectedUrl,
                'errors' => [],
            ]);
    }

    #[Test]
    public function getConsoleUrlVmNotFound(): void
    {
        $mockVirtualMachineService = self::createMock(VirtualMachineServiceInterface::class);
        $this->app->bind(VirtualMachineServiceInterface::class, fn () => $mockVirtualMachineService);

        $mockVirtualMachineService
            ->expects(self::once())
            ->method('getConsole')
            ->with(self::assertCallbackIsModel($this->virtualMachineDeployment))
            ->willThrowException(new VirtualMachineNotFoundException('exception message'));

        $this->actingAsCustomer($this->customer)
            ->get(
                $this->generateRoute('partners.cloudstack.virtual-machine.console', [
                    'subscription' => $this->virtualMachineDeployment->subscription_uuid,
                ]),
            )
            ->assertNotFound()
            ->assertJsonMissing(['console_url'])
            ->assertJsonFragment([
                'message' => 'vps.not-found',
                'errors' => [],
            ]);
    }

    #[Test]
    public function updateCustomName(): void
    {
        $custom_name = 'sandwave vps';

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.cloudstack.virtual-machine.custom-name', [
                    'subscription' => $this->virtualMachineDeployment->subscription_uuid,
                    'custom_name' => $custom_name,
                ]),
            )
            ->assertOk()
            ->assertJsonFragment(['message' => 'success']);

        self::assertSame($custom_name, $this->virtualMachineDeployment->refresh()->custom_name);
    }

    #[Test]
    public function updateCustomNameTooLongError(): void
    {
        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.cloudstack.virtual-machine.custom-name', [
                    'subscription' => $this->virtualMachineDeployment->subscription_uuid,
                    'custom_name' => 'sandwave sandwave sandwave sandwave sandwave sandwave sandwave sandwave sandwave',
                ]),
            )
            ->assertUnprocessable()
            ->assertJsonFragment(['custom_name' => ['Dit veld mag niet groter zijn dan 50 karakters.']]);
    }

    #[Test]
    public function updateCustomNameVirtualMachineNotFound(): void
    {
        $this->virtualMachineDeployment->delete();

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.cloudstack.virtual-machine.custom-name', [
                    'subscription' => $this->virtualMachineDeployment->subscription_uuid,
                    'custom_name' => 'sandwave vps',
                ]),
            )
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'vps.not-found']);
    }

    private function createMockNic(): Nic
    {
        return new Nic(
            id: '1',
            networkId: 'network-id',
            networkName: 'network-name',
            netmask: 'netmask',
            gateway: 'gateway',
            ipAddress: '13.37.13.37',
            isolationUri: 'isolation-uri',
            broadcastUri: 'broadcast-uri',
            trafficType: 'traffic-type',
            type: 'type',
            isDefault: true,
            macAddress: 'mac-address',
            ip6Gateway: 'ip6-gateway',
            ip6Cidr: 'ip6-cidr',
            ip6Address: '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            secondaryIp: [],
            extraDhcpOption: [],
            deviceId: 'device-id',
        );
    }

    private function setupMockingVps(string $method, int $calls, bool $exception = false): void
    {
        $client = $this->createMock(CloudStackClient::class);

        if ($exception) {
            $client->expects(self::exactly($calls))->method($method)->willThrowException(new ClientException());
        } else {
            $client->expects(self::exactly($calls))->method($method);
        }

        $clientFactory = $this->createStub(ClientFactoryInterface::class);
        $clientFactory->method('create')->willReturn($client);

        $this->app->bind(ClientFactory::class, fn () => $clientFactory);
    }
}
