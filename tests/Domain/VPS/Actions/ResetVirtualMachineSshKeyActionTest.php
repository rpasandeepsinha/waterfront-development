<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Actions;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CloudstackVirtualMachineDeploymentFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SshKeyFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\VPS\Actions\ResetVirtualMachineSshKeyAction;
use Waterfront\Domain\VPS\Exceptions\CloudstackNotFoundException;
use Waterfront\Domain\VPS\Exceptions\VirtualMachineNotFoundException;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\SshKey;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Repositories\SshKeyRepository;
use Waterfront\Domain\VPS\Services\VirtualMachineService;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\DTO\VirtualMachine;
use Waterfront\Infra\CloudStackClient\Enums\CloudstackMachineState;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(ResetVirtualMachineSshKeyAction::class)]
#[AllowMockObjectsWithoutExpectations]
class ResetVirtualMachineSshKeyActionTest extends IntegrationTestCase
{
    private ResetVirtualMachineSshKeyAction $action;

    private Customer $customer;

    private VirtualMachineDeployment $virtualMachineDeployment;

    private VirtualMachineService&MockObject $virtualMachineServiceMock;

    private SshKey $currentSshKey;

    private ClientFactoryInterface&MockObject $cloudStackClientFactoryMock;

    private CloudStackClient&MockObject $cloudStackClientMock;

    private SshKeyRepository&MockObject $sshKeyRepositoryMock;

    private LoggerInterface&MockObject $loggerMock;

    private ManagerDomainDeployment $managerDomainDeployment;

