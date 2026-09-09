<?php

declare(strict_types=1);

namespace Tests\Domain\ManualProvisioning\Events;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\ManualProvisioning\Events\DispatchCreateManualProvisioning;
use Waterfront\Domain\Products\Enums\ProductGroupType;

#[CoversClass(DispatchCreateManualProvisioning::class)]
class DispatchCreateManualProvisioningTest extends IntegrationTestCase
{
    #[Test]
    public function dispatchCreateManualProvisioning(): void
    {
        $productGroup = new ProductGroupFactory()->manualSubscription()->createOne([
            'slug' => ProductGroupType::MANUAL_SUBSCRIPTION,
        ]);

        $product = new ProductFactory()->createOne([
            'slug' => 'manual-testproduct',
            'name' => 'ManualTestProduct',
            'product_group_id' => $productGroup->id,
        ]);

        $subscription = new SubscriptionFactory()->for(new CustomerFactory()->createOne())->createOne([
            'product_uuid' => $product->uuid,
        ]);

        Event::fake([DispatchCreateManualProvisioning::class]);

        self::resolve(Dispatcher::class)->dispatch(
            new DispatchCreateManualProvisioning(
                $subscription
            )
        );

        Event::assertDispatched(fn (DispatchCreateManualProvisioning $event) => $event->subscription === $subscription);
    }
}
