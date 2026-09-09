<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\DomainTransferStatus;
use Throwable;
use Waterfront\Apps\OneOffScripts\Domain\NovaCancelMissedRtrTransferAwaySubscriptionsAction;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Actions\CancelSubscriptionsAction;
use Waterfront\Domain\Subscriptions\DTO\Cancellation;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Exceptions\CancelCreditSubscriptionsException;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Transfers\Enums\TransferStatus;
use Waterfront\Infra\RtrClient\Enums\TransferTypeType;
use Waterfront\Infra\RtrClient\Repositories\RtrResponseLogRepository;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class MissedRtrTransferAwayCancellationService
{
    public function __construct(
        private readonly RtrService $rtrService,
        private readonly CancelSubscriptionsAction $cancelSubscriptionsAction,
        private readonly RtrResponseLogRepository $rtrResponseLogRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(
        CarbonImmutable $startDate,
        bool $dryRun,
    ): int {
        $processed = 0;

        foreach ($this->rtrResponseLogRepository->getTransferDomainNotificationLogsFrom(startDate: $startDate) as $rtrLog) {
            $notification = json_decode($rtrLog->response, true, 512, JSON_THROW_ON_ERROR);
            Assert::isMap($notification);

            $domainName = $this->getDomainName(notification: $notification);
            $processId = $this->getProcessId(notification: $notification);

            if ($domainName === null) {
                $this->logger->warning(
                    'Skipping missed RTR transfer-away notification because domain name is missing.',
                    [
                        LoggingContextKeys::ONE_OFF_SCRIPT => NovaCancelMissedRtrTransferAwaySubscriptionsAction::SLUG,
                        LoggingContextKeys::META => [
                            'dry_run' => $dryRun,
                            'rtr_response_log_id' => $rtrLog->id,
                            'notification_id' => $notification['id'] ?? null,
                        ],
                    ],
                );

                continue;
            }

            if ($processId === null) {
                $this->logger->warning(
                    'Skipping missed RTR transfer-away notification because process id is missing.',
                    [
                        LoggingContextKeys::ONE_OFF_SCRIPT => NovaCancelMissedRtrTransferAwaySubscriptionsAction::SLUG,
                        LoggingContextKeys::DOMAIN_NAME => $domainName,
                        LoggingContextKeys::META => [
                            'dry_run' => $dryRun,
                            'rtr_response_log_id' => $rtrLog->id,
                            'notification_id' => $notification['id'] ?? null,
                        ],
                    ],
                );

                continue;
            }

            try {
                $subscription = $this->subscriptionRepository->findByDomainAndType(
                    domain: $domainName,
                    productGroupType: ProductGroupType::EXTENSION,
                );
            } catch (ModelNotFoundException) {
                $this->logger->error(
                    'Skipping missed RTR transfer-away notification because no active domain subscription was found.',
                    [
                        LoggingContextKeys::ONE_OFF_SCRIPT => NovaCancelMissedRtrTransferAwaySubscriptionsAction::SLUG,
                        LoggingContextKeys::DOMAIN_NAME => $domainName,
                        LoggingContextKeys::META => [
                            'dry_run' => $dryRun,
                        ],
                    ],
                );

                continue;
            }

            try {
                $transferStatus = $this->rtrService->transferInfo(
                    domain: $domainName,
                    processId: $processId,
                );
            } catch (Throwable $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
                $this->logger->error(
                    'Skipping missed RTR transfer-away notification because confirming transfer with RTR failed.',
                    [
                        LoggingContextKeys::ONE_OFF_SCRIPT => NovaCancelMissedRtrTransferAwaySubscriptionsAction::SLUG,
                        LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                        LoggingContextKeys::DOMAIN_NAME => $domainName,
                        LoggingContextKeys::EXCEPTION => $exception,
                        LoggingContextKeys::META => [
                            'dry_run' => $dryRun,
                        ],
                    ],
                );

                continue;
            }

            if (! $this->isCompletedOutgoingTransfer(transferStatus: $transferStatus)) {
                $this->logger->info(
                    'Skipping missed RTR transfer-away notification because transfer is not completed.',
                    [
                        LoggingContextKeys::ONE_OFF_SCRIPT => NovaCancelMissedRtrTransferAwaySubscriptionsAction::SLUG,
                        LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                        LoggingContextKeys::DOMAIN_NAME => $domainName,
                        LoggingContextKeys::META => [
                            'dry_run' => $dryRun,
                        ],
                    ],
                );

                continue;
            }

            if ($dryRun) {
                $this->logger->info(
                    'Dry run found missed RTR transfer-away subscription.',
                    [
                        LoggingContextKeys::ONE_OFF_SCRIPT => NovaCancelMissedRtrTransferAwaySubscriptionsAction::SLUG,
                        LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                        LoggingContextKeys::DOMAIN_NAME => $domainName,
                        LoggingContextKeys::META => [
                            'dry_run' => $dryRun,
                        ],
                    ],
                );

                $processed++;
                continue;
            }

            Assert::notNull(
                $rtrLog->created_at,
                'RTR response log must have a created_at timestamp.'
            );
            $subscriptionEndDate = CarbonImmutable::instance($rtrLog->created_at);

            try {
                $this->cancelSubscriptionsAction->execute(
                    cancellation: new Cancellation(
                        subscriptions: new Collection([$subscription]),
                        cancelReason: SubscriptionCancelReason::REASON_DOMAIN_TRANSFERRED_AWAY,
                        cancelReasonOther: null,
                        cancelType: SubscriptionCancelType::CANCEL_OTHER,
                        selectedCancelEndDate: $subscriptionEndDate,
                        creditRelatedInvoices: false,
                    )
                );
            } catch (CancelCreditSubscriptionsException $exception) {
                $this->logger->error(
                    'Failed to cancel missed RTR transfer-away subscription.',
                    [
                        LoggingContextKeys::ONE_OFF_SCRIPT => NovaCancelMissedRtrTransferAwaySubscriptionsAction::SLUG,
                        LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                        LoggingContextKeys::DOMAIN_NAME => $domainName,
                        LoggingContextKeys::EXCEPTION => $exception,
                        LoggingContextKeys::META => [
                            'dry_run' => $dryRun,
                        ],
                    ],
                );

                continue;
            }

            $this->logger->info(
                'Cancelled missed RTR transfer-away subscription.',
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaCancelMissedRtrTransferAwaySubscriptionsAction::SLUG,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $domainName,
                    LoggingContextKeys::META => [
                        'dry_run' => $dryRun,
                    ],
                ],
            );

            $processed++;
        }

        return $processed;
    }

    /**
     * @param array<string, mixed> $notification
     */
    private function getDomainName(array $notification): ?string
    {
        $domainName = $notification['domainName']
            ?? data_get($notification, 'payload.domainName')
            ?? $notification['processIdentifier']
            ?? null;

        return is_string($domainName) && $domainName !== '' ? $domainName : null;
    }

    /**
     * @param array<string, mixed> $notification
     */
    private function getProcessId(array $notification): ?string
    {
        $processId = $notification['process'] ?? null;

        return is_int($processId) ? (string) $processId : null;
    }

    private function isCompletedOutgoingTransfer(DomainTransferStatus $transferStatus): bool
    {
        return in_array($transferStatus->type, [
            TransferTypeType::OUT->value,
            TransferTypeType::OUT_INTERNAL->value,
        ], true)
            && $transferStatus->status === TransferStatus::COMPLETED->value;
    }
}
