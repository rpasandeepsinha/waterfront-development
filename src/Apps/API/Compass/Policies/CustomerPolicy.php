<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Policies;

use SandwaveIo\LighthouseAuthBase\Authorization\AuthorizationService;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Services\ServicePlanChecker;
use Waterfront\Infra\Authentication\AuthenticationManager;

class CustomerPolicy
{
    public function __construct(
        private readonly AuthenticationManager $authManager,
        private readonly AuthorizationService $authorizationService,
        private readonly ServicePlanChecker $servicePlanChecker,
    ) {
    }

    /**
     * @return string[]
     */
    public function getAvailableCompassActions(Customer $customer): array
    {
        $actions = [];
        if ($this->authManager->getAuthenticatedSubject()->identitySchema->schemaId !== SchemaId::EMPLOYEE) {
            return $actions;
        }

        if ($this->assertCanRequestOldInvoices($customer)) {
            $actions[] = 'sendInvoicesFromOldBu';
        }

        if ($this->servicePlanChecker->hasAccessToServicePlan($customer)) {
            $actions[] = 'canAccessCallback';
            $actions[] = 'accessServicePlus';
        }

        if ($this->canCancelAndCreditSubscriptions()) {
            $actions[] = 'canCancelAndCreditSubscriptions';
        }

        return $actions;
    }

    private function assertCanRequestOldInvoices(Customer $customer): bool
    {
        if (count($customer->migratedCustomers) >= 1) {
            foreach ($customer->migratedCustomers as $migratedCustomer) {
                //Reference names are not enums and can contain "versio" or "Versio"
                if (! in_array(strtolower($migratedCustomer->reference_name), ['versio', 'yourhosting'], true)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private function canCancelAndCreditSubscriptions(): bool
    {
        return $this->authorizationService->can(
            $this->authManager->getAuthenticatedEmployee()->identitySchema,
            Permissions::CAN_CANCEL_AND_CREDIT,
            null,
        );
    }
}
