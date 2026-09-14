<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Unit\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\CloudstackJobFactory;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CloudstackVirtualMachineDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\VPS\Enums\VpsActionStatus;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Jobs\ReinstallVirtualMachineJob;
use Waterfront\Domain\VPS\Jobs\WaitForVirtualMachineStoppedSetCredentialsJob;
use Waterfront\Domain\VPS\Mailer\MailCloudstackManagerVpsDetails;
use Waterfront\Domain\VPS\Models\CloudstackJob;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Services\ClientFactory;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\DTO\VirtualMachine;
use Waterfront\Infra\CloudStackClient\Enums\CloudstackMachineState;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(ReinstallVirtualMachineJob::class)]
class ReinstallVirtualMachineTest extends IntegrationTestCase
{
    private const string MOCK_JOB_ID = 'fbhdt34e-bb25-40hj-bd5f-320b9cq27151';
    private const string MOCK_CLOUDSTACK_VM_ID = '055ea001-bf17-4fde-9979-6d5f1c840eb0';

    private VirtualMachineDeployment $vmDeployment;

    private CloudstackJob $cloudstackJob;

    protected function setUp(): void
    {
        parent::setUp();

        $vpsProductGroup = new ProductGroupFactory()->vps()->createOne();
        $vmProduct = new ProductFactory()->createOne([
            'product_group_id' => $vpsProductGroup->id,
            'name' => 'VPS large',
            'slug' => 'vps_large',
        ]);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($vmProduct)
            ->administrativeStatusActive()
            ->createOne();

        $environment = new CloudstackEnvironmentFactory()->createOne();
        $managerDomain = new CloudstackManagerDomainDeploymentFactory()
            ->for($subscription->customer)
            ->for($environment)
            ->createOne();

        $this->vmDeployment = new CloudstackVirtualMachineDeploymentFactory()
            ->for($managerDomain)
            ->for($subscription, 'subscription')
            ->createOne(['cloudstack_id' => self::MOCK_CLOUDSTACK_VM_ID]);

        $osProduct = new ProductFactory()->ubuntu()->createOne();
        new SubscriptionFactory()
            ->withCustomer()
            ->for($osProduct)
            ->parentSubscription($subscription)
            ->createOne();

        $this->cloudstackJob = new CloudstackJobFactory()->createOne([
            'job_id' => self::MOCK_JOB_ID,
            'template_uuid' => $this->vmDeployment->subscription->product->uuid,
        ]);
    }

    #[Test]
    public function cloudstackQueueDispatch(): void
    {
        Queue::fake();

        Queue::assertNothingPushed();

        self::resolve(Dispatcher::class)
            ->dispatch(new ReinstallVirtualMachineJob(
                new VirtualMachineDeployment(),
                new CloudstackJob(),
            ));

        Queue::assertPushedOn(QueueName::CLOUDSTACK->value, ReinstallVirtualMachineJob::class);
    }

    #[Test]
    public function jobShouldBeAsyncOnQueue(): void
    {
        Bus::fake();

        self::resolve(Dispatcher::class)
            ->dispatch(new ReinstallVirtualMachineJob(
                new VirtualMachineDeployment(),
                new CloudstackJob(),
            ));

        Bus::assertNotDispatchedSync(ReinstallVirtualMachineJob::class);
    }

