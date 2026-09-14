<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\AuditLogs\Actions\StoreAuditLogAction;
use Waterfront\Domain\AuditLogs\Enums\AuditLogEvent;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Jobs\UnsuspendDomainJob;
use Waterfront\Domain\Hosting\Jobs\UnsuspendHostingJob;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Exceptions\UnableToSuspendSubscriptionException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\UnsuspendSubscriptionService;

#[CoversClass(UnsuspendSubscriptionService::class)]
class UnsuspendSubscriptionServiceTest extends IntegrationTestCase
{
    private Subscription $domainSubscription;

    private Subscription $hostingSubscription;

    private Customer $customer;

    private MockObject&StoreAuditLogAction $storeAuditLogAction;

    private UnsuspendSubscriptionService $unsuspendSubscriptionsAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $extensionProductGroup = new ProductGroupFactory()->extension()->createOne();
        $extensionProduct = new ProductFactory()->for($extensionProductGroup)->createOne();
        $hostingProductGroup = new ProductGroupFactory()->hosting()->createOne();
        $hostingProduct = new ProductFactory()->for($hostingProductGroup)->createOne();

        $this->domainSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($extensionProduct)
            ->createOne([
                'technical_status' => TechnicalStatus::SUSPENDED->value,
                'administrative_status' => AdministrativeStatus::SUSPENDED->value,
            ]);
        $this->hostingSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($hostingProduct)
            ->createOne([
                'technical_status' => TechnicalStatus::SUSPENDED->value,
                'administrative_status' => AdministrativeStatus::SUSPENDED->value,
            ]);

        $this->storeAuditLogAction = $this->createMock(StoreAuditLogAction::class);
        $dispatcher = self::resolve(Dispatcher::class);

        $this->unsuspendSubscriptionsAction = new UnsuspendSubscriptionService(
            $this->storeAuditLogAction,
            $dispatcher,
            self::createStub(LoggerInterface::class),
        );
    }

    #[Test]
    public function executeExtensionWillBeUnsuspend(): void
    {
        new DomainDeploymentFactory()
            ->withRtrProvider()
            ->createOne([
                'subscription_uuid' => $this->domainSubscription->uuid,
            ]);
        Queue::fake(UnsuspendDomainJob::class);

        $this->storeAuditLogAction
            ->expects(self::once())
            ->method('execute')
            ->with(
                AuditLogEvent::UNSUSPENSION,
                Subscription::class,
                $this->domainSubscription->id,
                [],
                [],
            );
        $this->unsuspendSubscriptionsAction->execute($this->domainSubscription);

        Queue::assertPushed(UnsuspendDomainJob::class);
    }

    #[Test]
    public function executeHostingWillBeUnsuspend(): void
    {
        $provider = new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);
        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $this->hostingSubscription->uuid,
            'provider_id' => $provider->id,
        ]);
        Queue::fake(UnsuspendHostingJob::class);

        $this->hostingSubscription->refresh();

        $this->storeAuditLogAction
            ->expects(self::once())
            ->method('execute')
            ->with(
                AuditLogEvent::UNSUSPENSION,
                Subscription::class,
                $this->hostingSubscription->id,
                [],
                [],
            );

        $this->unsuspendSubscriptionsAction->execute($this->hostingSubscription);

        Queue::assertPushed(UnsuspendHostingJob::class);
        Queue::assertNotPushed(UnsuspendDomainJob::class);
    }

    #[Test]
    public function subscriptionIsNotEligibleForSuspension(): void
    {
        Queue::fake([UnsuspendDomainJob::class, UnsuspendHostingJob::class]);

        $this->domainSubscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
        $this->domainSubscription->save();

        $this->domainSubscription->refresh();

        $this->expectException(UnableToSuspendSubscriptionException::class);

        $this->storeAuditLogAction->expects(self::never())->method('execute');

        try {
            $this->unsuspendSubscriptionsAction->execute($this->domainSubscription);
        } finally {
            Queue::assertNotPushed(UnsuspendHostingJob::class);
            Queue::assertNotPushed(UnsuspendDomainJob::class);
            self::assertSame(AdministrativeStatus::ARCHIVED->value, $this->domainSubscription->administrative_status);
        }
    }

    #[Test]
    public function technicalUnsuspendNotSupported(): void
    {
        Queue::fake([UnsuspendDomainJob::class, UnsuspendHostingJob::class]);

        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $faultySubscription = new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->createOne([
                'technical_status' => TechnicalStatus::SUSPENDED->value,
                'administrative_status' => AdministrativeStatus::SUSPENDED->value,
            ]);

        $this->storeAuditLogAction
            ->expects(self::once())
            ->method('execute')
            ->with(
                AuditLogEvent::UNSUSPENSION,
                Subscription::class,
                $faultySubscription->id,
                [],
                [],
            );
        $this->unsuspendSubscriptionsAction->execute($faultySubscription);

        Queue::assertNotPushed(UnsuspendDomainJob::class);
        Queue::assertNotPushed(UnsuspendHostingJob::class);

        self::assertSame(AdministrativeStatus::ACTIVE->value, $faultySubscription->administrative_status);
        self::assertNull($faultySubscription->suspended_at);
    }
}
