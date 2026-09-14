<?php

declare(strict_types=1);

namespace Waterfront\Domain\Experiment\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Experiment\Enums\ExperimentType;
use Waterfront\Domain\Experiment\Models\Experiment;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ExperimentRepository
{
    public function isCustomerPartOfLadderExperiment(Customer $customer): bool
    {
        return Experiment::whereHas(
            'subscriptions',
            /** @param Builder<Subscription> $query */
            fn (Builder $query): Builder => $query->where('customer_id', $customer->id)->whereNotIn(
                'administrative_status',
                AdministrativeStatus::administrativelyEnded(),
            ),
        )
            ->where('slug', ExperimentType::PRICING_LADDER->value)
            ->exists();
    }

    public function getBySlug(string $slug): Experiment
    {
        return Experiment::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * @return list<ExperimentType>
     */
    public function getExperimentsSubscriptionParticipatesIn(Subscription $subscription): array
    {
        /** @var list<string> $slugs */
        $slugs = DB::table('experiment_subscriptions')
            ->join('experiment', 'experiment.id', '=', 'experiment_subscriptions.experiment_id')
            ->where('experiment_subscriptions.subscription_id', $subscription->id)
            ->pluck('experiment.slug')
            ->all();

        $experiments = array_map(
            fn (string $slug): ?ExperimentType => ExperimentType::tryFrom($slug),
            $slugs,
        );

        return array_values(array_filter(
            $experiments,
            fn (?ExperimentType $experiment): bool => $experiment !== null,
        ));
    }

    public function findEnrolledExperiment(Subscription $subscription, ExperimentType $type): ?Experiment
    {
        return $subscription->experiments()->where('experiment.slug', $type->value)->first();
    }

    /**
     * @param array<int, string> $slugsByProductId
     *
     * @return array<int, int>
     */
    public function filterProductsCoveredByExperiment(array $slugsByProductId): array
    {
        if ($slugsByProductId === []) {
            return [];
        }

        $rows = DB::table('experiment_products')
            ->join('experiment', 'experiment.id', '=', 'experiment_products.experiment_id')
            ->whereIn('experiment_products.product_id', array_keys($slugsByProductId))
            ->whereIn('experiment.slug', array_values($slugsByProductId))
            ->get(['experiment_products.product_id', 'experiment.slug']);

        $coveredProductIds = [];
        foreach ($rows as $row) {
            $productId = intval($row->product_id);

            if (($slugsByProductId[$productId] ?? null) === $row->slug) {
                $coveredProductIds[] = $productId;
            }
        }

        return array_values(array_unique($coveredProductIds));
    }
}
