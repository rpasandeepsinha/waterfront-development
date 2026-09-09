<?php

declare(strict_types=1);

namespace Waterfront\Domain\Admin\Actions;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Email\Repositories\EmailHistoryRepository;

class AnonymizeEmailHistoryForReceiverUuidAction
{
    public function __construct(
        private readonly EmailHistoryRepository $emailHistoryRepository,
    ) {
    }

    public function execute(UuidInterface $uuid): void
    {
        foreach ($this->emailHistoryRepository->getByReceiverUuid($uuid) as $emailHistory) {
            $this->emailHistoryRepository->removeReceiverEmailAndPayload($emailHistory);
        }
    }
}
