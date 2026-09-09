<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Event;

use RealtimeRegister\Domain\Notification;
use Waterfront\Infra\RtrClient\Models\RtrResponseLog;

class NewNotificationReceived
{
    public function __construct(
        private readonly Notification $notification,
        private readonly RtrResponseLog $rtrResponseLog,
    ) {
    }

    public function getNotification(): Notification
    {
        return $this->notification;
    }

    public function getRtrResponseLog(): RtrResponseLog
    {
        return $this->rtrResponseLog;
    }
}
