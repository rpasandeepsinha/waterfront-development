<?php

declare(strict_types=1);

namespace Tests\Domain\Experiments\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ExperimentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Services\CustomerExperimentService;
use Waterfront\Domain\Experiment\Enums\ExperimentType;

#[CoversClass(CustomerExperimentService::class)]
class CustomerExperimentServiceTest extends IntegrationTestCase
{
    #[Test]
    public function customerParticipatesInPricingLadderExperiment(): void
    {
        $service = self::resolve(CustomerExperimentService::class);

        $customer = new CustomerFactory()->createOne();
        $experiment = new ExperimentFactory()->createOne(['slug' => ExperimentType::PRICING_LADDER]);
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        $subscription = new SubscriptionFactory()->for($customer)->for($product)->createOne();

        $experiment->products()->save($product);
        $experiment->subscriptions()->save($subscription);

        $experiments = $service->participatesInExperiments($customer);

        self::assertArraysAreEqualIgnoringOrder([ExperimentType::PRICING_LADDER], $experiments);
    }
}
