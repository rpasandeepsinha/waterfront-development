<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Services;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;

#[CoversNothing]
class GetServiceTest extends IntegrationTestCase
{
    #[Test]
    public function customerHasTheCorrectAvailableActions(): void
    {
        $customer = new CustomerFactory()->createOne();

        ProviderFactory::new()->domainOpenProvider()->createOne();

        $extensionProduct = new ProductFactory()
            ->nlDomain()
            ->for(new ProductGroupFactory()->extension()->createOne())
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($extensionProduct)
            ->prolongation()
            ->createMany([
                [
                    'billing_period' => 24,
                    'contract_period' => 24,
                    'price' => 1008,
                ],
                [
                    'billing_period' => 12,
                    'contract_period' => 12,
                    'price' => 1008,
                ],
            ]);

        $extensionPrice = ProductPriceComponent::where('product_id', $extensionProduct->id)->firstOrFail();

        $subscription = new SubscriptionFactory()
            ->for($extensionProduct)
            ->for($customer)
            ->createOne([
                'gross_price' => $extensionPrice->price,
                'net_price' => $extensionPrice->price,
                'technical_status' => DomainStatus::ACTIVE->value,
                'contract_period' => 12,
                'billing_period' => 12,
            ]);

        $response = $this->actingAsCustomer($customer)
            ->getJson($this->generateRoute('partners.subscriptions.show', ['subscription' => $subscription->uuid]))
            ->assertOk();

        self::assertSame(
            [
                'view',
                'cancel',
                'manageDomain',
                'canChangeContract',
                'suspendSubscription',
                'extendContract',
            ],
            $response->json('data.available_actions'),
        );
    }
}
