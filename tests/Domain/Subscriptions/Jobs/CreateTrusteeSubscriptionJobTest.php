<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\Factories\ProductAddonCouplingFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Subscriptions\Jobs\CreateTrusteeSubscriptionJob;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(CreateTrusteeSubscriptionJob::class)]
class CreateTrusteeSubscriptionJobTest extends IntegrationTestCase
{
    #[Test]
    public function handleCreatesSubscriptionAndInvoice(): void
    {
        $domainSubscription = DomainSubscriptionDataProvider::subscription();
        $trusteeProduct = new ProductFactory()->for(new ProductGroupFactory()->addon())->createOne();
        new ProductPriceComponentFactory()->for($trusteeProduct)->registration()->createOne();

        new ProductAddonCouplingFactory()
            ->createOne([
                'parent_product_id' => $domainSubscription->product->id,
                'addon_product_id' => $trusteeProduct->id,
            ]);

        $job = new CreateTrusteeSubscriptionJob(
            $domainSubscription,
            $trusteeProduct,
        );

        self::resolve(Dispatcher::class)->dispatch($job);

        self::assertDatabaseHas(Invoice::class, [
            'product_id' => $trusteeProduct->id,
        ]);

        self::assertDatabaseHas(Subscription::class, [
            'product_uuid' => $trusteeProduct->uuid,
            'domain' => $domainSubscription->domain,
            'parent_subscription_id' => $domainSubscription->id,
        ]);
    }
}
