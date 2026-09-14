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
use Waterfront\Domain\Domains\Exceptions\DomainDoesNotExistException;
use Waterfront\Domain\Domains\Exceptions\DomainForbiddenException;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Domains\Jobs\UnsuspendDomainJob;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Email\Actions\SendSubscriptionUnSuspendedMailAction;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;

#[CoversClass(UnsuspendDomainJob::class)]
#[AllowMockObjectsWithoutExpectations]
class UnsuspendDomainJobTest extends IntegrationTestCase
{
    private Subscription $subscription;

    private DomainDeployment $domainDeployment;

    private DomainServiceFactory&MockObject $domainServiceFactory;

    private SendSubscriptionUnSuspendedMailAction&MockObject $domainUnSuspendedMailer;

    private StoreAuditLogAction&MockObject $storeNameserversAction;

    private DomainDriverInterface&MockObject $domainDriver;

    public function setUp(): void
    {
        parent::setUp();

        $this->domainServiceFactory = $this->createMock(DomainServiceFactory::class);
        $this->domainUnSuspendedMailer = $this->createMock(SendSubscriptionUnSuspendedMailAction::class);
        $this->storeNameserversAction = $this->createMock(StoreAuditLogAction::class);
        $this->domainDriver = $this->createMock(DomainDriverInterface::class);

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne()))
            ->createOne([
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => TechnicalStatus::SUSPENDED->value,
            ]);

        $this->domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($this->subscription, 'subscription')
            ->createOne();
    }

    #[Test]
    public function unsuspendDomainSuccessfully(): void
    {
        $this->domainServiceFactory->expects(self::once())->method('driver')->willReturn($this->domainDriver);

        $this->domainDriver
            ->expects(self::once())
            ->method('unsuspend')
            ->with($this->domainDeployment->subscription->domain);

        $this->storeNameserversAction
            ->expects(self::once())
            ->method('execute')
            ->with(
                AuditLogEvent::UNSUSPENSION,
                DomainDeployment::class,
                $this->domainDeployment->id,
            );

        $this->domainUnSuspendedMailer
            ->expects(self::once())
            ->method('execute')
            ->with($this->domainDeployment->subscription);

        $unsuspendJob = new UnsuspendDomainJob($this->domainDeployment);
        $unsuspendJob->handle(
            $this->domainServiceFactory,
            $this->domainUnSuspendedMailer,
            $this->storeNameserversAction,
            self::createMock(LoggerInterface::class),
        );

        $this->subscription->refresh();

        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
    }

    #[Test]
    public function domainModificationFailedException(): void
    {
        $this->domainDriver
            ->expects(self::once())
            ->method('unsuspend')
            ->willThrowException(new DomainModificationFailedException());

        $this->domainServiceFactory->expects(self::once())->method('driver')->willReturn($this->domainDriver);

        $this->domainUnSuspendedMailer->expects(self::never())->method('execute');

        $unsuspendJob = new UnsuspendDomainJob($this->domainDeployment);
        $unsuspendJob->handle(
            $this->domainServiceFactory,
            $this->domainUnSuspendedMailer,
            $this->storeNameserversAction,
            self::createMock(LoggerInterface::class),
        );

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::SUSPENDED->value, $this->subscription->technical_status);
    }

    #[Test]
    public function notImplementedException(): void
    {
        $this->domainDriver
            ->expects(self::once())
            ->method('unsuspend')
            ->willThrowException(new NotImplementedException());

        $this->domainServiceFactory->expects(self::once())->method('driver')->willReturn($this->domainDriver);

        $this->domainUnSuspendedMailer->expects(self::never())->method('execute');

        $unsuspendJob = new UnsuspendDomainJob($this->domainDeployment);
        $unsuspendJob->handle(
            $this->domainServiceFactory,
            $this->domainUnSuspendedMailer,
            $this->storeNameserversAction,
            self::createMock(LoggerInterface::class),
        );

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::SUSPENDED->value, $this->subscription->technical_status);
    }

    #[Test]
    public function domainForbiddenExceptionFailsFast(): void
    {
        $this->domainDriver
            ->expects(self::once())
            ->method('unsuspend')
            ->willThrowException(new DomainForbiddenException());

        $this->domainServiceFactory->expects(self::once())->method('driver')->willReturn($this->domainDriver);

        $this->domainUnSuspendedMailer->expects(self::never())->method('execute');

        $loggerMock = self::createMock(LoggerInterface::class);
        $loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed unsuspend for {domain.name}, RTR access forbidden for business unit.',
                self::callback(
                    fn (array $context): bool => (
                        $context[LoggingContextKeys::DOMAIN_NAME] === $this->domainDeployment->subscription->domain
                        && $context[LoggingContextKeys::PROVISIONING_PROVIDER]
                        === $this->domainDeployment->provider->slug
                        && $context[LoggingContextKeys::PROVISIONING_TYPE] === ProvisionType::DOMAIN_NAME
                    ),
                ),
            );

        $unsuspendJob = new UnsuspendDomainJob($this->domainDeployment);
        $unsuspendJob->handle(
            $this->domainServiceFactory,
            $this->domainUnSuspendedMailer,
            $this->storeNameserversAction,
            $loggerMock,
        );

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::SUSPENDED->value, $this->subscription->technical_status);
    }

    #[Test]
    public function domainDoesNotExistExceptionFailsFast(): void
    {
        $this->domainDriver
            ->expects(self::once())
            ->method('unsuspend')
            ->willThrowException(new DomainDoesNotExistException());

        $this->domainServiceFactory->expects(self::once())->method('driver')->willReturn($this->domainDriver);

        $this->domainUnSuspendedMailer->expects(self::never())->method('execute');

        $loggerMock = self::createMock(LoggerInterface::class);
        $loggerMock->expects(self::once())->method('error');

        $unsuspendJob = new UnsuspendDomainJob($this->domainDeployment);
        $unsuspendJob->handle(
            $this->domainServiceFactory,
            $this->domainUnSuspendedMailer,
            $this->storeNameserversAction,
            $loggerMock,
        );

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::SUSPENDED->value, $this->subscription->technical_status);
    }

    #[Test]
    public function runtimeException(): void
    {
        $this->domainServiceFactory
            ->expects(self::once())
            ->method('driver')
            ->willThrowException(new Exception('Test not caught exception'));

        $this->expectException(Exception::class);
        $suspendJob = new UnsuspendDomainJob($this->domainDeployment);
        $suspendJob->handle(
            $this->domainServiceFactory,
            $this->domainUnSuspendedMailer,
            $this->storeNameserversAction,
            self::createMock(LoggerInterface::class),
        );
    }

    #[Test]
    public function failedAddsUnsuspensionSubscriptionCategory(): void
    {
        $exception = new DomainModificationFailedException();

        $unsuspendJob = new UnsuspendDomainJob($this->domainDeployment);
        $unsuspendJob->failed($exception);

        $this->subscription->refresh();

        self::assertSame(TechnicalStatus::UNSUSPENSION_FAILED->value, $this->subscription->technical_status);
        self::assertDatabaseHas('subscription_categories', [
            'subscription_id' => $this->subscription->id,
            'name' => SubscriptionCategory::UNSUSPENSION->value,
        ]);
    }
}
