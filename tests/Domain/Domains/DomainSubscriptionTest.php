<?php

declare(strict_types=1);

namespace Tests\Domain\Domains;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(DomainDeployment::class)]
class DomainSubscriptionTest extends IntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->generateTestData();
    }

    #[Test]
    public function domainSubscriptionCreate(): void
    {
        $subscription = Subscription::where('domain', 'ketchup.nl')->firstOrFail();
        self::assertDatabaseHas(
            'subscriptions',
            [
                'product_uuid' => $subscription->product->uuid,
                'domain' => 'ketchup.nl',
                'contract_period' => '24',
                'gross_price' => 1008,
                'net_price' => 585,
            ],
        );

        self::assertNotNull($subscription->domainDeployment?->id);
    }

    #[Test]
    public function domainSubscriptionCreateWithContactOwner(): void
    {
        $customer = new CustomerFactory()->createOne();
        $contact = new DomainContactFactory()->createOne(['customer_id' => $customer->id]);

        $domainSubscription = Subscription::where('domain', 'ketchup.nl')->first()?->domainDeployment;

        self::assertNotNull($domainSubscription);

        $domainSubscription->contact_owner_id = $contact->id;
        $domainSubscription->save();

        self::assertNotNull($domainSubscription->contact_owner_id);
    }

    private function generateTestData(): void
    {
        $customer = new CustomerFactory()->createOne();

        $group = new ProductGroupFactory()->createOne([
            'name' => 'extension',
            'slug' => 'extension',
        ]);

        $product = new ProductFactory()->createOne([
            'product_group_id' => $group->id,
            'name' => '.nl',
            'slug' => 'extension_nl',
        ]);
        $subscription = new SubscriptionFactory()->for($product)->createOne([
            'customer_id' => $customer->id,
            'domain' => 'ketchup.nl',
            'contract_period' => '24',
            'gross_price' => 1008,
            'net_price' => 585,
            'product_uuid' => $product->uuid,
        ]);

        new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne())->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);
    }
}
