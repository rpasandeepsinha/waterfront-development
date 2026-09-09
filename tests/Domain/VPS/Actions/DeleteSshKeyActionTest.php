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
use Waterfront\Domain\VPS\Actions\DeleteSshKeyAction;
use Waterfront\Domain\VPS\DTO\DeleteSshKeyPairResponse;
use Waterfront\Domain\VPS\Exceptions\CloudstackException;
use Waterfront\Domain\VPS\Exceptions\SshKeyNotDeletableException;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\SshKey;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Repositories\SshKeyRepository;
use Waterfront\Domain\VPS\Services\CloudstackService;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(DeleteSshKeyAction::class)]
#[AllowMockObjectsWithoutExpectations]
class DeleteSshKeyActionTest extends IntegrationTestCase
{
    private DeleteSshKeyAction $deleteSshKeyAction;

    private LoggerInterface&MockObject $loggerMock;

    private Customer $customer;

    private SshKey $sshKey;

    private VirtualMachineDeployment $virtualMachineDeployment;

    private CloudstackService&MockObject $cloudstackServiceMock;

    private ManagerDomainDeployment $managerDomainDeployment;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer =  new CustomerFactory()->createOne();

        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->cloudstackServiceMock = $this->createMock(CloudstackService::class);
        $sshKeyRepositoryMock = $this->resolve(SshKeyRepository::class);

        $this->deleteSshKeyAction = new DeleteSshKeyAction(
            logger: $this->loggerMock,
            cloudstackService: $this->cloudstackServiceMock,
            sshKeyRepository: $sshKeyRepositoryMock
        );

        $subscription  = new SubscriptionFactory()
            ->for($this->customer)
            ->for(
                new ProductFactory()->vps()
            )->createOne();

        $environment = new CloudstackEnvironmentFactory()->createOne();
        $this->managerDomainDeployment = new CloudstackManagerDomainDeploymentFactory()
            ->for($subscription->customer)
            ->for($environment)
            ->createOne();

        $this->sshKey = new SshKeyFactory()
            ->for($this->customer)
            ->createOne();