    #[Test]
    public function reinstallVirtualMachinePendingIsReleased(): void
    {
        $cloudstackJobPendingResponse = (string) file_get_contents(__DIR__ . '/../../data/reinstall/pending_job.json');
        /** @var array<string, array<int|string, mixed>|int|string> $cloudStackPendingJob */
        $cloudStackPendingJob = json_decode($cloudstackJobPendingResponse, true, 512, JSON_THROW_ON_ERROR);

        $baseClientMock = self::createMock(CloudStackBaseClient::class);
        $clientMock = self::createMock(CloudStackClient::class);

        $clientFactoryMock = self::createMock(ClientFactoryInterface::class);
        $clientFactoryMock->expects(self::once())->method('create')->willReturn($clientMock);

        $this->app->bind(ClientFactory::class, fn () => $clientFactoryMock);

        $baseClientMock
            ->expects(self::once())
            ->method('execute')
            ->with('queryAsyncJobResult', ['jobid' => self::MOCK_JOB_ID])
            ->willReturn($cloudStackPendingJob);
        $clientMock->expects(self::once())->method('getBaseClient')->willReturn($baseClientMock);

        Queue::after(function (JobProcessed $event) {
            /** @var array<string,string> $body */
            $body = json_decode($event->job->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(ReinstallVirtualMachineJob::class, $body['displayName']);
            self::assertTrue($event->job->isReleased());
            self::assertFalse($event->job->hasFailed());
        });

        self::assertNull($this->vmDeployment->subscription->technical_status);

        self::resolve(Dispatcher::class)
            ->dispatch(new ReinstallVirtualMachineJob(
                $this->vmDeployment,
                $this->cloudstackJob,
            ));

        $this->vmDeployment->refresh();

        self::assertSame(TechnicalStatus::PENDING->value, $this->vmDeployment->subscription->technical_status);
        self::assertSame(VpsActionStatus::REINSTALLING, $this->vmDeployment->last_action_status);
    }

    #[Test]
    public function reinstallVirtualMachineFailesAndLogged(): void
    {
        $cloudstackJobFailedResponse = (string) file_get_contents(__DIR__ . '/../../data/reinstall/failed_job.json');
        /** @var array<string, array<int|string, mixed>|int|string> $cloudStackFailedJob */
        $cloudStackFailedJob = json_decode($cloudstackJobFailedResponse, true, 512, JSON_THROW_ON_ERROR);

        $baseClientMock = self::createMock(CloudStackBaseClient::class);
        $clientMock = self::createMock(CloudStackClient::class);
        $clientFactoryMock = self::createMock(ClientFactoryInterface::class);
        $clientFactoryMock->expects(self::once())->method('create')->willReturn($clientMock);
        $this->app->bind(ClientFactory::class, fn () => $clientFactoryMock);

        $baseClientMock
            ->expects(self::once())
            ->method('execute')
            ->with('queryAsyncJobResult', ['jobid' => self::MOCK_JOB_ID])
            ->willReturn($cloudStackFailedJob);
        $clientMock->expects(self::once())->method('getBaseClient')->willReturn($baseClientMock);

        Queue::after(function (JobProcessed $event) {
            /** @var array<string,string> $body */
            $body = json_decode($event->job->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(ReinstallVirtualMachineJob::class, $body['displayName']);
            self::assertTrue($event->job->hasFailed());
            self::assertFalse($event->job->isReleased());
        });

        self::assertNull($this->vmDeployment->subscription->technical_status);

        self::resolve(Dispatcher::class)
            ->dispatch(new ReinstallVirtualMachineJob(
                $this->vmDeployment,
                $this->cloudstackJob,
            ));

        $this->vmDeployment->refresh();
        self::assertNotNull($this->vmDeployment->last_result_received);
        self::assertStringContainsString('Some error given by Cloudstack', (string) $this->vmDeployment->last_result);
        self::assertSame(TechnicalStatus::OK->value, $this->vmDeployment->subscription->technical_status);
        self::assertSame(VpsActionStatus::REINSTALLING_FAILED, $this->vmDeployment->last_action_status);
    }

    #[Test]
    public function reinstallJobSuccess(): void
    {
        self::assertEmailsSend([
            MailCloudstackManagerVpsDetails::class,
        ]);

        $cloudstackJobFinishedResponse = (string) file_get_contents(__DIR__
        . '/../../data/reinstall/finished_job.json');
        /** @var array<string, mixed> $cloudStackFinishedJob */
        $cloudStackFinishedJob = json_decode($cloudstackJobFinishedResponse, true, 512, JSON_THROW_ON_ERROR);

        $baseClientMock = self::createMock(CloudStackBaseClient::class);
        $clientMock = self::createMock(CloudStackClient::class);
        $clientFactoryMock = self::createStub(ClientFactoryInterface::class);
        $clientFactoryMock->method('create')->willReturn($clientMock);
        $this->app->bind(ClientFactory::class, fn () => $clientFactoryMock);

        $baseClientMock
            ->expects(self::once())
            ->method('execute')
            ->with('queryAsyncJobResult', ['jobid' => self::MOCK_JOB_ID])
            ->willReturn($cloudStackFinishedJob);
        $clientMock->expects(self::once())->method('getBaseClient')->willReturn($baseClientMock);

        $vmDto = new VirtualMachine(
            id: self::MOCK_CLOUDSTACK_VM_ID,
            name: 'restored-vm',
            domainId: 'dom',
            account: 'acct',
            username: 'user',
            nic: [],
            state: CloudstackMachineState::STOPPED,
            serviceOfferingId: 'so-id',
        );
        $clientMock
            ->expects(self::once())
            ->method('getVirtualMachine')
            ->with(self::MOCK_CLOUDSTACK_VM_ID)
            ->willReturn($vmDto);

        $processed = [];
        Queue::after(function (JobProcessed $event) use (&$processed) {
            /** @var array<string, mixed> $body */
            $body = json_decode($event->job->getRawBody(), true, 512, JSON_THROW_ON_ERROR);

            $processed[] = $body['displayName'];

            self::assertFalse($event->job->isReleased());
            self::assertFalse($event->job->hasFailed());
        });

        self::assertNull($this->vmDeployment->subscription->technical_status);

        self::resolve(Dispatcher::class)
            ->dispatch(new ReinstallVirtualMachineJob(
                $this->vmDeployment,
                $this->cloudstackJob,
            ));

        self::assertEqualsCanonicalizing(
            [
                ReinstallVirtualMachineJob::class,
                WaitForVirtualMachineStoppedSetCredentialsJob::class,
            ],
            $processed,
        );

        $this->vmDeployment->refresh();
        self::assertSame(self::MOCK_CLOUDSTACK_VM_ID, $this->vmDeployment->cloudstack_id);
        self::assertSame(TechnicalStatus::OK->value, $this->vmDeployment->subscription->technical_status);
        self::assertSame(VpsActionStatus::REINSTALL_SUCCESS, $this->vmDeployment->last_action_status);
    }
}
