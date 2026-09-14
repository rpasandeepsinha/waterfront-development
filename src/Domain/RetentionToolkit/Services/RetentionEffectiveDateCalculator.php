<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\Services;

use Carbon\CarbonImmutable;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;
use Waterfront\Domain\RetentionToolkit\Enums\ExecutionDate;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Exceptions\InvalidRetentionEffectiveDateException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionMutationRepository;

class RetentionEffectiveDateCalculator
{
    public function __construct(
        private readonly SubscriptionMutationRepository $subscriptionMutationRepository,
    ) {
    }

    /**
     * @throws InvalidRetentionEffectiveDateException
     */
    public function calculate(
        Subscription $subscription,
        CustomerType $customerType,
        SelectedAction $selectedAction,
        ExecutionDate $executionDate,
    ): CarbonImmutable {
        if ($customerType === CustomerType::BUSINESS && $executionDate === ExecutionDate::IMMEDIATE) {
            throw new InvalidRetentionEffectiveDateException(
                'Business retention offers cannot be executed immediately.',
            );
        }

        if ($selectedAction !== SelectedAction::RF) {
            return match ($executionDate) {
                ExecutionDate::IMMEDIATE => CarbonImmutable::today(),
                ExecutionDate::CONTRACT_END => $subscription->end_date,
            };
        }

        $noticeDate = CarbonImmutable::today()->addMonthNoOverflow();

        if ($customerType === CustomerType::BUSINESS) {
            if ($subscription->end_date->greaterThanOrEqualTo($noticeDate)) {
                return $subscription->end_date;
            }

            $contractPeriod =
                $this->subscriptionMutationRepository->findOpenMutation($subscription)->contract_period
                ?? $subscription->contract_period;

            return $subscription->end_date->addMonths($contractPeriod);
        }

        if ($executionDate === ExecutionDate::CONTRACT_END) {
            return $subscription->end_date;
        }

        $initialContractEndDate = $subscription->start_date->addMonths(
            $subscription->contract_period,
        );

        return $noticeDate->max($initialContractEndDate);
    }
}
