<?php

declare(strict_types=1);

namespace Tests\Domain\Orders\Jobs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Orders\Jobs\BillOrderJob;
use Waterfront\Domain\Orders\Services\OrderBiller;

#[CoversClass(BillOrderJob::class)]
class BillOrderJobTest extends IntegrationTestCase
{
    #[Test]
    public function handleBillProcessedOrder(): void
    {
        $order = new OrderFactory()->for(new CustomerFactory())->createOne();
        $billOrderJob = new BillOrderJob($order);
        $orderBiller = self::createMock(OrderBiller::class);
        $orderBiller->expects(self::once())->method('bill')->with($order);
        $logger = self::resolve(LoggerInterface::class);

        $billOrderJob->handle(
            $logger,
            $orderBiller,
        );
    }
}
