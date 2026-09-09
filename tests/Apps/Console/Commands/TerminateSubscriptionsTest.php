<?php

declare(strict_types=1);

namespace Tests\Apps\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Console\Commands\Subscriptions\TerminateSubscriptions;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Jobs\TerminateSubscription;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(TerminateSubscriptions::class)]
class TerminateSubscriptionsTest extends IntegrationTestCase
{
    #[Test]
    public function subscriptionsAreDispatchedToQueue(): void
    {
        Queue::fake();

        $now = new CarbonImmutable();
        CarbonImmutable::setTestNow($now);

        $yesterday = $now->modify('-1 days')->format(DateTimeFormat::DEFAULT);
        $productGroup = new ProductGroupFactory()->createOne(['slug' => ProductGroupType::EXTENSION]);
        $product = new ProductFactory()->for($productGroup)->createOne();
        $subscriptions = new SubscriptionFactory()->withCustomer()->createMany([
            [
                'product_uuid' => $product->uuid,
                'administrative_status' => AdministrativeStatus::EXPIRED->value,
                'technical_status' => TechnicalStatus::OK->value,
                'end_date' => $yesterday,
                'termination_date' => $yesterday,
            ],
            [
                'product_uuid' => $product->uuid,
                'administrative_status' => AdministrativeStatus::EXPIRED->value,
                'technical_status' => TechnicalStatus::OK->value,
                'end_date' => $yesterday,
                'termination_date' => $yesterday,
            ],
        ]);
        $subscription3 = new SubscriptionFactory()->withCustomer()->createOne([
            'product_uuid' => $product->uuid,
            'administrative_status' => AdministrativeStatus::CANCELED->value,
        ]);

        $subscription1 = $subscriptions->first();
        $subscription2 = $subscriptions->last();

        self::assertNotNull($subscription1);
        self::assertNotNull($subscription2);

        $this->artisan(TerminateSubscriptions::class);

        Queue::assertPushed(TerminateSubscription::class, 2);

        Queue::assertPushedOn(QueueName::SUBSCRIPTIONS->value, fn (TerminateSubscription $job) => $subscription1->id === $job->subscription->id);

        Queue::assertPushedOn(QueueName::SUBSCRIPTIONS->value, fn (TerminateSubscription $job) => $subscription2->id === $job->subscription->id);

        Queue::assertNotPushed(fn (TerminateSubscription $job) => $subscription3->id === $job->subscription->id);
    }
}
