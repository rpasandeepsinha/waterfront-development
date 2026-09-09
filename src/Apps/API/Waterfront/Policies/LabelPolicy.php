<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Policies;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Models\Label;
use Waterfront\Infra\Authentication\AuthenticationManager;

class LabelPolicy
{
    public function __construct(
        private readonly AuthenticationManager $authManager,
        private readonly CustomerPolicy $customerPolicy,
    ) {
    }

    /**
     * @param non-empty-array<positive-int> $subscriptionIds
     *
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanAccess(array $subscriptionIds): void
    {
        $customer = $this->authManager->getAuthenticatedCustomer()->customer;
        $this->customerPolicy->assertCanManageLabels();

        if (! $this->canAccess($customer, $subscriptionIds)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanAccessLabel(Label $label): void
    {
        $customer = $this->authManager->getAuthenticatedCustomer()->customer;
        $this->customerPolicy->assertCanManageLabels();

        if (! $label->customer->is($customer)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @param non-empty-array<positive-int> $subscriptionIds
     */
    private function canAccess(Customer $customer, array $subscriptionIds): bool
    {
        return $customer->subscriptions()->whereIn('id', $subscriptionIds)->count() === count($subscriptionIds);
    }
}
