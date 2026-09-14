<?php

declare(strict_types=1);

namespace Waterfront\Domain\MailManagement\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Waterfront\Domain\MailManagement\Services\MailManagementService;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Interfaces\Events\MailableEventInterface;

class HostingMailOnlyCreationListener implements ShouldQueue
{
    public string $queue = QueueName::HOSTING->value;

    public function __construct(
        private readonly MailManagementService $mailService,
    ) {
    }

    public function handle(MailableEventInterface $event): void
    {
        if (! $event->getSubscription()->product->isSitebuilderProduct()) {
            Log::warning(sprintf(
                'Subscription for hosting with ID: {%s} DOMAIN {%s} was already in a deployed state. Skipping...',
                $event->getSubscription()->id,
                $event->getSubscription()->domain,
            ));

            return;
        }

        $this->mailService->createDomain(
            $event->getSubscription(),
            $event->getContactEmail(),
        );
    }
}
