<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Policies;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Services\ServicePlanChecker;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\AuthorizationChecker;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;

class CustomerPolicy
{
    public function __construct(
        private readonly AuthenticationManager $authManager,
        private readonly AuthorizationChecker $authorizationChecker,
        private readonly ServicePlanChecker $servicePlanChecker,
    ) {
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanAccess(Customer $customer): void
    {
        $subject = $this->authManager->getAuthenticatedSubject();

        if (! $subject instanceof AuthenticatedCustomer || $customer->id !== $subject->customer->id) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanManageDomainContacts(): void
    {
        $subject = $this->authManager->getAuthenticatedSubject();
        if ($subject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::MANAGE_DOMAIN_CONTACTS)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanManageLabels(): void
    {
        $subject = $this->authManager->getAuthenticatedSubject();
        if ($subject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::MANAGE_LABELS)) {
            throw new AuthorizationException();
        }
    }

    public function assertCanUpdate(Customer $customer): void
    {
        $subject = $this->authManager->getAuthenticatedSubject();
        if ($subject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if ($this->authManager->getAuthenticatedCustomer()->customer->id !== $customer->id) {
            throw new AuthorizationException();
        }

        if (! $this->authorizationChecker->can(Permissions::VIEW_PROFILE_CUSTOMER_DETAILS)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanManageM365(): void
    {
        $subject = $this->authManager->getAuthenticatedSubject();
        if ($subject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::MANAGE_M365)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanManageVPS(): void
    {
        $subject = $this->authManager->getAuthenticatedSubject();
        if ($subject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::MANAGE_VPS)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanUpdateWallet(Customer $customer): void
    {
        if ($customer->id !== $this->authManager->getAuthenticatedCustomer()->customer->id) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanManageDnsTemplates(): void
    {
        $subject = $this->authManager->getAuthenticatedSubject();
        if ($subject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::MANAGE_DNS_TEMPLATES)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanApplyTechnicalConfigurationToSubscriptions(): void
    {
        $subject = $this->authManager->getAuthenticatedSubject();
        if ($subject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::APPLY_TECHNICAL_CONFIGURATION_TO_SUBSCRIPTIONS)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanManageSubscription(): void
    {
        $subject = $this->authManager->getAuthenticatedSubject();
        if ($subject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::MANAGE_SUBSCRIPTION)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanManageDeployment(): void
    {
        $subject = $this->authManager->getAuthenticatedSubject();
        if ($subject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::MANAGE_DEPLOYMENT)) {
            throw new AuthorizationException();
        }
    }

    /** @return string[] */
    public function getAvailableActions(): array
    {
        $actions = [];

        try {
            self::assertCanManageLabels();
            $actions[] = Permissions::MANAGE_LABELS->value;
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanManageDnsTemplates();
            $actions[] = Permissions::MANAGE_DNS_TEMPLATES->value;
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanViewSecurity();
            $actions[] = 'viewSecurity';
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanViewOrders();
            $actions[] = 'viewOrders';
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanViewProfile();
            $actions[] = 'viewProfile';
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanViewIdentities();
            $actions[] = 'manageIdentities';
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanViewContact();
            $actions[] = 'viewContact';
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanViewConfiguration();
            $actions[] = 'viewConfiguration';
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanViewInvoices();
            $actions[] = 'viewInvoices';
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanViewSubscriptionDetails();
            $actions[] = Permissions::VIEW_SUBSCRIPTION_DETAILS->value;
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanManageSubscription();
            $actions[] = Permissions::MANAGE_SUBSCRIPTION->value;
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanManageDeployment();
            $actions[] = Permissions::MANAGE_DEPLOYMENT->value;
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanManageM365();
            $actions[] = Permissions::MANAGE_M365->value;
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanManageVPS();
            $actions[] = Permissions::MANAGE_VPS->value;
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanViewCustomerDetails();
            $actions[] = 'viewCustomerDetails';
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanSeeCustomerPayment();
            $actions[] = 'viewCustomerPayment';
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanViewCustomerDomain();
            $actions[] = 'viewCustomerDomain';
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanManageDomainContacts();
            $actions[] = Permissions::MANAGE_DOMAIN_CONTACTS->value;
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanApplyTechnicalConfigurationToSubscriptions();
            $actions[] = 'applyTechnicalConfigurationToSubscriptions';
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        if ($this->hasAccessToServicePlus()) {
            $actions[] = 'canAccessCallback';
        } elseif ($this->canOrderServicePlus()) {
            $actions[] = 'canOrderServicePlus';
        }

        return $actions;
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanSeeCustomerPayment(): void
    {
        $authSubject = $this->authManager->getAuthenticatedSubject();
        if ($authSubject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::VIEW_PROFILE_CUSTOMER_PAYMENT)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    private function assertCanViewSubscriptionDetails(): void
    {
        $subject = $this->authManager->getAuthenticatedSubject();
        if ($subject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::VIEW_SUBSCRIPTION_DETAILS)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    private function assertCanViewCustomerDetails(): void
    {
        $authSubject = $this->authManager->getAuthenticatedSubject();
        if ($authSubject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::VIEW_PROFILE_CUSTOMER_DETAILS)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    private function assertCanViewCustomerDomain(): void
    {
        $authSubject = $this->authManager->getAuthenticatedSubject();
        if ($authSubject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::VIEW_PROFILE_CUSTOMER_DOMAIN)) {
            throw new AuthorizationException();
        }
    }

    private function assertCanViewSecurity(): void
    {
        $authSubject = $this->authManager->getAuthenticatedSubject();
        if ($authSubject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            throw new AuthorizationException();
        }
    }

    private function assertCanViewOrders(): void
    {
        $authSubject = $this->authManager->getAuthenticatedSubject();
        if ($authSubject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::VIEW_ORDERS)) {
            throw new AuthorizationException();
        }
    }

    private function assertCanViewProfile(): void
    {
        $authSubject = $this->authManager->getAuthenticatedSubject();
        if ($authSubject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::VIEW_PROFILE)) {
            throw new AuthorizationException();
        }
    }

    private function assertCanViewIdentities(): void
    {
        $authSubject = $this->authManager->getAuthenticatedSubject();
        if ($authSubject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::MANAGE_IDENTITIES)) {
            throw new AuthorizationException();
        }
    }

    private function assertCanViewContact(): void
    {
        $authSubject = $this->authManager->getAuthenticatedSubject();
        if ($authSubject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::VIEW_CONTACT)) {
            throw new AuthorizationException();
        }
    }

    private function assertCanViewConfiguration(): void
    {
        $authSubject = $this->authManager->getAuthenticatedSubject();
        if ($authSubject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::VIEW_CONFIGURATION)) {
            throw new AuthorizationException();
        }
    }

    private function assertCanViewInvoices(): void
    {
        $authSubject = $this->authManager->getAuthenticatedSubject();
        if ($authSubject->identitySchema->schemaId === SchemaId::EMPLOYEE) {
            return;
        }

        if (! $this->authorizationChecker->can(Permissions::VIEW_INVOICES)) {
            throw new AuthorizationException();
        }
    }

    private function hasAccessToServicePlus(): bool
    {
        $authSubject = $this->authManager->getAuthenticatedSubject();

        if (! $authSubject instanceof AuthenticatedCustomer) {
            return false;
        }

        return $this->servicePlanChecker->hasAccessToServicePlan($authSubject->customer);
    }

    private function canOrderServicePlus(): bool
    {
        $authSubject = $this->authManager->getAuthenticatedSubject();

        if (! $authSubject instanceof AuthenticatedCustomer) {
            return false;
        }

        return $this->servicePlanChecker->canOrderServicePlan($authSubject->customer);
    }
}
