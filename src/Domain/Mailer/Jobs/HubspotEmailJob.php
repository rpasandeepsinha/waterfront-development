<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer\Jobs;

use Illuminate\Support\Sleep;
use Waterfront\Domain\Mailer\HubspotMailer;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotThrottledException;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class HubspotEmailJob extends AbstractQueueableJob
{
    private const int RATE_LIMIT_DELAY = 120;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(private readonly int $emailHistoryId)
    {
        parent::__construct();
    }

    public function handle(HubspotMailer $hubspotMailer): void
    {
        try {
            $hubspotMailer->send($this->emailHistoryId);
        } catch (HubspotThrottledException) {
            // Hubspot API rate limit exceeded, block the (single) worker for this queue
            // for a while so we don't trigger the same ratelimit error when picking up a new job
            Sleep::sleep(5);

            $this->release(self::RATE_LIMIT_DELAY);
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::CRM;
    }
}
