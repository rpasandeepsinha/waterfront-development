<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TransferFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\ProductTransferController;

#[CoversClass(ProductTransferController::class)]
class ProductTransferControllerTest extends IntegrationTestCase
{
    #[Test]
    public function showProductTransfers(): void
    {
        $domain = 'test.nl';

        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory()->createOne())
            ->for(new ProductFactory()->hostingBrons()->createOne())
            ->createOne([
                'domain' => $domain,
            ]);

        $transfer = new TransferFactory()->createOne([
            'from_customer_id' => $subscription->customer->id,
            'to_customer_id' => new CustomerFactory()->createOne()->id,
        ]);

        $subscription->transfers()->save($transfer);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.subscriptions.subscription.product.transfers', ['subscription' => $subscription->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function retryTransfer(): void
    {
        $fromCustomer = new CustomerFactory()->createOne();
        $toCustomer = new CustomerFactory()->createOne();
        $transfer = new TransferFactory()->createOne([
            'from_customer_id' => $fromCustomer->id,
            'to_customer_id' => $toCustomer->id,
            'accepted_at' => CarbonImmutable::now(),
        ]);
        $transfer->refresh();

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.transfer.retry', ['productTransfer' => $transfer->uuid]))
            ->assertNoContent();
    }

    #[Test]
    public function retryTransferFailsWhenNotAccepted(): void
    {
        $fromCustomer = new CustomerFactory()->createOne();
        $toCustomer = new CustomerFactory()->createOne();
        $transfer = new TransferFactory()->createOne([
            'from_customer_id' => $fromCustomer->id,
            'to_customer_id' => $toCustomer->id,
        ]);
        $transfer->refresh();

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.transfer.retry', ['productTransfer' => $transfer->uuid]))
            ->assertUnprocessable();
    }
}
