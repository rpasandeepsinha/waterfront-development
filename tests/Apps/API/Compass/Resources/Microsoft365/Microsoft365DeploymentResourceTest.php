<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Resources\Microsoft365;

use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Resources\Microsoft365\Microsoft365DeploymentResource;

#[CoversClass(Microsoft365DeploymentResource::class)]
class Microsoft365DeploymentResourceTest extends IntegrationTestCase
{
    public function testToArrayCountsActiveAndCanceledSeatsSeparately(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $parentProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);
        $childProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard',
        ]);

        $parentSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for($parentProduct)
            ->createOne();

        new SubscriptionFactory()
            ->count(2)
            ->for($customer)
            ->for($childProduct)
            ->parentSubscription($parentSubscription)
            ->administrativeStatusActive()
            ->create();
        new SubscriptionFactory()
            ->for($customer)
            ->for($childProduct)
            ->parentSubscription($parentSubscription)
            ->administrativeStatusCancelled()
            ->createOne();

        $microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($customer)->createOne();
        $microsoft365Deployment = new Microsoft365DeploymentFactory()
            ->for($parentSubscription)
            ->for($microsoft365CustomerInfo)
            ->createOne();

        $microsoft365Deployment->loadMissing([
            'subscription.children.product',
            'subscription.product',
            'microsoft365CustomerInfo',
        ]);

        $data = new Microsoft365DeploymentResource()->toArray($microsoft365Deployment);

        self::assertSame(2, $data['active_seats']);
        self::assertSame(1, $data['canceled_seats']);
    }

    public function testToArrayUsesParentSubscriptionWhenNoChildrenExist(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $parentProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);

        $parentSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for($parentProduct)
            ->createOne();

        $microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($customer)->createOne();
        $microsoft365Deployment = new Microsoft365DeploymentFactory()
            ->for($parentSubscription)
            ->for($microsoft365CustomerInfo)
            ->createOne();

        $microsoft365Deployment->loadMissing([
            'subscription.children.product',
            'subscription.product',
            'microsoft365CustomerInfo',
        ]);

        $data = new Microsoft365DeploymentResource()->toArray($microsoft365Deployment);

        self::assertSame($parentProduct->name, $data['product']);
        self::assertSame(0, $data['active_seats']);
        self::assertSame(0, $data['canceled_seats']);
    }
}
