<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Jobs;

use Exception;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\AuditLogs\Actions\StoreAuditLogAction;
use Waterfront\Domain\AuditLogs\Enums\AuditLogEvent;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Domains\Jobs\SuspendDomainJob;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Email\Actions\SendSubscriptionSuspendedMailAction;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Exceptions\NotImplementedException;

#[CoversClass(SuspendDomainJob::class)]
#[AllowMockObjectsWithoutExpectations]
class SuspendDomainJobTest extends IntegrationTestCase
{
    private Subscription $subscription;

    private DomainDeployment $domainDeployment;

    private DomainServiceFactory&MockObject $domainServiceFactory;

    private SendSubscriptionSuspendedMailAction&MockObject $domainSuspendedMailer;

    private StoreAuditLogAction&MockObject $storeNameserversAction;

    private DomainDriverInterface&MockObject $domainDriver;

    public function setUp(): void
    {
        parent::setUp();

        $this->domainServiceFactory = $this->createMock(DomainServiceFactory::class);
        $this->domainSuspendedMailer = $this->createMock(SendSubscriptionSuspendedMailAction::class);
        $this->storeNameserversAction = $this->createMock(StoreAuditLogAction::class);
        $this->domainDriver = $this->createMock(DomainDriverInterface::class);

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne()))
            ->createOne([
                'administrative_status' => AdministrativeStatus::SUSPENDED->value,
                'technical_status' => TechnicalStatus::SUSPENDING->value,
            ]);

        $this->domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($this->subscription, 'subscription')
            ->createOne();
    }

    #[Test]
    public function suspendDomainSuccessfully(): void
    {
        $this->domainDriver
            ->expects(self::once())
            ->method('suspend')
            ->with($this->domainDeployment->subscription->domain);

        $this->domainServiceFactory
            ->expects(self::once())
            ->method('driver')
            ->willReturn($this->domainDriver);

        $this->domainSuspendedMailer
            ->expects(self::once())
            ->method('execute')
            ->with($this->domainDeployment->subscription);

        $this->storeNameserversAction
            ->expects(self::once())
            ->method('execute')
            ->with(
                AuditLogEvent::SUSPENSION,
                DomainDeployment::class,
                $this->domainDeployment->id,
            );

        $suspendJob = new SuspendDomainJob(
            $this->domainDeployment,
            true
        );

        $suspendJob->handle(
            $this->domainServiceFactory,
            $this->domainSuspendedMailer,
            $this->storeNameserversAction,
            self::createMock(LoggerInterface::class),
            $this->createStub(StoreNoteAction::class)
        );

        $this->subscription->refresh();

        self::assertSame(AdministrativeStatus::SUSPENDED->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::SUSPENDED->value, $this->subscription->technical_status);
    }

    #[Test]
    public function domainModificationFailedException(): void
    {
        $this->domainDriver
            ->expects(self::once())
            ->method('suspend')
            ->willThrowException(new DomainModificationFailedException());

        $this->domainServiceFactory
            ->expects(self::once())
            ->method('driver')
            ->willReturn($this->domainDriver);

        $this->domainSuspendedMailer
            ->expects(self::never())
            ->method('execute');

        $suspendJob = new SuspendDomainJob(
            $this->domainDeployment,
            true
        );

        $suspendJob->handle(
            $this->domainServiceFactory,
            $this->domainSuspendedMailer,
            $this->storeNameserversAction,
            self::createMock(LoggerInterface::class),
            $this->createStub(StoreNoteAction::class)
        );

        self::assertSame(AdministrativeStatus::SUSPENDED->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::SUSPENDING->value, $this->subscription->technical_status);
    }

    #[Test]
    public function notImplementedException(): void
    {
        $this->domainDriver
            ->expects(self::once())
            ->method('suspend')
            ->willThrowException(new NotImplementedException());

        $this->domainServiceFactory
            ->expects(self::once())
            ->method('driver')
            ->willReturn($this->domainDriver);

        $this->domainSuspendedMailer
            ->expects(self::never())
            ->method('execute');

        $suspendJob = new SuspendDomainJob(
            $this->domainDeployment,
            true
        );

        $suspendJob->handle(
            $this->domainServiceFactory,
            $this->domainSuspendedMailer,
            $this->storeNameserversAction,
            self::createMock(LoggerInterface::class),
            $this->createStub(StoreNoteAction::class)
        );

        self::assertSame(AdministrativeStatus::SUSPENDED->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::SUSPENDING->value, $this->subscription->technical_status);
    }

    #[Test]
    public function runtimeException(): void
    {
        $this->domainServiceFactory
            ->expects(self::once())
            ->method('driver')
            ->willThrowException(new Exception('Test not caught exception'));

        $this->expectException(Exception::class);

        $suspendJob = new SuspendDomainJob(
            $this->domainDeployment,
            true
        );

        $suspendJob->handle(
            $this->domainServiceFactory,
            $this->domainSuspendedMailer,
            $this->storeNameserversAction,
            self::createMock(LoggerInterface::class),
            $this->createStub(StoreNoteAction::class)
        );
    }

    #[test]
    public function suspendDomainFailedTestFailedFunctionSendsAuditLog(): void
    {
        $exception = new DomainModificationFailedException();
        $this->domainDriver
            ->expects(self::once())
            ->method('suspend')
            ->willThrowException($exception);

        $this->domainServiceFactory
            ->expects(self::once())
            ->method('driver')
            ->willReturn($this->domainDriver);

        $this->domainSuspendedMailer
            ->expects(self::never())
            ->method('execute');

        $suspendJob = new SuspendDomainJob(
            $this->domainDeployment,
            true
        );

        $suspendJob->handle(
            $this->domainServiceFactory,
            $this->domainSuspendedMailer,
            $this->storeNameserversAction,
            self::createMock(LoggerInterface::class),
            $this->createStub(StoreNoteAction::class)
        );
        self::assertSame(AdministrativeStatus::SUSPENDED->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::SUSPENDING->value, $this->subscription->technical_status);

        $suspendJob->failed($exception);
        $this->subscription->refresh();

        self::assertDatabaseHas('audits', [
            'event' => AuditLogEvent::SUSPENSION,
            'auditable_id' => $this->domainDeployment->id,
        ]);
        self::assertSame(TechnicalStatus::SUSPENSION_FAILED->value, $this->subscription->technical_status);
        self::assertDatabaseHas('subscription_categories', [
            'subscription_id' => $this->subscription->id,
            'name' => SubscriptionCategory::SUSPENSION->value,
        ]);
    }
}
