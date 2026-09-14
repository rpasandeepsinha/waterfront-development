<?php

declare(strict_types=1);

namespace Waterfront\Domain\MailManagement\Jobs;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\MailManagement\Exceptions\MailOnlyException;
use Waterfront\Domain\MailManagement\Services\MailManagementService;
use Waterfront\Domain\Servers\Exceptions\ServerNotFoundException;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Interfaces\Events\MailableEventInterface;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class TerminateMailOnlyHosting extends AbstractQueueableJob
{
    public int $tries = 5;

    /** @var array<int> */
    public array $backoff = [60, 2 * 60, 10 * 60, 30 * 60, 60 * 60];

    public function __construct(
        private readonly MailableEventInterface $event,
    ) {
        parent::__construct();
    }

    public function handle(MailManagementService $mailOnlyService, LoggerInterface $logger): void
    {
        try {
            $mailOnlyService->terminate($this->event->getSubscription());
        } catch (MailOnlyException|ServerNotFoundException|ModelNotFoundException|InvalidArgumentException $exception) {
            $logger->error(
                'Error while terminating the mailOnly subscription',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->event->getSubscription()->uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->event->getSubscription()->technical_status = TechnicalStatus::DELETING_FAILED->value;
        $this->event->getSubscription()->save();
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::TERMINATE_HOSTING;
    }
}