    public function setUp(): void
    {
        parent::setUp();

        $this->virtualMachineServiceMock = $this->createMock(VirtualMachineService::class);

        $this->sshKeyRepositoryMock = $this->createMock(SshKeyRepository::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->customer = new CustomerFactory()->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(
                new ProductFactory()->vps(),
            )
            ->createOne();

        $environment = new CloudstackEnvironmentFactory()->createOne();
        $this->managerDomainDeployment = new CloudstackManagerDomainDeploymentFactory()
            ->for($this->customer)
            ->for($environment)
            ->createOne();

        $this->virtualMachineDeployment = new CloudstackVirtualMachineDeploymentFactory()
            ->for($subscription)
            ->for($this->managerDomainDeployment)
            ->createOne();

        $this->currentSshKey = new SshKeyFactory()->for($this->customer)->createOne();

        $this->managerDomainDeployment->sshKeys()->save($this->currentSshKey);
        $this->virtualMachineDeployment->sshKeys()->save($this->currentSshKey);

        $this->cloudStackClientMock = $this->createMock(CloudStackClient::class);
        $this->cloudStackClientFactoryMock = $this->createMock(ClientFactoryInterface::class);

        $this->action = new ResetVirtualMachineSshKeyAction(
            virtualMachineService: $this->virtualMachineServiceMock,
            sshKeyRepository: $this->sshKeyRepositoryMock,
            cloudStackClientFactory: $this->cloudStackClientFactoryMock,
            logger: $this->loggerMock,
        );
    }

    /**
     * Scenario 1 :
     *  - There is already a key set for the current VM. The key we want to use to reset with is not yet linked to a
     *    manager domain, so this needs to be registered at cloudstack through the API
     */
    #[Test]
    public function resetVirtualMachineSshKeyActionWithNewKey(): void
    {
        $newSshKey = new SshKeyFactory()->for($this->customer)->createOne();

        $this->virtualMachineServiceMock
            ->expects(self::once())
            ->method('findByDeployment')
            ->willReturn(new VirtualMachine(
                id: '63223f6d-83dd-4fd7-be8c-9832336d9e14',
                name: 'testname',
                domainId: '57f75e48-37be-43f8-835d-7c2c4bfd33f5',
                account: 'account',
                username: 'testusername',
                nic: [],
                state: CloudstackMachineState::STOPPED,
                serviceOfferingId: 'c983361b-0e5d-462a-af2c-e18afeaa4be4',
                password: 'secret',
            ));

        $this->sshKeyRepositoryMock
            ->expects(self::once())
            ->method('keyLinkedToManagerDomain')
            ->with($newSshKey, $this->managerDomainDeployment->id)
            ->willReturn(false);

        $this->cloudStackClientFactoryMock
            ->expects(self::once())
            ->method('create')
            ->willReturn($this->cloudStackClientMock);

        $this->cloudStackClientMock->expects(self::once())->method('registerSshKeyPair');

        $this->virtualMachineServiceMock
            ->expects(self::once())
            ->method('resetSshKey')
            ->with($this->virtualMachineDeployment, $newSshKey->cloudstack_ssh_name)
            ->willReturn(true);

        $logMessage = sprintf(
            'Resetting SSH key for virtual machine with subscription uuid : %s',
            $this->virtualMachineDeployment->subscription_uuid,
        );
        $this->loggerMock
            ->expects(self::once())
            ->method('info')
            ->with($logMessage, [
                LoggingContextKeys::CUSTOMER_ID => $this->virtualMachineDeployment->subscription->customer_id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->virtualMachineDeployment->subscription_uuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS->value,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK->value,
                LoggingContextKeys::META => [
                    'old_ssh_key_id' => $this->currentSshKey->id,
                    'old_ssh_key_cloudstack_name' => $this->currentSshKey->cloudstack_ssh_name,
                    'new_ssh_key_id' => $newSshKey->id,
                    'new_ssh_key_cloudstack_name' => $newSshKey->cloudstack_ssh_name,
                ],
            ]);

        $actionResult = $this->action->execute(
            virtualMachineDeployment: $this->virtualMachineDeployment,
            newSshKey: $newSshKey,
        );

        Assert::assertTrue($actionResult);
        Assert::assertCount(1, $this->virtualMachineDeployment->sshKeys);
        Assert::assertCount(2, $this->managerDomainDeployment->sshKeys);

        $this->assertDatabaseHas('cloudstack_managerdomain_cloudstack_vm_ssh_keys', [
            'ssh_key_id' => $newSshKey->id,
            'manager_domain_deployment_id' => $this->managerDomainDeployment->id,
        ]);

        $this->assertDatabaseHas('cloudstack_vm_deployment_ssh_key', [
            'ssh_key_id' => $newSshKey->id,
            'vm_deployment_id' => $this->virtualMachineDeployment->id,
        ]);
    }

    /**
     * Scenario 2 :
     *  - There is already a key set for the current VM. The key we want to use to reset with is already linked to a
     *    manager domain, so this needs not to be registered at cloudstack through the API
     */
    #[Test]
    public function resetVirtualMachineSshKeyActionWithExistingKey(): void
    {
        $newSshKey = new SshKeyFactory()->for($this->customer)->createOne();

        $this->managerDomainDeployment->sshKeys()->save($newSshKey);

        $this->virtualMachineServiceMock
            ->expects(self::once())
            ->method('findByDeployment')
            ->willReturn(new VirtualMachine(
                id: '63223f6d-83dd-4fd7-be8c-9832336d9e14',
                name: 'testname',
                domainId: '57f75e48-37be-43f8-835d-7c2c4bfd33f5',
                account: 'account',
                username: 'testusername',
                nic: [],
                state: CloudstackMachineState::STOPPED,
                serviceOfferingId: 'c983361b-0e5d-462a-af2c-e18afeaa4be4',
                password: 'secret',
            ));

        $this->sshKeyRepositoryMock
            ->expects(self::once())
            ->method('keyLinkedToManagerDomain')
            ->with($newSshKey, $this->managerDomainDeployment->id)
            ->willReturn(true);

        $this->cloudStackClientFactoryMock->expects(self::never())->method('create');

        $this->cloudStackClientMock->expects(self::never())->method('registerSshKeyPair');

        $this->virtualMachineServiceMock
            ->expects(self::once())
            ->method('resetSshKey')
            ->with($this->virtualMachineDeployment, $newSshKey->cloudstack_ssh_name)
            ->willReturn(true);

        $logMessage = sprintf(
            'Resetting SSH key for virtual machine with subscription uuid : %s',
            $this->virtualMachineDeployment->subscription_uuid,
        );
        $this->loggerMock
            ->expects(self::once())
            ->method('info')
            ->with($logMessage, [
                LoggingContextKeys::CUSTOMER_ID => $this->virtualMachineDeployment->subscription->customer_id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->virtualMachineDeployment->subscription_uuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS->value,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK->value,
                LoggingContextKeys::META => [
                    'old_ssh_key_id' => $this->currentSshKey->id,
                    'old_ssh_key_cloudstack_name' => $this->currentSshKey->cloudstack_ssh_name,
                    'new_ssh_key_id' => $newSshKey->id,
                    'new_ssh_key_cloudstack_name' => $newSshKey->cloudstack_ssh_name,
                ],
            ]);

        $actionResult = $this->action->execute(
            virtualMachineDeployment: $this->virtualMachineDeployment,
            newSshKey: $newSshKey,
        );

        Assert::assertTrue($actionResult);
        Assert::assertCount(1, $this->virtualMachineDeployment->sshKeys);
        Assert::assertCount(2, $this->managerDomainDeployment->sshKeys);

        $this->assertDatabaseHas('cloudstack_managerdomain_cloudstack_vm_ssh_keys', [
            'ssh_key_id' => $newSshKey->id,
            'manager_domain_deployment_id' => $this->managerDomainDeployment->id,
        ]);

        $this->assertDatabaseHas('cloudstack_vm_deployment_ssh_key', [
            'ssh_key_id' => $newSshKey->id,
            'vm_deployment_id' => $this->virtualMachineDeployment->id,
        ]);
    }

    #[Test]
    public function resetVirtualMachineSshKeyActionFailedNotInAStoppedState(): void
    {
        $newSshKey = new SshKeyFactory()->for($this->customer)->createOne();

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with('Cant reset virtual Machine SSH key | The machine is not in a stopped state', [
                LoggingContextKeys::CUSTOMER_ID => $this->virtualMachineDeployment->subscription->customer_id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->virtualMachineDeployment->subscription_uuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS->value,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK->value,
                LoggingContextKeys::META => [
                    'new_ssh_key_id' => $newSshKey->id,
                ],
            ]);

        $this->virtualMachineServiceMock
            ->expects(self::once())
            ->method('findByDeployment')
            ->willReturn(new VirtualMachine(
                id: '63223f6d-83dd-4fd7-be8c-9832336d9e14',
                name: 'testname',
                domainId: '57f75e48-37be-43f8-835d-7c2c4bfd33f5',
                account: 'account',
                username: 'testusername',
                nic: [],
                state: CloudstackMachineState::RUNNING,
                serviceOfferingId: 'c983361b-0e5d-462a-af2c-e18afeaa4be4',
                password: 'secret',
            ));

        $this->cloudStackClientFactoryMock->expects(self::never())->method('create');

        $this->virtualMachineServiceMock->expects(self::never())->method('resetSshKey');

        $actionResult = $this->action->execute(
            virtualMachineDeployment: $this->virtualMachineDeployment,
            newSshKey: $newSshKey,
        );

        Assert::assertFalse($actionResult);
    }

    #[Test]
    public function resetVirtualMachineSshKeyActionFailedNotFoundThroughApi(): void
    {
        $newSshKey = new SshKeyFactory()->for($this->customer)->createOne();

        $this->virtualMachineServiceMock
            ->expects(self::once())
            ->method('findByDeployment')
            ->willThrowException(new CloudstackNotFoundException());

        $this->virtualMachineServiceMock->expects(self::never())->method('resetSshKey');

        $this->sshKeyRepositoryMock->expects(self::never())->method('keyLinkedToManagerDomain');

        $this->cloudStackClientFactoryMock->expects(self::never())->method('create');

        $this->cloudStackClientMock->expects(self::never())->method('registerSshKeyPair');

        $this->expectException(VirtualMachineNotFoundException::class);
        $this->action->execute(
            virtualMachineDeployment: $this->virtualMachineDeployment,
            newSshKey: $newSshKey,
        );
    }

    #[Test]
    public function resetVirtualMachineSshKeyActionFailedNullReturned(): void
    {
        $newSshKey = new SshKeyFactory()->for($this->customer)->createOne();

        $this->virtualMachineServiceMock->expects(self::once())->method('findByDeployment')->willReturn(null);

        $this->virtualMachineServiceMock->expects(self::never())->method('resetSshKey');

        $this->sshKeyRepositoryMock->expects(self::never())->method('keyLinkedToManagerDomain');

        $this->cloudStackClientFactoryMock->expects(self::never())->method('create');

        $this->cloudStackClientMock->expects(self::never())->method('registerSshKeyPair');

        $this->expectException(VirtualMachineNotFoundException::class);
        $this->action->execute(
            virtualMachineDeployment: $this->virtualMachineDeployment,
            newSshKey: $newSshKey,
        );
    }

    #[Test]
    public function resetVirtualMachineSshKeyActionNotLinkedToEnvironment(): void
    {
        $newSshKey = new SshKeyFactory()->for($this->customer)->createOne();

        $this->virtualMachineServiceMock
            ->expects(self::once())
            ->method('findByDeployment')
            ->willReturn(new VirtualMachine(
                id: '63223f6d-83dd-4fd7-be8c-9832336d9e14',
                name: 'testname',
                domainId: '57f75e48-37be-43f8-835d-7c2c4bfd33f5',
                account: 'account',
                username: 'testusername',
                nic: [],
                state: CloudstackMachineState::STOPPED,
                serviceOfferingId: 'c983361b-0e5d-462a-af2c-e18afeaa4be4',
                password: 'secret',
            ));

        $this->virtualMachineServiceMock->expects(self::once())->method('resetSshKey')->willReturn(true);

        $this->sshKeyRepositoryMock
            ->expects(self::once())
            ->method('keyLinkedToManagerDomain')
            ->with($newSshKey, $this->managerDomainDeployment->id)
            ->willReturn(false);

        $this->cloudStackClientFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with($this->virtualMachineDeployment->managerDomainDeployment)
            ->willReturn($this->cloudStackClientMock);

        $this->cloudStackClientMock
            ->expects(self::once())
            ->method('registerSshKeyPair')
            ->with(
                $newSshKey->cloudstack_ssh_name,
                $newSshKey->public_key,
            );

        $actionResult = $this->action->execute(
            virtualMachineDeployment: $this->virtualMachineDeployment,
            newSshKey: $newSshKey,
        );

        Assert::assertTrue($actionResult);
        $this->assertDatabaseHas('cloudstack_managerdomain_cloudstack_vm_ssh_keys', [
            'ssh_key_id' => $newSshKey->id,
            'manager_domain_deployment_id' => $this->managerDomainDeployment->id,
        ]);

        $this->assertDatabaseHas('cloudstack_vm_deployment_ssh_key', [
            'ssh_key_id' => $newSshKey->id,
            'vm_deployment_id' => $this->virtualMachineDeployment->id,
        ]);
    }

    #[Test]
    public function resetVirtualMachineSshKeyActionFailedNottingToReset(): void
    {
        /**
         * The detachment is a bit unusual in a test.
         * But this was chosen because it is the only exception where we do not want an existing key.
         * The alternative would be to repeat the construct from the setup.
         */
        $this->virtualMachineDeployment->sshKeys()->detach($this->currentSshKey);

        $newSshKey = new SshKeyFactory()->for($this->customer)->createOne();

        $this->cloudStackClientFactoryMock->expects(self::never())->method('create');

        $this->virtualMachineServiceMock->expects(self::never())->method('findByDeployment');

        $this->virtualMachineServiceMock->expects(self::never())->method('resetSshKey');

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with('Cant reset virtual Machine SSH key | There is no key set yet on the virtual machine', [
                LoggingContextKeys::CUSTOMER_ID => $this->customer->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->virtualMachineDeployment->subscription_uuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS->value,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK->value,
                LoggingContextKeys::META => [
                    'new_ssh_key_id' => $newSshKey->id,
                ],
            ]);

        $actionResult = $this->action->execute(
            virtualMachineDeployment: $this->virtualMachineDeployment,
            newSshKey: $newSshKey,
        );

        Assert::assertFalse($actionResult);
    }
}
