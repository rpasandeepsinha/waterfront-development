<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\SubscriptionMutationFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Subscriptions\Actions\DeleteSubscriptionMutationAction;
use Waterfront\Domain\Subscriptions\Exceptions\RenewalDateTooNearToMutationException;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionAlreadyMutatedException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;

#[CoversClass(DeleteSubscriptionMutationAction::class)]
class DeleteSubscriptionMutationActionTest extends IntegrationTestCase
{
    private SubscriptionMutation $mutation;

    private Subscription $subscription;

    private CarbonImmutable $date;

    private DeleteSubscriptionMutationAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow();
        Config::set('constants.renewal-days', 11);

        $this->date = CarbonImmutable::now();

        $product = new ProductFactory()->for(
            new ProductGroupFactory()->extension(),
        )->createOne();

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusActive()
            ->for($product)
            ->createOne();

        $this->mutation = new SubscriptionMutationFactory()
            ->for($this->subscription)
            ->for($this->subscription->product)
            ->createOne();

        $this->action = self::resolve(DeleteSubscriptionMutationAction::class);
    }

    #[Test]
    public function mutationDeleteActionWillThrowAlreadyMutatedException(): void
    {
        $this->mutation->mutated_at = CarbonImmutable::now();
        $this->mutation->save();

        $this->expectException(SubscriptionAlreadyMutatedException::class);
        $this->action->execute($this->mutation);
    }

    #[Test]
    public function mutationDeleteActionWillThrowRenewalTooSoonException(): void
    {
        $this->subscription->update(['end_date' => $this->date->addDays(10)]);

        $this->subscription->refresh();
        $this->mutation->refresh();

        $this->expectException(RenewalDateTooNearToMutationException::class);
        $this->action->execute($this->mutation);
    }

    #[Test]
    public function mutationWillBeDeleted(): void
    {
        $this->subscription->update(['end_date' => $this->date->subDays(16)]);

        self::assertCount(1, $this->subscription->mutations);
        $this->action->execute($this->mutation);
        $this->subscription->refresh();
        self::assertCount(0, $this->subscription->mutations);
    }
}
