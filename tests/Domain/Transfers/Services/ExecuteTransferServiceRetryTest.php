<?php

declare(strict_types=1);

namespace Tests\Domain\Transfers\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\DataProvider\HostingSubscriptionDataProvider;
use Tests\Factories\CustomerFactory;
use Tests\Factories\TransferFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Transfers\Models\Transfer;
use Waterfront\Domain\Transfers\Services\ExecuteTransferService;

#[CoversClass(ExecuteTransferService::class)]
class ExecuteTransferServiceRetryTest extends IntegrationTestCase
{
    #[Test]
    public function retryExecuteShouldBeIdempotent(): void
    {
        // Model observers .........
        Transfer::flushEventListeners();

        $fromCustomer = new CustomerFactory()->withAddress()->createOne();
        $toCustomer = new CustomerFactory()->withAddress()->createOne();

        $domainSubscription = DomainSubscriptionDataProvider::subscription(customer: $fromCustomer);
        $hostingSubscription = HostingSubscriptionDataProvider::administrativeSubscription(customer: $toCustomer);

        $transfer = new TransferFactory()->createOne([
            'from_customer_id' => $fromCustomer->id,
            'to_customer_id' => $toCustomer->id,
            'accepted_at' => CarbonImmutable::now(),
            'uuid' => Uuid::uuid4()->toString(),
        ]);

        // Mark the hosting subscription as executed
        $transfer->subscriptions()->attach($hostingSubscription);
        $transfer->subscriptions->firstOrFail()->pivot->executed_at = CarbonImmutable::now();
        $transfer->subscriptions->firstOrFail()->pivot->save();

        $transfer->subscriptions()->attach($domainSubscription);
        $transfer->refresh();

        $service = self::resolve(ExecuteTransferService::class);
        $service->execute($transfer);

        $domainSubscription->refresh();
        $hostingSubscription->refresh();

        self::assertSame($fromCustomer->id, $domainSubscription->customer->id);
        self::assertSame($toCustomer->id, $hostingSubscription->customer->id);
    }
}
