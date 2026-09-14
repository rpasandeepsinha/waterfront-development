<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Jobs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\TransferFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Subscriptions\Jobs\TransferSubscriptions;
use Waterfront\Domain\Transfers\Interfaces\ExecuteTransferInterface;

#[CoversClass(TransferSubscriptions::class)]
class TransferSubscriptionsTest extends IntegrationTestCase
{
    #[Test]
    public function transferSubscriptionsJobIsCalled(): void
    {
        $transfer = new TransferFactory()->createOne([
            'from_customer_id' => new CustomerFactory()->createOne()->id,
            'to_customer_id' => new CustomerFactory()->createOne()->id,
        ]);

        $executeTransferService = self::createMock(ExecuteTransferInterface::class);

        $executeTransferService->expects(self::once())->method('execute')->with($transfer)->willReturn($transfer);

        $job = new TransferSubscriptions($transfer);
        $job->handle($executeTransferService);
    }
}
