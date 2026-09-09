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
use Waterfront\Domain\VPS\Jobs\ResetPasswordJob;
use Waterfront\Domain\VPS\Models\CloudstackJob;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Services\ClientFactory;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(ResetPasswordJob::class)]
class ResetPasswordJobTest extends IntegrationTestCase
{
    private const string MOCK_JOB_ID = '741c702b-8857-4e3c-8b88-0157e1321cad';
    private const string MOCK_CLOUDSTACK_VM_ID = '44df3fc3-ad2d-4b28-a81a-0f5758124105';

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

        $this->cloudstackJob = new CloudstackJobFactory()->createOne([
            'job_id' => self::MOCK_JOB_ID,
        ]);
    }

    #[Test]
    public function cloudstackQueueDispatch(): void
    {
        Queue::fake();

        Queue::assertNothingPushed();

        self::resolve(Dispatcher::class)->dispatch(new ResetPasswordJob(
            (new VirtualMachineDeployment()),
            (new CloudstackJob())
        ));

        Queue::assertPushedOn(QueueName::CLOUDSTACK->value, ResetPasswordJob::class);
    }

    #[Test]
    public function jobShouldBeAsyncOnQueue(): void
    {
        Bus::fake();

        self::resolve(Dispatcher::class)->dispatch(new ResetPasswordJob(
            (new VirtualMachineDeployment()),
            (new CloudstackJob())
        ));

        Bus::assertNotDispatchedSync(ResetPasswordJob::class);
    }

    #[Test]
    public function resetPasswordVirtualMachinePendingIsReleased(): void
    {
        $cloudstackJobPendingResponse = (string) file_get_contents(__DIR__ . '/../../data/reset_password/pending_job.json');
        /** @var array<string, array<int|string, mixed>|int|string> $cloudStackPendingJob */
        $cloudStackPendingJob = json_decode($cloudstackJobPendingResponse, true, 512, JSON_THROW_ON_ERROR);

        $baseClientMock = self::createMock(CloudStackBaseClient::class);
        $clientMock = self::createMock(CloudStackClient::class);

        $clientFactoryMock = self::createMock(ClientFactoryInterface::class);
        $clientFactoryMock->expects(self::once())->method('create')->willReturn($clientMock);

        $this->app->bind(ClientFactory::class, fn () => $clientFactoryMock);

        $baseClientMock->expects(self::once())->method('execute')
            ->with('queryAsyncJobResult', ['jobid' => self::MOCK_JOB_ID])
            ->willReturn($cloudStackPendingJob);
        $clientMock->expects(self::once())->method('getBaseClient')->willReturn($baseClientMock);

        Queue::after(function (JobProcessed $event) {
            /** @var array<string,string> $body */
            $body = json_decode($event->job->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(ResetPasswordJob::class, $body['displayName']);
            self::assertTrue($event->job->isReleased());
            self::assertFalse($event->job->hasFailed());
        });

        self::assertNull($this->vmDeployment->subscription->technical_status);

        self::resolve(Dispatcher::class)->dispatch(new ResetPasswordJob(
            $this->vmDeployment,
            $this->cloudstackJob
        ));

        $this->vmDeployment->refresh();

        self::assertSame(TechnicalStatus::PENDING->value, $this->vmDeployment->subscription->technical_status);
        self::assertSame(VpsActionStatus::RESETTING_CREDENTIALS, $this->vmDeployment->last_action_status);
    }

    #[Test]
    public function resetPasswordVirtualMachineFailsAndLogs(): void
    {
        $cloudstackJobFailedResponse = (string) file_get_contents(__DIR__ . '/../../data/reset_password/failed_job.json');
        /** @var array<string, array<int|string, mixed>|int|string> $cloudStackFailedJob */
        $cloudStackFailedJob = json_decode($cloudstackJobFailedResponse, true, 512, JSON_THROW_ON_ERROR);

        $baseClientMock = self::createMock(CloudStackBaseClient::class);
        $clientMock = self::createMock(CloudStackClient::class);
        $clientFactoryMock = self::createMock(ClientFactoryInterface::class);
        $clientFactoryMock->expects(self::once())->method('create')->willReturn($clientMock);
        $this->app->bind(ClientFactory::class, fn () => $clientFactoryMock);

        $baseClientMock->expects(self::once())->method('execute')
            ->with('queryAsyncJobResult', ['jobid' => self::MOCK_JOB_ID])
            ->willReturn($cloudStackFailedJob);
        $clientMock->expects(self::once())->method('getBaseClient')->willReturn($baseClientMock);

        Queue::after(function (JobProcessed $event) {
            /** @var array<string,string> $body */
            $body = json_decode($event->job->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(ResetPasswordJob::class, $body['displayName']);
            self::assertTrue($event->job->hasFailed());
            self::assertFalse($event->job->isReleased());
        });

        self::assertNull($this->vmDeployment->subscription->technical_status);

        self::resolve(Dispatcher::class)->dispatch(new ResetPasswordJob(
            $this->vmDeployment,
            $this->cloudstackJob
        ));

        $this->vmDeployment->refresh();

        self::assertNotNull($this->vmDeployment->last_result_received);
        self::assertStringContainsString('Unable to reset password for VM instance', (string) $this->vmDeployment->last_result);
        self::assertSame(TechnicalStatus::OK->value, $this->vmDeployment->subscription->technical_status);
        self::assertSame(VpsActionStatus::RESET_CREDENTIALS_FAILED, $this->vmDeployment->last_action_status);
    }

    #[Test]
    public function resetPasswordJobSuccess(): void
    {
        $subscription = $this->vmDeployment->subscription;
        $cloudstackJobFinishedResponse = (string) file_get_contents(__DIR__ . '/../../data/reset_password/finished_job.json');
        /** @var array<string, array<int|string, mixed>|int|string> $cloudStackFinishedJob */
        $cloudStackFinishedJob = json_decode($cloudstackJobFinishedResponse, true, 512, JSON_THROW_ON_ERROR);

        $baseClientMock = self::createMock(CloudStackBaseClient::class);
        $clientMock = self::createMock(CloudStackClient::class);
        $clientFactoryMock = self::createMock(ClientFactoryInterface::class);
        $clientFactoryMock->expects(self::once())->method('create')->willReturn($clientMock);
        $this->app->bind(ClientFactory::class, fn () => $clientFactoryMock);

        $baseClientMock->expects(self::once())->method('execute')
            ->with('queryAsyncJobResult', ['jobid' => self::MOCK_JOB_ID])
            ->willReturn($cloudStackFinishedJob);
        $clientMock->expects(self::once())->method('getBaseClient')->willReturn($baseClientMock);

        Queue::after(function (JobProcessed $event) {
            /** @var array<string,string> $body */
            $body = json_decode($event->job->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(ResetPasswordJob::class, $body['displayName']);
            self::assertFalse($event->job->isReleased());
            self::assertFalse($event->job->hasFailed());
        });

        self::assertNull($subscription->technical_status);

        self::resolve(Dispatcher::class)->dispatch(new ResetPasswordJob(
            $this->vmDeployment,
            $this->cloudstackJob
        ));

        $subscription->refresh();
        $this->vmDeployment->refresh();

        self::assertSame(TechnicalStatus::OK->value, $subscription->technical_status);
        self::assertSame(VpsActionStatus::RESET_CREDENTIALS_SUCCESS, $this->vmDeployment->last_action_status);
    }
}
