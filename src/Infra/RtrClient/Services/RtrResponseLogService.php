<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Services;

use JsonException;
use RealtimeRegister\Domain\Notification;
use Waterfront\Infra\RtrClient\Enums\RtrResponseSource;
use Waterfront\Infra\RtrClient\Models\RtrResponseLog;

class RtrResponseLogService
{
    /**
     * @throws JsonException
     */
    public function logNotification(Notification $notification): RtrResponseLog
    {
        $rtrResponseLog = new RtrResponseLog();
        $rtrResponseLog->source = RtrResponseSource::NOTIFICATION;
        $rtrResponseLog->response = json_encode($notification, JSON_THROW_ON_ERROR);
        $rtrResponseLog->rtr_notification_id = $notification->id;
        $rtrResponseLog->save();

        return $rtrResponseLog;
    }

    public function logApiResponse(string $response): RtrResponseLog
    {
        $rtrResponseLog = new RtrResponseLog();
        $rtrResponseLog->source = RtrResponseSource::API_CALL;
        $rtrResponseLog->response = $response;
        $rtrResponseLog->save();

        return $rtrResponseLog;
    }
}
