<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Policies;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Infra\Authentication\AuthenticationManager;

class DnsCustomerTemplatePolicy
{
    public function __construct(
        private readonly AuthenticationManager $authManager,
        private readonly CustomerPolicy $customerPolicy,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanShow(DnsCustomerTemplate $template): void
    {
        $this->customerPolicy->assertCanManageDnsTemplates();

        if (! $this->templateBelongsToCustomer($template)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanUpdate(DnsCustomerTemplate $template): void
    {
        $this->customerPolicy->assertCanManageDnsTemplates();
        $this->customerPolicy->assertCanApplyTechnicalConfigurationToSubscriptions();

        if (! $this->templateBelongsToCustomer($template)) {
            throw new AuthorizationException();
        }
    }

    private function templateBelongsToCustomer(DnsCustomerTemplate $template): bool
    {
        $this->customerPolicy->assertCanManageDnsTemplates();

        $subject = $this->authManager->getAuthenticatedCustomer();
        return $subject->customer->id === $template->customer_id;
    }
}
