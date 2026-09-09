<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Services;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Experiment\Enums\ExperimentType;
use Waterfront\Domain\Experiment\Repositories\ExperimentRepository;

class CustomerExperimentService
{
    public function __construct(
        private readonly ExperimentRepository $experimentRepository,
    ) {
    }

    /**
     * @return list<ExperimentType>
     */
    public function participatesInExperiments(Customer $customer): array
    {
        $experiments = [];

        foreach (ExperimentType::cases() as $case) {
            $experiments[] = match ($case) {
                ExperimentType::PRICING_LADDER => $this->experimentRepository->isPartOfLadderExperiment($customer) ? ExperimentType::PRICING_LADDER : null,
            };
        }

        return array_filter($experiments, fn ($x) => $x !== null);
    }
}
