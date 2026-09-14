<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Policies;

use Illuminate\Database\Eloquent\Collection;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\AuthorizationChecker;

class CancellationFlowPolicy
{
    public function __construct(
        private readonly AuthorizationChecker $authorizationChecker,
        private readonly AuthenticationManager $authManager,
    ) {
    }

    public function validatePermissions(): bool
    {
        $subject = $this->authManager->getAuthenticatedSubject();

        return (
            $subject->identitySchema->schemaId === SchemaId::EMPLOYEE
            || $this->authorizationChecker->can(Permissions::MANAGE_SUBSCRIPTION)
        );
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    public function validateAdminstrativeState(Collection $subscriptions): bool
    {
        return $subscriptions->every(fn (Subscription $sub): bool => in_array(
            $sub->administrative_status,
            [
                AdministrativeStatus::ACTIVE->value,
                AdministrativeStatus::CANCELED->value,
            ],
            true,
        ));
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    public function validateOwnership(Collection $subscriptions): bool
    {
        $customer = $this->authManager->getAuthenticatedCustomer()->customer;

        return $subscriptions->every(fn (Subscription $sub) => $sub->customer_id === $customer->id);
    }
}
