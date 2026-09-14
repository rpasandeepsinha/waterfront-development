<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Subscriptions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\SubscriptionController;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

#[CoversClass(SubscriptionController::class)]
class SubscriptionIndexOverviewTest extends IntegrationTestCase
{
    #[Test]
    public function indexOverview(): void
    {
        $domain = 'test.nl';
        $customer = new CustomerFactory()->createOne();

        $product = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();
        new SubscriptionFactory()
            ->for($customer)
            ->for($product)
            ->administrativeStatusActive()
            ->createOne(['domain' => $domain, 'technical_status' => TechnicalStatus::OK->value]);

        $this->actingAsCustomer($customer)
            ->getJson(
                $this->generateRoute('partners.subscriptions.index.overview'),
            )
            ->assertOk()
            ->assertJsonFragment(['domain' => $domain, 'product_slug' => $product->slug]);
    }
}
