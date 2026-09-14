<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Policies;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Waterfront\Domain\Transfers\Enums\TransferStatus;
use Waterfront\Domain\Transfers\Models\Transfer;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;

class TransferPolicy
{
    public function __construct(
        private readonly AuthenticationManager $authManager,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanShow(Transfer $transfer): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();

        if (! $this->transferBelongsToCustomer($subject, $transfer)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     */
    public function assertCanStore(): void
    {
        $this->authManager->getAuthenticatedCustomer();
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanAccept(Transfer $transfer): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();

        if (
            $transfer->to_customer_id !== $subject->customer->id
            || $transfer->getStatus() !== TransferStatus::REQUESTED
        ) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanCancel(Transfer $transfer): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();

        if (! $this->belongsToCustomer($subject, $transfer)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanReject(Transfer $transfer): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();

        if (! $this->belongsToCustomer($subject, $transfer)) {
            throw new AuthorizationException();
        }
    }

    private function belongsToCustomer(AuthenticatedCustomer $subject, Transfer $transfer): bool
    {
        return $transfer->from_customer_id === $subject->customer->id;
    }

    private function transferBelongsToCustomer(AuthenticatedCustomer $subject, Transfer $transfer): bool
    {
        return in_array($subject->customer->id, [$transfer->from_customer_id, $transfer->to_customer_id], true);
    }
}
