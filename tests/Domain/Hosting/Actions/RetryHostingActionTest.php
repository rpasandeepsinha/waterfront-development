<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Actions;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Actions\RetryHostingAction;
use Waterfront\Domain\Hosting\Enums\HostingRetryType;
use Waterfront\Domain\Hosting\Events\CreateHosting;
use Waterfront\Domain\MailManagement\Events\CreateMailOnlyHosting;
use Waterfront\Domain\ResellerHosting\Jobs\CreateResellerHostingJob;
use Waterfront\Domain\Sitebuilder\Events\CreateSitebuilder;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Webmozart\Assert\InvalidArgumentException;

#[CoversClass(RetryHostingAction::class)]
class RetryHostingActionTest extends IntegrationTestCase
{
    #[Test]
    public function basicRetryDispatchesCreateHostingEventWithGivenServerId(): void
    {
        Event::fake([CreateHosting::class, CreateSitebuilder::class, CreateMailOnlyHosting::class]);

        $subscription = $this->makeHostingSubscription(TechnicalStatus::FAILED->value, 'retry-hosting.nl');

        self::resolve(RetryHostingAction::class)->execute($subscription, HostingRetryType::BASIC, 4321);

        Event::assertDispatched(
            CreateHosting::class,
            fn (CreateHosting $event): bool => $event->subscriptionUuid === $subscription->uuid
                && $event->serverId === 4321
                && $event->domain === $subscription->domain
                && $event->contactEmail === $subscription->customer->email
        );
    }

    #[Test]
    public function basicRetryWithoutServerIdDispatchesCreateHostingEventWithoutServer(): void
    {
        Event::fake([CreateHosting::class, CreateSitebuilder::class, CreateMailOnlyHosting::class]);

        $subscription = $this->makeHostingSubscription(TechnicalStatus::FAILED->value, 'retry-hosting.nl');

        self::resolve(RetryHostingAction::class)->execute($subscription, HostingRetryType::BASIC, null);

        Event::assertDispatched(
            CreateHosting::class,
            fn (CreateHosting $event): bool => $event->serverId === null
        );
    }

    #[Test]
    public function basicRetryForcesDeletionOfHostingDeploymentWhenTechnicalStatusIsError(): void
    {
        Event::fake([CreateHosting::class, CreateSitebuilder::class, CreateMailOnlyHosting::class]);

        $subscription = $this->makeHostingSubscription(TechnicalStatus::ERROR->value, 'retry-hosting.nl');
        $hostingDeployment = new HostingDeploymentFactory()
            ->withPleskProvider()
            ->createOne(['subscription_uuid' => $subscription->uuid]);

        self::resolve(RetryHostingAction::class)->execute($subscription, HostingRetryType::BASIC, null);

        self::assertDatabaseMissing('hosting_deployments', ['id' => $hostingDeployment->id]);
        Event::assertDispatched(CreateHosting::class);
    }

    #[Test]
    public function basicRetryKeepsHostingDeploymentWhenTechnicalStatusIsNotError(): void
    {
        Event::fake([CreateHosting::class, CreateSitebuilder::class, CreateMailOnlyHosting::class]);

        $subscription = $this->makeHostingSubscription(TechnicalStatus::FAILED->value, 'retry-hosting.nl');
        $hostingDeployment = new HostingDeploymentFactory()
            ->withPleskProvider()
            ->createOne(['subscription_uuid' => $subscription->uuid]);

        self::resolve(RetryHostingAction::class)->execute($subscription, HostingRetryType::BASIC, null);

        self::assertDatabaseHas('hosting_deployments', ['id' => $hostingDeployment->id, 'deleted_at' => null]);
    }

    #[Test]
    public function sitebuilderRetryDispatchesCreateSitebuilderEvent(): void
    {
        Event::fake([CreateHosting::class, CreateSitebuilder::class, CreateMailOnlyHosting::class]);

        $subscription = $this->makeHostingSubscription(TechnicalStatus::FAILED->value, 'retry-hosting.nl');

        self::resolve(RetryHostingAction::class)->execute($subscription, HostingRetryType::SITEBUILDER, null);

        Event::assertDispatched(
            CreateSitebuilder::class,
            fn (CreateSitebuilder $event): bool => $event->subscription->uuid === $subscription->uuid
                && $event->contactEmail === $subscription->customer->email
        );
    }

    #[Test]
    public function sitebuilderRetryThrowsWhenSubscriptionHasNoDomain(): void
    {
        Event::fake([CreateHosting::class, CreateSitebuilder::class, CreateMailOnlyHosting::class]);

        $subscription = $this->makeHostingSubscription(TechnicalStatus::FAILED->value, null);

        $this->expectException(InvalidArgumentException::class);

        self::resolve(RetryHostingAction::class)->execute($subscription, HostingRetryType::SITEBUILDER, null);
    }

    #[Test]
    public function mailOnlyRetryDispatchesCreateMailOnlyHostingEvent(): void
    {
        Event::fake([CreateHosting::class, CreateSitebuilder::class, CreateMailOnlyHosting::class]);

        $subscription = $this->makeHostingSubscription(TechnicalStatus::FAILED->value, 'retry-hosting.nl');

        self::resolve(RetryHostingAction::class)->execute($subscription, HostingRetryType::MAIL_ONLY, null);

        Event::assertDispatched(
            CreateMailOnlyHosting::class,
            fn (CreateMailOnlyHosting $event): bool => $event->subscription->uuid === $subscription->uuid
        );
    }

    #[Test]
    public function resellerHostingRetryDispatchesCreateResellerHostingJob(): void
    {
        Bus::fake();

        $product = new ProductFactory()->resellerHostingBrons()->createOne();
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->technicalStatus(TechnicalStatus::FAILED->value)
            ->createOne();

        self::resolve(RetryHostingAction::class)->execute($subscription, HostingRetryType::RESELLER_HOSTING, 99);

        Bus::assertDispatched(
            CreateResellerHostingJob::class,
            fn (CreateResellerHostingJob $job): bool => $job->subscriptionUuid === $subscription->uuid
                && $job->serverId === 99
        );
    }

    private function makeHostingSubscription(?string $technicalStatus, ?string $domain): Subscription
    {
        $product = new ProductFactory()->hostingBrons()->createOne();

        return new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->technicalStatus($technicalStatus)
            ->createOne(['domain' => $domain]);
    }
}
