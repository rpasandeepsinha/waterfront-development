<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Unit;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Events\TerminateMicrosoft365;
use Waterfront\Domain\Microsoft365\Listeners\Microsoft365TerminationListener;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(Microsoft365TerminationListener::class)]
class Microsoft365TerminationListenerTest extends IntegrationTestCase
{
    private Customer $customer;

    private Product $product;

    private Subscription $subscription;

    private Microsoft365Deployment $microsoft365Deployment;

    private MailerInterface $mailer;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([TerminateMicrosoft365::class]);

        $this->customer = new CustomerFactory()->createOne();

        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $this->product = new ProductFactory()->for($productGroup)->createOne();

        $this->subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $this->product->uuid,
        ]);

        $customerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'technical_status' => Microsoft365ProcessStatus::ACTIVE,
        ]);

        $this->microsoft365Deployment = new Microsoft365DeploymentFactory()->for($customerInfo)->createOne([
            'subscription_id' => $this->subscription->id,
            'kpn_order_id' => '123',
            'kpn_start_date' => CarbonImmutable::now()->subDays(4),
        ]);

        $this->mailer = self::resolve(MailerInterface::class);
    }

    #[Test]
    public function microsoft365TerminationListenerTerminateOrder(): void
    {
        new SubscriptionFactory()->count(3)->for($this->customer)->for($this->product)->parentSubscription($this->subscription)->createOne([
            'administrative_status' => AdministrativeStatus::ARCHIVED->value,
        ]);

        $microsoftSubscriptionService = self::createMock(Microsoft365Service::class);
        $microsoftSubscriptionService->expects(self::once())->method('terminateOrder')
            ->with(self::callback(fn (Microsoft365Deployment $microsoft365Deployment) => $microsoft365Deployment->id === $this->microsoft365Deployment->id));

        $event = new TerminateMicrosoft365($this->subscription);

        $listener = new Microsoft365TerminationListener(
            $microsoftSubscriptionService,
            $this->mailer,
            self::createStub(LoggerInterface::class),
        );

        $listener->handle($event);

        $this->subscription->refresh();

        self::assertSame(AdministrativeStatus::ARCHIVING->value, $this->subscription->administrative_status);
    }

    #[Test]
    public function microsoft365TerminationListenerModifyOrder(): void
    {
        new SubscriptionFactory()->for($this->customer)->for($this->product)->parentSubscription($this->subscription)->state([
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ])->createMany(2);

        new SubscriptionFactory()->for($this->customer)->for($this->product)->parentSubscription($this->subscription)->state([
            'administrative_status' => AdministrativeStatus::EXPIRED->value,
            'end_date' => new CarbonImmutable(),
            'termination_date' => new CarbonImmutable(),
        ])->createMany(2);

        $microsoftSubscriptionService = self::createMock(Microsoft365Service::class);
        $microsoftSubscriptionService->expects(self::once())->method('modifyOrder')
            ->with($this->microsoft365Deployment->kpn_order_id, -2);

        $event = new TerminateMicrosoft365($this->subscription);

        $listener = new Microsoft365TerminationListener(
            $microsoftSubscriptionService,
            $this->mailer,
            self::createStub(LoggerInterface::class),
        );

        $listener->handle($event);

        $this->subscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);

        $active_children_count = Subscription::where('administrative_status', AdministrativeStatus::ACTIVE->value)->where('parent_subscription_id', $this->subscription->id)->count();
        $canceled_children_count = Subscription::where('administrative_status', AdministrativeStatus::CANCELED->value)->where('parent_subscription_id', $this->subscription->id)->count();
        $archiving_children_count = Subscription::where('administrative_status', AdministrativeStatus::ARCHIVING->value)->where('parent_subscription_id', $this->subscription->id)->count();

        self::assertSame(2, $active_children_count);
        self::assertSame(0, $canceled_children_count);
        self::assertSame(2, $archiving_children_count);
    }

    #[Test]
    public function microsoft365TerminationListenerModifyOrderWithUnevenAmount(): void
    {
        new SubscriptionFactory()->for($this->customer)->for($this->product)->parentSubscription($this->subscription)->state([
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ])->createMany(2);

        new SubscriptionFactory()->for($this->customer)->for($this->product)->parentSubscription($this->subscription)->state([
            'administrative_status' => AdministrativeStatus::EXPIRED->value,
            'end_date' => new CarbonImmutable(),
            'termination_date' => new CarbonImmutable(),
        ])->createMany(3);

        $microsoftSubscriptionService = self::createMock(Microsoft365Service::class);
        $microsoftSubscriptionService->expects(self::once())->method('modifyOrder')
            ->with($this->microsoft365Deployment->kpn_order_id, -3);

        $event = new TerminateMicrosoft365($this->subscription);

        $listener = new Microsoft365TerminationListener(
            $microsoftSubscriptionService,
            $this->mailer,
            self::createStub(LoggerInterface::class),
        );

        $listener->handle($event);

        $this->subscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);

        $active_children_count = Subscription::where('administrative_status', AdministrativeStatus::ACTIVE->value)->where('parent_subscription_id', $this->subscription->id)->count();
        $canceled_children_count = Subscription::where('administrative_status', AdministrativeStatus::CANCELED->value)->where('parent_subscription_id', $this->subscription->id)->count();
        $archiving_children_count = Subscription::where('administrative_status', AdministrativeStatus::ARCHIVING->value)->where('parent_subscription_id', $this->subscription->id)->count();

        self::assertSame(2, $active_children_count);
        self::assertSame(0, $canceled_children_count);
        self::assertSame(3, $archiving_children_count);
    }

    #[Test]
    public function microsoft365TerminationListenerOnlyActiveSeats(): void
    {
        new SubscriptionFactory()->count(2)->for($this->customer)->for($this->product)->parentSubscription($this->subscription)->createOne([
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ]);

        $microsoftSubscriptionService = self::createMock(Microsoft365Service::class);
        $microsoftSubscriptionService->expects(self::never())->method('terminateOrder');
        $microsoftSubscriptionService->expects(self::never())->method('modifyOrder');

        $event = new TerminateMicrosoft365($this->subscription);

        $listener = new Microsoft365TerminationListener(
            $microsoftSubscriptionService,
            $this->mailer,
            self::createStub(LoggerInterface::class),
        );

        $listener->handle($event);

        $this->subscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
    }

    #[Test]
    public function microsoft365TerminationListenerModifyOrderOlderThenSeven(): void
    {
        $this->microsoft365Deployment->kpn_start_date = CarbonImmutable::now()->subDays(2)->subYear();
        $this->microsoft365Deployment->save();

        new SubscriptionFactory()->for($this->customer)->for($this->product)->parentSubscription($this->subscription)->state([
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ])->createMany(2);

        new SubscriptionFactory()->for($this->customer)->for($this->product)->parentSubscription($this->subscription)->state([
            'administrative_status' => AdministrativeStatus::CANCELED->value,
            'end_date' => new CarbonImmutable(),
        ])->createMany(3);

        $microsoftSubscriptionService = self::createMock(Microsoft365Service::class);
        $microsoftSubscriptionService->expects(self::never())->method('modifyOrder');

        $event = new TerminateMicrosoft365($this->subscription);

        $listener = new Microsoft365TerminationListener(
            $microsoftSubscriptionService,
            $this->mailer,
            self::createStub(LoggerInterface::class),
        );

        $listener->handle($event);

        $this->subscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);

        $active_children_count = Subscription::where('administrative_status', AdministrativeStatus::ACTIVE->value)->where('parent_subscription_id', $this->subscription->id)->count();
        $canceled_children_count = Subscription::where('administrative_status', AdministrativeStatus::CANCELED->value)->where('parent_subscription_id', $this->subscription->id)->count();
        $archiving_children_count = Subscription::where('administrative_status', AdministrativeStatus::ARCHIVING->value)->where('parent_subscription_id', $this->subscription->id)->count();

        self::assertSame(2, $active_children_count);
        self::assertSame(3, $canceled_children_count);
        self::assertSame(0, $archiving_children_count);
    }
}
