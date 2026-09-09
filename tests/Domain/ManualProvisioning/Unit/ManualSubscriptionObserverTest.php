<?php

declare(strict_types=1);

namespace Tests\Domain\ManualProvisioning\Unit;

use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Mailer\Mailer;
use Waterfront\Domain\ManualProvisioning\Mailer\Customer\ActivatedManualSubscriptionCustomer;
use Waterfront\Domain\ManualProvisioning\Observers\ManualSubscriptionObserver;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

#[CoversClass(ManualSubscriptionObserver::class)]
class ManualSubscriptionObserverTest extends IntegrationTestCase
{
    #[Test]
    public function observerTechnicalStatusSubscription(): void
    {
        self::assertEmailsSend([
            ActivatedManualSubscriptionCustomer::class,
        ]);

        $manualSubscriptionGroup = new ProductGroupFactory()->manualSubscription()->createOne();
        $manualProduct = new ProductFactory()->for($manualSubscriptionGroup)
            ->createOne(['name' => 'manual test', 'slug' => 'manual_test']);

        $subscriptionUuid = Str::uuid();
        $customer = new CustomerFactory()->createOne();
        $subscription = new SubscriptionFactory()
            ->createOne([
                'uuid' => $subscriptionUuid,
                'customer_id' => $customer->id,
                'domain' => null,
                'product_uuid' => $manualProduct->uuid,
            ]);

        self::assertNull($subscription->technical_status);

        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->save();
    }

    #[Test]
    public function observerTechnicalStatusSubscriptionWrongStatus(): void
    {
        $mailer = self::createMock(Mailer::class);
        $mailer->expects(self::never())
            ->method('send');
        $this->app->bind(Mailer::class, fn () => $mailer);

        $manualSubscriptionGroup = new ProductGroupFactory()->manualSubscription()->createOne();
        $manualProduct = new ProductFactory()->for($manualSubscriptionGroup)
            ->createOne(['name' => 'manual test', 'slug' => 'manual_test']);

        $subscriptionUuid = Str::uuid();
        $customer = new CustomerFactory()->createOne();
        $subscription = new SubscriptionFactory()
            ->createOne([
                'uuid' => $subscriptionUuid,
                'customer_id' => $customer->id,
                'domain' => null,
                'product_uuid' => $manualProduct->uuid,
            ]);

        self::assertNull($subscription->technical_status);

        $subscription->technical_status = TechnicalStatus::ERROR->value;
        $subscription->save();
    }

    #[Test]
    public function observerTechnicalStatusSubscriptionWrongField(): void
    {
        $mailer = self::createMock(Mailer::class);
        $mailer->expects(self::never())
            ->method('send');
        $this->app->bind(Mailer::class, fn () => $mailer);

        $manualSubscriptionGroup = new ProductGroupFactory()->manualSubscription()->createOne();
        $manualProduct = new ProductFactory()->for($manualSubscriptionGroup)
            ->createOne(['name' => 'manual test', 'slug' => 'manual_test']);

        $subscriptionUuid = Str::uuid();
        $customer = new CustomerFactory()->createOne();
        $subscription = new SubscriptionFactory()
            ->createOne([
                'uuid' => $subscriptionUuid,
                'customer_id' => $customer->id,
                'domain' => null,
                'product_uuid' => $manualProduct->uuid,
            ]);

        self::assertNull($subscription->technical_status);

        $subscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $subscription->save();
    }
}
