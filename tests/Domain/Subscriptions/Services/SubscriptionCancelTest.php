<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Mailer\Mailer;
use Waterfront\Domain\Notes\Models\Notes;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCancelled;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\CancellationService;

#[CoversClass(CancellationService::class)]
class SubscriptionCancelTest extends IntegrationTestCase
{
    private Customer $customer;

    private CancellationService $cancellationService;

    private Product $product;

    private CarbonImmutable $cancelDate;

    private CarbonImmutable $startDate;

    private CarbonImmutable $endDate;

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading(false);

        new TemplateFactory()->createOne([
            'slug' => MailSubscriptionCancelled::getTemplateSlug(),
        ]);

        $this->product = new ProductFactory()
            ->for(new ProductGroupFactory()->extension())
            ->createOne(['name' => '.nl', 'slug' => 'extension_nl']);

        $this->customer = new CustomerFactory()->createOne();
        $this->actingAsCustomer($this->customer);

        $this->cancelDate = CarbonImmutable::now()->startOfDay();
        $this->startDate = $this->cancelDate->subMonths(4);
        $this->endDate = $this->startDate->addMonths(8);
        CarbonImmutable::setTestNow($this->startDate);

        $logger = $this->createStub(LoggerInterface::class);
        $this->app->bind(LoggerInterface::class, fn () => $logger);

