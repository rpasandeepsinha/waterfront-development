<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Jobs;

use Waterfront\Domain\Transfers\Interfaces\ExecuteTransferInterface;
use Waterfront\Domain\Transfers\Models\Transfer;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class TransferSubscriptions extends AbstractQueueableJob
{
    public function __construct(
        private readonly Transfer $transfer,
    ) {
        parent::__construct();
    }

    public function handle(ExecuteTransferInterface $executeTransferService): void
    {
        $executeTransferService->execute($this->transfer);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::SUBSCRIPTIONS;
    }
}
