<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Actions;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CloudstackVirtualMachineDeploymentFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SshKeyFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\VPS\Actions\ResolveAndLinkSshKeyAction;
use Waterfront\Domain\VPS\Exceptions\SshKeyNotFoundException;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Repositories\SshKeyRepository;
use Waterfront\Domain\VPS\Services\CloudstackService;

#[CoversClass(ResolveAndLinkSshKeyAction::class)]
#[AllowMockObjectsWithoutExpectations]
class ResolveAndLinkSshKeyActionTest extends IntegrationTestCase
{
    private ResolveAndLinkSshKeyAction $action;

    private SshKeyRepository&MockObject $sshKeyRepositoryMock;

    private Customer $customer;

    private VirtualMachineDeployment $virtualMachineDeployment;

    private ManagerDomainDeployment $managerDomainDeployment;

    private CloudstackService&MockObject $cloudstackServiceMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(
                new ProductFactory()->vps(),
            )
            ->createOne();

        $cloudstackEnvironment = new CloudstackEnvironmentFactory()->createOne();
        $this->managerDomainDeployment = new CloudstackManagerDomainDeploymentFactory()
            ->for($this->customer)
            ->for($cloudstackEnvironment)
            ->createOne();

        $this->virtualMachineDeployment = new CloudstackVirtualMachineDeploymentFactory()
            ->for($subscription)
            ->for($this->managerDomainDeployment)
            ->createOne();

        $this->sshKeyRepositoryMock = self::createMock(SshKeyRepository::class);

        $this->cloudstackServiceMock = self::createMock(CloudStackService::class);

        $this->action = new ResolveAndLinkSshKeyAction(
            sshKeyRepository: $this->sshKeyRepositoryMock,
            cloudstackService: $this->cloudstackServiceMock,
        );
    }

    #[Test]
    public function resolveKeyNotFound(): void
    {
        $this->sshKeyRepositoryMock->expects(self::once())->method('findByUuid')->willReturn(null);

        $this->expectException(SshKeyNotFoundException::class);

        $this->action->execute(
            sshKeyUuid: 'b2cb1ed9-d498-4039-b28c-d7610456a8f6',
            vmDeploymentId: 1,
            managerDomainDeployment: $this->managerDomainDeployment,
        );
    }

    #[Test]
    public function resolveKeyNotLinkedToEnvironment(): void
    {
        $sshKey = new SshKeyFactory()->for($this->customer)->createOne();

        $this->sshKeyRepositoryMock->expects(self::once())->method('findByUuid')->willReturn($sshKey);

        $this->sshKeyRepositoryMock
            ->expects(self::once())
            ->method('keyLinkedToManagerDomain')
            ->with($sshKey, $this->managerDomainDeployment->id)
            ->willReturn(false);

        $this->cloudstackServiceMock
            ->expects(self::once())
            ->method('registerSshKeyPair')
            ->with(
                $this->managerDomainDeployment,
                $sshKey->cloudstack_ssh_name,
                $sshKey->public_key,
            )
            ->willReturn([
                'name' => $sshKey->cloudstack_ssh_name,
                'public_key' => $sshKey->public_key,
            ]);

        $sshKeyPairString = $this->action->execute(
            sshKeyUuid: (string) $sshKey->uuid,
            vmDeploymentId: $this->virtualMachineDeployment->id,
            managerDomainDeployment: $this->managerDomainDeployment,
        );

        Assert::assertSame($sshKey->cloudstack_ssh_name, $sshKeyPairString);
        $this->assertDatabaseHas('cloudstack_managerdomain_cloudstack_vm_ssh_keys', [
            'ssh_key_id' => $sshKey->id,
            'manager_domain_deployment_id' => $this->managerDomainDeployment->id,
        ]);

        $this->assertDatabaseHas('cloudstack_vm_deployment_ssh_key', [
            'ssh_key_id' => $sshKey->id,
            'vm_deployment_id' => $this->virtualMachineDeployment->id,
        ]);
    }

    #[Test]
    public function resolveKeyLinkedToEnvironment(): void
    {
        $sshKey = new SshKeyFactory()->for($this->customer)->createOne();

        $sshKey->managerDomains()->save($this->managerDomainDeployment);

        $this->sshKeyRepositoryMock->expects(self::once())->method('findByUuid')->willReturn($sshKey);

        $this->sshKeyRepositoryMock
            ->expects(self::once())
            ->method('keyLinkedToManagerDomain')
            ->with($sshKey, $this->managerDomainDeployment->id)
            ->willReturn(true);

        $sshKeyPairString = $this->action->execute(
            sshKeyUuid: (string) $sshKey->uuid,
            vmDeploymentId: $this->virtualMachineDeployment->id,
            managerDomainDeployment: $this->managerDomainDeployment,
        );

        Assert::assertSame($sshKey->cloudstack_ssh_name, $sshKeyPairString);
        $this->assertDatabaseHas('cloudstack_managerdomain_cloudstack_vm_ssh_keys', [
            'ssh_key_id' => $sshKey->id,
            'manager_domain_deployment_id' => $this->managerDomainDeployment->id,
        ]);

        $this->assertDatabaseHas('cloudstack_vm_deployment_ssh_key', [
            'ssh_key_id' => $sshKey->id,
            'vm_deployment_id' => $this->virtualMachineDeployment->id,
        ]);
    }
}
