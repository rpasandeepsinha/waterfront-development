<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Support\LazyCollection;
use Waterfront\Infra\RtrClient\Enums\EventType;
use Waterfront\Infra\RtrClient\Enums\NotificationType;
use Waterfront\Infra\RtrClient\Enums\RtrResponseSource;
use Waterfront\Infra\RtrClient\Models\RtrResponseLog;

class RtrResponseLogRepository
{
    private const int CHUNK_SIZE = 100;

    /**
     * @return LazyCollection<int, RtrResponseLog>
     */
    public function getTransferDomainNotificationLogsFrom(CarbonImmutable $startDate): LazyCollection
    {
        return RtrResponseLog::query()
            ->where('source', RtrResponseSource::NOTIFICATION)
            ->where('created_at', '>=', $startDate)
            ->where('response->eventType', EventType::TransferDomainEvent->value)
            ->where('response->notificationType', NotificationType::TransferDomainNotification->value)
            ->lazyById(self::CHUNK_SIZE);
    }

    public function getProcessedNotificationLog(int $rtrNotificationId): ?RtrResponseLog
    {
        return RtrResponseLog::query()
            ->where('source', RtrResponseSource::NOTIFICATION)
            ->where('rtr_notification_id', $rtrNotificationId)
            ->whereNotNull('processed_at')
            ->first();
    }
}
