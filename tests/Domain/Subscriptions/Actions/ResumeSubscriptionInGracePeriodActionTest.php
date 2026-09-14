<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use UnexpectedValueException;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Subscriptions\Actions\ResumeSubscriptionInGracePeriodAction;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(ResumeSubscriptionInGracePeriodAction::class)]
class ResumeSubscriptionInGracePeriodActionTest extends IntegrationTestCase
{
    private Subscription $subscription;

    private ResumeSubscriptionInGracePeriodAction $resumeSubscriptionInGracePeriodAction;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow();

        Model::preventLazyLoading(false);

        $date = CarbonImmutable::now();

        $product = new ProductFactory()->for(
            new ProductGroupFactory()->extension(),
        )->createOne();

        new ProductPriceComponentFactory()
            ->registration()
            ->for($product)
            ->createOne();
        new ProductPriceComponentFactory()
            ->prolongation()
            ->for($product)
            ->createOne();

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusExpired()
            ->for($product)
            ->createOne([
                'cancel_date' => $date->subWeek(),
                'termination_date' => $date->addWeek(),
            ]);

        $this->resumeSubscriptionInGracePeriodAction = self::resolve(ResumeSubscriptionInGracePeriodAction::class);
    }

    #[Test]
    public function subscriptionIsNotEligibleForResumingWrongAdministrativeStatus(): void
    {
        $this->subscription->update(['administrative_status' => AdministrativeStatus::ACTIVE->value]);
        $this->subscription->refresh();

        $this->expectException(UnexpectedValueException::class);
        $this->resumeSubscriptionInGracePeriodAction->execute($this->subscription);
    }

    #[Test]
    public function subscriptionSuccessfullyRenewed(): void
    {
        $this->subscription->update(['administrative_status' => AdministrativeStatus::EXPIRED->value]);
        $this->subscription->refresh();
        $endDate = $this->subscription->end_date->addYear();

        $this->resumeSubscriptionInGracePeriodAction->execute($this->subscription);

        $this->subscription->refresh();

        $productPrice = ProductPriceComponent::where('type', PriceComponentType::PROLONGATION)->first();
        self::assertNotNull($productPrice);

        self::assertSame($endDate->toString(), $this->subscription->end_date->toString());
        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertNull($this->subscription->termination_date);
        self::assertSame($productPrice->price, $this->subscription->net_price);
    }
}
