<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Policies;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Services\TransferService;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;

class RedirectPolicy
{
    public function __construct(private readonly AuthenticationManager $authManager, private readonly TransferService $transferService)
    {
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanCreate(Subscription $subscription): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();

        if (! $this->canAccess($subject, $subscription)) {
            throw new AuthorizationException();
        }

        if (! $this->isModifiable($subscription)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanUpdate(Subscription $subscription): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();

        if (! $this->canAccess($subject, $subscription)) {
            throw new AuthorizationException();
        }

        if (! $this->isModifiable($subscription)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanDelete(Subscription $subscription): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();

        if (! $this->canAccess($subject, $subscription)) {
            throw new AuthorizationException();
        }

        if (! $this->isModifiable($subscription)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanList(Subscription $subscription): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();

        if (! $this->canAccess($subject, $subscription)) {
            throw new AuthorizationException();
        }
    }

    private function canAccess(AuthenticatedCustomer $subject, Subscription $subscription): bool
    {
        if (! $subscription->product->isRedirectProduct()) {
            return false;
        }

        if ($this->isSuspended($subscription)) {
            return false;
        }

        if (! $subject->customer->is($subscription->customer)) {
            return false;
        }

        return true;
    }

    private function isSuspended(Subscription $subscription): bool
    {
        return $subscription->technical_status === TechnicalStatus::SUSPENDING->value
            || $subscription->technical_status === TechnicalStatus::UNSUSPENDING->value
            || $subscription->administrative_status === AdministrativeStatus::SUSPENDED->value
            // In fail cases we will treat the subscription as suspended
            || $subscription->technical_status === TechnicalStatus::SUSPENSION_FAILED->value
            || $subscription->technical_status === TechnicalStatus::UNSUSPENSION_FAILED->value;
    }

    private function isModifiable(Subscription $subscription): bool
    {
        return ! $this->transferService->hasOpenTransfer($subscription);
    }
}
