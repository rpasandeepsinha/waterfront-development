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
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Jobs\DeployVirtualMachineJob;
use Waterfront\Domain\VPS\Mailer\MailCloudstackManagerVpsDetails;
use Waterfront\Domain\VPS\Models\CloudstackJob;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Services\ClientFactory;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(DeployVirtualMachineJob::class)]
class DeployVirtualMachineTest extends IntegrationTestCase
{
    private const string MOCK_JOB_ID = 'fbede33e-aa89-40ac-bc5f-320b9ae27151';

    private VirtualMachineDeployment $vmDeployment;

    private CloudstackJob $cloudstackJob;

    protected function setUp(): void
    {
        parent::setUp();

        $vpsProductGroup = new ProductGroupFactory()->vps()->createOne();

        $vmProduct = new ProductFactory()->for($vpsProductGroup)->createOne([
            'name' => 'VPS large',
            'slug' => 'vps_large',
        ]);

        $cloudStackOsProductGroup = new ProductGroupFactory()->createOne([
            'name' => 'VPS Operating System',
            'slug' => 'cloudstack-os',
            'ledger_code' => 7331,
        ]);

        $cloudStackOsProduct = new ProductFactory()->for($cloudStackOsProductGroup)->createOne([
            'name' => 'Operating System',
            'slug' => 'cloudstack-os',
            'description' => 'VPS operating system',
            'orderable' => true,
            'weight' => 1,
        ]);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($vmProduct)
            ->administrativeStatusActive()
            ->createOne();

        new SubscriptionFactory()
            ->withCustomer()
            ->for($cloudStackOsProduct)
            ->administrativeStatusActive()
            ->parentSubscription($subscription)
            ->createOne();

        $environment = new CloudstackEnvironmentFactory()->createOne();

        $managerDomain = new CloudstackManagerDomainDeploymentFactory()
            ->for($subscription->customer)
            ->for($environment)
            ->createOne();

        $this->vmDeployment = new CloudstackVirtualMachineDeploymentFactory()
            ->for($managerDomain)
            ->for($subscription, 'subscription')
            ->createOne(['cloudstack_id' => null]);

        $this->cloudstackJob = new CloudstackJobFactory()->createOne([
            'job_id' => self::MOCK_JOB_ID,
        ]);
    }

    #[Test]
    public function cloudstackQueueDispatch(): void
    {
        Queue::fake();

        Queue::assertNothingPushed();

        self::resolve(Dispatcher::class)
            ->dispatch(new DeployVirtualMachineJob(
                new VirtualMachineDeployment(),
                new CloudstackJob(),
            ));

        Queue::assertPushedOn(QueueName::CLOUDSTACK->value, DeployVirtualMachineJob::class);
    }

    #[Test]
    public function jobShouldBeAsyncOnQueue(): void
    {
        Bus::fake();

        self::resolve(Dispatcher::class)
            ->dispatch(new DeployVirtualMachineJob(
                new VirtualMachineDeployment(),
                new CloudstackJob(),
            ));

        Bus::assertNotDispatchedSync(DeployVirtualMachineJob::class);
    }

    #[Test]
    public function deployVirtualMachinePendingIsReleased(): void
    {
        $cloudstackJobPendingResponse = (string) file_get_contents(__DIR__ . '/../../data/deployment/pending_job.json');
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
            self::assertSame(DeployVirtualMachineJob::class, $body['displayName']);
            self::assertTrue($event->job->isReleased());
            self::assertFalse($event->job->hasFailed());
        });

        self::resolve(Dispatcher::class)
            ->dispatch(new DeployVirtualMachineJob(
                $this->vmDeployment,
                $this->cloudstackJob,
            ));
    }

    #[Test]
    public function deployVirtualMachineFailesAndLogged(): void
    {
        $cloudstackJobFailedResponse = (string) file_get_contents(__DIR__ . '/../../data/deployment/failed_job.json');
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
            self::assertSame(DeployVirtualMachineJob::class, $body['displayName']);
            self::assertTrue($event->job->hasFailed());
            self::assertFalse($event->job->isReleased());
        });

        self::assertNull($this->vmDeployment->subscription->technical_status);

        self::resolve(Dispatcher::class)
            ->dispatch(new DeployVirtualMachineJob(
                $this->vmDeployment,
                $this->cloudstackJob,
            ));

        $this->vmDeployment->refresh();
        self::assertSame(TechnicalStatus::FAILED->value, $this->vmDeployment->subscription->technical_status);
        self::assertSame(
            TechnicalStatus::FAILED->value,
            $this->vmDeployment->subscription->children()->firstOrFail()->technical_status,
        );
        self::assertNotNull($this->vmDeployment->last_result_received);
        self::assertStringContainsString(
            'No destination found for a deployment for VM instance',
            (string) $this->vmDeployment->last_result,
        );
    }

    #[Test]
    public function virtualMachineJobSuccess(): void
    {
        self::assertEmailsSend([
            MailCloudstackManagerVpsDetails::class,
        ]);

        $cloudstackJobFinishedResponse = (string) file_get_contents(__DIR__
        . '/../../data/deployment/finished_job.json');
        /** @var array<string, array<int|string, mixed>|int|string> $cloudStackFinishedJob */
        $cloudStackFinishedJob = json_decode($cloudstackJobFinishedResponse, true, 512, JSON_THROW_ON_ERROR);

        /** @var AsynchronousCloudstackResponse $vmJob */
        $vmJob = CloudstackSerializerFactory::get()->denormalize(
            $cloudStackFinishedJob,
            AsynchronousCloudstackResponse::class,
        );

        $baseClientMock = self::createMock(CloudStackBaseClient::class);
        $clientMock = self::createMock(CloudStackClient::class);
        $clientFactoryMock = self::createMock(ClientFactoryInterface::class);
        $clientFactoryMock->expects(self::once())->method('create')->willReturn($clientMock);
        $this->app->bind(ClientFactory::class, fn () => $clientFactoryMock);

        $baseClientMock
            ->expects(self::once())
            ->method('execute')
            ->with('queryAsyncJobResult', ['jobid' => self::MOCK_JOB_ID])
            ->willReturn($cloudStackFinishedJob);
        $clientMock->expects(self::once())->method('getBaseClient')->willReturn($baseClientMock);

        Queue::after(function (JobProcessed $event) {
            /** @var array<string,string> $body */
            $body = json_decode($event->job->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(DeployVirtualMachineJob::class, $body['displayName']);
            self::assertFalse($event->job->isReleased());
            self::assertFalse($event->job->hasFailed());
        });

        self::assertNull($this->vmDeployment->cloudstack_id);
        self::assertNull($this->vmDeployment->subscription->technical_status);

        self::resolve(Dispatcher::class)
            ->dispatch(new DeployVirtualMachineJob(
                $this->vmDeployment,
                $this->cloudstackJob,
            ));

        $this->vmDeployment->refresh();

        self::assertArrayHasKey('id', $vmJob->retrieveVirtualMachineData());
        self::assertSame($vmJob->retrieveVirtualMachineData()['id'], $this->vmDeployment->cloudstack_id);
        self::assertSame(TechnicalStatus::OK->value, $this->vmDeployment->subscription->technical_status);
        self::assertSame(
            TechnicalStatus::OK->value,
            $this->vmDeployment->subscription->children()->firstOrFail()->technical_status,
        );
    }
}
