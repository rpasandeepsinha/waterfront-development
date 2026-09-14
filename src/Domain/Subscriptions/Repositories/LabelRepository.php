<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Models\Label;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class LabelRepository
{
    /**
     * @return Collection<int, Label>
     */
    public function getByCustomer(Customer $customer): Collection
    {
        return $customer->labels;
    }

    /**
     * @param array<int, non-empty-string> $values
     */
    public function create(array $values, Customer $customer): void
    {
        foreach ($values as $value) {
            if ($this->labelExists($value, $customer)) {
                continue;
            }

            $label = new Label();
            $label->value = $value;
            $customer->labels()->save($label);
        }
    }

    /**
     * @param non-empty-array<non-empty-string> $values
     */
    public function delete(array $values, Customer $customer): void
    {
        $labels = Label::whereIn('value', $values)->where('customer_id', $customer->id)->get();

        $labels->each(function (Label $label) {
            $label->subscriptions()->detach();
            $label->delete();
        });
    }

    /**
     * @param non-empty-array<int> $subscriptionIds
     */
    public function attachSubscriptions(Label $label, array $subscriptionIds): void
    {
        $label->subscriptions()->attach($subscriptionIds);
    }

    /**
     * @param array<int, non-empty-string> $labelValues
     */
    public function attachLabelsToSubscription(array $labelValues, Subscription $subscription): void
    {
        foreach ($labelValues as $labelValue) {
            $labelModel = $this->getLabel($labelValue, $subscription->customer);

            if ($labelModel === null) {
                $labelModel = new Label();
                $labelModel->value = $labelValue;
                $subscription->customer->labels()->save($labelModel);
            }

            $labelModel->subscriptions()->attach($subscription);
        }
    }

    /**
     * @param non-empty-array<int> $subscriptionIds
     */
    public function detachSubscriptions(Label $label, array $subscriptionIds): void
    {
        $label->subscriptions()->detach($subscriptionIds);
    }

    private function labelExists(string $label, Customer $customer): bool
    {
        return $this->getLabel($label, $customer) !== null;
    }

    private function getLabel(string $label, Customer $customer): ?Label
    {
        return Label::where('value', $label)->where('customer_id', $customer->id)->first();
    }
}
