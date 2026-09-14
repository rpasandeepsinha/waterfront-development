<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Listeners;

use JsonException;
use Waterfront\Infra\RtrClient\Event\NewNotificationReceived;
use Waterfront\Infra\RtrClient\Helpers\NotificationHelper;
use Waterfront\Infra\RtrClient\Services\NotificationHandlers\CertificateNotificationHandler;

class CertificateRequestNotificationListener
{
    public function __construct(
        private readonly CertificateNotificationHandler $certificateNotificationHandler,
        private readonly NotificationHelper $notificationHelper,
    ) {
    }

    /**
     * @throws JsonException
     */
    public function handle(NewNotificationReceived $newNotificationEvent): void
    {
        if (! $this->notificationHelper->isCertificateRequestNotification($newNotificationEvent->getNotification())) {
            return;
        }

        $this->certificateNotificationHandler->handle(
            $newNotificationEvent->getNotification(),
        );
    }
}
