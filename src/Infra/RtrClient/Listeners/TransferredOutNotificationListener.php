<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Listeners;

use JsonException;
use Waterfront\Infra\RtrClient\Event\NewNotificationReceived;
use Waterfront\Infra\RtrClient\Helpers\NotificationHelper;
use Waterfront\Infra\RtrClient\Services\NotificationHandlers\TransferDomainNotificationHandler;

class TransferredOutNotificationListener
{
    public function __construct(
        private readonly TransferDomainNotificationHandler $transferDomainNotificationHandler,
        private readonly NotificationHelper $notificationHelper,
    ) {
    }

    /**
     * @throws JsonException
     */
    public function handle(NewNotificationReceived $newNotificationEvent): void
    {
        if (! $this->notificationHelper->isTransferredDomainNotification($newNotificationEvent->getNotification())) {
            return;
        }

        $this->transferDomainNotificationHandler->handle(
            $newNotificationEvent->getNotification()
        );
    }
}
