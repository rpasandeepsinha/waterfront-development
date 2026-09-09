<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Services\NotificationHandlers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Notification;
use Waterfront\Domain\Domains\Services\DomainProviderHistory;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionTerminateService;
use Waterfront\Domain\Transfers\Enums\TransferStatus;
use Waterfront\Infra\RtrClient\Action\ParseRtrTransferStatusToWfStatusAction;
use Waterfront\Infra\RtrClient\Enums\TransferTypeType;
use Waterfront\Infra\RtrClient\Exceptions\InvalidTransferDomainNotificationException;
use Waterfront\Infra\RtrClient\Helpers\NotificationHelper;
use Waterfront\Infra\RtrClient\Services\RtrResponseLogService;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Support\Enums\LoggingContextKeys;

class TransferDomainNotificationHandler
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly SubscriptionTerminateService $subscriptionTerminateService,
        private readonly DomainProviderHistory $domainProviderHistory,
        private readonly RtrService $rtrService,
        private readonly RtrResponseLogService $rtrResponseLogPersister,
        private readonly ParseRtrTransferStatusToWfStatusAction $parseRtrTransferStatusToWfStatusAction,
        private readonly LoggerInterface $logger,
        private readonly NotificationHelper $notificationHelper,
    ) {
    }

    /**
     * @throws JsonException
     * @throws InvalidTransferDomainNotificationException
     */
    public function handle(Notification $notification): void
    {
        if (! $this->notificationHelper->isTransferredDomainNotification($notification)) {
            throw new InvalidArgumentException(
                'Cannot handle notification because it is not a domain request notification.'
            );
        }

        $payload = $notification->payload ?? null;
        $domainName = $this->notificationHelper->extractDomainName($notification);
        $transferType = is_array($payload) ? Arr::get($payload, 'transferType') : null;
        $transferType ??= $notification->transferType ?? null;
        if (! is_string($domainName) || $transferType === null) {
            throw new InvalidTransferDomainNotificationException(sprintf(
                'TransferDomainNotificationHandler cannot process notification %s: for domain %s.',
                $notification->id,
                $domainName,
            ));
        }

        $transferStatus = $this->rtrService->transferInfo($domainName, (string) $notification->process);

        $responseLog = $this->rtrResponseLogPersister->logApiResponse(json_encode($transferStatus, JSON_THROW_ON_ERROR));

        try {
            $this->domainProviderHistory->saveHistory(
                $responseLog,
                ProviderSlug::REALTIME_REGISTER,
                $domainName,
                $transferStatus->status,
                $notification->message
            );
        } catch (ModelNotFoundException $exception) {
            $this->logger->warning('Unable to find domain subscription for domain {domain.name}', [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::DOMAIN_NAME => $domainName,
            ]);
            return;
        }

        if (
            ($transferType === TransferTypeType::OUT->value || $transferType === TransferTypeType::OUT_INTERNAL->value)
            && $transferStatus->status === TransferStatus::COMPLETED->value
        ) {
            $this->subscriptionTerminateService->endTransferredSubscription($domainName);
        } elseif ($transferType === TransferTypeType::IN->value || $transferType === TransferTypeType::IN_INTERNAL->value) {
            $status = $this->parseRtrTransferStatusToWfStatusAction->execute($transferStatus->status);
            $this->subscriptionService->updateSubscriptionStatus($domainName, $status, $notification->message);
        }
    }
}
