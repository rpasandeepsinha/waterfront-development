<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Actions\CancelSubscriptionsAction;
use Waterfront\Domain\Subscriptions\DTO\Cancellation;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\DateTimeFormat;

#[CoversClass(CancelSubscriptionsAction::class)]
class CancelSubscriptionsActionTest extends IntegrationTestCase
{
    private CarbonImmutable $now;

    private CarbonImmutable $endDate;

    private Customer $customer;

    private Product $product;

    private CancelSubscriptionsAction $cancelSubscriptionsAction;

    public function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        // We are testing on a yearly subscription, exactly half way the period
        $this->now = new CarbonImmutable('today 00:00:00');
        $this->endDate = $this->now->addMonths(6);
        CarbonImmutable::setTestNow($this->now);

        $this->customer = new CustomerFactory()
            ->withAddress()
            ->createOne();

        $this->product = new ProductFactory()
            ->for(new ProductGroupFactory()->extension())
            ->createOne();

        $this->cancelSubscriptionsAction = self::resolve(CancelSubscriptionsAction::class);

        $this->actingAsEmployee();
    }

    #[Test]
    public function cancelOnGivenDateInThePast(): void
    {
        $subscriptions = new Collection([$this->getSubscription(
            AdministrativeStatus::ACTIVE->value,
            $this->endDate
        )]);

        $cancelTypeOtherDate = $this->now->subDays(12);
        $cancellation = new Cancellation(
            $subscriptions,
            SubscriptionCancelReason::REASON_CANCELLATION,
            null,
            SubscriptionCancelType::CANCEL_OTHER,
            $cancelTypeOtherDate,
            false,
        );

        $this->cancelSubscriptionsAction->execute($cancellation);

        foreach ($subscriptions as $subscription) {
            $subscription->refresh();

            self::assertSame(AdministrativeStatus::ARCHIVED->value, $subscription->administrative_status);
            self::assertSame($cancelTypeOtherDate->format(DateTimeFormat::DATE), $subscription->end_date->format(DateTimeFormat::DATE));
        }
    }

    #[Test]
    public function cancelOnGivenDateInTheFuture(): void
    {
        $subscriptions = new Collection([$this->getSubscription(
            AdministrativeStatus::ACTIVE->value,
            $this->endDate
        )]);

        $cancelTypeOtherDate = $this->now->addDays(17);
        $cancellation = new Cancellation(
            $subscriptions,
            SubscriptionCancelReason::REASON_CANCELLATION,
            null,
            SubscriptionCancelType::CANCEL_OTHER,
            $cancelTypeOtherDate,
            false
        );

        $this->cancelSubscriptionsAction->execute($cancellation);

        foreach ($subscriptions as $subscription) {
            $subscription->refresh();

            self::assertSame(AdministrativeStatus::CANCELED->value, $subscription->administrative_status);
            self::assertSame($cancelTypeOtherDate->format(DateTimeFormat::DATE), $subscription->end_date->format(DateTimeFormat::DATE));
        }
    }

    #[Test]
    public function cancelOnEndDate(): void
    {
        $subscriptions = new Collection([$this->getSubscription(
            AdministrativeStatus::ACTIVE->value,
            $this->endDate
        )]);

        $cancelTypeOtherDate = null;
        $cancellation = new Cancellation(
            $subscriptions,
            SubscriptionCancelReason::REASON_CANCELLATION,
            null,
            SubscriptionCancelType::CANCEL_END_DATE,
            $cancelTypeOtherDate,
            false
        );

        $this->cancelSubscriptionsAction->execute($cancellation);

        foreach ($subscriptions as $subscription) {
            $subscription->refresh();

            self::assertSame(AdministrativeStatus::CANCELED->value, $subscription->administrative_status);
            self::assertSame($this->endDate->format(DateTimeFormat::DATE), $subscription->end_date->format(DateTimeFormat::DATE));
        }
    }

    private function getSubscription(
        string $administrativeStatus,
        CarbonImmutable $endDate
    ): Subscription {
        return new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->createOneQuietly([
                'contract_period' => 12,
                'billing_period' => 12,
                'start_date' => $endDate->subMonths(24),
                'end_date' => $endDate,
                'next_billing_date' => $endDate,
                'administrative_status' => $administrativeStatus,
                'gross_price' => 100,
                'net_price' => 100,
            ]);
    }
}
