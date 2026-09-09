<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Listeners;

use Waterfront\Infra\RtrClient\Event\NewNotificationReceived;
use Waterfront\Infra\RtrClient\Helpers\NotificationHelper;
use Waterfront\Infra\RtrClient\Services\NotificationHandlers\DomainNotificationHandler;

class UpdateDomainNotificationListener
{
    public function __construct(
        private readonly DomainNotificationHandler $domainNotificationHandler,
        private readonly NotificationHelper $notificationHelper,
    ) {
    }

    public function handle(NewNotificationReceived $newNotificationEvent): void
    {
        if (! $this->notificationHelper->isUpdateDomainNotification($newNotificationEvent->getNotification())) {
            return;
        }

        $this->domainNotificationHandler->handle(
            $newNotificationEvent->getNotification(),
            $newNotificationEvent->getRtrResponseLog(),
        );
    }
}
