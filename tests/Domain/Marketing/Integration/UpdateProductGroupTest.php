<?php

declare(strict_types=1);

namespace Tests\Domain\Marketing\Integration;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Observers\ProductGroupObserver;

#[CoversClass(ProductGroupObserver::class)]
class UpdateProductGroupTest extends IntegrationTestCase
{
    #[Test]
    public function productGroupUpdateShouldUpdateSubscriptions(): void
    {
        $now = CarbonImmutable::now();
        $yesterday = $now->subDay();
        CarbonImmutable::setTestNow($yesterday);

        $extensionProduct = new ProductFactory()->nlDomain()->createOne();
        $hostingProduct = new ProductFactory()->hostingBrons()->createOne();

        $domainSubscription = new SubscriptionFactory()->withCustomer()->for($extensionProduct)->createOne();
        $hostingDeployment = new SubscriptionFactory()->withCustomer()->for($hostingProduct)->createOne();

        self::assertSame($yesterday->timestamp, $domainSubscription->updated_at?->timestamp);
        self::assertSame($yesterday->timestamp, $hostingDeployment->updated_at?->timestamp);

        CarbonImmutable::setTestNow($now);

        $extensionProduct->productGroup->slug = ProductGroupType::CLOUDSTACK_OS;
        $extensionProduct->productGroup->save();

        $domainSubscription->refresh();
        $hostingDeployment->refresh();

        self::assertSame($now->timestamp, $domainSubscription->updated_at?->timestamp);
        self::assertSame($yesterday->timestamp, $hostingDeployment->updated_at?->timestamp);
    }
}
