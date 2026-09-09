<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Services;

use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Notification;
use RealtimeRegister\RealtimeRegister;
use ValueError;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Support\Enums\LoggingContextKeys;

class RtrNotificationsService
{
    public function __construct(
        private readonly RealtimeRegister $realtimeRegister,
        private readonly LoggerInterface $logger,
        private readonly string $rtrCustomer
    ) {
    }

    /**
     * @return array<Notification>
     */
    public function pollForNextNotifications(int $limit = 10): array
    {
        $rtrResult = $this->realtimeRegister->notifications->list(
            customer: $this->rtrCustomer,
            limit: $limit,
            parameters: [
                'acknowledgeDate:null' => '',
            ]
        );

        $notifications = [];
        foreach ($rtrResult->entities as $rtrNotification) {
            $rtrNotificationArray = $rtrNotification->toArray();

            try {
                $notifications[] = Notification::fromArray($rtrNotification->toArray());
            } catch (ValueError $exception) {
                $this->logger->warning(sprintf(
                    'Enum error when converting RTR notification to DTO (Event: %s, Notification: %s)',
                    $rtrNotificationArray['eventType'] ?? 'null',
                    $rtrNotificationArray['notificationType'] ?? 'null'
                ), [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                    LoggingContextKeys::META => ['notification' => $rtrNotificationArray],
                ]);
            }
        }
        return $notifications;
    }

    public function acknowledgeNotification(int $notificationId): void
    {
        $this->realtimeRegister->notifications->ack($this->rtrCustomer, $notificationId);
    }
}
