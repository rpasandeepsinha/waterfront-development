<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Helpers;

use Illuminate\Support\Arr;
use RealtimeRegister\Domain\Notification;
use Waterfront\Infra\RtrClient\Enums\EventType;
use Waterfront\Infra\RtrClient\Enums\NotificationType;

class NotificationHelper
{
    public function isCertificateRequestNotification(Notification $notification): bool
    {
        return $notification->notificationType === NotificationType::SSLCertificateNotification->value
            && $notification->eventType === EventType::RequestCertificateEvent->value;
    }

    public function isCreateDomainNotification(Notification $notification): bool
    {
        return $notification->notificationType === NotificationType::CreateDomainNotification->value
            && $notification->eventType === EventType::CreateDomainEvent->value;
    }

    public function isDeleteDomainNotification(Notification $notification): bool
    {
        return $notification->notificationType === NotificationType::DeleteDomainNotification->value
            && $notification->eventType === EventType::DeleteDomainEvent->value;
    }

    public function isRenewDomainNotification(Notification $notification): bool
    {
        return $notification->notificationType === NotificationType::RenewDomainNotification->value
            && $notification->eventType === EventType::RenewDomainEvent->value;
    }

    public function isUpdateDomainNotification(Notification $notification): bool
    {
        return $notification->notificationType === NotificationType::UpdateDomainNotification->value
            && $notification->eventType === EventType::UpdateDomainEvent->value;
    }

    public function isTransferredDomainNotification(Notification $notification): bool
    {
        return $notification->notificationType === NotificationType::TransferDomainNotification->value
            && $notification->eventType === EventType::TransferDomainEvent->value;
    }

    public function isValidateContactNotification(Notification $notification): bool
    {
        return $notification->notificationType === NotificationType::NOTIFICATION->value
            && $notification->eventType === EventType::VALIDATE_CONTACT_EVENT->value;
    }

    public function extractDomainName(Notification $notification): ?string
    {
        $domainName = $notification->domainName ?? null;
        if (is_string($domainName) && $domainName !== '') {
            return $domainName;
        }

        $payload = $notification->payload ?? null;
        $domainNameFromPayload = is_array($payload) ? Arr::get($payload, 'domainName') : null;

        return (is_string($domainNameFromPayload) && $domainNameFromPayload !== '') ? $domainNameFromPayload : null;
    }
}
