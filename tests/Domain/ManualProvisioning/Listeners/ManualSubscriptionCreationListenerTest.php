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
use Waterfront\Domain\ManualProvisioning\Events\DispatchCreateManualProvisioning;
use Waterfront\Domain\ManualProvisioning\Listeners\ManualSubscriptionCreationListener;
use Waterfront\Domain\ManualProvisioning\Mailer\Employee\OrderedManualSubscriptionEmployee;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(ManualSubscriptionCreationListener::class)]
class ManualSubscriptionCreationListenerTest extends IntegrationTestCase
{
    private Subscription $subscription;

    public function setUp(): void
    {
        parent::setUp();

        $productGroup = new ProductGroupFactory()
            ->manualSubscription()
            ->createOne([
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
    public function manualSubscriptionCreationListener(): void
    {
        self::assertEmailsSend([
            OrderedManualSubscriptionEmployee::class,
        ]);

        $event = new DispatchCreateManualProvisioning(
            $this->subscription,
        );

        $listener = self::resolve(ManualSubscriptionCreationListener::class);
        $listener->handle($event);
    }
}
