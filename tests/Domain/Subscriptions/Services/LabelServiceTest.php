<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\LabelFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\LabelService;

#[CoversClass(LabelService::class)]
class LabelServiceTest extends IntegrationTestCase
{
    private LabelService $labelService;

    private Customer $customer;

    public function setUp(): void
    {
        parent::setUp();

        $this->labelService = self::resolve(LabelService::class);
        $this->customer = CustomerFactory::new()->createOne();
    }

    #[Test]
    public function createLabel(): void
    {
        $this->labelService->createLabels(['test-label'], $this->customer);

        self::assertDatabaseHas('labels', [
            'value' => 'test-label',
            'customer_id' => $this->customer->id,
        ]);
    }

    #[Test]
    public function getLabel(): void
    {
        $label = LabelFactory::new()->for($this->customer)->createOne();

        $labels = $this->labelService->getLabels($this->customer);

        self::assertTrue($labels->contains($label));
    }

    #[Test]
    public function deleteLabelWithAttachedSubscription(): void
    {
        $subscription = $this->createSubscription();
        $label = LabelFactory::new()->for($this->customer)->createOne();
        $label->subscriptions()->save($subscription);

        $this->labelService->deleteLabels([$label->value], $this->customer);

        self::assertModelMissing($label);
        self::assertEmpty($subscription->refresh()->labels);
    }

    #[Test]
    public function attachSubscriptionToLabel(): void
    {
        $subscription = $this->createSubscription();
        $label = LabelFactory::new()->for($this->customer)->createOne();

        $this->labelService->attachSubscriptions($label, [$subscription->id]);

        self::assertTrue($label->subscriptions->contains($subscription));
    }

    #[Test]
    public function detachSubscriptionFromLabel(): void
    {
        $subscription = $this->createSubscription();
        $label = LabelFactory::new()->for($this->customer)->createOne();
        $label->subscriptions()->save($subscription);

        $this->labelService->detachSubscriptions($label, [$subscription->id]);

        self::assertEmpty($label->subscriptions);
    }

    private function createSubscription(): Subscription
    {
        $productGroup = ProductGroupFactory::new()->hosting()->createOne();
        $product = ProductFactory::new()->for($productGroup)->createOne();

        return SubscriptionFactory::new()->for($this->customer)->for($product)->createOne();
    }
}
