<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Policies;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Arr;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;

class DomainContactPolicy
{
    public function __construct(
        private readonly AuthenticationManager $authManager,
        private readonly CustomerPolicy $customerPolicy,
    ) {
    }

    /**
     * @param array<string, string> $domains
     *
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanLink(array $domains, DomainContact $domainContact): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();
        $this->customerPolicy->assertCanManageDomainContacts();

        if ($domainContact->has_anonymous_handle) {
            throw new AuthorizationException();
        }

        if (! $this->multipleDomainDeploymentsBelongToCustomerForLink($subject, $domains)) {
            throw new AuthorizationException();
        }

        if (! $this->domainContactBelongsToCustomer($subject, $domainContact)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanSetDefault(DomainContact $domainContact): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();
        $this->customerPolicy->assertCanManageDomainContacts();
        if ($domainContact->has_anonymous_handle) {
            throw new AuthorizationException();
        }

        if (! $this->domainContactBelongsToCustomer($subject, $domainContact)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanDelete(DomainContact $domainContact): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();
        $this->customerPolicy->assertCanManageDomainContacts();
        if (! $this->domainContactBelongsToCustomer($subject, $domainContact)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanShow(DomainContact $domainContact): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();
        $this->customerPolicy->assertCanManageDomainContacts();

        if (! $this->domainContactBelongsToCustomer($subject, $domainContact)) {
            throw new AuthorizationException();
        }
    }

    private function domainContactBelongsToCustomer(AuthenticatedCustomer $subject, DomainContact $domainContact): bool
    {
        return $subject->customer->id === $domainContact->customer_id;
    }

    /**
     * @param array<string, string> $domains
     */
    private function multipleDomainDeploymentsBelongToCustomerForLink(
        AuthenticatedCustomer $subject,
        array $domains,
    ): bool {
        $subscriptions = Subscription::query()
            ->whereProductGroupType(ProductGroupType::EXTENSION)
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->where('customer_id', $subject->customer->id)
            ->whereIn('domain', Arr::pluck($domains, 'domain'))
            ->get();

        return $subscriptions->count() === count($domains);
    }
}
