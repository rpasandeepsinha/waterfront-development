<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Jobs;

use Waterfront\Domain\Mailer\LegacyMailer;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class SendEmail extends AbstractQueueableJob
{
    public function __construct(
        public readonly int $emailHistoryId,
    ) {
        parent::__construct();
    }

    public function handle(
        LegacyMailer $mailer,
    ): void {
        $mailer->send($this->emailHistoryId);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::CRM;
    }
}
