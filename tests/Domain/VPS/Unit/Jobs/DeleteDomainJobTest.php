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
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\VPS\Enums\JobStatus;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Jobs\DeleteDomainJob;
use Waterfront\Domain\VPS\Models\CloudstackJob;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Services\ClientFactory;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(DeleteDomainJob::class)]
class DeleteDomainJobTest extends IntegrationTestCase
{
    private const string MOCK_JOB_ID = '741c702b-8857-4e3c-8b88-0157e1321cad';
    private const string MOCK_DOMAIN_ID = '741c702b-8857-4e3c-8b88-0157e1321cad';

    private ManagerDomainDeployment $managerDomainDeployment;

    private CloudstackJob $cloudstackJob;

    protected function setUp(): void
    {
        parent::setUp();

        $vmProduct = new ProductFactory()->vps()->createOne();

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($vmProduct)
            ->administrativeStatusActive()
            ->createOne();

        $environment = new CloudstackEnvironmentFactory()->createOne();

        $this->managerDomainDeployment = new CloudstackManagerDomainDeploymentFactory()
            ->for($subscription->customer)
            ->for($environment)
            ->createOne();

        $this->cloudstackJob = new CloudstackJobFactory()->createOne([
            'job_id' => self::MOCK_JOB_ID,
        ]);
    }

    #[Test]
    public function cloudstackQueueDispatch(): void
    {
        Queue::fake();

        Queue::assertNothingPushed();

        self::resolve(Dispatcher::class)->dispatch(new DeleteDomainJob(
            new ManagerDomainDeployment(),
            new CloudstackJob(),
        ));

        Queue::assertPushedOn(QueueName::CLOUDSTACK->value, DeleteDomainJob::class);
    }

    #[Test]
    public function jobShouldBeAsyncOnQueue(): void
    {
        Bus::fake();

        self::resolve(Dispatcher::class)->dispatch(new DeleteDomainJob(
            new ManagerDomainDeployment(),
            new CloudstackJob(),
        ));

        Bus::assertNotDispatchedSync(DeleteDomainJob::class);
    }

    #[Test]
    public function deleteDomainJobPendingIsReleased(): void
    {
        $baseClientMock = self::createMock(CloudStackBaseClient::class);
        $clientMock = self::createMock(CloudStackClient::class);

        $clientFactoryMock = self::createMock(ClientFactoryInterface::class);
        $clientFactoryMock->expects(self::once())->method('create')->willReturn($clientMock);
        $this->app->bind(ClientFactory::class, fn () => $clientFactoryMock);

        $baseClientMock->expects(self::once())->method('execute')
            ->with('queryAsyncJobResult', ['jobid' => self::MOCK_JOB_ID])
            ->willReturn(['domainid' => self::MOCK_DOMAIN_ID, 'jobid' => self::MOCK_JOB_ID, 'jobstatus' => JobStatus::PENDING->value]);
        $clientMock->expects(self::once())->method('getBaseClient')->willReturn($baseClientMock);

        Queue::after(function (JobProcessed $event) {
            /** @var array<string,string> $body */
            $body = json_decode($event->job->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(DeleteDomainJob::class, $body['displayName']);
            self::assertTrue($event->job->isReleased());
            self::assertFalse($event->job->hasFailed());
        });

        self::resolve(Dispatcher::class)->dispatch(new DeleteDomainJob(
            $this->managerDomainDeployment,
            $this->cloudstackJob,
        ));
    }

    #[Test]
    public function deleteDomainJobFailsAndLogged(): void
    {
        $baseClientMock = self::createMock(CloudStackBaseClient::class);
        $clientMock = self::createMock(CloudStackClient::class);

        $clientFactoryMock = self::createMock(ClientFactoryInterface::class);
        $clientFactoryMock->expects(self::once())->method('create')->willReturn($clientMock);
        $this->app->bind(ClientFactory::class, fn () => $clientFactoryMock);

        $baseClientMock->expects(self::once())->method('execute')
            ->with('queryAsyncJobResult', ['jobid' => self::MOCK_JOB_ID])
            ->willReturn(['domainid' => self::MOCK_DOMAIN_ID, 'jobid' => self::MOCK_JOB_ID, 'jobstatus' => JobStatus::FAILED->value]);
        $clientMock->expects(self::once())->method('getBaseClient')->willReturn($baseClientMock);

        Queue::after(function (JobProcessed $event) {
            /** @var array<string,string> $body */
            $body = json_decode($event->job->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(DeleteDomainJob::class, $body['displayName']);
            self::assertTrue($event->job->hasFailed());
            self::assertFalse($event->job->isReleased());
        });

        self::resolve(Dispatcher::class)->dispatch(new DeleteDomainJob(
            $this->managerDomainDeployment,
            $this->cloudstackJob,
        ));

        $this->managerDomainDeployment->refresh();

        self::assertNull($this->managerDomainDeployment->deleted_at);
    }

    #[Test]
    public function deleteDomainJobSuccess(): void
    {
        $baseClientMock = self::createMock(CloudStackBaseClient::class);
        $clientMock = self::createMock(CloudStackClient::class);

        $clientFactoryMock = self::createMock(ClientFactoryInterface::class);
        $clientFactoryMock->expects(self::once())->method('create')->willReturn($clientMock);
        $this->app->bind(ClientFactory::class, fn () => $clientFactoryMock);

        $baseClientMock->expects(self::once())->method('execute')
            ->with('queryAsyncJobResult', ['jobid' => self::MOCK_JOB_ID])
            ->willReturn(['domainid' => self::MOCK_DOMAIN_ID, 'jobid' => self::MOCK_JOB_ID, 'jobstatus' => JobStatus::SUCCESS->value]);
        $clientMock->expects(self::once())->method('getBaseClient')->willReturn($baseClientMock);

        Queue::after(function (JobProcessed $event) {
            /** @var array<string,string> $body */
            $body = json_decode($event->job->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(DeleteDomainJob::class, $body['displayName']);
            self::assertFalse($event->job->isReleased());
            self::assertFalse($event->job->hasFailed());
        });

        self::assertNull($this->managerDomainDeployment->deleted_at);

        self::resolve(Dispatcher::class)->dispatch(new DeleteDomainJob(
            $this->managerDomainDeployment,
            $this->cloudstackJob,
        ));

        $this->managerDomainDeployment->refresh();

        self::assertNotNull($this->managerDomainDeployment->deleted_at);
    }
}
