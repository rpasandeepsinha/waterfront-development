<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Jobs;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Jobs\UpdateTechnicalStatusJob;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

#[CoversClass(UpdateTechnicalStatusJob::class)]
class UpdateTechnicalStatusJobTest extends IntegrationTestCase
{
    #[Test]
    public function technicalStatusUpdateOkDefault(): void
    {
        $subscriptionRepo = self::resolve(SubscriptionRepository::class);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->hostingBrons())
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->createOne();

        $job = new UpdateTechnicalStatusJob($subscription);
        $job->handle($subscriptionRepo);

        self::assertSame(TechnicalStatus::OK->value, $subscription->refresh()->technical_status);
    }

    #[DataProvider('TechnicalStatusProvider')]
    #[Test]
    public function technicalStatusUpdate(string $from, string $to): void
    {
        $subscriptionRepo = self::resolve(SubscriptionRepository::class);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->hostingBrons())
            ->technicalStatus($from)
            ->createOne();

        $job = new UpdateTechnicalStatusJob($subscription, $to);
        $job->handle($subscriptionRepo);

        self::assertSame($to, $subscription->refresh()->technical_status);
    }

    public static function TechnicalStatusProvider(): Generator
    {
        yield [
            TechnicalStatus::OK->value,
            TechnicalStatus::PENDING->value,
        ];

        yield [
            TechnicalStatus::ERROR->value,
            TechnicalStatus::OK->value,
        ];

        yield [
            TechnicalStatus::FAILED->value,
            TechnicalStatus::OK->value,
        ];

        yield [
            TechnicalStatus::REGISTRATION->value,
            TechnicalStatus::OK->value,
        ];

        yield [
            TechnicalStatus::DELETED->value,
            TechnicalStatus::OK->value,
        ];

        yield [
            TechnicalStatus::DELETING->value,
            TechnicalStatus::OK->value,
        ];

        yield [
            TechnicalStatus::DELETING_FAILED->value,
            TechnicalStatus::OK->value,
        ];

        yield [
            TechnicalStatus::PENDING->value,
            TechnicalStatus::OK->value,
        ];

        yield [
            TechnicalStatus::SUSPENDED->value,
            TechnicalStatus::OK->value,
        ];

        yield [
            TechnicalStatus::SUSPENDING->value,
            TechnicalStatus::OK->value,
        ];

        yield [
            TechnicalStatus::SUSPENSION_FAILED->value,
            TechnicalStatus::OK->value,
        ];

        yield [
            TechnicalStatus::UNSUSPENDING->value,
            TechnicalStatus::OK->value,
        ];

        yield [
            TechnicalStatus::UNSUSPENSION_FAILED->value,
            TechnicalStatus::OK->value,
        ];
    }
}
