<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Notification;
use Throwable;
use Waterfront\Infra\RtrClient\Event\NewNotificationReceived;
use Waterfront\Infra\RtrClient\Repositories\RtrResponseLogRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

class RtrNotificationProcessor
{
    public function __construct(
        private readonly RtrResponseLogRepository $rtrResponseLogRepository,
        private readonly RtrResponseLogService $rtrResponseLogService,
        private readonly Dispatcher $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function process(Notification $notification): void
    {
        if ($this->rtrResponseLogRepository->getProcessedNotificationLog($notification->id) !== null) {
            $this->logger->info('Skipping processed RTR notification', [
                LoggingContextKeys::META => [
                    'rtr_notification_id' => $notification->id,
                ],
            ]);

            return;
        }

        $this->logger->info('Dispatching RTR notification', [
            LoggingContextKeys::META => [
                'rtr_notification_id' => $notification->id,
                'event_type' => $notification->eventType,
                'notification_type' => $notification->notificationType,
            ],
        ]);

        $rtrResponseLog = $this->rtrResponseLogService->logNotification($notification);

        try {
            $this->eventDispatcher->dispatch(
                new NewNotificationReceived($notification, $rtrResponseLog)
            );

            $rtrResponseLog->processed_at = CarbonImmutable::now();
            $rtrResponseLog->save();
        } catch (Throwable $exception) {
            $rtrResponseLog->failed_at = CarbonImmutable::now();
            $rtrResponseLog->save();

            $this->logger->critical('Dispatching RTR notification failed', [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => [
                    'rtr_notification_id' => $notification->id,
                    'event_type' => $notification->eventType,
                    'notification_type' => $notification->notificationType,
                ],
            ]);

            throw $exception;
        }
    }
}
