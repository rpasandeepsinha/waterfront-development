<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Services\NotificationHandlers;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Notification;
use Waterfront\Infra\RtrClient\Job\UpdateValidatedDomainStatusJob;
use Waterfront\Infra\RtrClient\Models\RtrResponseLog;
use Waterfront\Support\Enums\LoggingContextKeys;

class ValidateContactNotificationHandler
{
    public function __construct(
        private readonly Dispatcher $busDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Notification $notification, RtrResponseLog $rtrResponseLog): void
    {
        $includedDomains = Arr::get($notification->toArray(), 'payload.includedDomains');

        if (! is_array($includedDomains) || $includedDomains === []) {
            $this->logger->info('RTR contact validation skipped: included domains missing', [
                LoggingContextKeys::META => [
                    'rtr_notification_id' => $notification->id,
                ],
            ]);

            return;
        }

        foreach ($includedDomains as $domainName) {
            if (! is_string($domainName) || $domainName === '') {
                continue;
            }

            $this->busDispatcher->dispatch(new UpdateValidatedDomainStatusJob(
                domainName: $domainName,
                message: $notification->message,
                rtrResponseLog: $rtrResponseLog,
            ));
        }
    }
}
