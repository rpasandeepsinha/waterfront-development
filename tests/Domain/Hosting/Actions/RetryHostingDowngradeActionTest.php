<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\ItemNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionChangeFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\SubscriptionMutationFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Actions\RetryHostingDowngradeAction;
use Waterfront\Domain\Hosting\DTO\DowngradeCheckResult;
use Waterfront\Domain\Hosting\Services\HostingDowngradePossibilityChecker;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Webmozart\Assert\InvalidArgumentException;

#[CoversClass(RetryHostingDowngradeAction::class)]
class RetryHostingDowngradeActionTest extends IntegrationTestCase
{
    #[Test]
    public function throwsExceptionWhenNoOpenMutationExists(): void
    {
        $subscription = $this->makeSubscriptionWithHostingDeployment();

        self::assertSame(
            0,
            SubscriptionMutation::query()
                ->where('subscription_id', $subscription->id)
                ->whereNull('mutated_at')
                ->count()
        );

        $action = self::resolve(RetryHostingDowngradeAction::class);

        $this->expectException(ItemNotFoundException::class);
        $action->execute($subscription);
    }

    #[Test]
    public function throwsWhenSubscriptionHasNoHostingDeployment(): void
    {
        $product = ProductFactory::new()
            ->for(ProductGroupFactory::new()->createOne())
            ->createOne();

        $subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for($product)
            ->createOne();

        $action = self::resolve(RetryHostingDowngradeAction::class);

        $this->expectException(InvalidArgumentException::class);

        $action->execute($subscription);
    }

    #[Test]
    public function returnsTrueWhenOpenMutationExistsAndDowngradeSucceeds(): void
    {
        $this->mock(HostingDowngradePossibilityChecker::class)
            ->shouldReceive('canDowngradeToServicePlan')  // Changed from 'check'
            ->once()
            ->andReturn(new DowngradeCheckResult(true, null));

        $subscription = $this->makeSubscriptionWithHostingDeployment();
        $hostingProductGroup = $subscription->product->productGroup;

        $downgradeProduct = new ProductFactory()
        ->for($hostingProductGroup)
        ->createOne([
            'name' => 'basic',
            'slug' => 'basic',
        ]);

        SubscriptionChangeFactory::new()->for($subscription)->createOne([
            'from_product_uuid' => $subscription->product->uuid,
            'to_product_uuid' => $downgradeProduct->uuid,
            'status' => SubscriptionChangeStatus::EXECUTION_FAILED,
            'type' => ProductChangeType::DOWNGRADE,
            'requested_at' => CarbonImmutable::now(),
            'completed_at' => null,
        ]);

        SubscriptionMutationFactory::new()->createOne([
            'subscription_id' => $subscription->id,
            'product_id' => $downgradeProduct->id,
            'mutated_at' => null,
        ]);

        $action = self::resolve(RetryHostingDowngradeAction::class);

        $result = $action->execute($subscription);

        self::assertTrue($result);
    }

    private function makeSubscriptionWithHostingDeployment(): Subscription
    {
        $customer = new CustomerFactory()->createOne();

        $hostingProductGroup = new ProductGroupFactory()->createOne([
        'name' => ProductGroupType::HOSTING,
        'slug' => ProductGroupType::HOSTING,
    ]);

        $product = new ProductFactory()
            ->for($hostingProductGroup)
            ->createOne([
                'name' => 'premium',
                'slug' => 'premium',
            ]);

        $provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::PLESK,
            'enabled' => true,
            'default' => true,
        ]);
        return new SubscriptionFactory()
            ->for($customer)
            ->for($product)
            ->has(
                new HostingDeploymentFactory()
                    ->for($provider, 'provider')
                    ->for(new ServerFactory())
            )
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'domain' => 'testdomein.com',
                'product_uuid' => $product->uuid,
                'contract_period' => 12,
            ]);
    }
}
