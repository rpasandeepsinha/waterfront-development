<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Actions;

use Waterfront\Domain\Email\Models\EmailHistory;
use Waterfront\Domain\Email\Repositories\EmailHistoryRepository;

class CleanEmailHistoryAction
{
    public function __construct(private readonly EmailHistoryRepository $emailHistoryRepository)
    {
    }

    public function execute(EmailHistory $emailHistory): void
    {
        $this->emailHistoryRepository->removePayloadFromEmailHistory($emailHistory);
    }
}
