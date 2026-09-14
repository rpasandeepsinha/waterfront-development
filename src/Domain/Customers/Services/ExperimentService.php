<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Services;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Experiment\Enums\ExperimentType;
use Waterfront\Domain\Experiment\Repositories\ExperimentRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ExperimentService
{
    public function __construct(
        private readonly ExperimentRepository $experimentRepository,
    ) {
    }

    /**
     * @return list<ExperimentType>
     */
    public function customerParticipatesInExperiments(Customer $customer): array
    {
        $experiments = [];

        foreach (ExperimentType::cases() as $case) {
            $experiments[] = match ($case) {
                ExperimentType::PRICING_LADDER => $this->experimentRepository->isCustomerPartOfLadderExperiment(
                    $customer,
                )
                        ? ExperimentType::PRICING_LADDER
                        : null,
            };
        }

        return array_filter($experiments, fn ($x) => $x !== null);
    }

    /**
     * @return list<ExperimentType>
     */
    public function subscriptionParticipatesInExperiments(Subscription $subscription): array
    {
        return $this->experimentRepository->getExperimentsSubscriptionParticipatesIn($subscription);
    }
}
