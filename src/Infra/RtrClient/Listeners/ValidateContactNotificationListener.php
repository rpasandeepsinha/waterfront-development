<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Listeners;

use Waterfront\Infra\RtrClient\Event\NewNotificationReceived;
use Waterfront\Infra\RtrClient\Helpers\NotificationHelper;
use Waterfront\Infra\RtrClient\Services\NotificationHandlers\ValidateContactNotificationHandler;

class ValidateContactNotificationListener
{
    public function __construct(
        private readonly ValidateContactNotificationHandler $validateContactNotificationHandler,
        private readonly NotificationHelper $notificationHelper,
    ) {
    }

    public function handle(NewNotificationReceived $newNotificationEvent): void
    {
        if (! $this->notificationHelper->isValidateContactNotification($newNotificationEvent->getNotification())) {
            return;
        }

        $this->validateContactNotificationHandler->handle(
            $newNotificationEvent->getNotification(),
            $newNotificationEvent->getRtrResponseLog(),
        );
    }
}
