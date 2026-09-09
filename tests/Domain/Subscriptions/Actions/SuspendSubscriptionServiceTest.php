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
use Waterfront\Domain\Domains\Jobs\SuspendDomainJob;
use Waterfront\Domain\Hosting\Jobs\SuspendHostingJob;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Exceptions\UnableToSuspendSubscriptionException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SuspendSubscriptionService;

#[CoversClass(SuspendSubscriptionService::class)]
class SuspendSubscriptionServiceTest extends IntegrationTestCase
{
    private Subscription $domainSubscription;

    private Subscription $hostingSubscription;

    private Customer $customer;

    private MockObject&StoreAuditLogAction $storeAuditLogAction;

    private SuspendSubscriptionService $suspendSubscriptionsAction;

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
            ->technicalStatusOk()
            ->createOne();
        $this->hostingSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($hostingProduct)
            ->technicalStatusOk()
            ->createOne();

        $this->storeAuditLogAction = $this->createMock(StoreAuditLogAction::class);
        $dispatcher = self::resolve(Dispatcher::class);

        $this->suspendSubscriptionsAction = new SuspendSubscriptionService(
            $this->storeAuditLogAction,
            $dispatcher,
            self::createStub(LoggerInterface::class),
        );
    }

    #[Test]
    public function executeExtensionWillBeSuspended(): void
    {
        new DomainDeploymentFactory()->withRtrProvider()->createOne([
            'subscription_uuid' => $this->domainSubscription->uuid,
        ]);
        Queue::fake(SuspendDomainJob::class);

        $this->storeAuditLogAction->expects(self::once())
            ->method('execute')
            ->with(
                AuditLogEvent::SUSPENSION,
                Subscription::class,
                $this->domainSubscription->id,
                [],
                []
            );

        $this->suspendSubscriptionsAction->execute($this->domainSubscription);

        self::assertSame(AdministrativeStatus::SUSPENDED->value, $this->domainSubscription->administrative_status);

        Queue::assertNotPushed(SuspendHostingJob::class);
        Queue::assertPushed(SuspendDomainJob::class);
    }

    #[Test]
    public function executeHostingWillBeSuspended(): void
    {
        $provider = new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => true]);
        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $this->hostingSubscription->uuid,
            'provider_id' => $provider->id,
        ]);
        Queue::fake(SuspendHostingJob::class);

        $this->hostingSubscription->refresh();

        $this->storeAuditLogAction->expects(self::once())
            ->method('execute')
            ->with(
                AuditLogEvent::SUSPENSION,
                Subscription::class,
                $this->hostingSubscription->id,
                [],
                []
            );

        $this->suspendSubscriptionsAction->execute($this->hostingSubscription);

        self::assertSame(AdministrativeStatus::SUSPENDED->value, $this->hostingSubscription->administrative_status);

        Queue::assertPushed(SuspendHostingJob::class);
        Queue::assertNotPushed(SuspendDomainJob::class);
    }

    #[Test]
    public function subscriptionStatusNotSufficient(): void
    {
        Queue::fake([SuspendDomainJob::class, SuspendHostingJob::class]);

        $this->domainSubscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
        $this->domainSubscription->save();

        $this->domainSubscription->refresh();

        self::assertSame(AdministrativeStatus::ARCHIVED->value, $this->domainSubscription->administrative_status);

        $this->expectException(UnableToSuspendSubscriptionException::class);

        $this->storeAuditLogAction->expects(self::never())
            ->method('execute');

        try {
            $this->suspendSubscriptionsAction->execute($this->domainSubscription);
        } finally {
            Queue::assertNotPushed(SuspendHostingJob::class);
            Queue::assertNotPushed(SuspendDomainJob::class);
        }
    }

    #[Test]
    public function technicalSuspendNotSupported(): void
    {
        Queue::fake([SuspendDomainJob::class, SuspendHostingJob::class]);

        $productGroup = new ProductGroupFactory()->ssl()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $faultySubscription = new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->createOne([
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => TechnicalStatus::OK->value,
            ]);

        $this->storeAuditLogAction->expects(self::once())
            ->method('execute');

        $this->suspendSubscriptionsAction->execute($faultySubscription);

        Queue::assertNotPushed(SuspendDomainJob::class);
        Queue::assertNotPushed(SuspendHostingJob::class);

        self::assertSame(AdministrativeStatus::SUSPENDED->value, $faultySubscription->administrative_status);
        self::assertSame(TechnicalStatus::OK->value, $faultySubscription->technical_status);
    }
}
