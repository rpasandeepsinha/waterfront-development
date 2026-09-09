<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Unit\Events;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\VPS\Events\CreateVps;

#[CoversClass(CreateVps::class)]
class CreateVpsEventTest extends IntegrationTestCase
{
    #[Test]
    public function createVps(): void
    {
        $vpsProductGroup = new ProductGroupFactory()->createOne([
            'name' => 'VPS',
            'slug' => ProductGroupType::VPS,
        ]);

        $vpsProduct = new ProductFactory()->for($vpsProductGroup)->createOne();

        $subscription = new SubscriptionFactory()->withCustomer()->createOne([
            'product_uuid' => $vpsProduct->uuid,
        ]);

        Event::fake([CreateVps::class]);

        self::resolve(Dispatcher::class)->dispatch(
            new CreateVps(
                subscriptionUuid: $subscription->uuid,
                sshKeyUuid: null,
            )
        );

        Event::assertDispatched(
            fn (CreateVps $event) =>
            $event->subscriptionUuid === $subscription->uuid
            && $event->sshKeyUuid === null
        );
    }
}
