<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Subscriptions\Actions\DetermineExpirationPathForSubscriptionAction;
use Waterfront\Domain\Subscriptions\Actions\GracefullyExpireSubscriptionAction;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(DetermineExpirationPathForSubscriptionAction::class)]
class DetermineExpirationPathForSubscriptionActionTest extends IntegrationTestCase
{
    private ProductGroup $extensionProductGroup;

    private Subscription $parentSubscription;

    private Subscription $childSubscription;

    private GracefullyExpireSubscriptionAction&MockObject $expireAction;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow();

        $this->expireAction = self::createMock(GracefullyExpireSubscriptionAction::class);

        $this->extensionProductGroup = new ProductGroupFactory()->extension()->createOne();

        $this->parentSubscription = new SubscriptionFactory()
            ->administrativeStatusCancelled()
            ->withCustomer()
            ->for(new ProductFactory()->for($this->extensionProductGroup))
            ->createOne([
                'end_date' => new CarbonImmutable('yesterday'),
            ]);

        $this->childSubscription = new SubscriptionFactory()
            ->administrativeStatusCancelled()
            ->withCustomer()
            ->parentSubscription($this->parentSubscription)
            ->for(new ProductFactory()->for($this->extensionProductGroup))
            ->createOne([
                'end_date' => new CarbonImmutable('yesterday'),
            ]);
    }

    #[Test]
    public function expireChildSubscriptionShouldThrowError(): void
    {
        $childSubscription = new SubscriptionFactory()
            ->administrativeStatusCancelled()
            ->withCustomer()
            ->parentSubscription($this->parentSubscription)
            ->for(new ProductFactory()->for($this->extensionProductGroup))
            ->createOne([
                'end_date' => new CarbonImmutable('yesterday'),
            ]);

        $this->expireAction->expects(self::never())->method('execute');

        $this->expectException(InvalidArgumentException::class);

        $determineAction = new DetermineExpirationPathForSubscriptionAction($this->expireAction);
        $determineAction->execute($childSubscription);
    }

    #[Test]
    public function parentSubscriptionCanceledWithNotSurpassedEndDateShouldNotExpire(): void
    {
        $this->parentSubscription->update(['end_date' => new CarbonImmutable()->addYear()]);
        $this->childSubscription->update(['parent_subscription_id' => null]);
        $this->parentSubscription->refresh();

        $this->expireAction->expects(self::never())->method('execute');

        $determineAction = new DetermineExpirationPathForSubscriptionAction($this->expireAction);
        $determineAction->execute($this->parentSubscription);
    }

    #[Test]
    public function parentSubscriptionWithoutChildrenShouldExpire(): void
    {
        $this->childSubscription->update(['parent_subscription_id' => null]);
        $this->parentSubscription->refresh();

        $this->expireAction->expects(self::once())->method('execute')->with($this->parentSubscription);

        $determineAction = new DetermineExpirationPathForSubscriptionAction($this->expireAction);
        $determineAction->execute($this->parentSubscription);
    }

    #[Test]
    public function parentSubscriptionCanceledWithCanceledChildrenShouldExpireBoth(): void
    {
        $childSubscription = $this->childSubscription;

        $compareSubscriptionId = fn ($subscriptionId) => self::callback(function (Subscription $subscription) use (
            $subscriptionId,
        ) {
            self::assertSame($subscription->id, $subscriptionId);

            return true;
        });

        $this->expireAction
            ->expects(self::exactly(2))
            ->method('execute')
            ->with(
                ...self::withConsecutive(
                    [$compareSubscriptionId($childSubscription->id)],
                    [$compareSubscriptionId($this->parentSubscription->id)],
                ),
            );

        $determineAction = new DetermineExpirationPathForSubscriptionAction($this->expireAction);
        $determineAction->execute($this->parentSubscription);
    }

    #[Test]
    public function parentSubscriptionCanceledWithNotCanceledChildrenShouldExpireBoth(): void
    {
        $childSubscription = $this->childSubscription;
        $childSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $childSubscription->end_date = new CarbonImmutable('tomorrow');
        $childSubscription->save();

        $subscriptionAssertions = fn (
            Subscription $expectedSubscription,
            string $expectedStatus,
        ) => self::callback(function (Subscription $subscription) use ($expectedSubscription, $expectedStatus) {
            self::assertSame($subscription->id, $expectedSubscription->id);
            self::assertSame($expectedStatus, $expectedSubscription->administrative_status);

            return true;
        });

        $this->expireAction
            ->expects(self::exactly(2))
            ->method('execute')
            ->with(
                ...self::withConsecutive(
                    [$subscriptionAssertions($childSubscription, AdministrativeStatus::ACTIVE->value)],
                    [$subscriptionAssertions($this->parentSubscription, AdministrativeStatus::CANCELED->value)],
                ),
            );

        $determineAction = new DetermineExpirationPathForSubscriptionAction($this->expireAction);
        $determineAction->execute($this->parentSubscription);
    }

    #[Test]
    public function parentSubscriptionWithCanceledChildrenShouldExpireChildren(): void
    {
        $this->parentSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;

        $childSubscription = $this->childSubscription;

        $this->expireAction
            ->expects(self::once())
            ->method('execute')
            ->with(self::callback(
                function ($subscription) use ($childSubscription) {
                    self::assertSame($subscription->id, $childSubscription->id);
                    self::assertSame(
                        AdministrativeStatus::ACTIVE->value,
                        $this->parentSubscription->administrative_status,
                    );

                    return true;
                },
            ));

        $determineAction = new DetermineExpirationPathForSubscriptionAction($this->expireAction);
        $determineAction->execute($this->parentSubscription);
    }
}
