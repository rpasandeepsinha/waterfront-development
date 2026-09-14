<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Microsoft365;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\Microsoft365Controller;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;

#[CoversClass(Microsoft365Controller::class)]
class Microsoft365UpdatePrimaryDomainTest extends IntegrationTestCase
{
    private Customer $customer;

    private Dispatcher&MockObject $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $group = new ProductGroupFactory()->microsoft365()->createOne();

        $parentProduct = new ProductFactory()->createOne([
            'slug' => 'microsoft-business-standard-parent',
            'product_group_id' => $group->id,
        ]);

        new ProductFactory()->createOne([
            'slug' => 'microsoft-business-standard',
            'product_group_id' => $group->id,
        ]);

        $customerInfo = new Microsoft365CustomerInfoFactory()->createOne([
            'customer_id' => $this->customer->id,
            'tenant_name' => $this->customer->customer_number . '.onmicrosoft.com',
        ]);

        $parentSubscription = new SubscriptionFactory()->makeOne([
            'customer_id' => $this->customer->id,
        ]);

        $parentProduct->subscriptions()->save($parentSubscription);

        new Microsoft365DeploymentFactory()->createOne([
            'subscription_id' => $parentSubscription->id,
            'microsoft365_customer_info_id' => $customerInfo->id,
        ]);

        new SubscriptionFactory()->state([
            'customer_id' => $this->customer->id,
            'parent_subscription_id' => $parentSubscription->id,
            'product_uuid' => $parentProduct->uuid,
        ])->createMany(4);

        new SubscriptionFactory()->state([
            'customer_id' => $this->customer->id,
            'parent_subscription_id' => $parentSubscription->id,
            'product_uuid' => $parentProduct->uuid,
            'administrative_status' => AdministrativeStatus::CANCELED->value,
            'cancel_date' => new CarbonImmutable()->subDay(),
        ])->createMany(2);

        $this->dispatcher = self::createMock(Dispatcher::class);
        $this->app->bind(Dispatcher::class, fn (): Dispatcher => $this->dispatcher);
    }

    #[Test]
    public function updatePrimaryDomain(): void
    {
        $nlProduct = new ProductFactory()->nlDomain();
        $domainSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($nlProduct)
            ->createOne(['domain' => 'yourhosting.nl']);

        new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($domainSubscription)
            ->createOne();

        $this->dispatcher->expects(self::once())->method('dispatch');

        $this->actingAsCustomer($this->customer)
            ->post(
                $this->generateRoute('partners.microsoft365.microsoft-update-primary-domain'),
                ['subscription' => $domainSubscription->uuid],
            )
            ->assertOk();
    }
}
