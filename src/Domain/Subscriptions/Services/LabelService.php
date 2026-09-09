<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Models\Label;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\LabelRepository;

class LabelService
{
    public function __construct(
        public readonly LabelRepository $repository,
    ) {
    }

    /**
     * @return Collection<int, Label>
     */
    public function getLabels(Customer $customer): Collection
    {
        return $this->repository->getByCustomer($customer);
    }

    /**
     * @param array<int, non-empty-string> $values
     */
    public function createLabels(array $values, Customer $customer): void
    {
        $this->repository->create($values, $customer);
    }

    /**
     * @param non-empty-array<non-empty-string> $values
     */
    public function deleteLabels(array $values, Customer $customer): void
    {
        $this->repository->delete($values, $customer);
    }

    /**
     * @param non-empty-array<int> $subscriptionIds
     */
    public function attachSubscriptions(Label $label, array $subscriptionIds): void
    {
        $this->repository->attachSubscriptions($label, $subscriptionIds);
    }

    /**
     * @param array<int, non-empty-string> $labelValues
     */
    public function attachLabelsToSubscription(array $labelValues, Subscription $subscription): void
    {
        $this->repository->attachLabelsToSubscription($labelValues, $subscription);
    }

    /**
     * @param non-empty-array<int> $subscriptionIds
     */
    public function detachSubscriptions(Label $label, array $subscriptionIds): void
    {
        $this->repository->detachSubscriptions($label, $subscriptionIds);
    }
}
