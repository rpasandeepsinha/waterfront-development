<?php

declare(strict_types=1);

namespace Waterfront\Domain\Experiment\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Experiment\Enums\ExperimentType;
use Waterfront\Domain\Experiment\Models\Experiment;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ExperimentRepository
{
    public function isPartOfLadderExperiment(Customer $customer): bool
    {
        return Experiment::whereHas(
            'subscriptions',
            /** @param Builder<Subscription> $query */
            fn (Builder $query): Builder => $query->where('customer_id', $customer->id)->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
        )
            ->where('slug', ExperimentType::PRICING_LADDER->value)
            ->exists();
    }
}
