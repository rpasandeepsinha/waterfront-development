<?php

declare(strict_types=1);

namespace Tests\Domain\Marketing\Integration;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Observers\ProductObserver;

#[CoversClass(ProductObserver::class)]
class UpdateProductTest extends IntegrationTestCase
{
    #[Test]
    public function productUpdateShouldUpdateSubscription(): void
    {
        $yesterday = CarbonImmutable::yesterday();
        CarbonImmutable::setTestNow($yesterday);

        $extensionProduct = new ProductFactory()->nlDomain()->createOne();
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($extensionProduct)
            ->createOne();

        self::assertSame($yesterday->timestamp, $subscription->updated_at?->timestamp);

        CarbonImmutable::setTestNow();

        $extensionProduct->slug = 'domain_nl';
        $extensionProduct->save();

        $subscription->refresh();

        self::assertTrue($subscription->updated_at?->isToday());
    }
}
