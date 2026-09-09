<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Jobs;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\AuditLogs\Actions\StoreAuditLogAction;
use Waterfront\Domain\AuditLogs\Enums\AuditLogEvent;
use Waterfront\Domain\Email\Actions\SendSubscriptionUnSuspendedMailAction;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Interfaces\Drivers\HostingServiceInterface;
use Waterfront\Domain\Hosting\Jobs\UnsuspendHostingJob;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Exceptions\NotImplementedException;

#[CoversClass(UnsuspendHostingJob::class)]
class UnsuspendHostingJobTest extends IntegrationTestCase
{
    private Subscription $subscription;

    private HostingDeployment $hostingDeployment;

    private HostingServiceInterface&MockObject $hostingService;

    private HostingServiceFactory&MockObject $hostingServiceFactory;

    private SendSubscriptionUnsuspendedMailAction&MockObject $unsuspendMailAction;

    private StoreAuditLogAction&MockObject $storeNameserversAction;

    public function setUp(): void
    {
        parent::setUp();

        $this->hostingService = $this->createMock(HostingServiceInterface::class);
        $this->hostingServiceFactory = $this->createMock(HostingServiceFactory::class);
        $this->hostingServiceFactory->expects(self::once())
            ->method('defaultDriver')
            ->willReturn($this->hostingService);
        $this->unsuspendMailAction = $this->createMock(SendSubscriptionUnSuspendedMailAction::class);
        $this->storeNameserversAction = $this->createMock(StoreAuditLogAction::class);

        $customer = new CustomerFactory()->createOne();

        $this->subscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne()))
            ->createOne([
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => TechnicalStatus::UNSUSPENDING->value,
            ]);

        $hostingProvider = new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => true]);
        $this->hostingDeployment = new HostingDeploymentFactory()
            ->for($this->subscription, 'subscription')
            ->createOne([
                'provider_id' => $hostingProvider->id,
            ]);
    }

    #[Test]
    public function suspendHostingSuccessfully(): void
    {
        $this->hostingService->expects(self::once())
            ->method('unsuspend')
            ->with($this->hostingDeployment);

        $this->storeNameserversAction
            ->expects(self::once())
            ->method('execute')
            ->with(
                AuditLogEvent::UNSUSPENSION,
                HostingDeployment::class,
                $this->hostingDeployment->id,
            );

        $this->unsuspendMailAction
            ->expects(self::once())
            ->method('execute')
            ->with($this->hostingDeployment->subscription);

        $unsuspendJob = new UnsuspendHostingJob($this->hostingDeployment, sendEmailOnSuccess: true);
        $unsuspendJob->handle(
            $this->hostingServiceFactory,
            $this->unsuspendMailAction,
            $this->storeNameserversAction,
            self::createStub(LoggerInterface::class),
        );

        $this->subscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
    }

    #[Test]
    public function suspendHostingAndMailNotSent(): void
    {
        $this->hostingService->expects(self::once())
            ->method('unsuspend')
            ->with($this->hostingDeployment);

        $this->storeNameserversAction
            ->expects(self::once())
            ->method('execute')
            ->with(
                AuditLogEvent::UNSUSPENSION,
                HostingDeployment::class,
                $this->hostingDeployment->id,
            );

        $this->unsuspendMailAction
            ->expects(self::never())
            ->method('execute');

        $unsuspendJob = new UnsuspendHostingJob($this->hostingDeployment, sendEmailOnSuccess: false);
        $unsuspendJob->handle(
            $this->hostingServiceFactory,
            $this->unsuspendMailAction,
            $this->storeNameserversAction,
            self::createStub(LoggerInterface::class),
        );

        $this->subscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
    }

    #[Test]
    public function notImplementedException(): void
    {
        $this->hostingService->expects(self::once())
            ->method('unsuspend')
            ->with($this->hostingDeployment)
            ->willThrowException(new NotImplementedException());

        $this->storeNameserversAction
            ->expects(self::never())
            ->method('execute');

        $this->unsuspendMailAction
            ->expects(self::never())
            ->method('execute');

        $unsuspendJob = new UnsuspendHostingJob($this->hostingDeployment, sendEmailOnSuccess: true);
        $unsuspendJob->handle(
            $this->hostingServiceFactory,
            $this->unsuspendMailAction,
            $this->storeNameserversAction,
            self::createStub(LoggerInterface::class),
        );

        $this->subscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::UNSUSPENSION_FAILED->value, $this->subscription->technical_status);
        self::assertDatabaseHas('subscription_categories', [
            'subscription_id' => $this->subscription->id,
            'name' => SubscriptionCategory::UNSUSPENSION->value,
        ]);
    }

    #[Test]
    public function invalidArgumentException(): void
    {
        $this->hostingService->expects(self::once())
            ->method('unsuspend')
            ->with($this->hostingDeployment)
            ->willThrowException(new InvalidArgumentException());

        $this->storeNameserversAction
            ->expects(self::never())
            ->method('execute');

        $this->unsuspendMailAction
            ->expects(self::never())
            ->method('execute');

        $unsuspendJob = new UnsuspendHostingJob($this->hostingDeployment, sendEmailOnSuccess: true);
        $unsuspendJob->handle(
            $this->hostingServiceFactory,
            $this->unsuspendMailAction,
            $this->storeNameserversAction,
            self::createStub(LoggerInterface::class),
        );

        $this->subscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::UNSUSPENSION_FAILED->value, $this->subscription->technical_status);
    }

    #[Test]
    public function mailOnlySubscriptionUnsuspendSuccess(): void
    {
        $mailProvider = ProviderFactory::new()->createOne(['type' => ProviderType::MAILONLY, 'slug' => ProviderSlug::DIRECTADMIN, 'default' => true, 'enabled' => true]);

        $this->hostingDeployment->update([
            'server_id' => null,
            'provider_id' => null,
            'mail_only_provider_id' => $mailProvider->id,
            'mail_only_server_id' => new ServerFactory()->directadmin()->createOne()->id,
        ]);
        $this->hostingDeployment->refresh();

        $this->hostingService->expects(self::once())
            ->method('unsuspend')
            ->with($this->hostingDeployment);

        $this->storeNameserversAction
            ->expects(self::once())
            ->method('execute')
            ->with(
                AuditLogEvent::UNSUSPENSION,
                HostingDeployment::class,
                $this->hostingDeployment->id,
            );

        $this->unsuspendMailAction
            ->expects(self::once())
            ->method('execute')
            ->with($this->hostingDeployment->subscription);

        $unsuspendJob = new UnsuspendHostingJob($this->hostingDeployment, sendEmailOnSuccess: true);
        $unsuspendJob->handle(
            $this->hostingServiceFactory,
            $this->unsuspendMailAction,
            $this->storeNameserversAction,
            self::createStub(LoggerInterface::class),
        );

        $this->subscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
    }

    #[Test]
    public function mailOnlyNoServerSuppliedInvalidArgumentExceptionThrown(): void
    {
        $mailProvider = ProviderFactory::new()->createOne(['type' => ProviderType::MAILONLY, 'slug' => ProviderSlug::DIRECTADMIN, 'default' => true, 'enabled' => true]);

        $this->hostingDeployment->update([
            'server_id' => null,
            'provider_id' => null,
            'mail_only_provider_id' => $mailProvider->id,
            'mail_only_server_id' => null,
        ]);
        $this->hostingDeployment->refresh();

        $this->hostingService->expects(self::once())
            ->method('unsuspend')
            ->with($this->hostingDeployment)
            ->willThrowException(new InvalidArgumentException());

        $this->storeNameserversAction
            ->expects(self::never())
            ->method('execute');

        $this->unsuspendMailAction
            ->expects(self::never())
            ->method('execute');

        $unsuspendJob = new UnsuspendHostingJob($this->hostingDeployment, sendEmailOnSuccess: true);
        $unsuspendJob->handle(
            $this->hostingServiceFactory,
            $this->unsuspendMailAction,
            $this->storeNameserversAction,
            self::createStub(LoggerInterface::class),
        );

        $this->subscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::UNSUSPENSION_FAILED->value, $this->subscription->technical_status);
    }
}
