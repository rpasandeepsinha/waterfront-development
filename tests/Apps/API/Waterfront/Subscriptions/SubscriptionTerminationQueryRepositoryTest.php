<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Subscriptions;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

#[CoversClass(SubscriptionRepository::class)]
class SubscriptionTerminationQueryRepositoryTest extends IntegrationTestCase
{
    private SubscriptionRepository $subscriptionRepository;

    private ProductGroup $hostingProductGroup;

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = self::resolve(SubscriptionRepository::class);

        $this->hostingProductGroup = new ProductGroupFactory()->hosting()->createOne();
    }

    #[Test]
    public function getAllDueForTerminationExpiredSuspend(): void
    {
        $sub = new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->createOne([
           'administrative_status' => AdministrativeStatus::EXPIRED->value,
           'technical_status' => TechnicalStatus::SUSPENDED->value,
           'termination_date' => CarbonImmutable::today(),
        ]);

        $subs = $this->subscriptionRepository->getAllDueForTermination();

        $subscription = $subs[0];

        self::assertCount(1, $subs);
        self::assertInstanceOf(Subscription::class, $subscription);
        self::assertSame($sub->uuid, $subscription->uuid);
        self::assertSame(AdministrativeStatus::EXPIRED->value, $subscription->administrative_status);
    }

    #[Test]
    public function getAllDueForTerminationDeletedOk(): void
    {
        $sub = new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->createOne([
            'administrative_status' => AdministrativeStatus::EXPIRED->value,
            'technical_status' => TechnicalStatus::OK->value,
            'termination_date' => CarbonImmutable::today(),
        ]);
        $subs = $this->subscriptionRepository->getAllDueForTermination();
        $subscription = $subs[0];

        self::assertCount(1, $subs);
        self::assertInstanceOf(Subscription::class, $subscription);
        self::assertSame($sub->uuid, $subscription->uuid);
        self::assertSame(AdministrativeStatus::EXPIRED->value, $subscription->administrative_status);
    }

    #[Test]
    public function getAllDueForTerminationSuspendSuspendNoResult(): void
    {
        new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->createOne([
            'administrative_status' => AdministrativeStatus::SUSPENDED->value,
            'technical_status' => TechnicalStatus::SUSPENDED->value,
            'termination_date' => CarbonImmutable::today(),
        ]);

        $subs = $this->subscriptionRepository->getAllDueForTermination();

        self::assertCount(0, $subs);
    }

    #[Test]
    public function getAllDueForTerminationMultiple(): void
    {
        // mag
        $sub1 = new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->createOne([
            'administrative_status' => AdministrativeStatus::EXPIRED->value,
            'technical_status' => TechnicalStatus::SUSPENDED->value,
            'termination_date' => CarbonImmutable::today(),
        ]);
        $sub2 = new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->createOne([
            'administrative_status' => AdministrativeStatus::EXPIRED->value,
            'technical_status' => TechnicalStatus::OK->value,
            'termination_date' => CarbonImmutable::today(),
        ]);

        // mag niet
        new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->createOne([
            'administrative_status' => AdministrativeStatus::ARCHIVED->value,
            'technical_status' => TechnicalStatus::DELETED->value,
            'termination_date' => CarbonImmutable::today(),
        ]);
        new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->createOne([
            'administrative_status' => AdministrativeStatus::SUSPENDED->value,
            'technical_status' => TechnicalStatus::SUSPENDED->value,
            'termination_date' => CarbonImmutable::today(),
        ]);

        $subs = $this->subscriptionRepository->getAllDueForTermination();
        $subscription1 = $subs->where('uuid', $sub1->uuid)->firstOrFail();
        $subscription2 = $subs->where('uuid', $sub2->uuid)->firstOrFail();

        self::assertCount(2, $subs);

        self::assertSame(AdministrativeStatus::EXPIRED->value, $subscription1->administrative_status);
        self::assertSame(AdministrativeStatus::EXPIRED->value, $subscription2->administrative_status);
    }

    #[Test]
    public function getActiveParentForTerminationChildDeletedOk(): void
    {
        $parentSub = new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->createOne([
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'technical_status' => TechnicalStatus::OK->value,
        ]);

        $childSub = new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->parentSubscription($parentSub)->createOne([
            'administrative_status' => AdministrativeStatus::EXPIRED->value,
            'technical_status' => TechnicalStatus::OK->value,
            'termination_date' => CarbonImmutable::today(),
        ]);

        $subs = $this->subscriptionRepository->getAllDueForTermination();

        self::assertCount(1, $subs);

        $subscription = $subs[0];

        self::assertCount(1, $subs);
        self::assertInstanceOf(Subscription::class, $subscription);
        self::assertNotSame($childSub->uuid, $subscription->uuid);
        self::assertSame($parentSub->uuid, $subscription->uuid);
    }

    #[Test]
    public function getParentForTerminationExpiredParentWithChildDeleted(): void
    {
        $parentSub = new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->createOne([
            'administrative_status' => AdministrativeStatus::EXPIRED->value,
            'technical_status' => TechnicalStatus::SUSPENDED->value,
        ]);

        new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->parentSubscription($parentSub)->createOne([
            'administrative_status' => AdministrativeStatus::EXPIRED->value,
            'technical_status' => TechnicalStatus::OK->value,
            'termination_date' => CarbonImmutable::today(),
        ]);

        $subs = $this->subscriptionRepository->getAllDueForTermination();

        $subscription = $subs[0];

        self::assertCount(1, $subs);
        self::assertInstanceOf(Subscription::class, $subscription);
        self::assertSame($parentSub->uuid, $subscription->uuid);
    }

    #[Test]
    public function terminateGracedParentSubscriptionWithActiveChild(): void
    {
        $parentSub = new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->createOne([
            'administrative_status' => AdministrativeStatus::EXPIRED->value,
            'technical_status' => TechnicalStatus::SUSPENDED->value,
            'termination_date' => CarbonImmutable::today(),
        ]);

        new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->parentSubscription($parentSub)->createOne([
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'technical_status' => TechnicalStatus::OK->value,
        ]);

        $subs = $this->subscriptionRepository->getAllDueForTermination();

        $subscription = $subs[0];

        self::assertCount(1, $subs);
        self::assertInstanceOf(Subscription::class, $subscription);
        self::assertSame($parentSub->uuid, $subscription->uuid);
    }

    #[Test]
    public function terminateMultipleParentSubscriptionWithChildSubs(): void
    {
        $graceParentSub = new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->createOne([
            'administrative_status' => AdministrativeStatus::EXPIRED->value,
            'technical_status' => TechnicalStatus::SUSPENDED->value,
            'termination_date' => CarbonImmutable::today(),
        ]);
        new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->parentSubscription($graceParentSub)->createOne([
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'technical_status' => TechnicalStatus::OK->value,
        ]);

        $parentSub = new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->createOne([
            'administrative_status' => AdministrativeStatus::EXPIRED->value,
            'technical_status' => TechnicalStatus::OK->value,
            'termination_date' => CarbonImmutable::today(),
        ]);

        $activeParentSubscription = new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->createOne([
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'technical_status' => TechnicalStatus::OK->value,
        ]);
        new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for($this->hostingProductGroup))->parentSubscription($activeParentSubscription)->createOne([
            'administrative_status' => AdministrativeStatus::EXPIRED->value,
            'technical_status' => TechnicalStatus::OK->value,
            'termination_date' => CarbonImmutable::today(),
        ]);

        $subs = $this->subscriptionRepository->getAllDueForTermination();

        self::assertCount(3, $subs);
        self::assertTrue($subs->contains('uuid', $graceParentSub->uuid));
        self::assertTrue($subs->contains('uuid', $parentSub->uuid));
        self::assertTrue($subs->contains('uuid', $activeParentSubscription->uuid));
        self::assertSame(AdministrativeStatus::ACTIVE->value, $subs->where('uuid', $activeParentSubscription->uuid)->firstOrFail()->administrative_status);
    }
}