        $this->virtualMachineDeployment = new CloudstackVirtualMachineDeploymentFactory()
            ->for($subscription)
            ->for($this->managerDomainDeployment)
            ->createOne();
    }

    #[Test]
    public function deleteSshKeyAction(): void
    {
        $this->managerDomainDeployment->sshKeys()->save($this->sshKey);

        $cloudstackResponse = new DeleteSshKeyPairResponse(
            success: true,
            displaytext: 'Successfully deleted the SSH key.'
        );

        $this->cloudstackServiceMock
            ->expects(self::once())
            ->method('deleteSshKeyPair')
            ->with(
                self::assertCallbackIsModel($this->managerDomainDeployment),
                $this->sshKey->cloudstack_ssh_name
            )
        ->willReturn($cloudstackResponse);

        $this->loggerMock->expects(self::once())
            ->method('info')
            ->with('SSH key deleted successfully in CloudStack.', [
                LoggingContextKeys::CUSTOMER_ID => $this->managerDomainDeployment->customer_id,
                LoggingContextKeys::META => [
                    'cloudstack_message' => $cloudstackResponse->displaytext,
                    'key_name' => $this->sshKey->key_name,
                    'fingerprint' => $this->sshKey->fingerprint,
                    'ssh_key_name' => $this->sshKey->cloudstack_ssh_name,
                ],
            ]);

        $actionResult = $this->deleteSshKeyAction->execute($this->sshKey, $this->customer);

        Assert::assertTrue($actionResult);

        $existingKeys = SshKey::where('id', $this->sshKey->id)->get();
        Assert::assertCount(0, $existingKeys);

        $this->assertDatabaseMissing(
            'cloudstack_managerdomain_cloudstack_vm_ssh_keys',
            ['ssh_key_id' => $this->sshKey->id]
        );

        $this->assertDatabaseMissing(
            'cloudstack_vm_deployment_ssh_key',
            ['ssh_key_id' => $this->sshKey->id]
        );
    }

    #[Test]
    public function deleteSshKeyActionFailedNotDeletable(): void
    {
        $this->managerDomainDeployment->sshKeys()->save($this->sshKey);
        $this->virtualMachineDeployment->sshKeys()->save($this->sshKey);

        $this->loggerMock->expects(self::once())
            ->method('debug')
            ->with(
                'SSH key is not deletable',
                [
                    LoggingContextKeys::CUSTOMER_ID => $this->customer->id,
                    LoggingContextKeys::META => [
                        'key_id' => $this->sshKey->id,
                        'key_name' => $this->sshKey->key_name,
                        'fingerprint' => $this->sshKey->fingerprint,
                        'ssh_key_name' => $this->sshKey->cloudstack_ssh_name,
                    ],
                ]
            );

        $this->expectException(SshKeyNotDeletableException::class);
        $this->deleteSshKeyAction->execute($this->sshKey, $this->customer);
    }

    #[Test]
    public function deleteSshKeyActionFailedDueErrorFromCloudstackApi(): void
    {
        $this->managerDomainDeployment->sshKeys()->save($this->sshKey);

        $cloudstackResponse = new DeleteSshKeyPairResponse(
            success: false,
            displaytext: 'Error message from cloudstack.'
        );

        $this->cloudstackServiceMock
            ->expects(self::once())
            ->method('deleteSshKeyPair')
            ->with(
                self::assertCallbackIsModel($this->managerDomainDeployment),
                $this->sshKey->cloudstack_ssh_name
            )
            ->willReturn($cloudstackResponse);

        $this->loggerMock->expects(self::once())
            ->method('error')
            ->with('SSH key could not be deleted due to an error in cloudstack', [
                LoggingContextKeys::CUSTOMER_ID =>  $this->managerDomainDeployment->customer_id,
                LoggingContextKeys::META => [
                    'cloudstack_error_message' => $cloudstackResponse->displaytext,
                    'key_name' => $this->sshKey->key_name,
                    'fingerprint' => $this->sshKey->fingerprint,
                    'ssh_key_name' => $this->sshKey->cloudstack_ssh_name,
                    'manager_domain_deployment_id' =>  $this->managerDomainDeployment->id,
                ],
            ]);

        $actionResult = $this->deleteSshKeyAction->execute($this->sshKey, $this->customer);

        Assert::assertFalse($actionResult);

        $existingKeys = SshKey::where('id', $this->sshKey->id)->get();
        Assert::assertCount(1, $existingKeys);
    }

    #[Test]
    public function deleteSshKeyActionFailedClientError(): void
    {
        $this->managerDomainDeployment->sshKeys()->save($this->sshKey);

        $cloudstackException = new CloudstackException('Test message text');
        $this->cloudstackServiceMock
            ->expects(self::once())
            ->method('deleteSshKeyPair')
            ->with(
                self::assertCallbackIsModel($this->managerDomainDeployment),
                $this->sshKey->cloudstack_ssh_name
            )
            ->willThrowException($cloudstackException);

        $this->loggerMock->expects(self::once())
            ->method('error')
            ->with('Cloudstack responded with a client error: SSH key is not deleted', [
                LoggingContextKeys::CUSTOMER_ID => $this->managerDomainDeployment->customer_id,
                LoggingContextKeys::EXCEPTION => $cloudstackException,
                LoggingContextKeys::META => [
                    'key_name' => $this->sshKey->key_name,
                    'fingerprint' => $this->sshKey->fingerprint,
                    'ssh_key_name' => $this->sshKey->cloudstack_ssh_name,
                    'manager_domain_deployment_id' => $this->managerDomainDeployment->id,
                    'environment_id' => $this->managerDomainDeployment->environment_id,
                ],
            ]);

        $actionResult = $this->deleteSshKeyAction->execute($this->sshKey, $this->customer);

        Assert::assertFalse($actionResult);

        $existingKeys = SshKey::where('id', $this->sshKey->id)->get();
        Assert::assertCount(1, $existingKeys);
    }
}
