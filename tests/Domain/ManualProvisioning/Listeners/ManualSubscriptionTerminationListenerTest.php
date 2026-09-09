<?php

declare(strict_types=1);

namespace Tests\Domain\ManualProvisioning\Listeners;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\ManualProvisioning\Events\DispatchTerminateManualProvisioning;
use Waterfront\Domain\ManualProvisioning\Listeners\ManualSubscriptionTerminationListener;
use Waterfront\Domain\ManualProvisioning\Mailer\Employee\CanceledManualSubscriptionEmployee;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(ManualSubscriptionTerminationListener::class)]
class ManualSubscriptionTerminationListenerTest extends IntegrationTestCase
{
    private Subscription $subscription;

    public function setUp(): void
    {
        parent::setUp();

        $productGroup = new ProductGroupFactory()->manualSubscription()->createOne([
            'slug' => ProductGroupType::MANUAL_SUBSCRIPTION,
        ]);

        $product = new ProductFactory()->createOne([
            'slug' => 'manual-testproduct',
            'name' => 'ManualTestProduct',
            'product_group_id' => $productGroup->id,
        ]);

        $this->subscription = new SubscriptionFactory()->for(new CustomerFactory()->createOne())->createOne([
            'product_uuid' => $product->uuid,
        ]);
    }

    #[Test]
    public function manualSubscriptionTerminationListener(): void
    {
        self::assertEmailsSend([
            CanceledManualSubscriptionEmployee::class,
        ]);

        $event = new DispatchTerminateManualProvisioning(
            $this->subscription
        );

        $listener = self::resolve(ManualSubscriptionTerminationListener::class);
        $listener->handle($event);
    }
}
