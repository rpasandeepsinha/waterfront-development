<?php

declare(strict_types=1);

namespace Waterfront\Domain\Transfers\Observers;

use Waterfront\Domain\Transfers\Models\Transfer;
use Waterfront\Domain\Transfers\Services\TransferService;

class TransferObserver
{
    public function __construct(
        private readonly TransferService $transferService
    ) {
    }

    public function updated(Transfer $transfer): void
    {
        if ($transfer->isDirty(['canceled_at']) ||
            $transfer->isDirty(['accepted_at']) ||
            $transfer->isDirty(['rejected_at']) ||
            $transfer->isDirty(['completed_at']) ||
            $transfer->isDirty(['started_at'])
        ) {
            $this->transferService->sendNotificationMail($transfer);
        }
    }
}
