<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;
use Waterfront\Infra\RtrClient\Services\RtrNotificationProcessor;
use Waterfront\Infra\RtrClient\Services\RtrNotificationsService;
use Waterfront\Support\Enums\LoggingContextKeys;

#[AsCommand(name: 'rtr:poll-notifications')]
#[Description('Poll for process notifications on the RTR API and dispatch an event for each one received.')]
class PollNotifications extends Command
{
    /**
     * Maximum amount of new notifications that can be polled in a single
     * execution of this command. This prevents running a single execution
     * to poll and acknowledge an unlimited amount of new notifications.
     */
    private const int POLL_LIMIT = 10;

    public function handle(
        RtrNotificationsService $notificationsService,
        RtrNotificationProcessor $notificationProcessor,
        LoggerInterface $logger,
    ): void {
        foreach ($notificationsService->pollForNextNotifications(self::POLL_LIMIT) as $rtrNotification) {
            try {
                $notificationProcessor->process($rtrNotification);
            } catch (Throwable $exception) { // @phpstan-ignore-line
                $logger->error('RTR notification processing failed', [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'rtr_notification_id' => $rtrNotification->id,
                        'event_type' => $rtrNotification->eventType,
                        'notification_type' => $rtrNotification->notificationType,
                        'process_id' => $rtrNotification->process,
                        'process_identifier' => $rtrNotification->processIdentifier,
                    ],
                ]);
            }

            $notificationsService->acknowledgeNotification($rtrNotification->id);
        }
    }
}
