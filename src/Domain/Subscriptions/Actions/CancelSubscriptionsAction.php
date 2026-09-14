<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Subscriptions\DTO\Cancellation;
use Waterfront\Domain\Subscriptions\Exceptions\CancelCreditSubscriptionsException;
use Waterfront\Domain\Subscriptions\Services\CancellationService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionTerminateService;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

readonly class CancelSubscriptionsAction
{
    public function __construct(
        private CancellationService $cancellationService,
        private LoggerInterface $logger,
        private TranslatorInterface $translator,
        private SubscriptionTerminateService $subscriptionTerminateService,
        private SubscriptionChangeService $changeService,
    ) {
    }

    /**
     * @throws CancelCreditSubscriptionsException
     */
    public function execute(
        Cancellation $cancellation,
    ): void {
        $this->logger->notice('Executing cancel action', [
            LoggingContextKeys::META => [
                'cancellation' => [
                    'subscriptions' => $cancellation->getSubscriptions()->pluck('id')->all(),
                    'reason' => $cancellation->getCancelReason()->value,
                    'reason_other' => $cancellation->getCancelReasonOther(),
                    'type' => $cancellation->getCancelType()->value,
                    'type_other_date' => $cancellation
                        ->getSelectedCancellationEndDate()
                        ?->format(DateTimeFormat::DUTCH),
                    'credit' => (int) $cancellation->shouldCreditRelatedInvoices(),
                ],
            ],
        ]);

        try {
            $cancelType = $cancellation->getCancelType();

            $cancelReasonNote = sprintf(
                'Reason: %s %s, type: %s, marked for credit: %s.',
                $this->translator->translate(
                    'cancel_subscriptions.reason.' . strtolower($cancellation->getCancelReason()->name),
                ),
                $cancellation->getCancelReasonOther(),
                $this->translator->translate('cancel_subscriptions.cancel_type.' . strtolower($cancelType->name)),
                $cancellation->shouldCreditRelatedInvoices() ? 'yes' : 'no',
            );

            foreach ($cancellation->getSubscriptions() as $subscription) {
                $this->cancellationService->cancel(
                    $subscription,
                    $cancelType,
                    $cancellation->getCancelReason(),
                    false,
                    $cancellation->getCancellationEndDate($subscription),
                    $cancelReasonNote,
                );

                if ($subscription->refresh()->end_date <= CarbonImmutable::now()) {
                    if ($this->changeService->shouldDowngradeSubscriptionWithParent($subscription)) {
                        $this->changeService->downgradeCanceled($subscription);
                    } else {
                        $this->subscriptionTerminateService->terminate($subscription->parent ?? $subscription);
                    }
                }
            }
        } catch (Exception $exception) {
            throw new CancelCreditSubscriptionsException(
                sprintf('Failed to cancel selected subscriptions: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }
    }
}
