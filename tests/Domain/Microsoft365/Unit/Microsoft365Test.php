<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Unit;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Microsoft365\Events\CreateMicrosoft365;
use Waterfront\Domain\Microsoft365\Listeners\Microsoft365CreationListener;
use Waterfront\Domain\Microsoft365\Services\Microsoft365SubscriptionService;
use Waterfront\Domain\Products\Models\Product;

#[CoversClass(Microsoft365CreationListener::class)]
class Microsoft365Test extends IntegrationTestCase
{
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        new CustomerFactory()->createOne();

        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $this->product = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);
    }

    #[Test]
    public function microsoftEventListener(): void
    {
        Event::fake([CreateMicrosoft365::class]);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'product_uuid' => $this->product->uuid,
                'domain' => '',
            ]);

        $microsoftSubscriptionService = self::createMock(Microsoft365SubscriptionService::class);
        $microsoftSubscriptionService->expects(self::once())->method('create');

        $event = new CreateMicrosoft365([$subscription]);

        $listener = new Microsoft365CreationListener(
            $microsoftSubscriptionService,
        );

        $listener->handle($event);
    }
}