        $this->cancellationService = self::resolve(CancellationService::class);
    }

    #[Test]
    public function cancelSingleSubscriptionOnEndDateWithEmail(): void
    {
        self::assertEmailsSend([
            MailSubscriptionCancelled::class,
        ]);

        $subscription = $this->getSubscription();

        CarbonImmutable::setTestNow($this->cancelDate);
        self::resolve(CancellationService::class)->cancel($subscription, SubscriptionCancelType::CANCEL_END_DATE, SubscriptionCancelReason::REASON_CANCELLATION);

        self::assertSame(AdministrativeStatus::CANCELED->value, $subscription->administrative_status);
        self::assertSame($this->cancelDate->getTimestamp(), $subscription->cancel_date?->getTimestamp());
        self::assertSame($this->endDate->getTimestamp(), $subscription->end_date->getTimestamp());
    }

    #[Test]
    public function cancelSingleSubscriptionOnEndDateWithoutEmail(): void
    {
        $mailer = self::createMock(Mailer::class);
        $mailer->expects(self::never())
            ->method('send');
        $this->app->bind(Mailer::class, fn () => $mailer);
        $subscription = $this->getSubscription();

        CarbonImmutable::setTestNow($this->cancelDate);
        $this->cancellationService->cancel($subscription, SubscriptionCancelType::CANCEL_END_DATE, SubscriptionCancelReason::REASON_CANCELLATION, false);

        self::assertSame(AdministrativeStatus::CANCELED->value, $subscription->administrative_status);
        self::assertSame($this->cancelDate->getTimestamp(), $subscription->cancel_date?->getTimestamp());
        self::assertSame($this->endDate->getTimestamp(), $subscription->end_date->getTimestamp());
    }

    #[Test]
    public function cancelWithRelatedSubscriptions(): void
    {
        self::assertEmailsSend([
            MailSubscriptionCancelled::class,
            MailSubscriptionCancelled::class,
        ]);
        $this->cancellationService = self::resolve(CancellationService::class);

        $domainSubscription = $this->getSubscription();

        $freeRedirectProduct = new ProductFactory()->freeRedirect()->createOne();
        $freeDnsProduct = new ProductFactory()->freeDns()->createOne();

        $provider = ProviderFactory::new()->createOne(['type' => ProviderType::DOMAIN, 'slug' => ProviderSlug::PLACEHOLDER, 'enabled' => true, 'default' => true]);
        new DomainDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $domainSubscription->uuid,
        ]);

        $freeRedirectSubscription = $this->getSubscription(null, $freeRedirectProduct);
        $freeDnsSubscription = $this->getSubscription($domainSubscription, $freeDnsProduct);

        CarbonImmutable::setTestNow($this->cancelDate);
        $this->cancellationService->cancel($domainSubscription, SubscriptionCancelType::CANCEL_END_DATE, SubscriptionCancelReason::REASON_CANCELLATION);

        $domainSubscription->refresh();
        $freeRedirectSubscription->refresh();
        $freeDnsSubscription->refresh();

        self::assertSame(AdministrativeStatus::CANCELED->value, $domainSubscription->administrative_status);
        self::assertSame($this->cancelDate->getTimestamp(), $domainSubscription->cancel_date?->getTimestamp());
        self::assertSame($this->endDate->getTimestamp(), $domainSubscription->end_date->getTimestamp());

        self::assertSame(AdministrativeStatus::CANCELED->value, $freeRedirectSubscription->administrative_status);
        self::assertSame($this->cancelDate->getTimestamp(), $freeRedirectSubscription->cancel_date?->getTimestamp());
        self::assertSame($this->endDate->getTimestamp(), $freeRedirectSubscription->end_date->getTimestamp());

        self::assertSame(AdministrativeStatus::CANCELED->value, $freeDnsSubscription->administrative_status);
        self::assertSame($this->cancelDate->getTimestamp(), $freeDnsSubscription->cancel_date?->getTimestamp());
        self::assertSame($this->endDate->getTimestamp(), $freeDnsSubscription->end_date->getTimestamp());

        self::assertDatabaseCount('notes', 1);
    }

    #[Test]
    public function subscriptionWithChildCancelChildPlannedShouldNotAffectParent(): void
    {
        $subscription = $this->getSubscription();
        $childSubscription = $this->getSubscription($subscription);

        CarbonImmutable::setTestNow($this->cancelDate);
        $this->cancellationService->cancel($childSubscription, SubscriptionCancelType::CANCEL_END_DATE, SubscriptionCancelReason::REASON_CANCELLATION);

        $subscription = $subscription->refresh();
        $childSubscription = $childSubscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $subscription->administrative_status);
        self::assertNull($subscription->cancel_date);
        self::assertSame($this->endDate->getTimestamp(), $subscription->end_date->getTimestamp());

        self::assertSame(AdministrativeStatus::CANCELED->value, $childSubscription->administrative_status);
        self::assertNull($subscription->cancel_reason);
        self::assertSame($this->cancelDate->getTimestamp(), $childSubscription->cancel_date?->getTimestamp());
        self::assertSame($this->endDate->getTimestamp(), $childSubscription->end_date->getTimestamp());
    }

    #[Test]
    public function subscriptionWithChildCancelParentPlannedShouldCancelParentAndChildren(): void
    {
        $subscription = $this->getSubscription();
        $childSubscription = $this->getSubscription($subscription);

        CarbonImmutable::setTestNow($this->cancelDate);
        $this->cancellationService->cancel($subscription, SubscriptionCancelType::CANCEL_END_DATE, SubscriptionCancelReason::REASON_CANCELLATION);

        $subscription->refresh();
        $childSubscription->refresh();

        self::assertSame(AdministrativeStatus::CANCELED->value, $subscription->administrative_status);
        self::assertSame($this->cancelDate->getTimestamp(), $subscription->cancel_date?->getTimestamp());
        self::assertSame($this->endDate->getTimestamp(), $subscription->end_date->getTimestamp());

        self::assertSame(AdministrativeStatus::CANCELED->value, $childSubscription->administrative_status);
        self::assertSame($this->cancelDate->getTimestamp(), $childSubscription->cancel_date?->getTimestamp());
        self::assertSame($this->endDate->getTimestamp(), $childSubscription->end_date->getTimestamp());
    }

    #[Test]
    public function cancelWithOtherEndDate(): void
    {
        self::assertEmailsSend([
            MailSubscriptionCancelled::class,
        ]);
        $this->cancellationService = self::resolve(CancellationService::class);

        $subscription = $this->getSubscription();

        $notes = Notes::query()->where(['subscription_id' => $subscription->id])->count();
        self::assertSame(0, $notes);

        $terminateDate = $this->cancelDate->subMonth();

        CarbonImmutable::setTestNow($this->cancelDate);
        $this->cancellationService->cancel(
            $subscription,
            SubscriptionCancelType::CANCEL_OTHER,
            SubscriptionCancelReason::REASON_CANCELLATION,
            true,
            $terminateDate,
            'Wet van Dam'
        );

        $subscription->refresh();

        self::assertSame(AdministrativeStatus::CANCELED->value, $subscription->administrative_status);
        self::assertSame($this->cancelDate->getTimestamp(), $subscription->cancel_date?->getTimestamp());
        self::assertSame($terminateDate->getTimestamp(), $subscription->end_date->getTimestamp());

        $notes = Notes::where('subscription_id', $subscription->id)->firstOrFail();
        $noteMessage = sprintf(
            'Subscription cancelled. %s, generated end date: %s',
            'Wet van Dam',
            $terminateDate->format('Y-m-d')
        );

        self::assertInstanceOf(Subscription::class, $notes->subscription);
        self::assertSame($subscription->id, $notes->subscription->id);
        self::assertSame($subscription->customer_id, $notes->subscription->customer_id);
        self::assertSame($noteMessage, $notes->note);
    }

    private function getSubscription(
        ?Subscription $parentSubscription = null,
        ?Product $product = null,
    ): Subscription {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->createOne([
                'contract_period' => 12,
                'domain' => 'cancelsubscription.nl',
                'product_uuid' => $product !== null ? $product->uuid : $this->product->uuid,
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'cancel_date' => null,
            ]);

        $parentSubscription?->children()->save($subscription);

        return $subscription;
    }
}
