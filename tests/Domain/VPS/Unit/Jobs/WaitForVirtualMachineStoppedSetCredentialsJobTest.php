<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Unit\Jobs;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Jobs\WaitForVirtualMachineStoppedSetCredentialsJob;
use Waterfront\Domain\VPS\Models\CloudstackJob;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Services\VirtualMachineService;
use Waterfront\Domain\VPS\Services\VpsService;
use Waterfront\Infra\CloudStackClient\DTO\VirtualMachine;
use Waterfront\Infra\CloudStackClient\Enums\CloudstackMachineState;

#[CoversClass(WaitForVirtualMachineStoppedSetCredentialsJob::class)]
#[AllowMockObjectsWithoutExpectations]
class WaitForVirtualMachineStoppedSetCredentialsJobTest extends TestCase
{
    private VirtualMachineDeployment $deployment;

    private CloudstackJob $cloudstackJob;

    private AsynchronousCloudstackResponse&MockObject $jobResponse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deployment = new VirtualMachineDeployment();
        $this->deployment->id = 42;
        $this->deployment->subscription_uuid = 'sub-xyz';

        $this->cloudstackJob = new CloudstackJob(['job_id' => 'job-123']);
        $this->jobResponse  = $this->createMock(AsynchronousCloudstackResponse::class);
    }

    #[Test]
    public function backoffReturnsConfiguredDelays(): void
    {
        $job = new WaitForVirtualMachineStoppedSetCredentialsJob(
            $this->deployment,
            $this->cloudstackJob,
            $this->jobResponse
        );

        self::assertSame(
            [10, 20, 30, 60, 360],
            $job->backoff()
        );
    }

    #[Test]
    public function handleWhenVmNotStoppedReleasesJobAndLogsInfo(): void
    {
        /** @var WaitForVirtualMachineStoppedSetCredentialsJob&MockObject $job */
        $job = $this->getMockBuilder(WaitForVirtualMachineStoppedSetCredentialsJob::class)
            ->setConstructorArgs([
                $this->deployment,
                $this->cloudstackJob,
                $this->jobResponse,
            ])
            ->onlyMethods(['release'])
            ->getMock();

        $vmService  = $this->createMock(VirtualMachineService::class);
        $vpsService = $this->createMock(VpsService::class);
        $logger     = $this->createMock(LoggerInterface::class);

        $vmService
            ->expects(self::once())
            ->method('findByDeployment')
            ->with($this->deployment)
            ->willReturn(new VirtualMachine(
                id: 'vm1',
                name: 'n',
                domainId: 'd',
                account: 'a',
                username: 'u',
                nic: [],
                state: CloudstackMachineState::RUNNING,
                serviceOfferingId: 'so'
            ));

        $logger
            ->expects(self::once())
            ->method('info')
            ->with(self::stringContains('Waiting for VM to stop, current state: Running'));

        $job
            ->expects(self::once())
            ->method('release')
            ->with(self::isInt());

        $vpsService->expects(self::never())->method('mailCustomerVmDetails');
        $vmService->expects(self::never())->method('postReinstall');

        $job->handle($vmService, $vpsService, $logger);
    }

    #[Test]
    public function handleWhenVmStoppedMailsAndCallsPostReinstall(): void
    {
        $vmService  = $this->createMock(VirtualMachineService::class);
        $vpsService = $this->createMock(VpsService::class);
        $logger     = $this->createMock(LoggerInterface::class);

        $job = new WaitForVirtualMachineStoppedSetCredentialsJob(
            $this->deployment,
            $this->cloudstackJob,
            $this->jobResponse
        );

        $vmService
            ->expects(self::once())
            ->method('findByDeployment')
            ->with($this->deployment)
            ->willReturn(new VirtualMachine(
                id: 'vm1',
                name: 'n',
                domainId: 'd',
                account: 'a',
                username: 'u',
                nic: [],
                state: CloudstackMachineState::STOPPED,
                serviceOfferingId: 'so'
            ));

        $logger->expects(self::never())
               ->method('info')
               ->with(self::stringContains('Waiting for VM to stop'));

        $fakeData = [
            'id'                => 'vm1',
            'name'              => 'n',
            'domainid'          => 'd',
            'account'           => 'a',
            'username'          => 'u',
            'nic'               => [],
            'state'             => CloudstackMachineState::STOPPED->value,
            'serviceofferingid' => 'so',
        ];
        $this->jobResponse
            ->expects(self::once())
            ->method('retrieveReinstallData')
            ->willReturn($fakeData);

        $vpsService
            ->expects(self::once())
            ->method('mailCustomerVmDetails')
            ->with(
                $this->deployment,
                self::callback(fn ($vm) => $vm instanceof VirtualMachine && $vm->id === 'vm1')
            );

        $vmService
            ->expects(self::once())
            ->method('postReinstall')
            ->with($this->deployment, $this->cloudstackJob);

        $job->handle($vmService, $vpsService, $logger);
    }
}
