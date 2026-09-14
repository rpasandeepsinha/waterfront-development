<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services\Message\Handler;

use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\DebtCollectionStatusUpdated;
use SandwaveIo\HarborMessages\Message\Enum\DebtCollectionStatus;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\CancellationService;
use Waterfront\Domain\Subscriptions\Services\SuspendSubscriptionService;
use Waterfront\Domain\Subscriptions\Services\UnsuspendSubscriptionService;
use Waterfront\Support\Enums\LoggingContextKeys;

class DebtCollectionStatusUpdatedHandler
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SuspendSubscriptionService $subscriptionSuspendAction,
        private readonly UnsuspendSubscriptionService $unsuspendSubscriptionAction,
        private readonly StoreNoteAction $storeNoteAction,
        private readonly LoggerInterface $logger,
        private readonly CancellationService $cancellationService,
    ) {
    }

    public function handle(DebtCollectionStatusUpdated $message): void
    {
        foreach ($message->getSubscriptions() as $subscriptionData) {
            $subscription = $this->subscriptionRepository->getById($subscriptionData['id']);

            match ($subscriptionData['debt_collection_status']) {
                DebtCollectionStatus::NORMAL => $this->handleSubscriptionStatusNormal($subscription),
                DebtCollectionStatus::IN_ARREARS => $this->handleSubscriptionStatusInArrears($subscription),
                DebtCollectionStatus::BAD_DEBT => $this->handleSubscriptionStatusBadDebt($subscription),
            };
        }
    }

    private function handleSubscriptionStatusNormal(Subscription $subscription): void
    {
        if ($subscription->administrative_status !== AdministrativeStatus::SUSPENDED->value) {
            return;
        }

        $this->logger->info(
            'Auto unsuspending subscription {subscription.id} because debt collection status is now normal',
            [
                LoggingContextKeys::CUSTOMER_NUMBER => $subscription->customer->customer_number,
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            ],
        );

        $this->unsuspendSubscriptionAction->execute($subscription);

        $this->storeNoteAction->execute('Auto unsuspended; a subscription invoice is paid', $subscription);
    }

    private function handleSubscriptionStatusInArrears(Subscription $subscription): void
    {
        $loggingContext = [
            LoggingContextKeys::CUSTOMER_NUMBER => $subscription->customer->customer_number,
            LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
        ];

        if (in_array($subscription->administrative_status, AdministrativeStatus::getIneligibleForSuspension(), true)) {
            $this->logger->debug(
                'No action performed because subscription is not in an eligible state for suspension',
                $loggingContext,
            );

            return;
        }

        if (
            $subscription->administrative_status === AdministrativeStatus::CANCELED->value
            && $subscription->cancel_reason === SubscriptionCancelReason::REASON_BAD_DEBT
        ) {
            $this->logger->debug(
                'Re-activating auto cancelled subscriptions (caused by bad debt) must be done manually by CS.',
                $loggingContext,
            );

            return;
        }

        $this->logger->info(
            'Auto suspending subscription {subscription.id} because debt collection status is now in arrears',
            $loggingContext,
        );

        $this->subscriptionSuspendAction->execute($subscription);

        $this->storeNoteAction->execute('Auto suspended; subscription invoice is in arrears', $subscription);
    }

    private function handleSubscriptionStatusBadDebt(Subscription $subscription): void
    {
        if ($subscription->administrative_status !== AdministrativeStatus::SUSPENDED->value) {
            $this->logger->debug(
                'No auto cancellation performed for subscription because it\'s not suspended',
                [
                    LoggingContextKeys::CUSTOMER_NUMBER => $subscription->customer->customer_number,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                ],
            );

            return;
        }

        $this->logger->info(
            'Auto cancelling subscription because debt collection status is now bad debt',
            [
                LoggingContextKeys::CUSTOMER_NUMBER => $subscription->customer->customer_number,
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            ],
        );

        $this->cancellationService->cancel(
            $subscription,
            SubscriptionCancelType::CANCEL_END_DATE,
            SubscriptionCancelReason::REASON_BAD_DEBT,
            cancelNote: 'Auto cancelled by system due to bad debt',
        );
    }
}
