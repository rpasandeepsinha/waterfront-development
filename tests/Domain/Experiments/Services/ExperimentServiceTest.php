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
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Services\ExperimentService;
use Waterfront\Domain\Experiment\Enums\ExperimentType;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(ExperimentService::class)]
class ExperimentServiceTest extends IntegrationTestCase
{
    private ExperimentService $service;

    private Customer $customer;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = self::resolve(ExperimentService::class);

        $this->customer = new CustomerFactory()->createOne();
        $experiment = new ExperimentFactory()->createOne(['slug' => ExperimentType::PRICING_LADDER]);
        $product = new ProductFactory()->for(new ProductGroupFactory())->createOne();
        $this->subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne();

        $experiment->products()->save($product);
        $experiment->subscriptions()->save($this->subscription);
    }

    #[Test]
    public function customerParticipatesInPricingLadderExperiment(): void
    {
        $experiments = $this->service->customerParticipatesInExperiments($this->customer);

        self::assertArraysAreEqualIgnoringOrder([ExperimentType::PRICING_LADDER], $experiments);
    }

    #[Test]
    public function subscriptionParticipatesInPricingLadderExperiment(): void
    {
        $experiments = $this->service->subscriptionParticipatesInExperiments($this->subscription);

        self::assertArraysAreEqualIgnoringOrder([ExperimentType::PRICING_LADDER], $experiments);
    }
}
